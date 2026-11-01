<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BackupEngine;
use App\Support\RestoreCutoverEngine;
use App\Support\RestoreStagingEngine;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase8RestoreCutoverEngineTest extends TestCase
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

    public function test_orchestrates_successful_cutover_on_disposable_connections(): void
    {
        $admin = $this->admin();
        Storage::fake(BackupEngine::DISK);

        // Buat backup sumber
        $backupEngine = new BackupEngine();
        $backupRes = $backupEngine->buatBackupDatabase(BackupEngine::FORMAT_SQL, 'manual', $admin);
        $archivePath = Storage::disk(BackupEngine::DISK)->path($backupRes['lokasi']);

        $stagingEngine = new RestoreStagingEngine();
        $cutoverEngine = new RestoreCutoverEngine($backupEngine, $stagingEngine);

        $activeConn = DB::connection();
        $stagingConn = DB::connection(); // Pada pengujian lokal SQLite

        $res = $cutoverEngine->jalankanSimulasiCutover(
            $activeConn,
            $stagingConn,
            $archivePath,
            $backupRes['checksum'],
            $backupRes['manifest'],
            $admin
        );

        $this->assertSame('CUTOVER_SUCCESS', $res['status']);
        $this->assertIsArray($res['audit_trail']);
        $this->assertGreaterThanOrEqual(6, count($res['audit_trail']));
    }

    public function test_rolls_back_safely_when_failure_occurs_during_or_post_cutover(): void
    {
        $admin = $this->admin();
        Storage::fake(BackupEngine::DISK);

        $backupEngine = new BackupEngine();
        $backupRes = $backupEngine->buatBackupDatabase(BackupEngine::FORMAT_SQL, 'manual', $admin);
        $archivePath = Storage::disk(BackupEngine::DISK)->path($backupRes['lokasi']);

        $stagingEngine = new RestoreStagingEngine();
        $cutoverEngine = new RestoreCutoverEngine($backupEngine, $stagingEngine);

        $activeConn = DB::connection();
        $stagingConn = DB::connection();

        // Simulasi kegagalan saat cutover -> Harusnya memicu emergency rollback
        $resFailed = $cutoverEngine->jalankanSimulasiCutover(
            $activeConn,
            $stagingConn,
            $archivePath,
            $backupRes['checksum'],
            $backupRes['manifest'],
            $admin,
            simulateFailureDuringCutover: true
        );

        $this->assertSame('CUTOVER_FAILED_ROLLED_BACK', $resFailed['status']);
        $this->assertStringContainsString('Simulated failure during connection pointer switchover', $resFailed['error']);

        // Audit trail harus mencatat instant_rollback
        $stages = array_column($resFailed['audit_trail'], 'stage');
        $this->assertContains('instant_rollback', $stages);
    }
}
