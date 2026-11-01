<?php

namespace App\Console\Commands;

use App\Support\BackupEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BuatCadanganBulananCommand extends Command
{
    protected $signature = 'cadangan:buat-bulanan {--force : Paksa buat cadangan meskipun bulan ini sudah ada}';
    protected $description = 'Membuat cadangan database bulanan otomatis (sql.gz) secara idempotent';

    public function handle(BackupEngine $engine): int
    {
        $bulanIni = now()->format('Y-m');

        if (! $this->option('force')) {
            // Idempotency: cek apakah sudah ada backup otomatis bulanan yang berhasil pada bulan ini
            $prefix = "{$bulanIni}-";
            $sudahAda = DB::table('cadangan_arsip')
                ->where('tipe', 'otomatis_bulanan')
                ->where('status_validasi', 'terverifikasi')
                ->where('created_at', '>=', now()->startOfMonth())
                ->where('created_at', '<=', now()->endOfMonth())
                ->exists();

            if ($sudahAda) {
                $this->info("Cadangan otomatis untuk bulan {$bulanIni} sudah tersedia. Dilewati (idempotent).");
                return 0; // Command::SUCCESS
            }
        }

        $this->info("Memulai pembuatan cadangan otomatis bulanan...");

        try {
            $hasil = $engine->buatBackupDatabase(
                BackupEngine::FORMAT_SQL_GZ,
                'otomatis_bulanan',
                null,
                "Cadangan otomatis terjadwal bulan {$bulanIni}"
            );

            $this->info("Cadangan otomatis berhasil dibuat: {$hasil['nama_berkas']} (ID: {$hasil['id']})");
            return 0; // Command::SUCCESS
        } catch (Throwable $e) {
            $this->error("Gagal membuat cadangan otomatis: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return 1; // Command::FAILURE
        }
    }
}
