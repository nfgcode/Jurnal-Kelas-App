<?php

namespace App\Support;

use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillRiwayatKelas
{
    /**
     * Jalankan analisis atau eksekusi backfill baseline keanggotaan kelas siswa.
     *
     * @return array{
     *     total_siswa: int,
     *     siswa_dengan_kelas: int,
     *     siswa_tanpa_kelas: int,
     *     siap_ditambahkan: int,
     *     dilewati_sudah_ada: int,
     *     inkonsistensi: list<array<string, mixed>>,
     *     dry_run: bool,
     *     eksekusi_berhasil: bool
     * }
     */
    public static function jalankan(bool $dryRun = true): array
    {
        $semuaSiswa = Siswa::with('kelas')->get();
        $totalSiswa = $semuaSiswa->count();

        $siswaDenganKelas = $semuaSiswa->whereNotNull('kelas_id');
        $siswaTanpaKelas = $semuaSiswa->whereNull('kelas_id');

        $barisBaru = [];
        $dilewati = 0;
        $inkonsistensi = [];

        $tabelAda = Schema::hasTable('riwayat_kelas_siswa');

        if (! $dryRun && ! $tabelAda) {
            throw new \RuntimeException('Tabel riwayat_kelas_siswa belum ada di basis data. Jalankan migrasi terlebih dahulu.');
        }

        foreach ($siswaDenganKelas as $s) {
            $kelas = $s->kelas;

            if (! $kelas) {
                $inkonsistensi[] = [
                    'nis' => $s->nis,
                    'nama' => $s->nama,
                    'masalah' => "kelas_id {$s->kelas_id} tidak ditemukan pada tabel kelas",
                ];
                continue;
            }

            if (empty($kelas->tahun_ajaran_kode)) {
                $inkonsistensi[] = [
                    'nis' => $s->nis,
                    'nama' => $s->nama,
                    'masalah' => "Kelas {$kelas->nama_kelas} tidak memiliki tahun_ajaran_kode",
                ];
                continue;
            }

            // Pengecekan idempotensi jika tabel sudah ada
            $sudahAda = $tabelAda && RiwayatKelasSiswa::where('siswa_nis', $s->nis)
                ->where('kelas_id', $kelas->id)
                ->where('tahun_ajaran_kode', $kelas->tahun_ajaran_kode)
                ->exists();

            if ($sudahAda) {
                $dilewati++;
                continue;
            }

            $statusRiwayat = match ($s->status) {
                'lulus' => 'lulus',
                'nonaktif' => 'keluar',
                default => 'aktif',
            };

            $barisBaru[] = [
                'siswa_nis' => $s->nis,
                'kelas_id' => $kelas->id,
                'tahun_ajaran_kode' => $kelas->tahun_ajaran_kode,
                'tingkat' => $kelas->tingkat,
                'status' => $statusRiwayat,
                'aktif' => $s->status === 'aktif',
                'tanggal_mulai' => null,
                'tanggal_selesai' => null,
                'catatan' => 'Inisialisasi baseline keanggotaan kelas berjalan',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $eksekusiBerhasil = false;

        if (! $dryRun && count($barisBaru) > 0) {
            DB::transaction(function () use ($barisBaru, &$eksekusiBerhasil) {
                foreach (array_chunk($barisBaru, 250) as $chunk) {
                    DB::table('riwayat_kelas_siswa')->insert($chunk);
                }
                $eksekusiBerhasil = true;
            });
        }

        return [
            'total_siswa' => $totalSiswa,
            'siswa_dengan_kelas' => $siswaDenganKelas->count(),
            'siswa_tanpa_kelas' => $siswaTanpaKelas->count(),
            'siap_ditambahkan' => count($barisBaru),
            'dilewati_sudah_ada' => $dilewati,
            'inkonsistensi' => $inkonsistensi,
            'dry_run' => $dryRun,
            'eksekusi_berhasil' => $eksekusiBerhasil,
        ];
    }
}
