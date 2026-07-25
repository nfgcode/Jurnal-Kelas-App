<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may mark a meeting's attendance roster, and the admin-only audit trail of
 * who did. Marking is class-scoped (a guru who teaches the class, its wali, its
 * ketua, and admin) — separate from editing the journal's own content.
 */
class PresensiRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    /**
     * A class with a wali and >=2 teachers, a meeting taught by a guru who is
     * not the wali, and the actors around it.
     *
     * @return array<string, mixed>
     */
    private function skenario(): array
    {
        $kelas = Kelas::whereNotNull('wali_kelas_id')->firstOrFail();
        $wali = User::findOrFail($kelas->wali_kelas_id);

        $jadwals = Jadwal::where('kelas_id', $kelas->id)->get();
        // The meeting under test is taught by someone other than the wali.
        $jadwal = $jadwals->firstWhere('guru_id', '!=', $wali->id) ?? $jadwals->firstOrFail();

        $jurnal = Jurnal::firstOrCreate(
            ['jadwal_id' => $jadwal->id, 'tanggal' => now()->toDateString()],
            ['materi' => 'Uji', 'guru_id' => $jadwal->guru_id],
        );

        // Another teacher of the same class who does not own this meeting.
        $guruLainId = $jadwals->pluck('guru_id')->unique()->first(fn ($id) => $id !== $jadwal->guru_id);

        $ketua = User::where('role', 'siswa')->where('kelas_id', $kelas->id)
            ->where('is_ketua_kelas', true)->firstOrFail();

        // A guru who neither teaches this class nor is its wali — created fresh
        // so the assertion holds no matter how the demo timetable is wired.
        $luar = User::create([
            'name' => 'Guru Luar',
            'email' => 'guru.luar@test.app',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'nip' => '999999',
        ]);

        return [
            'kelas' => $kelas,
            'wali' => $wali,
            'jurnal' => $jurnal,
            'pengajar' => User::findOrFail($jadwal->guru_id),
            'guruLain' => $guruLainId ? User::find($guruLainId) : null,
            'ketua' => $ketua,
            'luar' => $luar,
        ];
    }

    public function test_a_wali_may_mark_a_homeroom_meeting_taught_by_another_teacher(): void
    {
        $s = $this->skenario();
        $this->assertTrue($s['wali']->can('markRoster', $s['jurnal']));
    }

    public function test_a_guru_teaching_the_class_may_mark_another_teachers_meeting(): void
    {
        $s = $this->skenario();

        if (! $s['guruLain']) {
            $this->markTestSkipped('Kelas demo hanya punya satu guru.');
        }

        $this->assertTrue($s['guruLain']->can('markRoster', $s['jurnal']));
    }

    public function test_the_ketua_kelas_may_mark_their_class_meeting(): void
    {
        $s = $this->skenario();
        $this->assertTrue($s['ketua']->can('markRoster', $s['jurnal']));
    }

    public function test_a_guru_who_neither_teaches_nor_is_wali_is_forbidden(): void
    {
        $s = $this->skenario();

        $this->assertFalse($s['luar']->can('markRoster', $s['jurnal']));

        $this->actingAs($s['luar'])
            ->get("/presensi/create/{$s['jurnal']->id}")
            ->assertForbidden();
    }

    public function test_saving_a_roster_writes_an_audit_log_entry(): void
    {
        $s = $this->skenario();
        $roster = $s['kelas']->siswa()->pluck('id');

        $payload = ['jurnal_id' => $s['jurnal']->id, 'presensi' => []];
        foreach ($roster as $i => $id) {
            $payload['presensi'][$i] = ['siswa_id' => $id, 'status' => 'hadir'];
        }

        $this->actingAs($s['wali'])
            ->post('/presensi', $payload)
            ->assertRedirect(route('presensi.show', $s['jurnal']->id));

        $this->assertDatabaseHas('presensi_log', [
            'jurnal_id' => $s['jurnal']->id,
            'diedit_oleh_id' => $s['wali']->id,
            'jumlah_siswa' => $roster->count(),
        ]);
    }

    public function test_presensi_log_page_is_admin_only(): void
    {
        $s = $this->skenario();
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($admin)->get('/admin/presensi-log')->assertOk();
        $this->actingAs($s['pengajar'])->get('/admin/presensi-log')->assertForbidden();
        $this->actingAs($s['ketua'])->get('/admin/presensi-log')->assertForbidden();
    }
}
