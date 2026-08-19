<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\PresensiHarian;
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

        $kelasLain = $lain->jadwal->kelas_id;
        $this->actingAs($this->siswa)
            ->get(route('presensi-harian.show', $kelasLain))
            ->assertForbidden();
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

    /**
     * A guru does not file attendance at all any more: the class's ketua kelas
     * takes one roll call a day. A guru reading the recap is fine; a guru
     * writing one must be refused even for a class they teach.
     */
    public function test_a_guru_cannot_file_attendance_even_for_a_class_they_teach(): void
    {
        $kelas = $this->jadwal->kelas;

        $this->actingAs($this->guru)
            ->get(route('presensi-harian.edit', $kelas))
            ->assertForbidden();

        $this->actingAs($this->guru)
            ->post(route('presensi-harian.store', $kelas), [
                'tanggal' => now()->toDateString(),
                'presensi' => [['siswa_id' => $kelas->siswa()->value('id'), 'status' => 'hadir']],
            ])
            ->assertForbidden();
    }

    public function test_a_guru_outside_the_class_cannot_even_read_its_attendance(): void
    {
        // A freshly created guru teaches nothing, so they are the reliable
        // "outsider" no matter how the demo timetable is wired.
        $luar = User::create([
            'name' => 'Guru Tak Mengajar',
            'email' => 'guru.tak.mengajar@test.app',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'nip' => '900900',
        ]);

        $this->actingAs($luar)
            ->get(route('presensi-harian.show', $this->jadwal->kelas_id))
            ->assertForbidden();
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
     * A wali kelas reads every meeting of their homeroom class — their own
     * screens list its contents, so refusing the read was inconsistent. Read
     * only: editing stays with the teacher who wrote it.
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
    }

    /**
     * The route key is the opaque public_id; the sequential primary key must no
     * longer resolve, so a hand-edited URL cannot walk the table.
     */
    public function test_the_numeric_journal_id_no_longer_resolves_in_web_routes(): void
    {
        $jurnal = Jurnal::where('guru_id', $this->guru->id)->firstOrFail();

        $this->actingAs($this->guru)->get("/jurnal/{$jurnal->id}")->assertNotFound();
        $this->actingAs($this->guru)->get('/jurnal/01ANGKASANGAWURXXXXXXXXXXXX')->assertNotFound();

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
     * Attendance belongs to the class's day, not to a lesson, so deleting a
     * journal must leave the roll call standing. The delete modal says so; this
     * pins that the promise is true.
     */
    public function test_deleting_a_journal_leaves_the_daily_attendance_intact(): void
    {
        $jurnal = Jurnal::where('guru_id', $this->guru->id)->firstOrFail();
        $kelasId = $jurnal->jadwal->kelas_id;
        $tanggal = $jurnal->tanggal->toDateString();

        $sebelum = PresensiHarian::where('kelas_id', $kelasId)->whereDate('tanggal', $tanggal)->count();
        $this->assertGreaterThan(0, $sebelum);

        $this->actingAs($this->guru)
            ->delete("/jurnal/{$jurnal->public_id}")
            ->assertRedirect(route('jurnal.index'));

        $this->assertDatabaseMissing('jurnal', ['id' => $jurnal->id]);
        $this->assertSame($sebelum, PresensiHarian::where('kelas_id', $kelasId)
            ->whereDate('tanggal', $tanggal)->count());
    }
}
