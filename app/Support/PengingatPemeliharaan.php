<?php

namespace App\Support;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Carbon\Carbon;
use Throwable;

class PengingatPemeliharaan
{
    /**
     * Evaluasi status pengingat pemeliharaan data tahunan sekolah secara dinamis.
     *
     * Berdasarkan siklus sekolah:
     * - Mei: Pengumpulan & validasi data siswa baru Kelas X dan persiapan kelulusan XII.
     * - Juni: Finalisasi verifikasi rombel, simulasi transisi, dan eksekusi tahun ajaran baru.
     * - Di luar Mei–Juni: Operasional harian berjalan normal (Belum Masa Persiapan),
     *   kecuali terdapat anomali data yang memerlukan tindak lanjut.
     *
     * @return array{
     *     status: string,
     *     label: string,
     *     judul: string,
     *     deskripsi: string,
     *     tone: string,
     *     bulan: int,
     *     nama_bulan: string,
     *     tahun_aktif: string|null,
     *     siswa_tanpa_kelas: int,
     *     tautan: list<array{label: string, url: string, ikon: string}>
     * }
     */
    public static function evaluasiPengingat(?Carbon $waktu = null): array
    {
        $waktu ??= now();
        $bulan = (int) $waktu->format('n');
        $namaBulan = $waktu->translatedFormat('F');

        $tahunAktif = TahunAjaran::berjalan();
        $siswaTanpaKelas = Siswa::where('status', 'aktif')->whereNull('kelas_id')->count();

        $tautan = [
            ['label' => 'Tahun Ajaran', 'url' => route('admin.tahun-ajaran.index'), 'ikon' => 'calendar3'],
            ['label' => 'Kenaikan & Kelulusan', 'url' => route('admin.transisi.index'), 'ikon' => 'mortarboard'],
            ['label' => 'Impor Data Siswa', 'url' => route('admin.impor.index'), 'ikon' => 'file-earmark-arrow-up'],
        ];

        // Anomali prioritas tinggi yang memerlukan tindakan segera
        if ($siswaTanpaKelas > 0 || ! $tahunAktif) {
            $pesanAnomali = ! $tahunAktif
                ? 'Belum ada tahun ajaran aktif yang ditetapkan pada sistem.'
                : "Terdapat {$siswaTanpaKelas} siswa berstatus aktif yang belum memiliki penugasan rombel kelas.";

            return [
                'status' => 'perlu_tindak_lanjut',
                'label' => 'Perlu Tindak Lanjut',
                'judul' => 'Perhatian Administrasi Data',
                'deskripsi' => $pesanAnomali.' Harap segera lengkapi konfigurasi akademik.',
                'tone' => 'red',
                'bulan' => $bulan,
                'nama_bulan' => $namaBulan,
                'tahun_aktif' => $tahunAktif?->kode,
                'siswa_tanpa_kelas' => $siswaTanpaKelas,
                'tautan' => $tautan,
            ];
        }

        // Periode Mei: Persiapan Data
        if ($bulan === 5) {
            return [
                'status' => 'persiapan_mei',
                'label' => 'Persiapan Data (Mei)',
                'judul' => 'Masa Persiapan Data Siswa & Kelulusan (Bulan Mei)',
                'deskripsi' => 'Kumpulkan dan validasi berkas spreadsheet calon siswa baru Kelas X melalui Impor Data, serta tinjau kelengkapan data siswa Kelas XII menjelang kelulusan.',
                'tone' => 'yellow',
                'bulan' => $bulan,
                'nama_bulan' => $namaBulan,
                'tahun_aktif' => $tahunAktif->kode,
                'siswa_tanpa_kelas' => 0,
                'tautan' => $tautan,
            ];
        }

        // Periode Juni: Finalisasi & Transisi
        if ($bulan === 6) {
            return [
                'status' => 'finalisasi_juni',
                'label' => 'Finalisasi Data (Juni)',
                'judul' => 'Masa Finalisasi & Transisi Tahun Ajaran (Bulan Juni)',
                'deskripsi' => 'Siapkan rombel kelas tujuan, lakukan simulasi pratinjau kenaikan kelas paralel, dan selesaikan eksekusi kelulusan sebelum tahun ajaran baru dimulai.',
                'tone' => 'yellow',
                'bulan' => $bulan,
                'nama_bulan' => $namaBulan,
                'tahun_aktif' => $tahunAktif->kode,
                'siswa_tanpa_kelas' => 0,
                'tautan' => $tautan,
            ];
        }

        // Di luar Mei–Juni: Operasional normal
        return [
            'status' => 'belum_masa_persiapan',
            'label' => 'Belum Masa Persiapan',
            'judul' => 'Di Luar Periode Pemeliharaan Tahunan',
            'deskripsi' => 'Periode pembaruan data tahunan dijadwalkan pada bulan Mei–Juni. Saat ini aplikasi berjalan normal dalam kegiatan pencatatan jurnal dan presensi harian.',
            'tone' => 'neutral',
            'bulan' => $bulan,
            'nama_bulan' => $namaBulan,
            'tahun_aktif' => $tahunAktif->kode,
            'siswa_tanpa_kelas' => 0,
            'tautan' => $tautan,
        ];
    }

