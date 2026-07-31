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
        $this->guru = $this->jadwal->guru;
        $this->siswa = User::where('role', 'siswa')
            ->where('kelas_id', $this->jadwal->kelas_id)
            ->firstOrFail();
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
            ->post('/kelas', ['nama_kelas' => 'Hacked', 'tingkat' => 'X', 'kapasitas' => 30, 'tahun_ajaran' => '2025/2026'])
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
        $this->actingAs($this->siswa)->get("/presensi/{$lain->public_id}")->assertForbidden();
    }

    public function test_a_student_with_no_class_sees_no_journals(): void
    {
        $lepas = User::factory()->create(['role' => 'siswa', 'kelas_id' => null, 'status' => 'aktif']);

        $this->actingAs($lepas)
            ->get('/jurnal')
            ->assertOk()
            ->assertSee('Belum ada jurnal kelas.');
    }

    public function test_a_guru_cannot_edit_another_gurus_journal(): void
    {
        $lain = User::where('role', 'guru')->where('id', '!=', $this->jadwal->guru_id)->firstOrFail();
        $jurnal = Jurnal::where('guru_id', $this->jadwal->guru_id)->firstOrFail();

        $this->actingAs($lain)->get("/jurnal/{$jurnal->public_id}/edit")->assertForbidden();
    }

    public function test_a_guru_cannot_view_a_class_they_do_not_teach(): void
    {
        // A fresh guru with no timetable teaches nothing.
        $lepas = User::factory()->create(['role' => 'guru', 'status' => 'aktif']);

        $this->actingAs($lepas)->get("/kelas/{$this->jadwal->kelas_id}")->assertForbidden();
    }

    public function test_attendance_rejects_a_student_outside_the_roster(): void
    {
        $jurnal = Jurnal::where('jadwal_id', $this->jadwal->id)->firstOrFail();

        // A student who belongs to a different class than this journal's.
        $luar = User::where('role', 'siswa')->where('kelas_id', '!=', $this->jadwal->kelas_id)->firstOrFail();

        $this->actingAs($this->guru)
            ->post('/presensi', [
                'jurnal_id' => $jurnal->public_id,
                'presensi' => [['siswa_id' => $luar->id, 'status' => 'hadir', 'keterangan' => null]],
            ])
            ->assertSessionHasErrors('presensi.0.siswa_id');

        $this->assertDatabaseMissing('presensi', ['jurnal_id' => $jurnal->id, 'siswa_id' => $luar->id]);
    }

    public function test_a_guru_who_does_not_teach_the_class_cannot_mark_its_meeting(): void
    {
        // Marking is class-scoped (JurnalPolicy::markRoster): a guru who teaches
        // the class or is its wali may mark any of its meetings, but an outsider
        // may not. A freshly created guru teaches nothing, so they are the
        // reliable "outsider" no matter how the demo timetable is wired.
        $luar = User::create([
            'name' => 'Guru Tak Mengajar',
            'email' => 'guru.tak.mengajar@test.app',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'nip' => '900900',
        ]);
        $jurnal = Jurnal::where('guru_id', $this->jadwal->guru_id)->firstOrFail();

        $this->actingAs($luar)->get("/presensi/create/{$jurnal->public_id}")->assertForbidden();
    }

    public function test_a_regular_siswa_cannot_author_a_journal(): void
    {
        $biasa = User::where('role', 'siswa')
            ->where('kelas_id', $this->jadwal->kelas_id)
            ->where('is_ketua_kelas', false)
            ->firstOrFail();

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
        $ketua = User::where('role', 'siswa')
            ->where('kelas_id', $this->jadwal->kelas_id)
            ->where('is_ketua_kelas', true)
            ->firstOrFail();

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
        $ketua = User::where('role', 'siswa')
            ->where('kelas_id', $this->jadwal->kelas_id)
            ->where('is_ketua_kelas', true)
            ->firstOrFail();

        $simpan = $this->actingAs($ketua)->post('/jurnal', [
            'jadwal_id' => $this->jadwal->id,
            'tanggal' => now()->toDateString(),
            'materi' => 'Materi dari ketua',
            'kehadiran_guru' => 'ada_tugas',
        ]);

        $jurnal = Jurnal::latest('id')->firstOrFail();

        // The save must hand off to the roster screen, and the ketua must be
        // allowed to open it — this exact chain 403'd when presensi gained its
        // update gate before the policy knew about the ketua.
        $simpan->assertRedirect(route('presensi.create', $jurnal));
        $this->actingAs($ketua)->get("/presensi/create/{$jurnal->public_id}")->assertOk();
    }

    public function test_a_guru_cannot_write_against_another_gurus_schedule(): void
    {
        $jadwalLain = Jadwal::where('guru_id', '!=', $this->guru->id)->firstOrFail();

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
     * markRoster already lets a wali kelas mark any meeting of their homeroom
     * class, so refusing them a read of that same meeting was inconsistent —
     * their own screens list its contents. Read only: editing stays with the
     * teacher who wrote it.
     */
    public function test_a_wali_kelas_reads_but_cannot_edit_another_gurus_journal_in_their_class(): void
    {
        $kelas = $this->jadwal->kelas;
        $wali = User::factory()->create(['role' => 'guru', 'status' => 'aktif']);
        $kelas->update(['wali_kelas_id' => $wali->id]);

        // A meeting of that class taught by somebody else.
        $jurnal = Jurnal::whereHas('jadwal', fn ($q) => $q->where('kelas_id', $kelas->id))
            ->where('guru_id', '!=', $wali->id)
            ->firstOrFail();

        $this->actingAs($wali)->get("/jurnal/{$jurnal->public_id}")->assertOk();
        $this->actingAs($wali)->get("/jurnal/{$jurnal->public_id}/edit")->assertForbidden();
    }

    public function test_a_guru_outside_the_class_cannot_read_its_journal(): void
    {
        // A fresh guru teaches nothing and chairs nothing, so they are outside
        // every class no matter how the demo timetable happens to be wired.
        $luar = User::factory()->create(['role' => 'guru', 'status' => 'aktif']);
        $jurnal = Jurnal::firstOrFail();

        $this->actingAs($luar)->get("/jurnal/{$jurnal->public_id}")->assertForbidden();
        $this->actingAs($luar)->get("/presensi/{$jurnal->public_id}")->assertForbidden();
    }

    /**
     * The route key is the opaque public_id; the sequential primary key must no
     * longer resolve, so a hand-edited URL cannot walk the table.
     */
    public function test_the_numeric_journal_id_no_longer_resolves_in_web_routes(): void
    {
        $jurnal = Jurnal::where('guru_id', $this->guru->id)->firstOrFail();

        $this->actingAs($this->guru)->get("/jurnal/{$jurnal->id}")->assertNotFound();
        $this->actingAs($this->guru)->get("/presensi/{$jurnal->id}")->assertNotFound();
        $this->actingAs($this->guru)->get('/presensi/01ANGKASANGAWURXXXXXXXXXXXX')->assertNotFound();

        $this->actingAs($this->guru)->get("/jurnal/{$jurnal->public_id}")->assertOk();
    }

    /**
     * Deleting a journal is offered in the UI, so the boundary matters: a wali
     * kelas may read every meeting of their class (see above) but must not be
     * able to erase another teacher's record of it.
     */
    public function test_only_the_author_or_admin_may_delete_a_journal(): void
    {
        $kelas = $this->jadwal->kelas;
        $wali = User::factory()->create(['role' => 'guru', 'status' => 'aktif']);
        $kelas->update(['wali_kelas_id' => $wali->id]);

        $jurnal = Jurnal::whereHas('jadwal', fn ($q) => $q->where('kelas_id', $kelas->id))
            ->where('guru_id', '!=', $wali->id)
            ->firstOrFail();

        // The wali can open it, but is offered no way to delete it...
        $this->actingAs($wali)->get("/jurnal/{$jurnal->public_id}")
            ->assertOk()
            ->assertDontSee('data-bs-target="#hapusJurnal"', false);

        // ...and forcing the request is refused, not merely hidden.
        $this->actingAs($wali)->delete("/jurnal/{$jurnal->public_id}")->assertForbidden();
        $this->assertDatabaseHas('jurnal', ['id' => $jurnal->id]);

        // The teacher who wrote it may, and is shown the button.
        $penulis = User::findOrFail($jurnal->guru_id);
        $this->actingAs($penulis)->get("/jurnal/{$jurnal->public_id}")
            ->assertOk()
            ->assertSee('data-bs-target="#hapusJurnal"', false);
    }

    /**
     * presensi and presensi_log both cascade off this row, so a deletion takes
     * the lesson's whole roster with it. The modal warns about that; this pins
     * that the warning is accurate.
     */
    public function test_deleting_a_journal_takes_its_attendance_with_it(): void
    {
        $jurnal = Jurnal::where('guru_id', $this->guru->id)
            ->whereHas('presensis')
            ->firstOrFail();

        $jumlah = $jurnal->presensis()->count();
        $this->assertGreaterThan(0, $jumlah);

        $this->actingAs($this->guru)
            ->delete("/jurnal/{$jurnal->public_id}")
            ->assertRedirect(route('jurnal.index'));

        $this->assertDatabaseMissing('jurnal', ['id' => $jurnal->id]);
        $this->assertDatabaseMissing('presensi', ['jurnal_id' => $jurnal->id]);
    }
}
