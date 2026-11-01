<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase9CoordinatorContextPersistenceTest extends TestCase
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

    public function test_rejects_fictitious_coordinator_id_and_falls_back_to_standalone_mode(): void
    {
        $this->admin();

        // Coba berikan coordinator_id fiktif 88888 (tidak ada di DB)
        $response = $this->get(route('admin.kelulusan.index', ['coordinator_id' => 88888]));
        $response->assertStatus(200);

        // Jangan tampilkan banner sesi terpadu atau link balik untuk ID fiktif
        $response->assertDontSee('Terhubung dengan Koordinator #88888');
        $response->assertDontSee(route('admin.transisi-terpadu.show', 88888));
    }

    private function createTahunAjaran(string $kode): void
    {
        DB::table('tahun_ajaran')->updateOrInsert(['kode' => $kode], [
            'mulai' => now()->toDateString(),
            'selesai' => now()->addYear()->toDateString(),
            'aktif' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_validates_real_coordinator_id_and_renders_coordination_banner(): void
    {
        $admin = $this->admin();
        $this->createTahunAjaran('2026/2027');
        $this->createTahunAjaran('2027/2028');

        // Buat record koordinator sah di DB dengan tahapan yang sesuai
        $coordId = DB::table('academic_workflow_coordinators')->insertGetId([
            'actor_id' => $admin->id,
            'source_year' => '2026/2027',
            'target_year' => '2027/2028',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('academic_workflow_stages')->insert([
            'coordinator_id' => $coordId,
            'position' => 1,
            'kind' => 'graduation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('admin.kelulusan.index', [
            'coordinator_id' => $coordId,
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
        ]));
        $response->assertStatus(200);

        // Harus menampilkan banner sesi terpadu sah
        $response->assertSee("Terhubung dengan Koordinator #{$coordId}");
        $response->assertSee(route('admin.transisi-terpadu.show', $coordId));
    }

    public function test_rejects_coordinator_id_if_kind_mismatches(): void
    {
        $admin = $this->admin();
        $this->createTahunAjaran('2026/2027');
        $this->createTahunAjaran('2027/2028');

        // Buat koordinator yang HANYA memiliki tahapan kenaikan (promotion), bukan kelulusan (graduation)
        $coordId = DB::table('academic_workflow_coordinators')->insertGetId([
            'actor_id' => $admin->id,
            'source_year' => '2026/2027',
            'target_year' => '2027/2028',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('academic_workflow_stages')->insert([
            'coordinator_id' => $coordId,
            'position' => 1,
            'kind' => 'promotion',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Coba akses modul kelulusan dengan ID koordinator promosi
        $response = $this->get(route('admin.kelulusan.index', [
            'coordinator_id' => $coordId,
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
        ]));
        $response->assertStatus(200);

        // Harus ditolak (fallback ke mode mandiri) karena kind mismatch
        $response->assertDontSee("Terhubung dengan Koordinator #{$coordId}");
        $response->assertDontSee(route('admin.transisi-terpadu.show', $coordId));
    }

    public function test_rejects_coordinator_id_if_period_mismatches(): void
    {
        $admin = $this->admin();
        $this->createTahunAjaran('2025/2026');
        $this->createTahunAjaran('2026/2027');
        $this->createTahunAjaran('2027/2028');

        // Buat koordinator untuk periode 2025/2026 -> 2026/2027
        $coordId = DB::table('academic_workflow_coordinators')->insertGetId([
            'actor_id' => $admin->id,
            'source_year' => '2025/2026',
            'target_year' => '2026/2027',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('academic_workflow_stages')->insert([
            'coordinator_id' => $coordId,
            'position' => 1,
            'kind' => 'graduation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Akses dengan periode berbeda 2026/2027 -> 2027/2028
        $response = $this->get(route('admin.kelulusan.index', [
            'coordinator_id' => $coordId,
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
        ]));
        $response->assertStatus(200);

        // Harus ditolak karena periode tidak sesuai
        $response->assertDontSee("Terhubung dengan Koordinator #{$coordId}");
        $response->assertDontSee(route('admin.transisi-terpadu.show', $coordId));
    }
}
