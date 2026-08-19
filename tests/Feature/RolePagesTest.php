<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Half the screens are chosen by role inside the controller: /jurnal renders
 * histori for a guru and riwayat for a siswa, /presensi renders the class recap
 * or the student's own record, and /dashboard forks three ways. The rest of the
 * suite only ever acts as an admin, so none of those branches were being
 * rendered. This covers them, plus the shared journal vocabulary that folds onto
 * the kehadiran_guru columns.
 */
class RolePagesTest extends TestCase
{
    use RefreshDatabase;

    private Jadwal $jadwal;

    private User $guru;

    private User $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);

        $this->jadwal = Jadwal::with('kelas')->firstOrFail();
        $this->guru = $this->jadwal->guru;
        // The ketua kelas: journal filling for a siswa is a ketua-only ability,
        // so the role screens/posts below must act as one.
        $this->siswa = User::where('role', 'siswa')
            ->where('kelas_id', $this->jadwal->kelas_id)
            ->where('is_ketua_kelas', true)
            ->firstOrFail();
    }

    /** A journal belonging to the class this guru and siswa share. */
    private function jurnal(): Jurnal
    {
        return Jurnal::where('jadwal_id', $this->jadwal->id)->firstOrFail();
    }

    public function test_guru_screens_render(): void
    {
        $jurnal = $this->jurnal();

        $expectations = [
            '/dashboard' => 'Dashboard Guru',
            '/jurnal' => 'Histori Jurnal',
            '/jurnal/create' => 'Isi Jurnal Mengajar',
            "/jurnal/{$jurnal->public_id}/edit" => 'Ubah Jurnal Mengajar',
            '/presensi' => 'Rekap Presensi Siswa',
            route('presensi-harian.show', [$this->jadwal->kelas_id, 'tanggal' => $jurnal->tanggal->toDateString()]) => 'Presensi Harian',
            "/jurnal/{$jurnal->public_id}" => 'Kehadiran Siswa Hari Itu',
        ];

        foreach ($expectations as $url => $penanda) {
            $this->actingAs($this->guru)
                ->get($url)
                ->assertOk("GET {$url} did not render for a guru")
                ->assertSee($penanda);
        }
    }

    public function test_siswa_screens_render(): void
    {
        $jurnal = $this->jurnal();

        $expectations = [
            '/dashboard' => 'Dashboard Siswa',
            '/jurnal' => 'Riwayat Jurnal Kelas',
            '/jurnal/create' => 'Mengisi Jurnal Kelas',
            // A ketua kelas files the class roll call, so /presensi is the class
            // recap rather than the personal record a regular siswa sees.
            '/presensi' => 'Rekap Presensi Siswa',
            route('presensi-harian.edit', $this->jadwal->kelas_id) => 'Presensi Hari Ini',
            "/jurnal/{$jurnal->public_id}" => 'Kehadiran Siswa Hari Itu',
        ];

        foreach ($expectations as $url => $penanda) {
            $this->actingAs($this->siswa)
                ->get($url)
                ->assertOk("GET {$url} did not render for a siswa")
                ->assertSee($penanda);
        }
    }

    /**
     * "Mode Wali Kelas" is a whole second set of screens no other test renders,
     * and its attendance page reads the daily roster rather than the journals.
     */
    public function test_wali_kelas_screens_render(): void
    {
        $kelas = $this->jadwal->kelas;
        $wali = User::factory()->create(['role' => 'guru', 'status' => 'aktif']);
        $kelas->update(['wali_kelas_id' => $wali->id]);

        $expectations = [
            '/wali-kelas' => $kelas->nama_kelas,
            '/wali-kelas/siswa' => $kelas->nama_kelas,
            '/wali-kelas/jadwal' => $kelas->nama_kelas,
            '/wali-kelas/jurnal' => $kelas->nama_kelas,
            '/wali-kelas/presensi' => 'Histori Presensi Harian',
        ];

        foreach ($expectations as $url => $penanda) {
            $this->actingAs($wali)
                ->get($url)
                ->assertOk("GET {$url} did not render for a wali kelas")
                ->assertSee($penanda);
        }
    }

    /**
     * A wali kelas oversees attendance but does not file it, so their screen
     * must not offer a way in.
     */
    public function test_the_wali_kelas_attendance_screen_offers_no_way_to_fill_it(): void
    {
        $kelas = $this->jadwal->kelas;
        $wali = User::factory()->create(['role' => 'guru', 'status' => 'aktif']);
        $kelas->update(['wali_kelas_id' => $wali->id]);

        $this->actingAs($wali)
            ->get('/wali-kelas/presensi')
            ->assertOk()
            ->assertDontSee(route('presensi-harian.edit', $kelas), false);
    }

    public function test_the_admin_import_screen_renders(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.impor.index'))
            ->assertOk()
            ->assertSee('Impor Data Siswa')
            ->assertSee('Unduh Template Siswa')
            ->assertSee('Unduh Template Guru');
    }

    public function test_siswa_cannot_access_admin_section(): void
    {
        $this->actingAs($this->siswa)->get('/admin')->assertForbidden();
        $this->actingAs($this->siswa)->get('/admin/users')->assertForbidden();
    }

    /**
     * A guru reports whether work was left behind, and nothing else: no reason
     * and no free-text keterangan end up on the record.
     */
    public function test_guru_reports_their_own_attendance_as_ada_tugas(): void
    {
        $this->actingAs($this->guru)
            ->post('/jurnal', [
                'jadwal_id' => $this->jadwal->id,
                'tanggal' => now()->toDateString(),
                'materi' => 'Turunan fungsi aljabar',
                'tugas' => 'Kerjakan LKS halaman 12.',
                'kehadiran_guru' => 'ada_tugas',
            ])
            ->assertSessionHasNoErrors();

        $jurnal = Jurnal::latest('id')->firstOrFail();

        $this->assertSame('tidak_hadir', $jurnal->kehadiran_guru_status);
        $this->assertTrue((bool) $jurnal->kehadiran_guru_ada_tugas);
        $this->assertNull($jurnal->kehadiran_guru_alasan);
        $this->assertNull($jurnal->kehadiran_guru_keterangan);
        $this->assertSame($this->guru->id, $jurnal->guru_id);
        $this->assertSame($this->guru->id, $jurnal->diisi_oleh_id);
    }

    /**
     * A siswa reports the same three outcomes, may add a note about the absence,
     * and the journal still belongs to the guru on the timetable rather than to
     * the student who typed it.
     */
    public function test_siswa_reports_the_guru_attendance(): void
    {
        $this->actingAs($this->siswa)
            ->post('/jurnal', [
                'jadwal_id' => $this->jadwal->id,
                'tanggal' => now()->toDateString(),
                'materi' => 'Belajar mandiri',
                'kehadiran_guru' => 'ada_tugas',
                'kehadiran_guru_keterangan' => 'Diwakili guru piket',
            ])
            ->assertSessionHasNoErrors();

        $jurnal = Jurnal::latest('id')->firstOrFail();

        $this->assertSame('tidak_hadir', $jurnal->kehadiran_guru_status);
        $this->assertTrue((bool) $jurnal->kehadiran_guru_ada_tugas);
        $this->assertNull($jurnal->kehadiran_guru_alasan);
        $this->assertSame('Diwakili guru piket', $jurnal->kehadiran_guru_keterangan);
        $this->assertSame($this->jadwal->guru_id, $jurnal->guru_id);
        $this->assertSame($this->siswa->id, $jurnal->diisi_oleh_id);
    }

    /**
     * Both roles now share one vocabulary — hadir / ada_tugas / tanpa_tugas.
     * The retired reason options are rejected for either of them.
     */
    public function test_both_roles_share_one_attendance_vocabulary(): void
    {
        $payload = [
            'jadwal_id' => $this->jadwal->id,
            'tanggal' => now()->toDateString(),
            'materi' => 'Materi apa saja',
        ];

        foreach ([$this->guru, $this->siswa] as $pengguna) {
            // The vocabulary both roles now share.
            $this->actingAs($pengguna)
                ->post('/jurnal', $payload + ['kehadiran_guru' => 'tanpa_tugas'])
                ->assertSessionHasNoErrors();

            // The reason vocabulary the ketua kelas used to submit is gone.
            foreach (['sakit', 'izin', 'alpa'] as $usang) {
                $this->actingAs($pengguna)
                    ->post('/jurnal', $payload + ['kehadiran_guru' => $usang])
                    ->assertSessionHasErrors('kehadiran_guru');
            }
        }
    }
}