    /**
     * Evaluasi daftar periksa (readiness checklist) kesiapan transisi akademik tahun ajaran.
     *
     * @return array{
     *     semua_siap: bool,
     *     item: list<array{kriteria: string, status: string, label: string, tone: string, pesan: string, tautan: string|null}>
     * }
     */
    public static function evaluasiKesiapanTransisi(?string $tahunAsalKode = null, ?string $tahunTujuanKode = null): array
    {
        $tahunAktif = TahunAjaran::berjalan();
        $tahunAsalKode ??= $tahunAktif?->kode;

        $tahunTujuan = null;
        if ($tahunTujuanKode) {
            $tahunTujuan = TahunAjaran::where('kode', $tahunTujuanKode)->first();
        } else {
            $tahunTujuan = TahunAjaran::where('aktif', false)->orderByDesc('kode')->first();
        }

        $items = [];
        $semuaSiap = true;

        // 1. Tahun Ajaran Aktif
        if ($tahunAktif) {
            $items[] = [
                'kriteria' => 'Tahun Ajaran Berjalan Aktif',
                'status' => 'siap',
                'label' => 'Siap',
                'tone' => 'green',
                'pesan' => "Tahun ajaran aktif terkonfigurasi: {$tahunAktif->kode}.",
                'tautan' => route('admin.tahun-ajaran.index'),
            ];
        } else {
            $semuaSiap = false;
            $items[] = [
                'kriteria' => 'Tahun Ajaran Berjalan Aktif',
                'status' => 'belum_siap',
                'label' => 'Belum Siap',
                'tone' => 'red',
                'pesan' => 'Belum ada tahun ajaran aktif yang ditetapkan.',
                'tautan' => route('admin.tahun-ajaran.index'),
            ];
        }

        // 2. Tahun Ajaran Tujuan Dibuat
        if ($tahunTujuan) {
            $items[] = [
                'kriteria' => 'Tahun Ajaran Tujuan Tersedia',
                'status' => 'siap',
                'label' => 'Siap',
                'tone' => 'green',
                'pesan' => "Tahun ajaran tujuan siap: {$tahunTujuan->kode} (status nonaktif).",
                'tautan' => route('admin.tahun-ajaran.index'),
            ];
        } else {
            $semuaSiap = false;
            $items[] = [
                'kriteria' => 'Tahun Ajaran Tujuan Tersedia',
                'status' => 'belum_siap',
                'label' => 'Belum Siap',
                'tone' => 'red',
                'pesan' => 'Tahun ajaran tujuan mendatang belum dibuat.',
                'tautan' => route('admin.tahun-ajaran.index'),
            ];
        }

        // 3. Penugasan Siswa Aktif pada Rombel
        $siswaTanpaKelas = Siswa::where('status', 'aktif')->whereNull('kelas_id')->count();
        if ($siswaTanpaKelas === 0) {
            $items[] = [
                'kriteria' => 'Penugasan Rombel Siswa Lengkap',
                'status' => 'siap',
                'label' => 'Siap',
                'tone' => 'green',
                'pesan' => 'Seluruh siswa aktif telah memiliki penugasan rombel kelas.',
                'tautan' => route('admin.siswa.index'),
            ];
        } else {
            $semuaSiap = false;
            $items[] = [
                'kriteria' => 'Penugasan Rombel Siswa Lengkap',
                'status' => 'perlu_pemeriksaan',
                'label' => 'Perlu Pemeriksaan',
                'tone' => 'yellow',
                'pesan' => "Terdapat {$siswaTanpaKelas} siswa aktif belum memiliki rombel kelas.",
                'tautan' => route('admin.siswa.index', ['status' => 'aktif']),
            ];
        }

        // 4. Penyiapan Rombel Kelas Tujuan
        if ($tahunAktif && $tahunTujuan) {
            $kelasAsal = Kelas::where('tahun_ajaran_kode', $tahunAktif->kode)->get();
            $kelasTujuan = Kelas::where('tahun_ajaran_kode', $tahunTujuan->kode)->get();

            // Periksa apakah rombel X, XI, dan XII yang diharapkan sudah ada di tahun tujuan
            $rombelHilang = 0;
            foreach ($kelasAsal as $k) {
                if ($k->tingkat === 'X') {
                    $adaXI = $kelasTujuan->where('tingkat', 'XI')->where('jurusan_kode', $k->jurusan_kode)->where('paralel', $k->paralel)->isNotEmpty();
                    $adaX = $kelasTujuan->where('tingkat', 'X')->where('jurusan_kode', $k->jurusan_kode)->where('paralel', $k->paralel)->isNotEmpty();
                    if (! $adaXI || ! $adaX) {
                        $rombelHilang++;
                    }
                } elseif ($k->tingkat === 'XI') {
                    $adaXII = $kelasTujuan->where('tingkat', 'XII')->where('jurusan_kode', $k->jurusan_kode)->where('paralel', $k->paralel)->isNotEmpty();
                    if (! $adaXII) {
                        $rombelHilang++;
                    }
                }
            }

            if ($kelasTujuan->isNotEmpty() && $rombelHilang === 0) {
                $items[] = [
                    'kriteria' => 'Penyiapan Rombel Kelas Tujuan',
                    'status' => 'siap',
                    'label' => 'Siap',
                    'tone' => 'green',
                    'pesan' => "Seluruh rombel kelas tujuan pada {$tahunTujuan->kode} telah disiapkan secara lengkap ({$kelasTujuan->count()} rombel).",
                    'tautan' => route('kelas.index'),
                ];
            } else {
                $semuaSiap = false;
                $items[] = [
                    'kriteria' => 'Penyiapan Rombel Kelas Tujuan',
                    'status' => 'belum_siap',
                    'label' => 'Belum Siap',
                    'tone' => 'yellow',
                    'pesan' => "Rombel kelas tujuan pada {$tahunTujuan->kode} belum lengkap ({$rombelHilang} rombel belum disiapkan).",
                    'tautan' => null,
                ];
            }
        } else {
            $semuaSiap = false;
            $items[] = [
                'kriteria' => 'Penyiapan Rombel Kelas Tujuan',
                'status' => 'belum_siap',
                'label' => 'Belum Siap',
                'tone' => 'neutral',
                'pesan' => 'Pilih tahun ajaran asal dan tujuan terlebih dahulu.',
                'tautan' => null,
            ];
        }

        // 5. Validasi Simulasi Pratinjau Transisi
        if ($tahunAktif && $tahunTujuan) {
            try {
                $pratinjau = TransisiAkademik::pratinjau($tahunAktif->kode, $tahunTujuan->kode);
                if ($pratinjau['siap_eksekusi'] && empty($pratinjau['masalah_validasi'])) {
                    $items[] = [
                        'kriteria' => 'Simulasi Pratinjau Bebas Masalah',
                        'status' => 'siap',
                        'label' => 'Siap',
                        'tone' => 'green',
                        'pesan' => "Simulasi valid: {$pratinjau['statistik']['total_promosi']} siswa siap promosi, {$pratinjau['statistik']['total_lulusan']} siswa siap lulus.",
                        'tautan' => route('admin.transisi.pratinjau', ['tahun_asal' => $tahunAktif->kode, 'tahun_tujuan' => $tahunTujuan->kode]),
                    ];
                } else {
                    $semuaSiap = false;
                    $pesanErr = ! empty($pratinjau['masalah_validasi'])
                        ? $pratinjau['masalah_validasi'][0]
                        : 'Ada prasyarat simulasi yang belum tuntas.';

                    $items[] = [
                        'kriteria' => 'Simulasi Pratinjau Bebas Masalah',
                        'status' => 'perlu_pemeriksaan',
                        'label' => 'Perlu Pemeriksaan',
                        'tone' => 'red',
                        'pesan' => $pesanErr,
                        'tautan' => route('admin.transisi.pratinjau', ['tahun_asal' => $tahunAktif->kode, 'tahun_tujuan' => $tahunTujuan->kode]),
                    ];
                }
            } catch (Throwable $e) {
                $semuaSiap = false;
                $items[] = [
                    'kriteria' => 'Simulasi Pratinjau Bebas Masalah',
                    'status' => 'belum_siap',
                    'label' => 'Belum Siap',
                    'tone' => 'red',
                    'pesan' => 'Simulasi gagal: '.$e->getMessage(),
                    'tautan' => null,
                ];
            }
        }

        return [
            'semua_siap' => $semuaSiap,
            'item' => $items,
        ];
    }
}
