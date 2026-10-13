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
        // A rank-and-file student: the ketua kelas has a narrower menu of their
        // own since Oct 2026 (see the ketua tests below).
        $this->siswa = $this->akunSiswaKelas($this->jadwal->kelas_id, false);
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

    public function test_a_regular_siswa_cannot_access_the_web_application(): void
    {
        foreach (['/jadwal', '/jurnal', '/presensi'] as $url) {
            $this->actingAs($this->siswa)->get($url)->assertForbidden();
        }
    }

    /** Oct 2026: the ketua kelas files today's journal and reads today's - nothing else. */
    public function test_the_ketua_kelas_only_reaches_todays_journals(): void
    {
        $ketua = $this->akunSiswaKelas($this->jadwal->kelas_id, true);

        foreach (['/jurnal', '/jurnal/create'] as $url) {
            $this->actingAs($ketua)->get($url)->assertOk("GET {$url} should be open to the ketua");
        }

        foreach (['/jadwal', "/jadwal/{$this->jadwal->id}", '/presensi',
            "/presensi-harian/{$ketua->kelas_id}"] as $url) {
            $this->actingAs($ketua)->get($url)->assertForbidden("GET {$url} should be closed to the ketua");
        }

        // Their class's journal from another day is out of reach...
        $lampau = Jurnal::whereHas('jadwal', fn ($q) => $q->where('kelas_id', $ketua->kelas_id))
            ->whereDate('tanggal', '<', today())
            ->firstOrFail();
        $this->actingAs($ketua)->get("/jurnal/{$lampau->public_id}")->assertForbidden();

        // ...and the day's list carries today's only.
        $this->actingAs($ketua)->get('/jurnal')
            ->assertViewHas('hanyaHariIni', true)
            ->assertSee('Jurnal Kelas Hari Ini');
    }

    /** Oct 2026: a guru's menu is the journal and its roster; master data is the admin's. */
    public function test_a_guru_is_kept_to_journals_and_attendance(): void
    {
        foreach (['/jurnal', '/jurnal/create', '/presensi'] as $url) {
            $this->actingAs($this->guru)->get($url)->assertOk("GET {$url} should be open to a guru");
        }

        foreach (['/kelas', "/kelas/{$this->jadwal->kelas_id}", '/ruangan', '/mata-pelajaran',
            '/jadwal', "/jadwal/{$this->jadwal->id}"] as $url) {
            $this->actingAs($this->guru)->get($url)->assertForbidden("GET {$url} should be closed to a guru");
        }

        $this->actingAs($this->guru)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('href="'.route('kelas.index').'"', false)
            ->assertDontSee('href="'.route('jadwal.index').'"', false);
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

    public function test_a_student_with_no_class_cannot_access_journals(): void
    {
        $lepas = $this->buatSiswa();

        $this->actingAs($lepas)
            ->get('/jurnal')
            ->assertForbidden();
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
     * Deleting a journal is offered in the UI, so the boundary matters. Since
     * Oct 2026 the guru has full CRUD over every journal on the lessons they
     * teach - including the one the ketua filed. The ketua kelas files and
     * reads but never deletes, a wali kelas who did not teach the lesson may
     * only read it, and another lesson's teacher may not touch it.
     */
    public function test_only_the_teacher_or_admin_may_delete_a_journal(): void
    {
        $kelas = $this->jadwal->kelas;
        $wali = $this->buatGuru();
        $kelas->update(['wali_kelas_nip' => $wali->nip]);
        $ketua = $this->akunSiswaKelas($kelas->id, true);

        // Today's, so the ketua can see them: both written by the class, on
        // two different teachers' lessons.
        [$dariKelas, $lainnya] = $this->duaPertemuan($kelas->id, today()->toDateString());

        foreach ([$wali, $ketua] as $akun) {
            // They can open it, but are offered no way to delete it...
            $this->actingAs($akun)->get("/jurnal/{$dariKelas->public_id}")
                ->assertOk()
                ->assertDontSee('data-bs-target="#hapusJurnal"', false);

            // ...and forcing the request is refused, not merely hidden.
            $this->actingAs($akun)->delete("/jurnal/{$dariKelas->public_id}")->assertForbidden();
            $this->assertDatabaseHas('jurnal', ['id' => $dariKelas->id]);
        }

        // Another lesson's teacher cannot even open it, let alone delete it.
        $guruLain = $this->akunGuru($lainnya->jadwal->guru_nip);
        $this->actingAs($guruLain)->get("/jurnal/{$dariKelas->public_id}")->assertForbidden();
        $this->actingAs($guruLain)->delete("/jurnal/{$dariKelas->public_id}")->assertForbidden();
        $this->assertDatabaseHas('jurnal', ['id' => $dariKelas->id]);

        // The lesson's own teacher is shown the edit and delete buttons, and may
        // delete it - even though the ketua wrote it.
        $pengajar = $this->akunGuru($dariKelas->jadwal->guru_nip);
        $this->actingAs($pengajar)->get("/jurnal/{$dariKelas->public_id}")
            ->assertOk()
            ->assertSee(route('jurnal.edit', $dariKelas), false)
            ->assertSee('data-bs-target="#hapusJurnal"', false);
        $this->actingAs($pengajar)->delete("/jurnal/{$dariKelas->public_id}")
            ->assertRedirect(route('jurnal.index'));
        $this->assertDatabaseMissing('jurnal', ['id' => $dariKelas->id]);

        // The admin may remove any journal.
        $this->actingAs($this->admin)->delete("/jurnal/{$lainnya->public_id}")
            ->assertRedirect(route('jurnal.index'));
        $this->assertDatabaseMissing('jurnal', ['id' => $lainnya->id]);
    }

    /** The guru corrects the class's journal on their lesson; it stays the class's entry. */
    public function test_a_guru_may_edit_the_class_journal_on_their_lesson(): void
    {
        $kelasId = $this->jadwal->kelas_id;
        [$dariKelas, $lainnya] = $this->duaPertemuan($kelasId, today()->toDateString());
        $pengajar = $this->akunGuru($dariKelas->jadwal->guru_nip);

        $isian = [
            'jadwal_id' => $dariKelas->jadwal_id,
            'tanggal' => today()->toDateString(),
            'materi' => 'Diperbaiki guru pengampu',
            'kehadiran_guru' => 'hadir',
        ];

        $this->actingAs($pengajar)->get("/jurnal/{$dariKelas->public_id}/edit")->assertOk();
        $this->actingAs($pengajar)->put("/jurnal/{$dariKelas->public_id}", $isian)
            ->assertRedirect(route('jurnal.index'));

        $segar = $dariKelas->fresh();
        $this->assertSame('Diperbaiki guru pengampu', $segar->materi);
        $this->assertSame('siswa', $segar->diisi_oleh_peran);

        // Another lesson's teacher still may not.
        $this->actingAs($this->akunGuru($lainnya->jadwal->guru_nip))
            ->get("/jurnal/{$dariKelas->public_id}/edit")->assertForbidden();
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

        // An admin may remove any journal.
        $this->actingAs($this->admin)
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
