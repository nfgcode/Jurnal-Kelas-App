<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase9TransisiTerpaduKpiTest extends TestCase
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

    private function createTahun(string $kode, bool $aktif = false): TahunAjaran
    {
        return TahunAjaran::create([
            'kode' => $kode,
            'mulai' => now()->toDateString(),
            'selesai' => now()->addYear()->toDateString(),
            'aktif' => $aktif,
        ]);
    }

    public function test_kpi_counts_strictly_reflect_active_students_in_selected_source_year(): void
    {
        $this->admin();

        $thA = $this->createTahun('2025/2026', aktif: true);
        $thB = $this->createTahun('2026/2027', aktif: false);

        // Buat kelas untuk 2025/2026
        $kX = Kelas::create(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'tahun_ajaran_kode' => '2025/2026']);
        $kXI = Kelas::create(['nama_kelas' => 'XI TKJ 1', 'tingkat' => 'XI', 'tahun_ajaran_kode' => '2025/2026']);
        $kXII = Kelas::create(['nama_kelas' => 'XII TKJ 1', 'tingkat' => 'XII', 'tahun_ajaran_kode' => '2025/2026']);

        // Siswa aktif di 2025/2026
        Siswa::create(['nis' => '1001', 'nama' => 'Siswa X 1', 'kelas_id' => $kX->id, 'status' => 'aktif']);
        Siswa::create(['nis' => '1002', 'nama' => 'Siswa X 2', 'kelas_id' => $kX->id, 'status' => 'aktif']);
        Siswa::create(['nis' => '2001', 'nama' => 'Siswa XI 1', 'kelas_id' => $kXI->id, 'status' => 'aktif']);
        Siswa::create(['nis' => '3001', 'nama' => 'Siswa XII 1', 'kelas_id' => $kXII->id, 'status' => 'aktif']);

        // Siswa nonaktif (tidak boleh dihitung)
        Siswa::create(['nis' => '1099', 'nama' => 'Siswa Nonaktif', 'kelas_id' => $kX->id, 'status' => 'nonaktif']);

        // Siswa alumni (tidak boleh dihitung)
        Siswa::create(['nis' => '3099', 'nama' => 'Siswa Alumni', 'kelas_id' => $kXII->id, 'status' => 'aktif']);
        DB::table('alumni')->insert([
            'siswa_nis' => '3099',
            'tahun_lulus' => '2025/2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Request ke index dengan tahun_asal = 2025/2026
        $res = $this->get(route('admin.transisi-terpadu.index', ['tahun_asal' => '2025/2026']));
        $res->assertStatus(200);

        // Verifikasi angka KPI: X = 2, XI = 1, XII = 1, Total = 4
        $res->assertViewHas('kpiSiswa', function ($kpi) {
            return $kpi['X'] === 2
                && $kpi['XI'] === 1
                && $kpi['XII'] === 1
                && $kpi['total'] === 4;
        });

        // Verifikasi rendered HTML
        $res->assertSee('Total Siswa');
        $res->assertSee('Siswa Kelas X');
        $res->assertSee('Siswa Kelas XI');
        $res->assertSee('Calon Lulusan (XII)');
    }
}
