<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase9WorkflowNavigationAndHarmonizationTest extends TestCase
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

    public function test_legacy_transition_page_displays_educational_banner_with_link(): void
    {
        $this->admin();

        $response = $this->get(route('admin.transisi.index'));
        $response->assertStatus(200);

        // Verifikasi Breadcrumb
        $response->assertSee('Tahun Ajaran');
        $response->assertSee('Kenaikan & Kelulusan (Legacy)', false);

        // Verifikasi Banner Edukasi
        $response->assertSee('Tersedia Alur Transisi Terpadu Terkoordinasi');
        $response->assertSee(route('admin.transisi-terpadu.index'));
    }

    public function test_workflow_pages_render_consistent_breadcrumbs_and_coordinator_persistence(): void
    {
        $this->admin();

        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true]);
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false]);

        // Buat record koordinator sah dengan tahapan terkait
        $coordId = \Illuminate\Support\Facades\DB::table('academic_workflow_coordinators')->insertGetId([
            'actor_id' => $this->admin()->id,
            'source_year' => '2026/2027',
            'target_year' => '2027/2028',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::table('academic_workflow_stages')->insert([
            ['coordinator_id' => $coordId, 'position' => 1, 'kind' => 'graduation', 'created_at' => now(), 'updated_at' => now()],
            ['coordinator_id' => $coordId, 'position' => 2, 'kind' => 'promotion', 'created_at' => now(), 'updated_at' => now()],
            ['coordinator_id' => $coordId, 'position' => 3, 'kind' => 'intake', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 1. Kelulusan mandiri tanpa koordinator
        $resKelulusanMandiri = $this->get(route('admin.kelulusan.index'));
        $resKelulusanMandiri->assertStatus(200);
        $resKelulusanMandiri->assertSee('Kelulusan Kelas XII');
        $resKelulusanMandiri->assertDontSee('Terhubung dengan Koordinator');

        // 2. Kelulusan dalam sesi terpadu (dengan coordinator_id)
        $resKelulusanTerpadu = $this->get(route('admin.kelulusan.index', [
            'coordinator_id' => $coordId,
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
        ]));
        $resKelulusanTerpadu->assertStatus(200);
        $resKelulusanTerpadu->assertSee("Terhubung dengan Koordinator #{$coordId}");
        $resKelulusanTerpadu->assertSee(route('admin.transisi-terpadu.show', $coordId));

        // 3. Kenaikan dalam sesi terpadu
        $resKenaikanTerpadu = $this->get(route('admin.kenaikan.index', [
            'coordinator_id' => $coordId,
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
        ]));
        $resKenaikanTerpadu->assertStatus(200);
        $resKenaikanTerpadu->assertSee("Terhubung dengan Koordinator #{$coordId}");
        $resKenaikanTerpadu->assertSee(route('admin.transisi-terpadu.show', $coordId));

        // 4. Penerimaan dalam sesi terpadu
        $resIntakeTerpadu = $this->get(route('admin.intake.index', [
            'coordinator_id' => $coordId,
            'tahun_tujuan' => '2027/2028',
        ]));
        $resIntakeTerpadu->assertStatus(200);
        $resIntakeTerpadu->assertSee("Terhubung dengan Koordinator #{$coordId}");
        $resIntakeTerpadu->assertSee(route('admin.transisi-terpadu.show', $coordId));
    }

    public function test_active_membership_counts_strictly_isolate_by_active_year(): void
    {
        $this->admin();

        // Tahun aktif A
        $tahunA = TahunAjaran::create(['kode' => '2025/2026', 'aktif' => true]);
        // Tahun nonaktif B
        $tahunB = TahunAjaran::create(['kode' => '2026/2027', 'aktif' => false]);

        $jurusan = Jurusan::create(['kode' => 'TKJ', 'nama' => 'Teknik Komputer dan Jaringan']);
        $kelasA = Kelas::create(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'tahun_ajaran_kode' => '2025/2026']);
        $kelasB = Kelas::create(['nama_kelas' => 'X TKJ 2', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'tahun_ajaran_kode' => '2026/2027']);

        // Siswa di tahun aktif A
        Siswa::factory()->create(['nis' => '9001', 'kelas_id' => $kelasA->id, 'status' => 'aktif']);
        // Siswa di tahun B (tidak boleh dihitung pada landing tahun aktif)
        Siswa::factory()->create(['nis' => '9002', 'kelas_id' => $kelasB->id, 'status' => 'aktif']);

        $res = $this->get(route('admin.tahun-ajaran.index'));
        $res->assertStatus(200);

        $dist = $res->viewData('distribusiSiswa');
        // Hanya siswa pada kelas tahun aktif 2025/2026 yang terhitung
        $this->assertSame(1, $dist['X']);
        $this->assertSame(1, $dist['total']);
    }
}
