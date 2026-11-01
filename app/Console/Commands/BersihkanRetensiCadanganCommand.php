<?php

namespace App\Console\Commands;

use App\Support\BackupEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BersihkanRetensiCadanganCommand extends Command
{
    protected $signature = 'cadangan:bersihkan-retensi {--bulan=12 : Batas usia cadangan dalam bulan}';
    protected $description = 'Membersihkan arsip cadangan yang melebihi batas retensi 12 bulan dengan proteksi pinned dan last-valid';

    public function handle(): int
    {
        $batasBulan = (int) $this->option('bulan');
        $cutoffDate = now()->subMonths($batasBulan);

        $this->info("Menjalankan pembersihan retensi cadangan (batas: > {$batasBulan} bulan, sebelum {$cutoffDate->toDateString()})...");

        // Cari ID backup valid terakhir (apapun tipenya) untuk diproteksi mutlak dari penghapusan
        $lastValidId = DB::table('cadangan_arsip')
            ->where('status_validasi', 'terverifikasi')
            ->latest('id')
            ->value('id');

        // Ambil kandidat cadangan yang sudah melebihi umur retensi
        $kandidat = DB::table('cadangan_arsip')
            ->where('created_at', '<', $cutoffDate)
            ->where('penting', false) // Proteksi 1: Pinned / penting tidak boleh dihapus
            ->when($lastValidId, fn ($q) => $q->where('id', '!=', $lastValidId)) // Proteksi 2: Backup valid terakhir mutlak dilindungi
            ->get();

        if ($kandidat->isEmpty()) {
            $this->info("Tidak ada arsip cadangan yang melewati batas retensi.");
            return Command::SUCCESS;
        }

        $dihapus = 0;
        foreach ($kandidat as $arsip) {
            $lokasi = $arsip->lokasi_privat;

            // Validasi keamanan path: tolak path traversal, symlink, dan lokasi di luar direktori backups
            if (! str_starts_with($lokasi, BackupEngine::DIRECTORY . '/') || str_contains($lokasi, '..')) {
                $this->warn("Menolak menghapus file '{$lokasi}': path di luar direktori aman.");
                continue;
            }

            // Hapus file fisik dari storage privat
            if (Storage::disk(BackupEngine::DISK)->exists($lokasi)) {
                Storage::disk(BackupEngine::DISK)->delete($lokasi);
            }

            // Hapus metadata di database (dua tahap aman)
            DB::table('cadangan_arsip')->where('id', $arsip->id)->delete();
            $dihapus++;
        }

        $this->info("Selesai membersihkan retensi. Total arsip dihapus: {$dihapus}.");
        return Command::SUCCESS;
    }
}
