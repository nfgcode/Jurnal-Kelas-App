<?php

namespace App\Support;

use App\Models\Kelas;
use App\Models\Presensi;
use App\Models\PresensiHarian;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TinjauanRetensi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class KebijakanRetensi
{
    /** Batas ambang hari inaktivitas */
    public const AMBANG_TINJAUAN_HARI = 365;   // 1 tahun: tinjauan deaktivasi / arsip
    public const AMBANG_HAPUS_HARI = 730;      // 2 tahun: ambang batas tinjau hapus

    /**
     * Hitung tanggal referensi inaktivitas yang sah untuk seorang siswa.
     */
    public static function tanggalReferensi(Siswa $siswa): Carbon
    {
        // Siswa aktif tidak inaktif
        if ($siswa->status === 'aktif') {
            return now();
        }

        // Untuk alumni (lulus), gunakan tanggal kelulusan formal
        if ($siswa->status === 'lulus' && $siswa->alumni) {
            if ($siswa->alumni->tanggal_lulus) {
                return Carbon::parse($siswa->alumni->tanggal_lulus);
            }
            if ($siswa->alumni->tahunLulus?->selesai) {
                return Carbon::parse($siswa->alumni->tahunLulus->selesai);
            }
            if ($siswa->alumni->created_at) {
                return Carbon::parse($siswa->alumni->created_at);
            }
        }

        // Untuk siswa nonaktif / keluar, cari aktivitas pendidikan terakhir
        $tanggalTerakhirPresensi = PresensiHarian::where('siswa_nis', $siswa->nis)->max('tanggal');
        if ($tanggalTerakhirPresensi) {
            return Carbon::parse($tanggalTerakhirPresensi);
        }

        $tanggalSelesaiKelas = RiwayatKelasSiswa::where('siswa_nis', $siswa->nis)->max('tanggal_selesai');
        if ($tanggalSelesaiKelas) {
            return Carbon::parse($tanggalSelesaiKelas);
        }

        // Fallback terakhir: created_at / updated_at
        return $siswa->updated_at ? Carbon::parse($siswa->updated_at) : Carbon::parse($siswa->created_at);
    }

    /**
     * Hitung durasi hari inaktivitas.
     */
    public static function usiaInaktifHari(Siswa $siswa): int
    {
        if ($siswa->status === 'aktif') {
            return 0;
        }

        $ref = self::tanggalReferensi($siswa);

        return max(0, (int) $ref->diffInDays(now()));
    }

    /**
     * Format usia inaktivitas ke dalam teks bahasa Indonesia.
     */
    public static function formatUsiaInaktif(int $hari): string
    {
        if ($hari === 0) {
            return 'Aktif (0 hari)';
        }

        $tahun = floor($hari / 365);
        $sisaHari = $hari % 365;
        $bulan = floor($sisaHari / 30);

        if ($tahun > 0) {
            return $bulan > 0
                ? "{$tahun} tahun {$bulan} bulan ({$hari} hari)"
                : "{$tahun} tahun ({$hari} hari)";
        }

        if ($bulan > 0) {
            return "{$bulan} bulan ({$hari} hari)";
        }

        return "{$hari} hari";
    }

    /**
     * Periksa seluruh dependensi relasional data siswa secara menyeluruh.
     *
     * @return array{
     *     presensi_harian: int,
     *     presensi_legacy: int,
     *     jurnal_diisi: int,
     *     riwayat_kelas: int,
     *     data_alumni: bool,
     *     akun_login: bool,
     *     ketua_kelas: bool,
     *     total_dependensi: int
     * }
     */
    public static function periksaDependensi(Siswa $siswa): array
    {
        $presensiHarian = PresensiHarian::where('siswa_nis', $siswa->nis)->count();
        $presensiLegacy = Presensi::where('siswa_nis', $siswa->nis)->count();
        $jurnalDiisi = $siswa->jurnals()->count();
        $riwayatKelas = RiwayatKelasSiswa::where('siswa_nis', $siswa->nis)->count();
        $dataAlumni = $siswa->alumni()->exists();
        $akunLogin = $siswa->akun()->exists();
        $ketuaKelas = Kelas::where('ketua_nis', $siswa->nis)->exists();

        $total = $presensiHarian + $presensiLegacy + $jurnalDiisi + $riwayatKelas
            + ($dataAlumni ? 1 : 0) + ($akunLogin ? 1 : 0) + ($ketuaKelas ? 1 : 0);

        return [
            'presensi_harian' => $presensiHarian,
            'presensi_legacy' => $presensiLegacy,
            'jurnal_diisi' => $jurnalDiisi,
            'riwayat_kelas' => $riwayatKelas,
            'data_alumni' => $dataAlumni,
            'akun_login' => $akunLogin,
            'ketua_kelas' => $ketuaKelas,
            'total_dependensi' => $total,
        ];
    }

    /**
     * Evaluasi siklus hidup dan kelayakan retensi siswa secara komprehensif.
     *
     * @return array<string, mixed>
     */
    public static function evaluasi(Siswa $siswa): array
    {
        $hariInaktif = self::usiaInaktifHari($siswa);
        $tglRef = self::tanggalReferensi($siswa);
        $dependensi = self::periksaDependensi($siswa);

        $alasanPemblokiran = [];

        if ($siswa->status === 'aktif') {
            $alasanPemblokiran[] = 'Siswa masih berstatus aktif dalam proses belajar mengajar.';
        }

        if ($dependensi['presensi_harian'] > 0) {
            $alasanPemblokiran[] = sprintf(
                'Memiliki %s catatan kehadiran pada presensi harian.',
                number_format($dependensi['presensi_harian'], 0, ',', '.')
            );
        }

        if ($dependensi['presensi_legacy'] > 0) {
            $alasanPemblokiran[] = sprintf(
                'Memiliki %s catatan kehadiran pada presensi per pertemuan.',
                number_format($dependensi['presensi_legacy'], 0, ',', '.')
            );
        }

        if ($dependensi['jurnal_diisi'] > 0) {
            $alasanPemblokiran[] = sprintf(
                'Tercatat sebagai pengisi jurnal untuk %s pertemuan kelas.',
                number_format($dependensi['jurnal_diisi'], 0, ',', '.')
            );
        }

        if ($dependensi['riwayat_kelas'] > 0) {
            $alasanPemblokiran[] = sprintf(
                'Memiliki %d rekam jejak keanggotaan kelas historis.',
                $dependensi['riwayat_kelas']
            );
        }

        if ($dependensi['data_alumni']) {
            $alasanPemblokiran[] = 'Terdaftar dalam basis data kelulusan alumni.';
        }

        if ($dependensi['ketua_kelas']) {
            $alasanPemblokiran[] = 'Masih tercatat sebagai ketua kelas aktif pada salah satu rombel.';
        }

        $memilikiDependensi = ! empty($alasanPemblokiran);

        // Kategori ambang retensi
        if ($siswa->status === 'aktif') {
            $ambangRetensi = 'aktif';
        } elseif ($hariInaktif < self::AMBANG_TINJAUAN_HARI) {
            $ambangRetensi = 'kurang_dari_1_tahun';
        } elseif ($hariInaktif <= self::AMBANG_HAPUS_HARI) {
            $ambangRetensi = '1_sampai_2_tahun';
        } else {
            $ambangRetensi = 'lebih_dari_2_tahun';
        }

        // Klasifikasi status siklus hidup
        if ($siswa->status === 'aktif') {
            $statusSiklus = 'siswa_aktif';
            $labelSiklus = 'Siswa Aktif';
            $toneSiklus = 'green';
            $perluTinjauan = false;
        } elseif ($siswa->status === 'lulus' && $hariInaktif < self::AMBANG_TINJAUAN_HARI) {
            $statusSiklus = 'alumni_aktif';
            $labelSiklus = 'Alumni (< 1 Thn)';
            $toneSiklus = 'khaki';
            $perluTinjauan = false;
        } elseif ($hariInaktif <= self::AMBANG_HAPUS_HARI) {
            $statusSiklus = 'tinjauan_retensi';
            $labelSiklus = 'Tinjauan Retensi (1-2 Thn)';
            $toneSiklus = 'yellow';
            $perluTinjauan = true;
        } elseif ($memilikiDependensi) {
            $statusSiklus = 'terblokir_dependensi';
            $labelSiklus = 'Terblokir Dependensi (> 2 Thn)';
            $toneSiklus = 'red';
            $perluTinjauan = true;
        } else {
            $statusSiklus = 'layak_tinjau_hapus';
            $labelSiklus = 'Layak Tinjau Hapus (> 2 Thn)';
            $toneSiklus = 'neutral';
            $perluTinjauan = true;
        }

        // Status Kelayakan Penghapusan
        if ($siswa->status === 'aktif') {
            $statusKelayakan = 'Tidak Layak (Siswa Aktif)';
        } elseif ($memilikiDependensi) {
            $statusKelayakan = 'DIBLOKIR: Memiliki Riwayat Pendidikan Historis';
        } elseif ($hariInaktif <= self::AMBANG_HAPUS_HARI) {
            $statusKelayakan = 'Belum Memenuhi Ambang Waktu (< 2 Tahun)';
        } else {
            $statusKelayakan = 'Memenuhi syarat untuk ditinjau lebih lanjut. Penghapusan permanen belum diaktifkan.';
        }

        // Ambil riwayat tinjauan administratif jika tabel migrasi ada
        $riwayatTinjauan = Schema::hasTable('tinjauan_retensi')
            ? TinjauanRetensi::with('ditinjauOleh')->where('siswa_nis', $siswa->nis)->latest()->first()
            : null;

        return [
            'siswa_nis' => $siswa->nis,
            'nama' => $siswa->nama,
            'status_akademik' => $siswa->status,
            'status_siklus_hidup' => $statusSiklus,
            'label_siklus_hidup' => $labelSiklus,
            'tone_siklus_hidup' => $toneSiklus,
            'tanggal_referensi' => $tglRef->format('d/m/Y'),
            'usia_inaktif_hari' => $hariInaktif,
            'usia_inaktif_teks' => self::formatUsiaInaktif($hariInaktif),
            'ambang_retensi' => $ambangRetensi,
            'perlu_tinjauan' => $perluTinjauan,
            'memiliki_dependensi' => $memilikiDependensi,
            'alasan_pemblokiran' => $alasanPemblokiran,
            'ringkasan_dependensi' => $dependensi,
            'status_kelayakan' => $statusKelayakan,
            'tinjauan_terakhir' => $riwayatTinjauan,
            // Sesuai Aturan Keselamatan Inti: Penghapusan fisik TIDAK PERNAH diaktifkan
            'dapat_dihapus_permanen' => false,
        ];
    }
}
