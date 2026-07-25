<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
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

        $this->actingAs($this->siswa)->get("/jurnal/{$lain->id}")->assertForbidden();
        $this->actingAs($this->siswa)->get("/presensi/{$lain->id}")->assertForbidden();
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

        $this->actingAs($lain)->get("/jurnal/{$jurnal->id}/edit")->assertForbidden();
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
                'jurnal_id' => $jurnal->id,
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

        $this->actingAs($luar)->get("/presensi/create/{$jurnal->id}")->assertForbidden();
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
        $simpan->assertRedirect(route('presensi.create', $jurnal->id));
        $this->actingAs($ketua)->get("/presensi/create/{$jurnal->id}")->assertOk();
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
}
