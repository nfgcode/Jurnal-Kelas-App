<?php

namespace App\Console\Commands;

use App\Support\AuditAdmin;
use App\Support\BackfillRiwayatKelas;
use Illuminate\Console\Command;

class BackfillRiwayatKelasCommand extends Command
{
    protected $signature = 'app:backfill-riwayat-kelas {--execute : Eksekusi penulisan ke database (bawaan: hanya dry-run)}';

    protected $description = 'Analisis atau eksekusi backfill baseline keanggotaan kelas siswa ke riwayat_kelas_siswa';

    public function handle(): int
    {
        $dryRun = ! $this->option('execute');

        $this->info($dryRun
            ? 'Menjalankan SIMULASI (dry-run) backfill riwayat kelas...'
            : 'Menjalankan EKSEKUSI NYATA backfill riwayat kelas...');

        $hasil = BackfillRiwayatKelas::jalankan($dryRun);

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Total Siswa Terdaftar', number_format($hasil['total_siswa'])],
                ['Siswa Memiliki Kelas', number_format($hasil['siswa_dengan_kelas'])],
                ['Siswa Belum Punya Kelas', number_format($hasil['siswa_tanpa_kelas'])],
                ['Siap Ditambahkan ke Riwayat', number_format($hasil['siap_ditambahkan'])],
                ['Dilewati (Sudah Ada di Riwayat)', number_format($hasil['dilewati_sudah_ada'])],
                ['Inkonsistensi Ditemukan', number_format(count($hasil['inkonsistensi']))],
                ['Mode Operasi', $hasil['dry_run'] ? 'SIMULASI (Dry Run)' : 'EKSEKUSI TERSIMPAN'],
            ]
        );

        if ($hasil['inkonsistensi'] !== []) {
            $this->warn('Daftar inkonsistensi yang perlu diperhatikan:');
            foreach ($hasil['inkonsistensi'] as $item) {
                $this->line("- NIS {$item['nis']} ({$item['nama']}): {$item['masalah']}");
            }
        }

        if (! $dryRun && $hasil['eksekusi_berhasil']) {
            AuditAdmin::catatKritis(
                aksi: 'riwayat_kelas.backfill',
                tipeEntitas: 'riwayat_kelas_siswa',
                entitasId: null,
                deskripsi: "Backfill baseline riwayat kelas: {$hasil['siap_ditambahkan']} data ditambahkan, {$hasil['dilewati_sudah_ada']} dilewati.",
                metadata: [
                    'siap_ditambahkan' => $hasil['siap_ditambahkan'],
                    'dilewati' => $hasil['dilewati_sudah_ada'],
                ],
                jumlah: $hasil['siap_ditambahkan'],
            );

            $this->info("✓ Sukses: {$hasil['siap_ditambahkan']} baris riwayat kelas berhasil disimpan.");
        } elseif ($dryRun) {
            $this->comment('Catatan: Tidak ada data yang diubah. Gunakan opsi --execute untuk menyimpan perubahan.');
        }

        return Command::SUCCESS;
    }
}
