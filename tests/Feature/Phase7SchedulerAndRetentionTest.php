<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BackupEngine;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase7SchedulerAndRetentionTest extends TestCase
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

    public function test_monthly_backup_command_is_idempotent_for_same_month(): void
    {
        $admin = $this->admin();
        Storage::fake(BackupEngine::DISK);

        $engine = new BackupEngine();
        $cmd = new \App\Console\Commands\BuatCadanganBulananCommand();
        $cmd->setLaravel($this->app);
        $definition = $cmd->getDefinition();
        $input = new \Symfony\Component\Console\Input\ArrayInput([], $definition);
        $output = new \Symfony\Component\Console\Output\NullOutput();
        $cmd->setInput($input);
        $cmd->setOutput(new \Illuminate\Console\OutputStyle($input, $output));

        // 1. Jalankan command pertama kali -> Berhasil membuat backup
        $exitCode1 = $cmd->handle($engine);
        $this->assertSame(0, $exitCode1);

        $count1 = DB::table('cadangan_arsip')->where('tipe', 'otomatis_bulanan')->count();
        $this->assertSame(1, $count1);

        // 2. Jalankan command kedua kali di bulan yang sama -> Dilewati (idempotent)
        $exitCode2 = $cmd->handle($engine);
        $this->assertSame(0, $exitCode2);

        $count2 = DB::table('cadangan_arsip')->where('tipe', 'otomatis_bulanan')->count();
        $this->assertSame(1, $count2); // Tidak bertambah
    }

    public function test_retention_cleans_old_backups_but_protects_pinned_and_last_valid(): void
    {
        Storage::fake(BackupEngine::DISK);

        $oldDate = now()->subMonths(14); // Lebih dari 12 bulan lalu

        // 1. Cadangan tua biasa (harus dihapus)
        $fileOld = BackupEngine::DIRECTORY . '/old.sql.gz';
        Storage::disk(BackupEngine::DISK)->put($fileOld, 'dummy data');
        $idOld = DB::table('cadangan_arsip')->insertGetId([
            'nama_berkas' => 'old.sql.gz',
            'format' => 'sql_gz',
            'tipe' => 'otomatis_bulanan',
            'lokasi_privat' => $fileOld,
            'ukuran_bytes' => 10,
            'checksum_sha256' => hash('sha256', 'dummy data'),
            'status_validasi' => 'terverifikasi',
            'penting' => false,
            'created_at' => $oldDate,
            'updated_at' => $oldDate,
        ]);

        // 2. Cadangan tua tapi Pinned / Penting (HARUS DILINDUNGI)
        $filePinned = BackupEngine::DIRECTORY . '/pinned.sql.gz';
        Storage::disk(BackupEngine::DISK)->put($filePinned, 'pinned data');
        $idPinned = DB::table('cadangan_arsip')->insertGetId([
            'nama_berkas' => 'pinned.sql.gz',
            'format' => 'sql_gz',
            'tipe' => 'manual',
            'lokasi_privat' => $filePinned,
            'ukuran_bytes' => 11,
            'checksum_sha256' => hash('sha256', 'pinned data'),
            'status_validasi' => 'terverifikasi',
            'penting' => true,
            'created_at' => $oldDate,
            'updated_at' => $oldDate,
        ]);

        // 3. Cadangan valid terakhir (HARUS DILINDUNGI)
        $fileLatest = BackupEngine::DIRECTORY . '/latest.sql.gz';
        Storage::disk(BackupEngine::DISK)->put($fileLatest, 'latest data');
        $idLatest = DB::table('cadangan_arsip')->insertGetId([
            'nama_berkas' => 'latest.sql.gz',
            'format' => 'sql_gz',
            'tipe' => 'manual',
            'lokasi_privat' => $fileLatest,
            'ukuran_bytes' => 11,
            'checksum_sha256' => hash('sha256', 'latest data'),
            'status_validasi' => 'terverifikasi',
            'penting' => false,
            'created_at' => now()->subMonths(1),
            'updated_at' => now()->subMonths(1),
        ]);

        $cmdRetention = new \App\Console\Commands\BersihkanRetensiCadanganCommand();
        $cmdRetention->setLaravel($this->app);
        $definitionR = $cmdRetention->getDefinition();
        $inputR = new \Symfony\Component\Console\Input\ArrayInput(['--bulan' => 12], $definitionR);
        $outputR = new \Symfony\Component\Console\Output\NullOutput();
        $cmdRetention->setInput($inputR);
        $cmdRetention->setOutput(new \Illuminate\Console\OutputStyle($inputR, $outputR));
        $exitCode = $cmdRetention->handle();
        $this->assertSame(0, $exitCode);

        // Verifikasi fileOld dihapus
        $this->assertFalse(Storage::disk(BackupEngine::DISK)->exists($fileOld));
        $this->assertDatabaseMissing('cadangan_arsip', ['id' => $idOld]);

        // Verifikasi pinned dan latest tetap ada
        $this->assertTrue(Storage::disk(BackupEngine::DISK)->exists($filePinned));
        $this->assertDatabaseHas('cadangan_arsip', ['id' => $idPinned]);

        $this->assertTrue(Storage::disk(BackupEngine::DISK)->exists($fileLatest));
        $this->assertDatabaseHas('cadangan_arsip', ['id' => $idLatest]);
    }

    public function test_status_kesehatan_detects_overdue_backup(): void
    {
        Storage::fake(BackupEngine::DISK);

        // Belum ada backup sama sekali
        $status1 = BackupEngine::statusKesehatan();
        $this->assertTrue($status1['is_overdue']);

        // Tambah backup baru hari ini
        DB::table('cadangan_arsip')->insert([
            'nama_berkas' => 'today.sql.gz',
            'format' => 'sql_gz',
            'tipe' => 'otomatis_bulanan',
            'lokasi_privat' => 'backups/today.sql.gz',
            'ukuran_bytes' => 100,
            'checksum_sha256' => hash('sha256', 'data'),
            'status_validasi' => 'terverifikasi',
            'penting' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $status2 = BackupEngine::statusKesehatan();
        $this->assertFalse($status2['is_overdue']);
        $this->assertSame(1, $status2['total_arsip']);
    }
}
