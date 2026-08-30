<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Presensi;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The web app had no authorization at all: any logged-in user could open the
 * master-data screens, edit anyone's journal, and mark any class's roster.
 * These tests pin the role boundaries closed.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    private User $siswa;

    private Jadwal $jadwal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->jadwal = Jadwal::with('kelas')->firstOrFail();
        $this->guru = $this->akunGuru($this->jadwal->guru_nip);
        $this->siswa = $this->akunSiswaKelas($this->jadwal->kelas_id);
    }

    public function test_siswa_is_denied_master_data_screens(): void
    {
        foreach (['/kelas', '/kelas/create', '/mata-pelajaran', '/mata-pelajaran/create'] as $url) {
            $this->actingAs($this->siswa)->get($url)->assertForbidden("GET {$url} should be forbidden for a siswa");
        }
    }

    public function test_siswa_cannot_write_master_data(): void
    {
        $this->actingAs($this->siswa)
            ->post('/kelas', ['nama_kelas' => 'Hacked', 'tingkat' => 'X', 'kapasitas' => 30, 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027'])
            ->assertForbidden();

        $this->assertDatabaseMissing('kelas', ['nama_kelas' => 'Hacked']);
    }

    public function test_siswa_may_still_see_their_own_timetable_and_journals(): void
    {
        foreach (['/jadwal', '/jurnal', '/presensi'] as $url) {
            $this->actingAs($this->siswa)->get($url)->assertOk("GET {$url} should be visible to a siswa");
        }
    }

    public function test_siswa_cannot_open_another_classs_journal_or_attendance(): void
    {
        // A journal belonging to a class that is not this student's.
        $lain = Jurnal::whereHas('jadwal', fn ($q) => $q->where('kelas_id', '!=', $this->siswa->kelas_id))
            ->firstOrFail();

        $this->actingAs($this->siswa)->get("/jurnal/{$lain->public_id}")->assertForbidden();

        $kelasLain = $lain->jadwal->kelas_id;
        $this->actingAs($this->siswa)
            ->get(route('presensi-harian.show', $kelasLain))
            ->assertForbidden();
    }

    public function test_a_student_with_no_class_sees_no_journals(): void
    {
        $lepas = $this->buatSiswa();

        $this->actingAs($lepas)
            ->get('/jurnal')
            ->assertOk()
            ->assertSee('Belum ada jurnal kelas.');
    }

    public function test_a_guru_cannot_edit_another_gurus_journal(): void
    {
        // Compare NIP against NIP: comparing users.id against a NIP is always
        // true, which quietly picked the journal's *own* teacher and let the
        // test pass while proving nothing.
        $lain = User::where('role', 'guru')->where('nip', '!=', $this->jadwal->guru_nip)->firstOrFail();
        $jurnal = Jurnal::diampu($this->jadwal->guru_nip)->firstOrFail();

        $this->actingAs($lain)->get("/jurnal/{$jurnal->public_id}/edit")->assertForbidden();
    }

    public function test_a_guru_cannot_view_a_class_they_do_not_teach(): void
    {
        // A fresh guru with no timetable teaches nothing.
        $lepas = $this->buatGuru();

        $this->actingAs($lepas)->get("/kelas/{$this->jadwal->kelas_id}")->assertForbidden();
    }

    /**
     * Attendance is the teacher's to mark, one roster per meeting they taught.
     * The boundary that matters is the meeting, not the class: teaching a class
     * at JP 1 does not license marking someone else's lesson with the same class.
     */
    public function test_a_guru_marks_only_the_meetings_they_teach(): void
    {
        $milik = Jurnal::diampu($this->guru->nip)->firstOrFail();

        $this->actingAs($this->guru)
            ->get(route('presensi-jurnal.edit', $milik))
            ->assertOk();

        $lain = Jurnal::whereHas('jadwal', fn ($q) => $q->where('guru_nip', '<>', $this->guru->nip))
            ->firstOrFail();

        $this->actingAs($this->guru)
            ->get(route('presensi-jurnal.edit', $lain))
            ->assertForbidden();
    }

    /**
     * The other half of the swap: the class writes the journal but never the
     * roster, not even its ketua kelas.
     */
    public function test_a_ketua_kelas_cannot_mark_attendance(): void
    {
        $ketua = $this->akunSiswaKelas($this->jadwal->kelas_id, true);

        $jurnal = Jurnal::whereHas('jadwal', fn ($q) => $q->where('kelas_id', $ketua->kelas_id))
            ->firstOrFail();

        $sebelum = Presensi::where('jurnal_id', $jurnal->id)
            ->orderBy('siswa_nis')->pluck('status', 'siswa_nis')->all();

        $this->actingAs($ketua)
            ->get(route('presensi-jurnal.edit', $jurnal))
            ->assertForbidden();

        $this->actingAs($ketua)
            ->post(route('presensi-jurnal.store', $jurnal), [
                'presensi' => [['siswa_nis' => $jurnal->jadwal->kelas->siswa()->value('nis'), 'status' => 'alpa']],
            ])
            ->assertForbidden();

        // Refused, not merely redirected: the roster is byte-for-byte unchanged.
        $this->assertSame($sebelum, Presensi::where('jurnal_id', $jurnal->id)
            ->orderBy('siswa_nis')->pluck('status', 'siswa_nis')->all());
    }

    public function test_a_guru_outside_the_class_cannot_even_read_its_attendance(): void
    {
        // A freshly created guru teaches nothing, so they are the reliable
        // "outsider" no matter how the demo timetable is wired.
        $luar = $this->buatGuru(
            ['nip' => '900900900900', 'nama' => 'Guru Tak Mengajar'],
            ['username' => 'guru.tak.mengajar', 'email' => 'guru.tak.mengajar@test.app'],
        );

        $this->actingAs($luar)
            ->get(route('presensi-harian.show', $this->jadwal->kelas_id))
            ->assertForbidden();
    }

    public function test_a_regular_siswa_cannot_author_a_journal(): void
    {
        $biasa = $this->akunSiswaKelas($this->jadwal->kelas_id, false);

        $this->actingAs($biasa)->get('/jurnal/create')->assertForbidden();

        $this->actingAs($biasa)
            ->post('/jurnal', [
                'jadwal_id' => $this->jadwal->id,
                'tanggal' => now()->toDateString(),
                'materi' => 'Palsu',
                'kehadiran_guru' => 'hadir',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('jurnal', ['materi' => 'Palsu']);
    }

    public function test_a_ketua_cannot_write_against_another_classs_schedule(): void
    {
        $ketua = $this->akunSiswaKelas($this->jadwal->kelas_id, true);

        $jadwalLain = Jadwal::where('kelas_id', '!=', $ketua->kelas_id)->firstOrFail();

        $this->actingAs($ketua)
            ->post('/jurnal', [
                'jadwal_id' => $jadwalLain->id,
                'tanggal' => now()->toDateString(),
                'materi' => 'Palsu lintas kelas',
                'kehadiran_guru' => 'hadir',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('jurnal', ['materi' => 'Palsu lintas kelas']);
    }

    public function test_the_ketua_flow_reaches_the_roster_after_saving(): void
    {
        $ketua = $this->akunSiswaKelas($this->jadwal->kelas_id, true);

        // DemoSeeder journals today's meetings too; clear this slot so the
        // ketua's journal is the one under test rather than a duplicate.
        Jurnal::where('jadwal_id', $this->jadwal->id)
            ->whereDate('tanggal', now()->toDateString())
            ->delete();

        $simpan = $this->actingAs($ketua)->post('/jurnal', [
            'jadwal_id' => $this->jadwal->id,
            'tanggal' => now()->toDateString(),
            'materi' => 'Materi dari ketua',
            'kehadiran_guru' => 'ada_tugas',
        ]);

        $jurnal = Jurnal::latest('id')->firstOrFail();

        // Saving a journal no longer hands off to a roster screen — attendance
        // is a separate, once-daily job — so it lands on the journal itself.
        $simpan->assertRedirect(route('jurnal.show', $jurnal));
        $this->actingAs($ketua)->get("/jurnal/{$jurnal->public_id}")->assertOk();
    }

    public function test_a_guru_cannot_write_against_another_gurus_schedule(): void
    {
        $jadwalLain = Jadwal::where('guru_nip', '!=', $this->guru->nip)->firstOrFail();

        $this->actingAs($this->guru)
            ->post('/jurnal', [
                'jadwal_id' => $jadwalLain->id,
                'tanggal' => now()->toDateString(),
                'materi' => 'Palsu lintas guru',
                'kehadiran_guru' => 'hadir',
            ])
            ->assertForbidden();
    }

    public function test_only_admin_can_export_the_reports(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/laporan/jurnal?ekspor=xlsx')
            ->assertOk()
            ->assertDownload()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->guru)->get('/admin/laporan/presensi?ekspor=xlsx')->assertForbidden();
        $this->actingAs($this->siswa)->get('/admin/laporan/jurnal?ekspor=xlsx')->assertForbidden();
    }

    /**
     * A wali kelas reads every meeting of their homeroom class — their own
     * screens list its contents, so refusing the read was inconsistent. Read
     * only: editing stays with the teacher who wrote it.
     */
    public function test_a_wali_kelas_reads_but_cannot_edit_another_gurus_journal_in_their_class(): void
    {
        $kelas = $this->jadwal->kelas;
        $wali = $this->buatGuru();
        $kelas->update(['wali_kelas_nip' => $wali->nip]);

        // A meeting of that class taught by somebody else.
        $jurnal = Jurnal::whereHas('jadwal', fn ($q) => $q
            ->where('kelas_id', $kelas->id)
            ->where('guru_nip', '!=', $wali->nip))
            ->firstOrFail();

        $this->actingAs($wali)->get("/jurnal/{$jurnal->public_id}")->assertOk();
        $this->actingAs($wali)->get("/jurnal/{$jurnal->public_id}/edit")->assertForbidden();
    }

    public function test_a_guru_outside_the_class_cannot_read_its_journal(): void
    {
        // A fresh guru teaches nothing and chairs nothing, so they are outside
        // every class no matter how the demo timetable happens to be wired.
        $luar = $this->buatGuru();
        $jurnal = Jurnal::firstOrFail();

        $this->actingAs($luar)->get("/jurnal/{$jurnal->public_id}")->assertForbidden();
    }

    /**
     * The route key is the opaque public_id; the sequential primary key must no
     * longer resolve, so a hand-edited URL cannot walk the table.
     */
    public function test_the_numeric_journal_id_no_longer_resolves_in_web_routes(): void
    {
        $jurnal = Jurnal::diampu($this->guru->nip)->firstOrFail();

        $this->actingAs($this->guru)->get("/jurnal/{$jurnal->id}")->assertNotFound();
        $this->actingAs($this->guru)->get('/jurnal/01ANGKASANGAWURXXXXXXXXXXXX')->assertNotFound();

        $this->actingAs($this->guru)->get("/jurnal/{$jurnal->public_id}")->assertOk();
    }

    /**
     * Deleting a journal is offered in the UI, so the boundary matters. The
     * journal is the class's record now, so the class's ketua may erase it and a
     * guru - even the one who taught the lesson, even the wali kelas - may not.
     */
    public function test_only_the_class_or_admin_may_delete_a_journal(): void
    {
        $kelas = $this->jadwal->kelas;
        $wali = $this->buatGuru();
        $kelas->update(['wali_kelas_nip' => $wali->nip]);

        $jurnal = Jurnal::whereHas('jadwal', fn ($q) => $q
            ->where('kelas_id', $kelas->id)
            ->where('guru_nip', '<>', $wali->nip))
            ->firstOrFail();

        // The wali can open it, but is offered no way to delete it...
        $this->actingAs($wali)->get("/jurnal/{$jurnal->public_id}")
            ->assertOk()
            ->assertDontSee('data-bs-target="#hapusJurnal"', false);

        // ...and forcing the request is refused, not merely hidden.
        $this->actingAs($wali)->delete("/jurnal/{$jurnal->public_id}")->assertForbidden();
        $this->assertDatabaseHas('jurnal', ['id' => $jurnal->id]);

        // Nor may the teacher whose lesson it records: their half is the roster.
        $pengajar = $this->akunGuru($jurnal->jadwal->guru_nip);
        $this->actingAs($pengajar)->delete("/jurnal/{$jurnal->public_id}")->assertForbidden();
        $this->assertDatabaseHas('jurnal', ['id' => $jurnal->id]);

        // The class's ketua may, and is shown the button.
        $ketua = $this->akunSiswaKelas($kelas->id, true);
        $this->actingAs($ketua)->get("/jurnal/{$jurnal->public_id}")
            ->assertOk()
            ->assertSee('data-bs-target="#hapusJurnal"', false);
    }

    /**
     * A roster belongs to its meeting, so deleting the journal takes that
     * meeting's marks with it - and the class's derived daily record is rebuilt
     * from whatever lessons remain rather than left describing a lesson that no
     * longer exists. The delete modal promises exactly this.
     */
    public function test_deleting_a_journal_removes_its_own_roster_only(): void
    {
        $kelasId = $this->jadwal->kelas_id;
        $ketua = $this->akunSiswaKelas($kelasId, true);
        $tanggal = now()->toDateString();

        // Two meetings of the same class on the same day, each marked by its own
        // teacher: one student present in the first, absent in the second.
        [$satu, $dua] = $this->duaPertemuan($kelasId, $tanggal);
        $nis = $this->jadwal->kelas->siswa()->value('nis');

        $this->tandai($satu, $nis, 'hadir');
        $this->tandai($dua, $nis, 'alpa');

        // The day takes the heavier mark while both lessons stand.
        $this->assertDatabaseHas('presensi_harian', [
            'kelas_id' => $kelasId, 'siswa_nis' => $nis, 'status' => 'alpa',
        ]);

        $this->actingAs($ketua)
            ->delete("/jurnal/{$dua->public_id}")
            ->assertRedirect(route('jurnal.index'));

        // Its roster went with it; the other lesson's is untouched, and the day
        // now reads from what is left.
        $this->assertDatabaseMissing('presensi', ['jurnal_id' => $dua->id]);
        $this->assertDatabaseHas('presensi', ['jurnal_id' => $satu->id, 'siswa_nis' => $nis]);
        $this->assertDatabaseHas('presensi_harian', [
            'kelas_id' => $kelasId, 'siswa_nis' => $nis, 'status' => 'hadir',
        ]);
    }

    /**
     * Two journals for one class on one date, on different teachers' slots.
     *
     * @return array<int, Jurnal>
     */
    private function duaPertemuan(int $kelasId, string $tanggal): array
    {
        $slots = Jadwal::where('kelas_id', $kelasId)
            ->get()
            ->unique('guru_nip')
            ->take(2)
            ->values();

        $this->assertCount(2, $slots, 'Perlu dua jadwal dengan guru berbeda di kelas ini.');

        return $slots->map(function ($jadwal) use ($tanggal) {
            Jurnal::where('jadwal_id', $jadwal->id)->whereDate('tanggal', $tanggal)->delete();

            return Jurnal::create([
                'jadwal_id' => $jadwal->id,
                'tanggal' => $tanggal,
                'materi' => 'Pertemuan uji',
                'kehadiran_guru_status' => 'hadir',
                'diisi_oleh_peran' => 'siswa',
            ]);
        })->all();
    }

    /** Mark one student on one meeting, as that meeting's own teacher. */
    private function tandai(Jurnal $jurnal, string $nis, string $status): void
    {
        $this->actingAs($this->akunGuru($jurnal->jadwal->guru_nip))
            ->post(route('presensi-jurnal.store', $jurnal), [
                'presensi' => [['siswa_nis' => $nis, 'status' => $status]],
            ])
            ->assertRedirect(route('jurnal.show', $jurnal));
    }
}
