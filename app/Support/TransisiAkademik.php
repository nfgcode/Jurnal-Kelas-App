<?php

namespace App\Support;

use App\Models\Alumni;
use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TransisiTahunAjaran;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TransisiAkademik
{
    /**
     * Siapkan rombel kelas tujuan pada tahun ajaran baru.
     *
     * Berdasarkan kebijakan sekolah:
     * - X TKJ 1 -> XI TKJ 1 (Tingkat X naik ke XI, paralel dan jurusan tetap)
     * - XI TKJ 1 -> XII TKJ 1 (Tingkat XI naik ke XII, paralel dan jurusan tetap)
     * - Template X TKJ 1 dibuat untuk siswa baru angkatan berikutnya.
     *
     * @return array{
     *     tahun_asal: string,
     *     tahun_tujuan: string,
     *     dibuat: int,
     *     sudah_ada: int,
     *     total: int,
     *     kelas: list<array{id: int, nama_kelas: string, tingkat: string, baru: bool}>
     * }
     */
    public static function siapkanKelasTujuan(string $tahunTujuanKode, ?string $tahunAsalKode = null): array
    {
        $tahunTujuan = TahunAjaran::where('kode', $tahunTujuanKode)->firstOrFail();
        $tahunAsal = $tahunAsalKode
            ? TahunAjaran::where('kode', $tahunAsalKode)->firstOrFail()
            : TahunAjaran::berjalan();

        if (! $tahunAsal) {
            throw new InvalidArgumentException('Tahun ajaran asal tidak ditemukan.');
        }

        if ($tahunAsal->kode === $tahunTujuan->kode) {
            throw new DomainException('Tahun ajaran tujuan tidak boleh sama dengan tahun ajaran asal.');
        }

        $kelasAsal = Kelas::where('tahun_ajaran_kode', $tahunAsal->kode)->get();

        $dibuat = 0;
        $sudahAda = 0;
        $hasilKelas = [];

        DB::transaction(function () use ($kelasAsal, $tahunTujuan, &$dibuat, &$sudahAda, &$hasilKelas) {
            // 1. Petakan rombel naik tingkat (X -> XI, XI -> XII)
            foreach ($kelasAsal as $k) {
                $tingkatTujuan = match ($k->tingkat) {
                    'X' => 'XI',
                    'XI' => 'XII',
                    default => null, // XII lulus, tidak naik ke tingkat XIII
                };

                if ($tingkatTujuan) {
                    $namaTujuan = self::formatNamaKelas($tingkatTujuan, $k->jurusan_kode, $k->paralel);

                    $kelasBaru = Kelas::firstOrCreate(
                        [
                            'tahun_ajaran_kode' => $tahunTujuan->kode,
                            'tingkat' => $tingkatTujuan,
                            'jurusan_kode' => $k->jurusan_kode,
                            'paralel' => $k->paralel,
                        ],
                        [
                            'nama_kelas' => $namaTujuan,
                            'kapasitas' => $k->kapasitas ?: 36,
                            'ruangan_kode' => null, // Ruangan diatur ulang
                            'wali_kelas_nip' => null, // Wali kelas diatur ulang
                            'ketua_nis' => null, // Ketua kelas baru
                        ]
                    );

                    if ($kelasBaru->wasRecentlyCreated) {
                        $dibuat++;
                    } else {
                        $sudahAda++;
                    }

                    $hasilKelas[] = [
                        'id' => $kelasBaru->id,
                        'nama_kelas' => $kelasBaru->nama_kelas,
                        'tingkat' => $kelasBaru->tingkat,
                        'baru' => $kelasBaru->wasRecentlyCreated,
                    ];
                }
            }

            // 2. Siapkan template rombel tingkat X untuk siswa baru di tahun ajaran tujuan
            // Mengambil seluruh kombinasi jurusan & paralel tingkat X dari tahun asal
            $rombelKelasX = $kelasAsal->where('tingkat', 'X');
            foreach ($rombelKelasX as $kX) {
                $namaTujuanX = self::formatNamaKelas('X', $kX->jurusan_kode, $kX->paralel);

                $kelasBaruX = Kelas::firstOrCreate(
                    [
                        'tahun_ajaran_kode' => $tahunTujuan->kode,
                        'tingkat' => 'X',
                        'jurusan_kode' => $kX->jurusan_kode,
                        'paralel' => $kX->paralel,
                    ],
                    [
                        'nama_kelas' => $namaTujuanX,
                        'kapasitas' => $kX->kapasitas ?: 36,
                        'ruangan_kode' => null,
                        'wali_kelas_nip' => null,
                        'ketua_nis' => null,
                    ]
                );

                if ($kelasBaruX->wasRecentlyCreated) {
                    $dibuat++;
                } else {
                    $sudahAda++;
                }

                $hasilKelas[] = [
                    'id' => $kelasBaruX->id,
                    'nama_kelas' => $kelasBaruX->nama_kelas,
                    'tingkat' => $kelasBaruX->tingkat,
                    'baru' => $kelasBaruX->wasRecentlyCreated,
                ];
            }
        });

        // Deduplikasi hasil kelas untuk pelaporan
        $unikKelas = collect($hasilKelas)->unique('id')->values()->all();

        return [
            'tahun_asal' => $tahunAsal->kode,
            'tahun_tujuan' => $tahunTujuan->kode,
            'dibuat' => $dibuat,
            'sudah_ada' => $sudahAda,
            'total' => count($unikKelas),
            'kelas' => $unikKelas,
        ];
    }

    /**
     * Hasilkan pratinjau lengkap kenaikan kelas dan kelulusan (Dry-Run Preview).
     *
     * @return array{
     *     tahun_asal: string,
     *     tahun_tujuan: string,
     *     status_transisi: string|null,
     *     sudah_selesai: bool,
     *     siap_eksekusi: bool,
     *     masalah_validasi: list<string>,
     *     peringatan: list<string>,
     *     statistik: array{
     *         total_lulusan: int,
     *         total_promosi_x_ke_xi: int,
     *         total_promosi_xi_ke_xii: int,
     *         total_promosi: int,
     *         total_siswa: int,
     *     },
     *     calon_lulus: list<array<string, mixed>>,
     *     calon_promosi: list<array<string, mixed>>,
     * }
     */
    public static function pratinjau(string $tahunAsalKode, string $tahunTujuanKode): array
    {
        $tahunAsal = TahunAjaran::where('kode', $tahunAsalKode)->firstOrFail();
        $tahunTujuan = TahunAjaran::where('kode', $tahunTujuanKode)->firstOrFail();

        $transisi = TransisiTahunAjaran::where('tahun_asal', $tahunAsalKode)
            ->where('tahun_tujuan', $tahunTujuanKode)
            ->first();

        $sudahSelesai = $transisi?->status === TransisiTahunAjaran::STATUS_SELESAI;

        $masalahValidasi = [];
        $peringatan = [];

        if ($sudahSelesai) {
            $masalahValidasi[] = "Transisi tahun ajaran dari {$tahunAsalKode} ke {$tahunTujuanKode} sudah berstatus SELESAI.";
        }

        // Ambil seluruh kelas di tahun asal dan tahun tujuan
        $kelasAsal = Kelas::where('tahun_ajaran_kode', $tahunAsalKode)->get()->keyBy('id');
        $kelasTujuan = Kelas::where('tahun_ajaran_kode', $tahunTujuanKode)->get();

        // Peta lookup kelas tujuan: "TINGKAT_JURUSAN_PARALEL" => Kelas
        $petaTujuan = [];
        foreach ($kelasTujuan as $kt) {
            $kunci = "{$kt->tingkat}_{$kt->jurusan_kode}_{$kt->paralel}";
            $petaTujuan[$kunci] = $kt;
        }

        // Ambil siswa aktif di tahun asal
        $siswaAsal = Siswa::whereIn('kelas_id', $kelasAsal->keys())
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get();

        $calonLulus = [];
        $calonPromosi = [];

        $totalX = 0;
        $totalXI = 0;

        foreach ($siswaAsal as $s) {
            $kelas = $kelasAsal[$s->kelas_id] ?? null;
            if (! $kelas) {
                continue;
            }

            if ($kelas->tingkat === 'XII') {
                $calonLulus[] = [
                    'nis' => $s->nis,
                    'nama' => $s->nama,
                    'kelas_id' => $kelas->id,
                    'nama_kelas' => $kelas->nama_kelas,
                    'jurusan_kode' => $kelas->jurusan_kode,
                ];
            } else {
                $tingkatBaru = $kelas->tingkat === 'X' ? 'XI' : 'XII';
                $kunciTujuan = "{$tingkatBaru}_{$kelas->jurusan_kode}_{$kelas->paralel}";
                $kelasTujuanObj = $petaTujuan[$kunciTujuan] ?? null;

                if (! $kelasTujuanObj) {
                    $namaHarapan = self::formatNamaKelas($tingkatBaru, $kelas->jurusan_kode, $kelas->paralel);
                    $masalahValidasi[] = "Kelas tujuan '{$namaHarapan}' belum tersedia di tahun ajaran {$tahunTujuanKode}.";
                }

                if ($kelas->tingkat === 'X') {
                    $totalX++;
                } else {
                    $totalXI++;
                }

                $calonPromosi[] = [
                    'nis' => $s->nis,
                    'nama' => $s->nama,
                    'kelas_asal_id' => $kelas->id,
                    'nama_kelas_asal' => $kelas->nama_kelas,
                    'tingkat_asal' => $kelas->tingkat,
                    'tingkat_tujuan' => $tingkatBaru,
                    'kelas_tujuan_id' => $kelasTujuanObj?->id,
                    'nama_kelas_tujuan' => $kelasTujuanObj?->nama_kelas ?? self::formatNamaKelas($tingkatBaru, $kelas->jurusan_kode, $kelas->paralel),
                    'siap' => $kelasTujuanObj !== null,
                ];
            }
        }

        $masalahValidasi = array_values(array_unique($masalahValidasi));

        if (count($calonLulus) === 0) {
            $peringatan[] = "Tidak ditemukan siswa aktif di tingkat XII pada tahun ajaran {$tahunAsalKode}.";
        }
        if (count($calonPromosi) === 0) {
            $peringatan[] = "Tidak ditemukan siswa aktif di tingkat X atau XI pada tahun ajaran {$tahunAsalKode}.";
        }

        $siapEksekusi = empty($masalahValidasi) && (count($calonLulus) > 0 || count($calonPromosi) > 0);

        return [
            'tahun_asal' => $tahunAsalKode,
            'tahun_tujuan' => $tahunTujuanKode,
            'status_transisi' => $transisi?->status,
            'sudah_selesai' => $sudahSelesai,
            'siap_eksekusi' => $siapEksekusi,
            'masalah_validasi' => $masalahValidasi,
            'peringatan' => $peringatan,
            'statistik' => [
                'total_lulusan' => count($calonLulus),
                'total_promosi_x_ke_xi' => $totalX,
                'total_promosi_xi_ke_xii' => $totalXI,
                'total_promosi' => count($calonPromosi),
                'total_siswa' => count($calonLulus) + count($calonPromosi),
            ],
            'calon_lulus' => $calonLulus,
            'calon_promosi' => $calonPromosi,
        ];
    }

    /**
     * Eksekusi transaksi kenaikan kelas dan kelulusan secara atomik dan efisien (batch-processed).
     *
     * @param  array{
     *     pengecualian?: array<string, array{aksi: string, kelas_id?: int, catatan?: string}>,
     *     nonaktifkan_akun?: bool,
     *     tanggal_lulus?: string,
     *     aktifkan_tahun_tujuan?: bool
     * }  $opsi
     * @return array{
     *     transisi_id: int,
     *     siswa_dipromosikan: int,
     *     siswa_lulus: int,
     *     siswa_tinggal: int,
     *     siswa_pindah: int,
     *     tahun_tujuan_aktif: bool
     * }
     */
    public static function eksekusi(
        string $tahunAsalKode,
        string $tahunTujuanKode,
        array $opsi = [],
        ?User $admin = null,
    ): array {
        $tahunAsal = TahunAjaran::where('kode', $tahunAsalKode)->firstOrFail();
        $tahunTujuan = TahunAjaran::where('kode', $tahunTujuanKode)->firstOrFail();

        $tanggalLulus = $opsi['tanggal_lulus'] ?? now()->toDateString();
        $nonaktifkanAkun = (bool) ($opsi['nonaktifkan_akun'] ?? false);
        $aktifkanTujuan = (bool) ($opsi['aktifkan_tahun_tujuan'] ?? false);
        $pengecualian = $opsi['pengecualian'] ?? [];

        return DB::transaction(function () use (
            $tahunAsal,
            $tahunTujuan,
            $tanggalLulus,
            $nonaktifkanAkun,
            $aktifkanTujuan,
            $pengecualian,
            $admin
        ) {
            // 1. Kunci baris transisi untuk mencegah eksekusi ganda / race conditions
            $transisi = TransisiTahunAjaran::lockForUpdate()->firstOrCreate(
                ['tahun_asal' => $tahunAsal->kode, 'tahun_tujuan' => $tahunTujuan->kode],
                ['status' => TransisiTahunAjaran::STATUS_PERSIAPAN]
            );

            if ($transisi->status === TransisiTahunAjaran::STATUS_SELESAI) {
                throw new DomainException(
                    "Transisi tahun ajaran dari {$tahunAsal->kode} ke {$tahunTujuan->kode} sudah pernah diproses dan berstatus SELESAI."
                );
            }

            $transisi->update(['status' => TransisiTahunAjaran::STATUS_DIPROSES]);

            $kelasAsal = Kelas::where('tahun_ajaran_kode', $tahunAsal->kode)->get()->keyBy('id');
            $kelasTujuan = Kelas::where('tahun_ajaran_kode', $tahunTujuan->kode)->get();

            $petaTujuan = [];
            foreach ($kelasTujuan as $kt) {
                $kunci = "{$kt->tingkat}_{$kt->jurusan_kode}_{$kt->paralel}";
                $petaTujuan[$kunci] = $kt;
            }

            $siswaAsal = Siswa::whereIn('kelas_id', $kelasAsal->keys())
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->get();

            // Kategori pemrosesan
            $lulusRegulerSiswa = [];
            $promosiNormalSiswa = []; // dest_class_id => list of [nis, dest_tingkat]
            $tinggalKelasSiswa = [];
            $keluarPindahSiswa = [];

            foreach ($siswaAsal as $s) {
                $kelas = $kelasAsal[$s->kelas_id] ?? null;
                if (! $kelas) {
                    continue;
                }

                $kecuali = $pengecualian[$s->nis] ?? null;
                $aksiKhusus = $kecuali['aksi'] ?? null;

                if ($aksiKhusus === 'keluar' || $aksiKhusus === 'pindah') {
                    $keluarPindahSiswa[] = [
                        'nis' => $s->nis,
                        'catatan' => $kecuali['catatan'] ?? 'Pindah/keluar sekolah saat transisi tahunan',
                    ];
                    continue;
                }

                if ($kelas->tingkat === 'XII') {
                    if ($aksiKhusus === 'tunda_lulus' || $aksiKhusus === 'tinggal_kelas') {
                        $kunciSama = "XII_{$kelas->jurusan_kode}_{$kelas->paralel}";
                        $destKelas = $petaTujuan[$kunciSama] ?? null;
                        if (! $destKelas) {
                            throw new RuntimeException("Kelas tujuan XII untuk siswa {$s->nis} belum tersedia.");
                        }
                        $tinggalKelasSiswa[] = [
                            'nis' => $s->nis,
                            'dest_kelas_id' => $destKelas->id,
                            'tingkat' => 'XII',
                            'catatan' => $kecuali['catatan'] ?? 'Mengulang kelas XII di tahun ajaran baru',
                        ];
                    } else {
                        $lulusRegulerSiswa[] = [
                            'nis' => $s->nis,
                            'kelas_terakhir_id' => $kelas->id,
                            'catatan' => $kecuali['catatan'] ?? 'Lulus reguler',
                        ];
                    }
                } else {
                    if ($aksiKhusus === 'tinggal_kelas') {
                        $kunciSama = "{$kelas->tingkat}_{$kelas->jurusan_kode}_{$kelas->paralel}";
                        $destKelas = $petaTujuan[$kunciSama] ?? null;
                        if (! $destKelas) {
                            throw new RuntimeException("Kelas tujuan tingkat {$kelas->tingkat} untuk siswa {$s->nis} belum tersedia.");
                        }
                        $tinggalKelasSiswa[] = [
                            'nis' => $s->nis,
                            'dest_kelas_id' => $destKelas->id,
                            'tingkat' => $kelas->tingkat,
                            'catatan' => $kecuali['catatan'] ?? "Tinggal di tingkat {$kelas->tingkat}",
                        ];
                    } else {
                        $tingkatTujuan = $kelas->tingkat === 'X' ? 'XI' : 'XII';
                        $kunciTujuan = "{$tingkatTujuan}_{$kelas->jurusan_kode}_{$kelas->paralel}";

                        $targetKelasId = ! empty($kecuali['kelas_id'])
                            ? (int) $kecuali['kelas_id']
                            : ($petaTujuan[$kunciTujuan]?->id ?? null);

                        if (! $targetKelasId) {
                            $namaHarapan = self::formatNamaKelas($tingkatTujuan, $kelas->jurusan_kode, $kelas->paralel);
                            throw new RuntimeException("Kelas tujuan '{$namaHarapan}' untuk siswa {$s->nis} ({$s->nama}) tidak ditemukan.");
                        }

                        $promosiNormalSiswa[$targetKelasId][] = [
                            'nis' => $s->nis,
                            'tingkat' => $tingkatTujuan,
                            'kelas_asal_nama' => $kelas->nama_kelas,
                        ];
                    }
                }
            }

            // ==========================================
            // EKSEKUSI BATCH 1: KELULUSAN
            // ==========================================
            $jumlahLulus = count($lulusRegulerSiswa);
            if ($jumlahLulus > 0) {
                $lulusNis = array_column($lulusRegulerSiswa, 'nis');

                // 1. Tutup riwayat kelas aktif
                foreach (array_chunk($lulusNis, 500) as $chunkNis) {
                    RiwayatKelasSiswa::whereIn('siswa_nis', $chunkNis)
                        ->where('aktif', true)
                        ->update([
                            'aktif' => false,
                            'status' => 'lulus',
                            'tanggal_selesai' => $tanggalLulus,
                            'updated_at' => now(),
                        ]);

                    // Update status siswa
                    Siswa::whereIn('nis', $chunkNis)->update([
                        'status' => 'lulus',
                        'kelas_id' => null,
                        'updated_at' => now(),
                    ]);

                    if ($nonaktifkanAkun) {
                        User::whereIn('nis', $chunkNis)->update(['status' => 'nonaktif', 'updated_at' => now()]);
                    }
                }

                // 2. Insert ke alumni
                $alumniRows = [];
                foreach ($lulusRegulerSiswa as $item) {
                    $alumniRows[] = [
                        'siswa_nis' => $item['nis'],
                        'tahun_lulus' => $tahunAsal->kode,
                        'tanggal_lulus' => $tanggalLulus,
                        'kelas_terakhir_id' => $item['kelas_terakhir_id'],
                        'keterangan' => $item['catatan'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                foreach (array_chunk($alumniRows, 500) as $chunkAlumni) {
                    DB::table('alumni')->insertOrIgnore($chunkAlumni);
                }
            }

            // ==========================================
            // EKSEKUSI BATCH 2: MUTASI PINDAH / KELUAR
            // ==========================================
            $jumlahPindah = count($keluarPindahSiswa);
            if ($jumlahPindah > 0) {
                $pindahNis = array_column($keluarPindahSiswa, 'nis');
                foreach (array_chunk($pindahNis, 500) as $chunkNis) {
                    RiwayatKelasSiswa::whereIn('siswa_nis', $chunkNis)
                        ->where('aktif', true)
                        ->update([
                            'aktif' => false,
                            'status' => 'pindah',
                            'tanggal_selesai' => now()->toDateString(),
                            'catatan' => 'Pindah/keluar sekolah saat transisi tahunan',
                            'updated_at' => now(),
                        ]);

                    Siswa::whereIn('nis', $chunkNis)->update([
                        'status' => 'nonaktif',
                        'kelas_id' => null,
                        'updated_at' => now(),
                    ]);
                }
            }

            // ==========================================
            // EKSEKUSI BATCH 3: TINGGAL KELAS
            // ==========================================
            $jumlahTinggal = count($tinggalKelasSiswa);
            if ($jumlahTinggal > 0) {
                foreach ($tinggalKelasSiswa as $item) {
                    RiwayatKelasSiswa::where('siswa_nis', $item['nis'])
                        ->where('aktif', true)
                        ->update([
                            'aktif' => false,
                            'status' => 'tinggal_kelas',
                            'tanggal_selesai' => now()->toDateString(),
                            'catatan' => $item['catatan'],
                            'updated_at' => now(),
                        ]);

                    RiwayatKelasSiswa::create([
                        'siswa_nis' => $item['nis'],
                        'kelas_id' => $item['dest_kelas_id'],
                        'tahun_ajaran_kode' => $tahunTujuan->kode,
                        'tingkat' => $item['tingkat'],
                        'status' => 'tinggal_kelas',
                        'aktif' => true,
                        'tanggal_mulai' => now()->toDateString(),
                        'catatan' => $item['catatan'],
                    ]);

                    Siswa::where('nis', $item['nis'])->update([
                        'kelas_id' => $item['dest_kelas_id'],
                        'updated_at' => now(),
                    ]);
                }
            }

            // ==========================================
            // EKSEKUSI BATCH 4: PROMOSI NORMAL
            // ==========================================
            $jumlahDipromosikan = 0;
            $riwayatBaruRows = [];

            foreach ($promosiNormalSiswa as $destKelasId => $siswaGroup) {
                $groupNis = array_column($siswaGroup, 'nis');
                $jumlahDipromosikan += count($groupNis);

                // 1. Tutup riwayat aktif lama
                foreach (array_chunk($groupNis, 500) as $chunkNis) {
                    RiwayatKelasSiswa::whereIn('siswa_nis', $chunkNis)
                        ->where('aktif', true)
                        ->update([
                            'aktif' => false,
                            'status' => 'aktif',
                            'tanggal_selesai' => now()->toDateString(),
                            'updated_at' => now(),
                        ]);

                    // Update kelas siswa di tabel siswa
                    Siswa::whereIn('nis', $chunkNis)->update([
                        'kelas_id' => $destKelasId,
                        'status' => 'aktif',
                        'updated_at' => now(),
                    ]);
                }

                // 2. Siapkan baris riwayat baru
                foreach ($siswaGroup as $sItem) {
                    $riwayatBaruRows[] = [
                        'siswa_nis' => $sItem['nis'],
                        'kelas_id' => $destKelasId,
                        'tahun_ajaran_kode' => $tahunTujuan->kode,
                        'tingkat' => $sItem['tingkat'],
                        'status' => 'aktif',
                        'aktif' => 1,
                        'tanggal_mulai' => now()->toDateString(),
                        'tanggal_selesai' => null,
                        'catatan' => "Promosi kelas {$sItem['kelas_asal_nama']} ke tingkat {$sItem['tingkat']}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Insert riwayat baru dalam chunk
            foreach (array_chunk($riwayatBaruRows, 500) as $chunkRiwayat) {
                DB::table('riwayat_kelas_siswa')->insert($chunkRiwayat);
            }

            // Finalisasi status transisi
            $deskripsiCatatan = sprintf(
                'Transisi dari %s ke %s selesai. %d siswa dipromosikan, %d siswa lulus, %d tinggal kelas, %d keluar/pindah.',
                $tahunAsal->kode,
                $tahunTujuan->kode,
                $jumlahDipromosikan,
                $jumlahLulus,
                $jumlahTinggal,
                $jumlahPindah
            );

            $transisi->update([
                'status' => TransisiTahunAjaran::STATUS_SELESAI,
                'jumlah_siswa_dipromosikan' => $jumlahDipromosikan,
                'jumlah_siswa_lulus' => $jumlahLulus,
                'jumlah_siswa_tinggal' => $jumlahTinggal,
                'diproses_oleh_id' => $admin?->id,
                'diproses_pada' => now(),
                'catatan' => $deskripsiCatatan,
            ]);

            // Jika admin meminta tahun ajaran tujuan langsung diaktifkan
            if ($aktifkanTujuan) {
                $tahunTujuan->aktifkan();
            }

            // Catat log audit kritis yang wajib berhasil
            AuditAdmin::catatKritis(
                aksi: 'akademik.transisi',
                tipeEntitas: 'transisi_tahun_ajaran',
                entitasId: (string) $transisi->id,
                deskripsi: $deskripsiCatatan,
                metadata: [
                    'tahun_asal' => $tahunAsal->kode,
                    'tahun_tujuan' => $tahunTujuan->kode,
                    'siswa_dipromosikan' => $jumlahDipromosikan,
                    'siswa_lulus' => $jumlahLulus,
                    'siswa_tinggal' => $jumlahTinggal,
                    'siswa_pindah' => $jumlahPindah,
                    'tahun_tujuan_diaktifkan' => $aktifkanTujuan,
                ],
                jumlah: $jumlahDipromosikan + $jumlahLulus + $jumlahTinggal + $jumlahPindah,
                user: $admin
            );

            return [
                'transisi_id' => $transisi->id,
                'siswa_dipromosikan' => $jumlahDipromosikan,
                'siswa_lulus' => $jumlahLulus,
                'siswa_tinggal' => $jumlahTinggal,
                'siswa_pindah' => $jumlahPindah,
                'tahun_tujuan_aktif' => $aktifkanTujuan,
            ];
        });
    }

    /**
     * Format nama rombel kelas konsisten: "{Tingkat} {Jurusan} {Paralel}".
     */
    public static function formatNamaKelas(string $tingkat, ?string $jurusanKode, int $paralel): string
    {
        $jurusan = trim((string) $jurusanKode);

        return trim("{$tingkat} {$jurusan} {$paralel}");
    }
}
