<?php

namespace App\Support;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TransisiTahunAjaran;
use Throwable;

class KesiapanTransisi
{
    public const STATUS_SIAP = 'siap';

    public const STATUS_PERLU_DITINJAU = 'perlu_ditinjau';

    public const STATUS_TERHAMBAT = 'terhambat';

    public const STATUS_BELUM_DIKONFIGURASI = 'belum_dikonfigurasi';

    /**
     * Evaluasi daftar periksa (readiness checklist) kesiapan transisi tahun ajaran.
     *
     * Berjalan secara READ-ONLY murni: tidak mengubah status basis data, tidak mengeksekusi
     * migrasi, dan tidak melakukan promosi/kelulusan data siswa.
     *
     * @return array{
     *     status_keseluruhan: string,
     *     label_keseluruhan: string,
     *     tone_keseluruhan: string,
     *     tahun_asal: string|null,
     *     tahun_tujuan: string|null,
     *     promosi_x_ke_xi: array{status: string, label: string, tone: string},
     *     promosi_xi_ke_xii: array{status: string, label: string, tone: string},
     *     kelulusan_xii: array{status: string, label: string, tone: string},
     *     jumlah_terhambat: int,
     *     jumlah_perlu_ditinjau: int,
     *     jumlah_siap: int,
     *     item: list<array{
     *         kategori: string,
     *         kriteria: string,
     *         status: string,
     *         label: string,
     *         tone: string,
     *         pesan: string,
     *         jumlah_terdampak: int|null,
     *         pemblokir: bool,
     *         tautan: string|null,
     *         label_tautan: string|null
     *     }>
     * }
     */
    public static function evaluasi(?string $tahunAsalKode = null, ?string $tahunTujuanKode = null): array
    {
        $tahunAktif = TahunAjaran::berjalan();
        $tahunAsalKode ??= $tahunAktif?->kode;

        $tahunAsal = $tahunAsalKode ? TahunAjaran::where('kode', $tahunAsalKode)->first() : null;

        $tahunTujuan = null;
        if ($tahunTujuanKode) {
            $tahunTujuan = TahunAjaran::where('kode', $tahunTujuanKode)->first();
        } else {
            // Pilih kandidat tahun ajaran nonaktif terbaru
            $tahunTujuan = TahunAjaran::where('aktif', false)->orderByDesc('kode')->first();
        }

        $items = [];

        // ========================================================
        // KATEGORI A: KONFIGURASI TAHUN AJARAN (Academic Year Config)
        // ========================================================

        // 1. Tahun Ajaran Berjalan Aktif
        if ($tahunAktif) {
            $items[] = [
                'kategori' => 'Konfigurasi Tahun Ajaran',
                'kriteria' => 'Tahun Ajaran Berjalan Aktif',
                'status' => self::STATUS_SIAP,
                'label' => 'Siap',
                'tone' => 'green',
                'pesan' => "Tahun ajaran aktif terkonfigurasi: {$tahunAktif->kode}.",
                'jumlah_terdampak' => null,
                'pemblokir' => false,
                'tautan' => route('admin.tahun-ajaran.index'),
                'label_tautan' => 'Kelola Tahun Ajaran',
            ];
        } else {
            $items[] = [
                'kategori' => 'Konfigurasi Tahun Ajaran',
                'kriteria' => 'Tahun Ajaran Berjalan Aktif',
                'status' => self::STATUS_TERHAMBAT,
                'label' => 'Terhambat',
                'tone' => 'red',
                'pesan' => 'Belum ada tahun ajaran aktif yang ditetapkan pada sistem.',
                'jumlah_terdampak' => null,
                'pemblokir' => true,
                'tautan' => route('admin.tahun-ajaran.index'),
                'label_tautan' => 'Tetapkan Tahun Aktif',
            ];
        }

        // 2. Ketersediaan Tahun Ajaran Tujuan
        if ($tahunTujuan) {
            if ($tahunAsal && $tahunTujuan->kode === $tahunAsal->kode) {
                $items[] = [
                    'kategori' => 'Konfigurasi Tahun Ajaran',
                    'kriteria' => 'Tahun Ajaran Tujuan Berbeda',
                    'status' => self::STATUS_TERHAMBAT,
                    'label' => 'Terhambat',
                    'tone' => 'red',
                    'pesan' => "Tahun tujuan ({$tahunTujuan->kode}) tidak boleh sama dengan tahun asal.",
                    'jumlah_terdampak' => null,
                    'pemblokir' => true,
                    'tautan' => route('admin.tahun-ajaran.index'),
                    'label_tautan' => 'Buat Tahun Baru',
                ];
            } else {
                $items[] = [
                    'kategori' => 'Konfigurasi Tahun Ajaran',
                    'kriteria' => 'Ketersediaan Tahun Ajaran Tujuan',
                    'status' => self::STATUS_SIAP,
                    'label' => 'Siap',
                    'tone' => 'green',
                    'pesan' => "Tahun ajaran tujuan tersedia: {$tahunTujuan->kode} (status nonaktif/persiapan).",
                    'jumlah_terdampak' => null,
                    'pemblokir' => false,
                    'tautan' => route('admin.tahun-ajaran.index'),
                    'label_tautan' => 'Lihat Periode',
                ];
            }
        } else {
            $items[] = [
                'kategori' => 'Konfigurasi Tahun Ajaran',
                'kriteria' => 'Ketersediaan Tahun Ajaran Tujuan',
                'status' => self::STATUS_BELUM_DIKONFIGURASI,
                'label' => 'Belum Dikonfigurasi',
                'tone' => 'neutral',
                'pesan' => 'Tahun ajaran tujuan mendatang belum dibuat pada Data Master.',
                'jumlah_terdampak' => null,
                'pemblokir' => true,
                'tautan' => route('admin.tahun-ajaran.index'),
                'label_tautan' => 'Tambah Tahun Ajaran',
            ];
        }

        // 3. Status Transisi Riil Masa Lalu (Pencegahan Eksekusi Ganda)
        if ($tahunAsal && $tahunTujuan) {
            $transisiSelesai = TransisiTahunAjaran::where('tahun_asal', $tahunAsal->kode)
                ->where('tahun_tujuan', $tahunTujuan->kode)
                ->where('status', TransisiTahunAjaran::STATUS_SELESAI)
                ->first();

            if ($transisiSelesai) {
                $items[] = [
                    'kategori' => 'Konfigurasi Tahun Ajaran',
                    'kriteria' => 'Status Transisi Periode',
                    'status' => self::STATUS_TERHAMBAT,
                    'label' => 'Terhambat',
                    'tone' => 'red',
                    'pesan' => "Transisi dari {$tahunAsal->kode} ke {$tahunTujuan->kode} sudah pernah diproses SELESAI.",
                    'jumlah_terdampak' => null,
                    'pemblokir' => true,
                    'tautan' => route('admin.transisi.index'),
                    'label_tautan' => 'Lihat Riwayat',
                ];
            }
        }

        // ========================================================
        // KATEGORI B: PENYIAPAN KELAS TUJUAN (Destination Classes)
        // ========================================================
        $rombelHilangXI = 0;
        $rombelHilangXII = 0;
        $rombelHilangXBaru = 0;

        if ($tahunAsal && $tahunTujuan) {
            $kelasAsal = Kelas::where('tahun_ajaran_kode', $tahunAsal->kode)->get();
            $kelasTujuan = Kelas::where('tahun_ajaran_kode', $tahunTujuan->kode)->get();

            foreach ($kelasAsal as $k) {
                if ($k->tingkat === 'X') {
                    $adaXI = $kelasTujuan->where('tingkat', 'XI')
                        ->where('jurusan_kode', $k->jurusan_kode)
                        ->where('paralel', $k->paralel)
                        ->isNotEmpty();
                    if (! $adaXI) {
                        $rombelHilangXI++;
                    }

                    $adaXBaru = $kelasTujuan->where('tingkat', 'X')
                        ->where('jurusan_kode', $k->jurusan_kode)
                        ->where('paralel', $k->paralel)
                        ->isNotEmpty();
                    if (! $adaXBaru) {
                        $rombelHilangXBaru++;
                    }
                } elseif ($k->tingkat === 'XI') {
                    $adaXII = $kelasTujuan->where('tingkat', 'XII')
                        ->where('jurusan_kode', $k->jurusan_kode)
                        ->where('paralel', $k->paralel)
                        ->isNotEmpty();
                    if (! $adaXII) {
                        $rombelHilangXII++;
                    }
                }
            }

            // Kesiapan rombel XI (tujuan kelas X)
            if ($rombelHilangXI === 0 && $kelasTujuan->where('tingkat', 'XI')->isNotEmpty()) {
                $items[] = [
                    'kategori' => 'Ketersediaan Rombel Kelas Tujuan',
                    'kriteria' => 'Ketersediaan Rombel Tingkat XI',
                    'status' => self::STATUS_SIAP,
                    'label' => 'Siap',
                    'tone' => 'green',
                    'pesan' => "Seluruh rombel XI tujuan ({$kelasTujuan->where('tingkat', 'XI')->count()} rombel) telah siap.",
                    'jumlah_terdampak' => 0,
                    'pemblokir' => false,
                    'tautan' => route('kelas.index'),
                    'label_tautan' => 'Lihat Kelas',
                ];
            } else {
                $items[] = [
                    'kategori' => 'Ketersediaan Rombel Kelas Tujuan',
                    'kriteria' => 'Ketersediaan Rombel Tingkat XI',
                    'status' => self::STATUS_TERHAMBAT,
                    'label' => 'Terhambat',
                    'tone' => 'red',
                    'pesan' => "Terdapat {$rombelHilangXI} rombel tingkat XI yang belum disiapkan pada {$tahunTujuan->kode}.",
                    'jumlah_terdampak' => $rombelHilangXI,
                    'pemblokir' => true,
                    'tautan' => route('admin.transisi.index'),
                    'label_tautan' => 'Siapkan Rombel',
                ];
            }

            // Kesiapan rombel XII (tujuan kelas XI)
            if ($rombelHilangXII === 0 && $kelasTujuan->where('tingkat', 'XII')->isNotEmpty()) {
                $items[] = [
                    'kategori' => 'Ketersediaan Rombel Kelas Tujuan',
                    'kriteria' => 'Ketersediaan Rombel Tingkat XII',
                    'status' => self::STATUS_SIAP,
                    'label' => 'Siap',
                    'tone' => 'green',
                    'pesan' => "Seluruh rombel XII tujuan ({$kelasTujuan->where('tingkat', 'XII')->count()} rombel) telah siap.",
                    'jumlah_terdampak' => 0,
                    'pemblokir' => false,
                    'tautan' => route('kelas.index'),
                    'label_tautan' => 'Lihat Kelas',
                ];
            } else {
                $items[] = [
                    'kategori' => 'Ketersediaan Rombel Kelas Tujuan',
                    'kriteria' => 'Ketersediaan Rombel Tingkat XII',
                    'status' => self::STATUS_TERHAMBAT,
                    'label' => 'Terhambat',
                    'tone' => 'red',
                    'pesan' => "Terdapat {$rombelHilangXII} rombel tingkat XII yang belum disiapkan pada {$tahunTujuan->kode}.",
                    'jumlah_terdampak' => $rombelHilangXII,
                    'pemblokir' => true,
                    'tautan' => route('admin.transisi.index'),
                    'label_tautan' => 'Siapkan Rombel',
                ];
            }

            // Kesiapan rombel X baru (peringatan non-blocking)
            if ($rombelHilangXBaru === 0 && $kelasTujuan->where('tingkat', 'X')->isNotEmpty()) {
                $items[] = [
                    'kategori' => 'Ketersediaan Rombel Kelas Tujuan',
                    'kriteria' => 'Template Rombel Tingkat X Baru',
                    'status' => self::STATUS_SIAP,
                    'label' => 'Siap',
                    'tone' => 'green',
                    'pesan' => "Rombel template kelas X ({$kelasTujuan->where('tingkat', 'X')->count()} rombel) siap untuk siswa baru.",
                    'jumlah_terdampak' => 0,
                    'pemblokir' => false,
                    'tautan' => route('kelas.index'),
                    'label_tautan' => 'Lihat Kelas',
                ];
            } else {
                $items[] = [
                    'kategori' => 'Ketersediaan Rombel Kelas Tujuan',
                    'kriteria' => 'Template Rombel Tingkat X Baru',
                    'status' => self::STATUS_PERLU_DITINJAU,
                    'label' => 'Perlu Ditinjau',
                    'tone' => 'yellow',
                    'pesan' => "Belum ada template rombel X baru pada {$tahunTujuan->kode} untuk penempatan siswa baru.",
                    'jumlah_terdampak' => $rombelHilangXBaru,
                    'pemblokir' => false,
                    'tautan' => route('admin.transisi.index'),
                    'label_tautan' => 'Siapkan Rombel',
                ];
            }
        } else {
            $items[] = [
                'kategori' => 'Ketersediaan Rombel Kelas Tujuan',
                'kriteria' => 'Penyiapan Rombel Tujuan',
                'status' => self::STATUS_BELUM_DIKONFIGURASI,
                'label' => 'Belum Dikonfigurasi',
                'tone' => 'neutral',
                'pesan' => 'Penyiapan rombel memerlukan penetapan tahun asal dan tahun tujuan.',
                'jumlah_terdampak' => null,
                'pemblokir' => true,
                'tautan' => route('admin.transisi.index'),
                'label_tautan' => 'Pilih Periode',
            ];
        }

        // ========================================================
        // KATEGORI C: INTEGRITAS DATA SISWA (Student Data Integrity)
        // ========================================================
        $siswaTanpaKelas = Siswa::where('status', 'aktif')->whereNull('kelas_id')->count();

        if ($siswaTanpaKelas === 0) {
            $items[] = [
                'kategori' => 'Integritas Data Siswa',
                'kriteria' => 'Penugasan Rombel Siswa Aktif',
                'status' => self::STATUS_SIAP,
                'label' => 'Siap',
                'tone' => 'green',
                'pesan' => 'Seluruh siswa berstatus aktif telah memiliki penugasan rombel kelas.',
                'jumlah_terdampak' => 0,
                'pemblokir' => false,
                'tautan' => route('admin.siswa.index'),
                'label_tautan' => 'Data Siswa',
            ];
        } else {
            $items[] = [
                'kategori' => 'Integritas Data Siswa',
                'kriteria' => 'Penugasan Rombel Siswa Aktif',
                'status' => self::STATUS_PERLU_DITINJAU,
                'label' => 'Perlu Ditinjau',
                'tone' => 'yellow',
                'pesan' => "Terdapat {$siswaTanpaKelas} siswa aktif belum terdaftar pada rombel kelas mana pun.",
                'jumlah_terdampak' => $siswaTanpaKelas,
                'pemblokir' => false, // Non-blocking: siswa tanpa kelas dilewati dan tidak merusak promosi siswa lain
                'tautan' => route('admin.siswa.index', ['status' => 'aktif']),
                'label_tautan' => 'Tinjau Siswa',
            ];
        }

        // Siswa aktif pada kelas di luar tahun ajaran asal
        if ($tahunAsal) {
            $kelasAsalIds = Kelas::where('tahun_ajaran_kode', $tahunAsal->kode)->pluck('id');
            $siswaKelasLain = Siswa::where('status', 'aktif')
                ->whereNotNull('kelas_id')
                ->whereNotIn('kelas_id', $kelasAsalIds)
                ->count();

            if ($siswaKelasLain === 0) {
                $items[] = [
                    'kategori' => 'Integritas Data Siswa',
                    'kriteria' => 'Konsistensi Periode Rombel Siswa',
                    'status' => self::STATUS_SIAP,
                    'label' => 'Siap',
                    'tone' => 'green',
                    'pesan' => "Seluruh siswa aktif terdata pada kelas periode {$tahunAsal->kode}.",
                    'jumlah_terdampak' => 0,
                    'pemblokir' => false,
                    'tautan' => route('admin.siswa.index'),
                    'label_tautan' => 'Data Siswa',
                ];
            } else {
                $items[] = [
                    'kategori' => 'Integritas Data Siswa',
                    'kriteria' => 'Konsistensi Periode Rombel Siswa',
                    'status' => self::STATUS_PERLU_DITINJAU,
                    'label' => 'Perlu Ditinjau',
                    'tone' => 'yellow',
                    'pesan' => "Terdapat {$siswaKelasLain} siswa aktif terdaftar pada kelas di luar periode {$tahunAsal->kode}.",
                    'jumlah_terdampak' => $siswaKelasLain,
                    'pemblokir' => false,
                    'tautan' => route('admin.siswa.index'),
                    'label_tautan' => 'Periksa Siswa',
                ];
            }
        }

        // ========================================================
        // KATEGORI D: PERSIAPAN KELULUSAN KELAS XII (Graduation Readiness)
        // ========================================================
        $totalSiswaXII = 0;
        if ($tahunAsal) {
            $kelasXIIIds = Kelas::where('tahun_ajaran_kode', $tahunAsal->kode)->where('tingkat', 'XII')->pluck('id');
            $totalSiswaXII = Siswa::where('status', 'aktif')->whereIn('kelas_id', $kelasXIIIds)->count();

            if ($totalSiswaXII > 0) {
                $items[] = [
                    'kategori' => 'Persiapan Kelulusan Tingkat XII',
                    'kriteria' => 'Calon Lulusan Tingkat XII',
                    'status' => self::STATUS_SIAP,
                    'label' => 'Siap',
                    'tone' => 'green',
                    'pesan' => "Terdapat {$totalSiswaXII} siswa tingkat XII siap diproses kelulusan dan dicatat ke Alumni.",
                    'jumlah_terdampak' => $totalSiswaXII,
                    'pemblokir' => false,
                    'tautan' => route('admin.transisi.pratinjau', ['tahun_asal' => $tahunAsal->kode, 'tahun_tujuan' => $tahunTujuan?->kode ?? '']),
                    'label_tautan' => 'Pratinjau Kelulusan',
                ];
            } else {
                $items[] = [
                    'kategori' => 'Persiapan Kelulusan Tingkat XII',
                    'kriteria' => 'Calon Lulusan Tingkat XII',
                    'status' => self::STATUS_PERLU_DITINJAU,
                    'label' => 'Perlu Ditinjau',
                    'tone' => 'yellow',
                    'pesan' => "Tidak ditemukan siswa aktif di tingkat XII pada tahun ajaran {$tahunAsal->kode}.",
                    'jumlah_terdampak' => 0,
                    'pemblokir' => false,
                    'tautan' => route('admin.siswa.index'),
                    'label_tautan' => 'Data Siswa',
                ];
            }
        }

        // ========================================================
        // KATEGORI E: PRATINJAU SIMULASI (Preview & Dry-Run Readiness)
        // ========================================================
        $simulasiSiap = false;
        if ($tahunAsal && $tahunTujuan) {
            try {
                $pratinjau = TransisiAkademik::pratinjau($tahunAsal->kode, $tahunTujuan->kode);
                if ($pratinjau['siap_eksekusi'] && empty($pratinjau['masalah_validasi'])) {
                    $simulasiSiap = true;
                    $items[] = [
                        'kategori' => 'Simulasi & Pratinjau Transisi',
                        'kriteria' => 'Hasil Validasi Pratinjau',
                        'status' => self::STATUS_SIAP,
                        'label' => 'Siap',
                        'tone' => 'green',
                        'pesan' => "Simulasi valid: {$pratinjau['statistik']['total_promosi']} siswa promosi, {$pratinjau['statistik']['total_lulusan']} siswa lulus.",
                        'jumlah_terdampak' => $pratinjau['statistik']['total_siswa'],
                        'pemblokir' => false,
                        'tautan' => route('admin.transisi.pratinjau', ['tahun_asal' => $tahunAsal->kode, 'tahun_tujuan' => $tahunTujuan->kode]),
                        'label_tautan' => 'Buka Pratinjau',
                    ];
                } else {
                    $pesanMasalah = ! empty($pratinjau['masalah_validasi'])
                        ? $pratinjau['masalah_validasi'][0]
                        : 'Ada kendala validasi pada simulasi transisi.';

                    $items[] = [
                        'kategori' => 'Simulasi & Pratinjau Transisi',
                        'kriteria' => 'Hasil Validasi Pratinjau',
                        'status' => self::STATUS_TERHAMBAT,
                        'label' => 'Terhambat',
                        'tone' => 'red',
                        'pesan' => $pesanMasalah,
                        'jumlah_terdampak' => count($pratinjau['masalah_validasi']),
                        'pemblokir' => true,
                        'tautan' => route('admin.transisi.pratinjau', ['tahun_asal' => $tahunAsal->kode, 'tahun_tujuan' => $tahunTujuan->kode]),
                        'label_tautan' => 'Periksa Masalah',
                    ];
                }
            } catch (Throwable $e) {
                $items[] = [
                    'kategori' => 'Simulasi & Pratinjau Transisi',
                    'kriteria' => 'Hasil Validasi Pratinjau',
                    'status' => self::STATUS_TERHAMBAT,
                    'label' => 'Terhambat',
                    'tone' => 'red',
                    'pesan' => 'Simulasi belum dapat dijalankan: '.$e->getMessage(),
                    'jumlah_terdampak' => null,
                    'pemblokir' => true,
                    'tautan' => null,
                    'label_tautan' => null,
                ];
            }
        } else {
            $items[] = [
                'kategori' => 'Simulasi & Pratinjau Transisi',
                'kriteria' => 'Hasil Validasi Pratinjau',
                'status' => self::STATUS_BELUM_DIKONFIGURASI,
                'label' => 'Belum Dikonfigurasi',
                'tone' => 'neutral',
                'pesan' => 'Simulasi memerlukan penetapan tahun ajaran asal dan tujuan.',
                'jumlah_terdampak' => null,
                'pemblokir' => true,
                'tautan' => null,
                'label_tautan' => null,
            ];
        }

        // ========================================================
        // KALKULASI STATUS DAN KESIAPAN PER OPERASI
        // ========================================================
        $jumlahTerhambat = collect($items)->where('status', self::STATUS_TERHAMBAT)->count();
        $jumlahPerluDitinjau = collect($items)->where('status', self::STATUS_PERLU_DITINJAU)->count();
        $jumlahSiap = collect($items)->where('status', self::STATUS_SIAP)->count();
        $jumlahBelumKonfigurasi = collect($items)->where('status', self::STATUS_BELUM_DIKONFIGURASI)->count();

        // Kesiapan Promosi X -> XI
        $promosiXStatus = self::STATUS_SIAP;
        if (! $tahunAktif || ! $tahunTujuan) {
            $promosiXStatus = self::STATUS_BELUM_DIKONFIGURASI;
        } elseif ($rombelHilangXI > 0) {
            $promosiXStatus = self::STATUS_TERHAMBAT;
        }
        $promosiXTone = match ($promosiXStatus) {
            self::STATUS_SIAP => 'green',
            self::STATUS_TERHAMBAT => 'red',
            self::STATUS_PERLU_DITINJAU => 'yellow',
            default => 'neutral',
        };
        $promosiXLabel = match ($promosiXStatus) {
            self::STATUS_SIAP => 'Siap',
            self::STATUS_TERHAMBAT => 'Terhambat',
            self::STATUS_PERLU_DITINJAU => 'Perlu Ditinjau',
            default => 'Belum Dikonfigurasi',
        };

        // Kesiapan Promosi XI -> XII
        $promosiXIStatus = self::STATUS_SIAP;
        if (! $tahunAktif || ! $tahunTujuan) {
            $promosiXIStatus = self::STATUS_BELUM_DIKONFIGURASI;
        } elseif ($rombelHilangXII > 0) {
            $promosiXIStatus = self::STATUS_TERHAMBAT;
        }
        $promosiXITone = match ($promosiXIStatus) {
            self::STATUS_SIAP => 'green',
            self::STATUS_TERHAMBAT => 'red',
            self::STATUS_PERLU_DITINJAU => 'yellow',
            default => 'neutral',
        };
        $promosiXILabel = match ($promosiXIStatus) {
            self::STATUS_SIAP => 'Siap',
            self::STATUS_TERHAMBAT => 'Terhambat',
            self::STATUS_PERLU_DITINJAU => 'Perlu Ditinjau',
            default => 'Belum Dikonfigurasi',
        };

        // Kesiapan Kelulusan XII (mandiri, tidak diblokir oleh kekurangan rombel kelas)
        $lulusXIIStatus = self::STATUS_SIAP;
        if (! $tahunAktif) {
            $lulusXIIStatus = self::STATUS_BELUM_DIKONFIGURASI;
        } elseif ($totalSiswaXII === 0) {
            $lulusXIIStatus = self::STATUS_PERLU_DITINJAU;
        }
        $lulusXIITone = match ($lulusXIIStatus) {
            self::STATUS_SIAP => 'green',
            self::STATUS_TERHAMBAT => 'red',
            self::STATUS_PERLU_DITINJAU => 'yellow',
            default => 'neutral',
        };
        $lulusXIILabel = match ($lulusXIIStatus) {
            self::STATUS_SIAP => 'Siap',
            self::STATUS_TERHAMBAT => 'Terhambat',
            self::STATUS_PERLU_DITINJAU => 'Perlu Ditinjau',
            default => 'Belum Dikonfigurasi',
        };

        // Status Keseluruhan
        if ($jumlahTerhambat > 0) {
            $statusKeseluruhan = self::STATUS_TERHAMBAT;
            $labelKeseluruhan = "Terhambat ({$jumlahTerhambat} Masalah)";
            $toneKeseluruhan = 'red';
        } elseif ($jumlahBelumKonfigurasi > 0 && ! $tahunTujuan) {
            $statusKeseluruhan = self::STATUS_BELUM_DIKONFIGURASI;
            $labelKeseluruhan = 'Belum Dikonfigurasi';
            $toneKeseluruhan = 'neutral';
        } elseif ($jumlahPerluDitinjau > 0) {
            $statusKeseluruhan = self::STATUS_PERLU_DITINJAU;
            $labelKeseluruhan = "Perlu Ditinjau ({$jumlahPerluDitinjau} Peringatan)";
            $toneKeseluruhan = 'yellow';
        } else {
            $statusKeseluruhan = self::STATUS_SIAP;
            $labelKeseluruhan = 'Siap Eksekusi';
            $toneKeseluruhan = 'green';
        }

        return [
            'status_keseluruhan' => $statusKeseluruhan,
            'label_keseluruhan' => $labelKeseluruhan,
            'tone_keseluruhan' => $toneKeseluruhan,
            'tahun_asal' => $tahunAsal?->kode,
            'tahun_tujuan' => $tahunTujuan?->kode,
            'promosi_x_ke_xi' => ['status' => $promosiXStatus, 'label' => $promosiXLabel, 'tone' => $promosiXTone],
            'promosi_xi_ke_xii' => ['status' => $promosiXIStatus, 'label' => $promosiXILabel, 'tone' => $promosiXITone],
            'kelulusan_xii' => ['status' => $lulusXIIStatus, 'label' => $lulusXIILabel, 'tone' => $lulusXIITone],
            'jumlah_terhambat' => $jumlahTerhambat,
            'jumlah_perlu_ditinjau' => $jumlahPerluDitinjau,
            'jumlah_siap' => $jumlahSiap,
            'item' => $items,
        ];
    }
}
