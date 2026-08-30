<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Presensi;
use App\Models\PresensiHarian;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may mark a meeting's attendance, what a class's day is made of, and the
 * admin-only audit trail of who changed it.
 *
 * The rule the whole feature rests on: one roster per meeting, marked by the
 * guru who taught it and nobody else. The class — including its ketua kelas —
 * reads it and never writes it. The class's day-level record is derived from
 * those rosters, so every recap still counts a student once per school day.
 */
class PresensiRosterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A class with a wali, its ketua, the teacher of one of its meetings, and
     * that meeting itself.
     *
     * @return array<string, mixed>
     */
    private function skenario(): array
    {
        $kelas = Kelas::whereNotNull('wali_kelas_nip')
            ->whereNotNull('ketua_nis')
            ->firstOrFail();

        $jadwal = $kelas->jadwals()->firstOrFail();

        return [
            'kelas' => $kelas,
            'jadwal' => $jadwal,
            'jurnal' => $this->jurnalUntuk($jadwal, now()->toDateString()),
            'wali' => $this->akunGuru($kelas->wali_kelas_nip),
            'pengajar' => $this->akunGuru($jadwal->guru_nip),
            'ketua' => $this->akunSiswaKelas($kelas->id, true),
            'siswaBiasa' => $this->akunSiswaKelas($kelas->id, false),
            'admin' => User::where('role', 'admin')->firstOrFail(),
        ];
    }

    /** The class's journal for one meeting on one date, made if absent. */
    private function jurnalUntuk(Jadwal $jadwal, string $tanggal): Jurnal
    {
        Jurnal::where('jadwal_id', $jadwal->id)->whereDate('tanggal', $tanggal)->delete();

        return Jurnal::create([
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal,
            'materi' => 'Pertemuan uji',
            'kehadiran_guru_status' => 'hadir',
            'diisi_oleh_peran' => 'siswa',
        ]);
    }

    /** The roster payload the form posts, marking everyone present. */
    private function payload(Kelas $kelas, string $status = 'hadir'): array
    {
        $payload = ['presensi' => []];

        foreach ($kelas->siswa()->pluck('nis') as $i => $nis) {
            $payload['presensi'][$i] = ['siswa_nis' => $nis, 'status' => $status];
        }

        return $payload;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_the_teaching_guru_may_mark_their_own_meeting(): void
    {
        $s = $this->skenario();

        $this->assertTrue($s['pengajar']->can('isiPresensi', $s['jurnal']));

        $this->actingAs($s['pengajar'])
            ->get(route('presensi-jurnal.edit', $s['jurnal']))
            ->assertOk();
    }

    public function test_the_ketua_kelas_may_read_but_not_mark_attendance(): void
    {
        $s = $this->skenario();

        $this->assertTrue($s['ketua']->can('lihatPresensiHarian', $s['kelas']));
        $this->assertFalse($s['ketua']->can('isiPresensi', $s['jurnal']));

        $this->actingAs($s['ketua'])
            ->get(route('presensi-jurnal.edit', $s['jurnal']))
            ->assertForbidden();
    }

    public function test_the_wali_kelas_may_read_but_not_mark_another_teachers_meeting(): void
    {
        $s = $this->skenario();

        $this->assertTrue($s['wali']->can('lihatPresensiHarian', $s['kelas']));

        // Unless the wali happens to be the meeting's own teacher, being the
        // homeroom teacher grants no right to mark somebody else's lesson.
        if ($s['wali']->nip !== $s['jadwal']->guru_nip) {
            $this->assertFalse($s['wali']->can('isiPresensi', $s['jurnal']));
        }
    }

    public function test_a_regular_student_may_not_mark_attendance(): void
    {
        $s = $this->skenario();

        $this->assertFalse($s['siswaBiasa']->can('isiPresensi', $s['jurnal']));

        $this->actingAs($s['siswaBiasa'])
            ->post(route('presensi-jurnal.store', $s['jurnal']), $this->payload($s['kelas']))
            ->assertForbidden();
    }

    public function test_a_guru_may_not_mark_another_gurus_meeting(): void
    {
        $s = $this->skenario();

        $lain = Jurnal::whereHas('jadwal', fn ($q) => $q->where('guru_nip', '<>', $s['pengajar']->nip))
            ->firstOrFail();

        $this->assertFalse($s['pengajar']->can('isiPresensi', $lain));

        $this->actingAs($s['pengajar'])
            ->post(route('presensi-jurnal.store', $lain), $this->payload($lain->jadwal->kelas))
            ->assertForbidden();
    }

    public function test_marking_stores_one_row_per_student_and_derives_the_day(): void
    {
        $s = $this->skenario();
        $roster = $s['kelas']->siswa()->pluck('nis');

        $this->actingAs($s['pengajar'])
            ->post(route('presensi-jurnal.store', $s['jurnal']), $this->payload($s['kelas']))
            ->assertRedirect(route('jurnal.show', $s['jurnal']));

        $this->assertDatabaseHas('presensi', [
            'jurnal_id' => $s['jurnal']->id,
            'siswa_nis' => $roster->first(),
            'status' => 'hadir',
            'diisi_oleh_id' => $s['pengajar']->id,
        ]);

        // The day-level record follows from it, so the recaps keep working.
        $this->assertDatabaseHas('presensi_harian', [
            'kelas_id' => $s['kelas']->id,
            'tanggal' => now()->toDateString(),
            'siswa_nis' => $roster->first(),
            'status' => 'hadir',
        ]);

        $this->assertDatabaseHas('presensi_harian_log', [
            'kelas_id' => $s['kelas']->id,
            'diedit_oleh_id' => $s['pengajar']->id,
        ]);
    }

    /**
     * "One roster per meeting" means the second save replaces the first rather
     * than adding a parallel one — the unique index (jurnal_id, siswa_nis).
     */
    public function test_marking_twice_replaces_rather_than_duplicates(): void
    {
        $s = $this->skenario();
        $roster = $s['kelas']->siswa()->pluck('nis');

        $this->actingAs($s['pengajar'])
            ->post(route('presensi-jurnal.store', $s['jurnal']), $this->payload($s['kelas']));

        $ubah = $this->payload($s['kelas']);
        $ubah['presensi'][0]['status'] = 'alpa';

        $this->actingAs($s['pengajar'])
            ->post(route('presensi-jurnal.store', $s['jurnal']), $ubah);

        $this->assertSame($roster->count(), Presensi::where('jurnal_id', $s['jurnal']->id)->count());

        $this->assertDatabaseHas('presensi', [
            'jurnal_id' => $s['jurnal']->id,
            'siswa_nis' => $roster->first(),
            'status' => 'alpa',
        ]);

        // The second save is recorded as a correction of the day, not a first one.
        $this->assertDatabaseHas('presensi_harian_log', [
            'kelas_id' => $s['kelas']->id,
            'koreksi' => true,
        ]);
    }

    /**
     * The point of the whole change: two subjects on the same day may report
     * the same student differently, and both answers survive.
     */
    public function test_two_subjects_on_one_day_keep_separate_rosters(): void
    {
        $s = $this->skenario();
        $tanggal = now()->toDateString();
        $nis = $s['kelas']->siswa()->value('nis');

        $lainJadwal = $s['kelas']->jadwals()
            ->where('id', '<>', $s['jadwal']->id)
            ->where('guru_nip', '<>', $s['jadwal']->guru_nip)
            ->firstOrFail();

        $lain = $this->jurnalUntuk($lainJadwal, $tanggal);

        $this->actingAs($s['pengajar'])
            ->post(route('presensi-jurnal.store', $s['jurnal']), [
                'presensi' => [['siswa_nis' => $nis, 'status' => 'hadir']],
            ]);

        $this->actingAs($this->akunGuru($lainJadwal->guru_nip))
            ->post(route('presensi-jurnal.store', $lain), [
                'presensi' => [['siswa_nis' => $nis, 'status' => 'izin']],
            ]);

        $this->assertDatabaseHas('presensi', [
            'jurnal_id' => $s['jurnal']->id, 'siswa_nis' => $nis, 'status' => 'hadir',
        ]);
        $this->assertDatabaseHas('presensi', [
            'jurnal_id' => $lain->id, 'siswa_nis' => $nis, 'status' => 'izin',
        ]);

        // The day still holds exactly one row for that student, carrying the
        // more consequential of the two marks.
        $harian = PresensiHarian::where('kelas_id', $s['kelas']->id)
            ->whereDate('tanggal', $tanggal)
            ->where('siswa_nis', $nis)
            ->get();

        $this->assertCount(1, $harian);
        $this->assertSame('izin', $harian->first()->status);
    }

    /**
     * Attendance may only be recorded for students actually in the class, so a
     * crafted siswa_nis from another class is rejected.
     */
    public function test_a_student_from_another_class_is_rejected(): void
    {
        $s = $this->skenario();
        $luar = Siswa::where('kelas_id', '<>', $s['kelas']->id)->firstOrFail();

        $this->actingAs($s['pengajar'])
            ->post(route('presensi-jurnal.store', $s['jurnal']), [
                'presensi' => [['siswa_nis' => $luar->nis, 'status' => 'hadir']],
            ])
            ->assertSessionHasErrors('presensi.0.siswa_nis');

        $this->assertDatabaseMissing('presensi', [
            'jurnal_id' => $s['jurnal']->id,
            'siswa_nis' => $luar->nis,
        ]);
    }

    /**
     * A guru marks the roll during the lesson, usually before the class has
     * written its journal, so opening the roster from the timetable creates the
     * meeting's record rather than making the teacher wait for someone else.
     */
    public function test_a_guru_can_open_a_roster_for_a_meeting_with_no_journal_yet(): void
    {
        $s = $this->skenario();
        $tanggal = now()->toDateString();

        Jurnal::where('jadwal_id', $s['jadwal']->id)->whereDate('tanggal', $tanggal)->delete();

        $this->actingAs($s['pengajar'])
            ->post(route('presensi-jurnal.mulai'), [
                'jadwal_id' => $s['jadwal']->id,
                'tanggal' => $tanggal,
            ])
            ->assertRedirect();

        $jurnal = Jurnal::where('jadwal_id', $s['jadwal']->id)->whereDate('tanggal', $tanggal)->firstOrFail();

        // A placeholder, not a journal anybody wrote: it stays out of every
        // "how much has been filled in" figure until the class adopts it.
        $this->assertTrue($jurnal->dibuatSistem());
        $this->assertSame('hadir', $jurnal->kehadiran_guru_status);
    }

    public function test_a_guru_cannot_open_a_roster_for_another_gurus_slot(): void
    {
        $s = $this->skenario();

        $lain = Jadwal::where('guru_nip', '<>', $s['pengajar']->nip)->firstOrFail();

        $this->actingAs($s['pengajar'])
            ->post(route('presensi-jurnal.mulai'), [
                'jadwal_id' => $lain->id,
                'tanggal' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_presensi_log_page_is_admin_only(): void
    {
        $s = $this->skenario();

        $this->actingAs($s['admin'])->get('/admin/presensi-log')->assertOk();
        $this->actingAs($s['pengajar'])->get('/admin/presensi-log')->assertForbidden();
        $this->actingAs($s['ketua'])->get('/admin/presensi-log')->assertForbidden();
    }
}
