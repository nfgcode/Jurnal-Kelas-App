<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TahunAjaran;
use App\AcademicWorkflows\Coordinator;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase9FullModuleCohesionAndE2ETest extends TestCase
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

    private function createTahun(string $kode, bool $aktif = false): void
    {
        DB::table('tahun_ajaran')->updateOrInsert(['kode' => $kode], [
            'mulai' => now()->toDateString(),
            'selesai' => now()->addYear()->toDateString(),
            'aktif' => $aktif,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_all_admin_navigation_routes_are_accessible_and_authenticated(): void
    {
        $this->admin();
        $this->createTahun('2026/2027', true);

        // 1. Tahun Ajaran Hub
        $resTahunAjaran = $this->get(route('admin.tahun-ajaran.index'));
        $resTahunAjaran->assertStatus(200);
        $resTahunAjaran->assertSee('Pusat Periode Akademik');

        // 2. Kelulusan XII
        $resKelulusan = $this->get(route('admin.kelulusan.index'));
        $resKelulusan->assertStatus(200);
        $resKelulusan->assertSee('Kelulusan Kelas XII');

        // 3. Kenaikan Kelas X-XI
        $resKenaikan = $this->get(route('admin.kenaikan.index'));
        $resKenaikan->assertStatus(200);
        $resKenaikan->assertSee('Kenaikan Kelas X–XI');

        // 4. Penerimaan Siswa Baru
        $resIntake = $this->get(route('admin.intake.index'));
        $resIntake->assertStatus(200);
        $resIntake->assertSee('Penerimaan Siswa Baru Tingkat X');

        // 5. Transisi Terpadu
        $resTransisiTerpadu = $this->get(route('admin.transisi-terpadu.index'));
        $resTransisiTerpadu->assertStatus(200);
        $resTransisiTerpadu->assertSee('Transisi Terpadu');

        // 6. Manajemen Data Hub
        $resManajemenData = $this->get(route('admin.manajemen-data.index'));
        $resManajemenData->assertStatus(200);
        $resManajemenData->assertSee('Manajemen Data');

        // 7. Impor Data
        $resImpor = $this->get(route('admin.impor.index'));
        $resImpor->assertStatus(200);
        $resImpor->assertSee('Impor Data');

        // 8. Cadangan / Backup
        $resCadangan = $this->get(route('admin.cadangan.index'));
        $resCadangan->assertStatus(200);
        $resCadangan->assertSee('Cadangan Data');

        // 9. Panduan Manajemen Data
        $resPanduan = $this->get(route('admin.manajemen-data.panduan'));
        $resPanduan->assertStatus(200);
        $resPanduan->assertSee('Panduan');

        // 10. Staged Restore
        $resRestore = $this->get(route('admin.manajemen-data.pemulihan.index'));
        $resRestore->assertStatus(200);
        $resRestore->assertSee('Uji Pemulihan Database');
    }

    public function test_transisi_terpadu_multi_stage_coordinator_flow_and_gating(): void
    {
        $admin = $this->admin();
        $this->createTahun('2026/2027', true);
        $this->createTahun('2027/2028', false);

        // Buat sesi koordinator terpadu
        $coordId = DB::table('academic_workflow_coordinators')->insertGetId([
            'actor_id' => $admin->id,
            'source_year' => '2026/2027',
            'target_year' => '2027/2028',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('academic_workflow_stages')->insert([
            ['coordinator_id' => $coordId, 'position' => 1, 'kind' => 'graduation', 'created_at' => now(), 'updated_at' => now()],
            ['coordinator_id' => $coordId, 'position' => 2, 'kind' => 'promotion', 'created_at' => now(), 'updated_at' => now()],
            ['coordinator_id' => $coordId, 'position' => 3, 'kind' => 'intake', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Kunjungi halaman detail sesi koordinator
        $response = $this->get(route('admin.transisi-terpadu.show', $coordId));
        $response->assertStatus(200);
        $response->assertSee("#{$coordId}");
        $response->assertSee('2026/2027');
        $response->assertSee('2027/2028');

        // Verifikasi tautan ke tahap 1 (Kelulusan) membawa coordinator_id
        $response->assertSee("coordinator_id={$coordId}");
    }

    public function test_staged_restore_ui_and_production_restore_disabled(): void
    {
        $this->admin();

        $response = $this->get(route('admin.manajemen-data.pemulihan.index'));
        $response->assertStatus(200);

        // Verifikasi badge/keterangan keamanan bahwa restore produksi nonaktif
        $response->assertSee('Production Restore: DISABLED', false);
        $response->assertSee('Lingkungan Aktif Dilindungi Penuh', false);
        $response->assertDontSee('Eksekusi Timpa Produksi');
    }

    public function test_resolve_valid_coordinator_id_deep_validation(): void
    {
        $admin = $this->admin();
        $this->createTahun('2026/2027', true);
        $this->createTahun('2027/2028', false);

        $coordId = DB::table('academic_workflow_coordinators')->insertGetId([
            'actor_id' => $admin->id,
            'source_year' => '2026/2027',
            'target_year' => '2027/2028',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('academic_workflow_stages')->insert([
            ['coordinator_id' => $coordId, 'position' => 1, 'kind' => 'graduation', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $resolver = app(Coordinator::class);

        // Kasus 1: ID null
        $this->assertNull($resolver->resolveValidCoordinatorId(null, 'graduation', '2026/2027', '2027/2028'));

        // Kasus 2: ID tidak ditemukan di DB
        $this->assertNull($resolver->resolveValidCoordinatorId(77777, 'graduation', '2026/2027', '2027/2028'));

        // Kasus 3: Kind salah (minta promotion tapi koordinator hanya punya graduation)
        $this->assertNull($resolver->resolveValidCoordinatorId($coordId, 'promotion', '2026/2027', '2027/2028'));

        // Kasus 4: Periode sumber salah
        $this->assertNull($resolver->resolveValidCoordinatorId($coordId, 'graduation', '2024/2025', '2027/2028'));

        // Kasus 5: Sah dan valid
        $this->assertEquals($coordId, $resolver->resolveValidCoordinatorId($coordId, 'graduation', '2026/2027', '2027/2028'));
    }
}
