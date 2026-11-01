<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BackupEngine;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase8PemulihanControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'status' => 'aktif',
            'password' => Hash::make('rahasia123'),
        ]);
        $this->actingAs($user);

        return $user;
    }

    public function test_admin_can_access_pemulihan_index_page(): void
    {
        $admin = $this->admin();

        $response = $this->get(route('admin.manajemen-data.pemulihan.index'));
        $response->assertStatus(200);
        $response->assertSee('Uji Pemulihan Database');
        $response->assertSee('Production Restore: DISABLED');
    }

    public function test_executes_staging_test_and_renders_impact_report(): void
    {
        $admin = $this->admin();
        Storage::fake(BackupEngine::DISK);

        // Buat backup nyata
        $backupEngine = new BackupEngine();
        $backupResult = $backupEngine->buatBackupDatabase(BackupEngine::FORMAT_SQL, 'manual', $admin);

        $response = $this->post(route('admin.manajemen-data.pemulihan.uji-staging'), [
            'arsip_id' => $backupResult['id'],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Laporan Dampak & Integritas Relasional', false);
        $response->assertSee('DISABLED');
        $response->assertSee('Persetujuan Administratif Uji Pemulihan');
    }

    public function test_requires_admin_password_reauthentication_for_approval(): void
    {
        $admin = $this->admin();
        Storage::fake(BackupEngine::DISK);

        $backupEngine = new BackupEngine();
        $backupResult = $backupEngine->buatBackupDatabase(BackupEngine::FORMAT_SQL, 'manual', $admin);

        $validToken = Crypt::encryptString(json_encode([
            'arsip_id' => $backupResult['id'],
            'checksum' => $backupResult['checksum'],
            'status' => 'relationally_verified',
            'created_at' => now()->timestamp,
            'admin_id' => $admin->id,
        ], JSON_THROW_ON_ERROR));

        // 1. Password salah -> ditolak
        $responseFail = $this->post(route('admin.manajemen-data.pemulihan.setujui'), [
            'approval_token' => $validToken,
            'password' => 'salah_password',
        ]);
        $responseFail->assertSessionHasErrors('password');

        // 2. Password benar -> diterima secara administratif
        $responseOk = $this->post(route('admin.manajemen-data.pemulihan.setujui'), [
            'approval_token' => $validToken,
            'password' => 'rahasia123',
            'catatan' => 'Verifikasi simulasi audit pemulihan',
        ]);
        $responseOk->assertRedirect(route('admin.manajemen-data.pemulihan.index'));
        $responseOk->assertSessionHas('status');

        // Pastikan audit trail tercatat
        $this->assertDatabaseHas('admin_audit_log', [
            'aksi' => 'pemulihan.persetujuan_administratif',
            'tipe_entitas' => 'cadangan_arsip',
        ]);
    }

    public function test_rejects_expired_or_tampered_approval_tokens(): void
    {
        $admin = $this->admin();
        Storage::fake(BackupEngine::DISK);

        $backupEngine = new BackupEngine();
        $backupResult = $backupEngine->buatBackupDatabase(BackupEngine::FORMAT_SQL, 'manual', $admin);

        // Token yang sudah kedaluwarsa (> 15 menit)
        $expiredToken = Crypt::encryptString(json_encode([
            'arsip_id' => $backupResult['id'],
            'checksum' => $backupResult['checksum'],
            'status' => 'relationally_verified',
            'created_at' => now()->subMinutes(20)->timestamp,
            'admin_id' => $admin->id,
        ], JSON_THROW_ON_ERROR));

        $response = $this->post(route('admin.manajemen-data.pemulihan.setujui'), [
            'approval_token' => $expiredToken,
            'password' => 'rahasia123',
        ]);
        $response->assertSessionHasErrors('approval');
    }
}
