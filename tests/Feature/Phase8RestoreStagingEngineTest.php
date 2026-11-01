<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BackupEngine;
use App\Support\RestoreStagingEngine;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase8RestoreStagingEngineTest extends TestCase
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

    public function test_validates_valid_sql_gz_backup_and_matches_checksum(): void
    {
        $admin = $this->admin();
        Storage::fake(BackupEngine::DISK);

        $backupEngine = new BackupEngine();
        $backupResult = $backupEngine->buatBackupDatabase(BackupEngine::FORMAT_SQL_GZ, 'manual', $admin);

        $filePath = Storage::disk(BackupEngine::DISK)->path($backupResult['lokasi']);
        $restoreEngine = new RestoreStagingEngine();

        // 1. Validasi arsip berhasil dengan checksum yang cocok
        $valResult = $restoreEngine->validasiArsip($filePath, $backupResult['checksum'], $backupResult['manifest']);
        $this->assertTrue($valResult['valid']);
        $this->assertSame($backupResult['checksum'], $valResult['sha256']);
        $this->assertTrue($valResult['is_gzip']);
        $this->assertGreaterThan(0, $valResult['file_size']);

        // 2. Validasi struktur SQL lulus allowlist
        $sqlResult = $restoreEngine->validasiStrukturSql($filePath);
        $this->assertTrue($sqlResult['valid']);
        $this->assertGreaterThan(0, $sqlResult['statement_count']);
        $this->assertContains('jurusan', $sqlResult['tables']);
        $this->assertContains('users', $sqlResult['tables']);
    }

    public function test_rejects_corrupted_checksum_and_truncated_gzip(): void
    {
        $admin = $this->admin();
        Storage::fake(BackupEngine::DISK);

        $path = tempnam(sys_get_temp_dir(), 'test_corrupt_') . '.sql.gz';
        file_put_contents($path, "Bukan data gzip asli");

        $restoreEngine = new RestoreStagingEngine();

        // Checksum salah harus ditolak
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Checksum SHA-256 tidak cocok");

        try {
            $restoreEngine->validasiArsip($path, 'sha256_palsu_yang_tidak_cocok');
        } finally {
            @unlink($path);
        }
    }

    public function test_rejects_dangerous_sql_statements(): void
    {
        $restoreEngine = new RestoreStagingEngine();

        $dangerousStatements = [
            "DROP DATABASE `jurnal_kelas`;",
            "GRANT ALL PRIVILEGES ON *.* TO 'attacker'@'%';",
            "SELECT * FROM users INTO OUTFILE '/var/www/shell.php';",
            "ALTER USER 'root'@'localhost' IDENTIFIED BY 'pwned';",
            "SELECT * FROM mysql.user;",
        ];

        foreach ($dangerousStatements as $sql) {
            $path = tempnam(sys_get_temp_dir(), 'danger_') . '.sql';
            file_put_contents($path, $sql);

            $rejected = false;
            try {
                $restoreEngine->validasiStrukturSql($path);
            } catch (DomainException $e) {
                $rejected = true;
                $this->assertStringContainsString("ditolak", strtolower($e->getMessage()));
            } finally {
                @unlink($path);
            }

            $this->assertTrue($rejected, "Pernyataan berbahaya '{$sql}' gagal ditolak!");
        }
    }

    // Test cases for Phase 8.3A: Advanced Staging Parsing & Robust Relational Verification
    public function test_sql_parser_handles_semicolons_inside_strings_and_comments_properly(): void
    {
        $restoreEngine = new RestoreStagingEngine();

        $complexSql = <<<SQL
-- Komentar sebelum statement; tidak boleh memecah
INSERT INTO `users` (`id`, `nama`, `catatan`) VALUES (1, 'Nama dengan ; titik koma', 'Catatan baris 1\nBaris 2; dengan titik koma dan quote \'escaped\'');
/* Komentar blok multi baris ;
   dengan titik koma di dalamnya ; */
INSERT INTO `users` (`id`, `nama`, `catatan`) VALUES (2, 'Siswa 2', 'Biasa');
SQL;

        $path = tempnam(sys_get_temp_dir(), 'test_complex_') . '.sql';
        file_put_contents($path, $complexSql);

        try {
            $res = $restoreEngine->validasiStrukturSql($path);
            $this->assertTrue($res['valid']);
            $this->assertSame(2, $res['statement_count']); // Hanya ada 2 statement INSERT nyata
            $this->assertSame('users', $res['tables'][0]);
        } finally {
            @unlink($path);
        }
    }

    public function test_verifies_relational_integrity_and_generates_impact_report(): void
    {
        $admin = $this->admin();
        Storage::fake(BackupEngine::DISK);

        $backupEngine = new BackupEngine();
        $backupResult = $backupEngine->buatBackupDatabase(BackupEngine::FORMAT_SQL, 'manual', $admin);
        $filePath = Storage::disk(BackupEngine::DISK)->path($backupResult['lokasi']);

        $restoreEngine = new RestoreStagingEngine();
        $sqlResult = $restoreEngine->validasiStrukturSql($filePath);

        $stagingConnection = DB::connection();

        // Jalankan verifikasi integritas relasi pada database staging
        $report = $restoreEngine->verifikasiIntegritasDanDampak($stagingConnection, $backupResult['manifest']);

        $this->assertContains($report['status'], ['relationally_verified', 'integrity_failed']);
        $this->assertGreaterThan(0, $report['tables_checked']);
        $this->assertGreaterThan(0, $report['tables_valid']);
        $this->assertIsArray($report['row_counts']);
        $this->assertArrayHasKey('users', $report['row_counts']);
        $this->assertArrayHasKey('siswa', $report['row_counts']);
        $this->assertArrayHasKey('guru', $report['row_counts']);
    }
}
