<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BackupEngine;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase7BackupEngineTest extends TestCase
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

    public function test_creates_sql_gz_database_backup_and_verifies_checksum(): void
    {
        $admin = $this->admin();
        Storage::fake(BackupEngine::DISK);

        $engine = new BackupEngine();
        $res = $engine->buatBackupDatabase(BackupEngine::FORMAT_SQL_GZ, 'manual', $admin, 'Uji backup gz');

        $this->assertNotNull($res['id']);
        $this->assertStringEndsWith('.sql.gz', $res['nama_berkas']);
        $this->assertTrue(Storage::disk(BackupEngine::DISK)->exists($res['lokasi']));

        // Verifikasi metadata di database
        $arsip = DB::table('cadangan_arsip')->where('id', $res['id'])->first();
        $this->assertNotNull($arsip);
        $this->assertSame('sql_gz', $arsip->format);
        $this->assertSame('manual', $arsip->tipe);
        $this->assertSame('terverifikasi', $arsip->status_validasi);
        $this->assertSame(64, strlen($arsip->checksum_sha256));
        $this->assertGreaterThan(0, $arsip->ukuran_bytes);

        // Verifikasi isi manifest
        $manifest = json_decode($arsip->metadata_manifest, true);
        $this->assertSame('1.0', $manifest['version']);
        $this->assertSame('sql_gz', $manifest['format']);
        $this->assertTrue($manifest['integrity_verified']);
        $this->assertFalse($manifest['restore_tested']); // Tidak boleh klaim restore-tested tanpa uji pemulihan nyata
    }

    public function test_creates_uncompressed_sql_backup_alternative(): void
    {
        $admin = $this->admin();
        Storage::fake(BackupEngine::DISK);

        $engine = new BackupEngine();
        $res = $engine->buatBackupDatabase(BackupEngine::FORMAT_SQL, 'manual', $admin);

        $this->assertStringEndsWith('.sql', $res['nama_berkas']);
        $this->assertTrue(Storage::disk(BackupEngine::DISK)->exists($res['lokasi']));

        $arsip = DB::table('cadangan_arsip')->where('id', $res['id'])->first();
        $this->assertSame('sql', $arsip->format);
    }

    public function test_creates_important_files_zip_archive_with_allowlist(): void
    {
        $admin = $this->admin();
        Storage::fake(BackupEngine::DISK);

        $engine = new BackupEngine();
        $res = $engine->buatBackupBerkasPenting('manual', $admin, 'Backup berkas penting');

        $this->assertStringEndsWith('.zip', $res['nama_berkas']);
        $this->assertTrue(Storage::disk(BackupEngine::DISK)->exists($res['lokasi']));

        $arsip = DB::table('cadangan_arsip')->where('id', $res['id'])->first();
        $this->assertSame('zip_berkas', $arsip->format);
        $manifest = json_decode($arsip->metadata_manifest, true);
        $this->assertSame(BackupEngine::ALLOWLIST_BERKAS, $manifest['allowlist']);
    }

    public function test_administrative_csv_export_sanitizes_formula_and_omits_sensitive_data(): void
    {
        $admin = $this->admin();

        $rows = [
            (object) [
                'id' => 1,
                'name' => '=HYPERLINK("http://malicious.site")',
                'email' => '+attacker@evil.com',
                'nis' => '00123',
                'password' => '$2y$10$secretHashMustNotAppear',
                'remember_token' => 'secretToken',
            ],
            (object) [
                'id' => 2,
                'name' => '-CommandInjection',
                'email' => '@AnotherFormula',
                'nis' => '00456',
                'password' => '$2y$10$anotherSecret',
                'remember_token' => 'secretToken2',
            ]
        ];

        $engine = new BackupEngine();
        $response = $engine->eksporCsvAman('users', $rows);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        // Baca konten CSV yang dihasilkan
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // 1. Password hash dan remember_token tidak boleh muncul sama sekali
        $this->assertStringNotContainsString('$2y$10$secretHashMustNotAppear', $content);
        $this->assertStringNotContainsString('remember_token', $content);

        // 2. Formula injection harus di-escape dengan kutip tunggal
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString("'+attacker", $content);
        $this->assertStringContainsString("'-CommandInjection", $content);
        $this->assertStringContainsString("'@AnotherFormula", $content);

        // 3. NIS dengan leading zero harus tetap utuh string murni tanpa tab prefix
        $this->assertStringContainsString('00123', $content);
        $this->assertStringContainsString('00456', $content);
        $this->assertStringNotContainsString("\t00123", $content);
    }
}
