<?php

namespace Tests\Feature;

use App\Models\Alumni;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class Phase9LandingTahunAjaranTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $this->actingAs($user);

        return $user;
    }

    public function test_renders_landing_hub_with_hero_and_four_workflow_cards(): void
    {
        $this->admin();

        $tahunAktif = TahunAjaran::create([
            'kode' => '2026/2027',
            'mulai' => '2026-07-15',
            'selesai' => '2027-06-20',
            'aktif' => true,
        ]);

        $jurusan = Jurusan::create(['kode' => 'RPL', 'nama' => 'Rekayasa Perangkat Lunak']);
        $kelasX = Kelas::create(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X', 'jurusan_kode' => 'RPL', 'tahun_ajaran_kode' => '2026/2027']);
        $kelasXI = Kelas::create(['nama_kelas' => 'XI RPL 1', 'tingkat' => 'XI', 'jurusan_kode' => 'RPL', 'tahun_ajaran_kode' => '2026/2027']);
        $kelasXII = Kelas::create(['nama_kelas' => 'XII RPL 1', 'tingkat' => 'XII', 'jurusan_kode' => 'RPL', 'tahun_ajaran_kode' => '2026/2027']);

        // Siswa aktif di masing-masing tingkat
        Siswa::factory()->create(['nis' => '1001', 'kelas_id' => $kelasX->id, 'status' => 'aktif']);
        Siswa::factory()->create(['nis' => '1002', 'kelas_id' => $kelasXI->id, 'status' => 'aktif']);
        Siswa::factory()->create(['nis' => '1003', 'kelas_id' => $kelasXII->id, 'status' => 'aktif']);

        // Siswa nonaktif & alumni (tidak boleh terhitung)
        Siswa::factory()->create(['nis' => '1004', 'kelas_id' => $kelasX->id, 'status' => 'nonaktif']);
        $siswaAlumni = Siswa::factory()->create(['nis' => '1005', 'kelas_id' => $kelasXII->id, 'status' => 'aktif']);
        Alumni::create(['siswa_nis' => $siswaAlumni->nis, 'tahun_lulus' => '2026/2027']);

        $response = $this->get(route('admin.tahun-ajaran.index'));
        $response->assertStatus(200);

        // 1. Verifikasi Hero & Status Tahun Aktif
        $response->assertSee('Pusat Periode Akademik');
        $response->assertSee('2026/2027 (Sedang Berjalan)');

        // 2. Verifikasi 4 Kartu Workflow Terarah
        $response->assertSee('Kelulusan Kelas XII');
        $response->assertSee(route('admin.kelulusan.index'));

        $response->assertSee('Kenaikan Kelas X–XI');
        $response->assertSee(route('admin.kenaikan.index'));

        $response->assertSee('Penerimaan Siswa Baru');
        $response->assertSee(route('admin.intake.index'));

        $response->assertSee('Transisi Terpadu');
        $response->assertSee(route('admin.transisi-terpadu.index'));

        // 3. Verifikasi Tampilan Statistik Tanpa Double Counting & Tanpa Alumni/Nonaktif
        $viewData = $response->viewData('distribusiSiswa');
        $this->assertSame(1, $viewData['X']);
        $this->assertSame(1, $viewData['XI']);
        $this->assertSame(1, $viewData['XII']);
        $this->assertSame(3, $viewData['total']);
    }

    public function test_renders_empty_state_when_no_active_year(): void
    {
        $this->admin();

        $response = $this->get(route('admin.tahun-ajaran.index'));
        $response->assertStatus(200);
        $response->assertSee('Belum Ada Tahun Ajaran Aktif');

        $viewData = $response->viewData('distribusiSiswa');
        $this->assertSame(0, $viewData['total']);
    }
}
