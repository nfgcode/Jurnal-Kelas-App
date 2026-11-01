<?php

namespace App\AcademicWorkflows;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Support\XlsxExport;
use App\Support\XlsxReader;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PromotionSpreadsheet
{
    public const CANONICAL_HEADERS = ['NIS', 'Nama Siswa', 'Status Kenaikan', 'Alasan / Catatan'];

    public static function templateXlsx(string $sourceYear = '2026/2027'): BinaryFileResponse
    {
        $header = self::CANONICAL_HEADERS;
        $rows = [
            ['00123', 'Contoh Siswa Naik', 'NAIK', ''],
            ['00124', 'Contoh Siswa Tinggal Kelas', 'TINGGAL KELAS', 'Kehadiran di bawah 75% dan belum tuntas remidi'],
            ['00125', 'Contoh Siswa Perlu Verifikasi', 'BELUM TERVERIFIKASI', 'Menunggu sidang pleno dewan guru'],
        ];

        $filename = 'template-kenaikan-x-xi-' . str_replace('/', '-', $sourceYear) . '.xlsx';

        return XlsxExport::download($filename, $header, $rows);
    }

    public static function aiPrompt(string $sourceYear): string
    {
        return <<<PROMPT
[PERINGATAN PRIVASI & KEAMANAN DATA SEKOLAH]
PERHATIAN: Jangan mengunggah dokumen yang memuat data pribadi sensitif (seperti NIK, nomor kontak pribadi, alamat rumah, atau data medis keluarga) ke layanan AI pihak ketiga tanpa izin resmi sekolah. Gunakan data dengan nama dan NIS saja atau lakukan anonimisasi jika ragu.

---
TUGAS:
Format ulang data hasil rapat kenaikan kelas (Tingkat X dan XI) Tahun Ajaran {$sourceYear} ke dalam tabel tab-separated values (TSV) 4 kolom agar siap disalin ke spreadsheet (Excel/Google Sheets).

KOLOM WAJIB (Persis 4 kolom):
1. NIS (Pertahankan semua angka dan leading zeros / nol di depan seperti 00123, jangan ubah jadi angka 123)
2. Nama Siswa (Sebagai data pembanding)
3. Status Kenaikan (Wajib bernilai salah satu dari: NAIK, TINGGAL KELAS, atau BELUM TERVERIFIKASI)
4. Alasan / Catatan (Wajib diisi jika status TINGGAL KELAS; opsional untuk status NAIK)

ATURAN KETAT:
- Siswa Tingkat X dan XI default adalah NAIK kelas jika memenuhi syarat.
- Siswa yang TINGGAL KELAS wajib memiliki keterangan alasan pada kolom ke-4.
- Jangan mengarang NIS atau nama siswa yang tidak ada di dokumen sumber.
- Jika status siswa pada dokumen asli belum diputuskan atau ditunda, isi dengan BELUM TERVERIFIKASI.
- Siswa tingkat XII tidak boleh dimasukkan dalam berkas kenaikan kelas ini (gunakan alur kelulusan).
- Hasilkan DUA KELUARAN:
  [OUTPUT A]: Tabel TSV data bersih tanpa komentar apapun, siap di-copy langsung ke lembar kerja.
  [OUTPUT B]: Laporan ringkasan pengecualian/catatan siswa yang bermasalah.
PROMPT;
    }

    /**
     * Parse berkas XLSX atau CSV kenaikan kelas dan petakan ke roster siswa aktif X & XI.
     */
    public static function parse(
        string $path,
        string $sourceYear,
        ?string $targetYear = null,
        ?string $originalName = null,
        array $manualOverrides = []
    ): array {
        $isCsv = ($originalName && str_ends_with(strtolower($originalName), '.csv')) || str_ends_with(strtolower($path), '.csv');
        if ($isCsv && ! str_ends_with(strtolower($path), '.csv')) {
            $tempCsv = $path . '.csv';
            copy($path, $tempCsv);
            try {
                $rawRows = XlsxReader::baca($tempCsv);
            } finally {
                @unlink($tempCsv);
            }
        } else {
            $rawRows = XlsxReader::baca($path);
        }

        if (empty($rawRows)) {
            throw new \RuntimeException('Berkas spreadsheet kosong.');
        }

        $contentHash = hash('sha256', (string) file_get_contents($path));

        $headerIndex = -1;
        $colNis = -1;
        $colNama = -1;
        $colStatus = -1;
        $colAlasan = -1;

        foreach ($rawRows as $idx => $row) {
            $normalizedRow = array_map(fn ($c) => strtolower(trim((string) $c)), $row);
            foreach ($normalizedRow as $cIdx => $cell) {
                if (in_array($cell, ['nis', 'nomor induk', 'no induk'], true)) {
                    $colNis = $cIdx;
                } elseif (in_array($cell, ['nama', 'nama siswa', 'nama lengkap'], true)) {
                    $colNama = $cIdx;
                } elseif (in_array($cell, ['status', 'status kenaikan', 'kenaikan', 'hasil'], true)) {
                    $colStatus = $cIdx;
                } elseif (in_array($cell, ['alasan', 'keterangan', 'catatan', 'alasan / catatan', 'alasan tinggal kelas'], true)) {
                    $colAlasan = $cIdx;
                }
            }
            if ($colNis !== -1 && $colStatus !== -1) {
                $headerIndex = $idx;
                break;
            }
        }

        if ($headerIndex === -1) {
            throw new \RuntimeException('Format berkas tidak sesuai. Kolom "NIS" dan "Status Kenaikan" wajib ada.');
        }

        $activeRoster = DB::table('siswa')
            ->join('kelas', 'kelas.id', '=', 'siswa.kelas_id')
            ->where('siswa.status', 'aktif')
            ->where('kelas.tahun_ajaran_kode', $sourceYear)
            ->whereIn('kelas.tingkat', ['X', 'XI'])
            ->select('siswa.nis', 'siswa.nama', 'siswa.kelas_id', 'kelas.tingkat', 'kelas.jurusan_kode', 'kelas.paralel')
            ->orderBy('siswa.nis')
            ->get()
            ->keyBy('nis');

        $targetClasses = collect();
        if ($targetYear) {
            $targetClasses = Kelas::where('tahun_ajaran_kode', $targetYear)->get();
        }

        $items = [];
        $fileEntries = [];
        $seenFileNis = [];

        for ($i = $headerIndex + 1; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            $nis = isset($row[$colNis]) ? trim((string) $row[$colNis]) : '';
            if ($nis === '') {
                continue;
            }

            $nama = ($colNama !== -1 && isset($row[$colNama])) ? trim((string) $row[$colNama]) : '';
            $statusRaw = trim((string) ($row[$colStatus] ?? ''));
            $alasan = ($colAlasan !== -1 && isset($row[$colAlasan])) ? trim((string) $row[$colAlasan]) : '';
            $statusUpper = strtoupper($statusRaw);

            $isDuplicateInFile = isset($seenFileNis[$nis]);
            $seenFileNis[$nis] = true;

            $fileEntries[] = [
                'nis' => $nis,
                'nama' => $nama,
                'status_raw' => $statusRaw,
                'status_upper' => $statusUpper,
                'alasan' => $alasan,
                'is_duplicate' => $isDuplicateInFile,
            ];
        }

        // Kumpulkan daftar semua NIS unik yang muncul di berkas atau manual overrides
        $processedNisList = [];

        foreach ($fileEntries as $entry) {
            $nis = $entry['nis'];
            $statusUpper = $entry['status_upper'];
            $alasan = $entry['alasan'];
            $errors = [];

            if ($entry['is_duplicate']) {
                $errors[] = 'nis_duplikat_di_berkas';
            }

            // Normalisasi keputusan
            if (in_array($statusUpper, ['NAIK', 'NAIK KELAS', 'PROMOTED'], true)) {
                $decision = 'naik';
            } elseif (in_array($statusUpper, ['TINGGAL KELAS', 'TINGGAL_KELAS', 'TIDAK NAIK', 'TIDAK_NAIK', 'RETAINED'], true)) {
                $decision = 'tinggal_kelas';
            } elseif (in_array($statusUpper, ['BELUM TERVERIFIKASI', 'BELUM_TERVERIFIKASI', 'UNVERIFIED', 'TUNDA'], true)) {
                $decision = 'unverified';
            } else {
                $decision = 'unverified';
                $errors[] = 'status_tidak_dikenal';
            }

            // Validasi alasan untuk tinggal kelas
            if ($decision === 'tinggal_kelas' && empty($alasan)) {
                $errors[] = 'alasan_tinggal_kelas_wajib';
            }

            // Deteksi konflik dengan input manual jika ada
            if (isset($manualOverrides[$nis])) {
                $manual = $manualOverrides[$nis];
                $manualDecision = $manual['decision'] ?? null;
                if ($manualDecision && $manualDecision !== $decision) {
                    $errors[] = 'konflik_manual_dan_berkas';
                    $decision = 'unverified'; // Harus diselesaikan admin secara eksplisit
                }
                if (! empty($manual['alasan'])) {
                    $alasan = $manual['alasan'];
                }
            }

            $student = $activeRoster->get($nis);
            $targetClassId = null;

            if (! $student) {
                $existing = Siswa::with('kelas')->where('nis', $nis)->first();
                if (! $existing) {
                    $errors[] = 'nis_tidak_dikenal';
                } else {
                    if ($existing->kelas?->tingkat === 'XII') {
                        $errors[] = 'siswa_tingkat_xii_tidak_termasuk';
                    } elseif ($existing->kelas?->tahun_ajaran_kode !== $sourceYear) {
                        $errors[] = 'bukan_tahun_sumber';
                    } elseif ($existing->status !== 'aktif') {
                        $errors[] = 'status_siswa_nonaktif';
                    } else {
                        $errors[] = 'bukan_anggota_roster_promosi';
                    }
                }
            } else {
                // Tentukan target class: utamakan manual override jika ada, jika tidak gunakan default rekomendasi
                $targetGrade = $decision === 'tinggal_kelas'
                    ? $student->tingkat
                    : ($student->tingkat === 'X' ? 'XI' : 'XII');

                $overrideClassId = $manualOverrides[$nis]['target_class_id'] ?? null;
                if ($overrideClassId) {
                    $targetClassId = (int) $overrideClassId;
                } else {
                    $matchedClass = self::resolveTargetClass($targetClasses, $targetGrade, $student->jurusan_kode, (int) $student->paralel);
                    if ($matchedClass) {
                        $targetClassId = $matchedClass->id;
                    } else {
                        if ($targetYear) {
                            $errors[] = 'target_class_invalid';
                        }
                    }
                }
            }

            $processedNisList[] = $nis;

            $items[] = [
                'student_ref' => $nis,
                'nama' => $student ? $student->nama : $entry['nama'],
                'decision' => $decision,
                'alasan' => $alasan,
                'target_class_id' => $targetClassId,
                'omitted' => false,
                'errors' => array_values(array_unique($errors)),
            ];
        }

        // Tangani siswa aktif X & XI yang tidak tercantum dalam berkas
        foreach ($activeRoster as $nis => $student) {
            if (in_array($nis, $processedNisList, true)) {
                continue;
            }

            $errors = [];
            $alasan = '';
            // Default adalah naik jika tidak ada berkas/manual
            $decision = 'naik';

            // Jika ada manual override untuk siswa yang tidak tercantum di berkas
            if (isset($manualOverrides[$nis])) {
                $manual = $manualOverrides[$nis];
                $decision = $manual['decision'] ?? 'naik';
                $alasan = $manual['alasan'] ?? '';
                if ($decision === 'tinggal_kelas' && empty($alasan)) {
                    $errors[] = 'alasan_tinggal_kelas_wajib';
                }
            }

            $targetGrade = $decision === 'tinggal_kelas'
                ? $student->tingkat
                : ($student->tingkat === 'X' ? 'XI' : 'XII');

            $overrideClassId = $manualOverrides[$nis]['target_class_id'] ?? null;
            if ($overrideClassId) {
                $targetClassId = (int) $overrideClassId;
            } else {
                $matchedClass = self::resolveTargetClass($targetClasses, $targetGrade, $student->jurusan_kode, (int) $student->paralel);
                $targetClassId = $matchedClass?->id;

                if ($targetYear && ! $matchedClass) {
                    $errors[] = 'target_class_invalid';
                }
            }

            $items[] = [
                'student_ref' => $nis,
                'nama' => $student->nama,
                'decision' => $decision,
                'alasan' => $alasan,
                'target_class_id' => $targetClassId,
                'omitted' => true,
                'errors' => array_values(array_unique($errors)),
            ];
        }

        return self::formatResult($contentHash, $items, $activeRoster->count(), count($fileEntries));
    }

    /**
     * Cari kelas tujuan berdasarkan tingkat, jurusan, dan nomor paralel yang cocok.
     */
    public static function resolveTargetClass($targetClasses, string $targetGrade, ?string $jurusanKode, int $paralel): ?Kelas
    {
        if ($targetClasses->isEmpty()) {
            return null;
        }

        return $targetClasses->first(function ($c) use ($targetGrade, $jurusanKode, $paralel) {
            return $c->tingkat === $targetGrade
                && $c->jurusan_kode === $jurusanKode
                && (int) $c->paralel === $paralel;
        });
    }

    /**
     * Bangun item default (semua siswa X & XI naik kelas) dengan kemungkinan manual override.
     */
    public static function buildDefaultRoster(
        string $sourceYear,
        ?string $targetYear = null,
        array $manualOverrides = []
    ): array {
        $activeRoster = DB::table('siswa')
            ->join('kelas', 'kelas.id', '=', 'siswa.kelas_id')
            ->where('siswa.status', 'aktif')
            ->where('kelas.tahun_ajaran_kode', $sourceYear)
            ->whereIn('kelas.tingkat', ['X', 'XI'])
            ->select('siswa.nis', 'siswa.nama', 'siswa.kelas_id', 'kelas.tingkat', 'kelas.jurusan_kode', 'kelas.paralel')
            ->orderBy('siswa.nis')
            ->get();

        $targetClasses = collect();
        if ($targetYear) {
            $targetClasses = Kelas::where('tahun_ajaran_kode', $targetYear)->get();
        }

        $items = [];
        foreach ($activeRoster as $student) {
            $nis = $student->nis;
            $decision = 'naik';
            $alasan = '';
            $errors = [];

            if (isset($manualOverrides[$nis])) {
                $manual = $manualOverrides[$nis];
                $decision = $manual['decision'] ?? 'naik';
                $alasan = $manual['alasan'] ?? '';

                if ($decision === 'tinggal_kelas' && empty($alasan)) {
                    $errors[] = 'alasan_tinggal_kelas_wajib';
                }
            }

            $targetGrade = $decision === 'tinggal_kelas'
                ? $student->tingkat
                : ($student->tingkat === 'X' ? 'XI' : 'XII');

            $overrideClassId = $manualOverrides[$nis]['target_class_id'] ?? null;
            if ($overrideClassId) {
                $targetClassId = (int) $overrideClassId;
            } else {
                $matchedClass = self::resolveTargetClass($targetClasses, $targetGrade, $student->jurusan_kode, (int) $student->paralel);
                $targetClassId = $matchedClass?->id;

                if ($targetYear && ! $matchedClass) {
                    $errors[] = 'target_class_invalid';
                }
            }

            $items[] = [
                'student_ref' => $nis,
                'nama' => $student->nama,
                'decision' => $decision,
                'alasan' => $alasan,
                'target_class_id' => $targetClassId,
                'omitted' => false,
                'errors' => array_values(array_unique($errors)),
            ];
        }

        return self::formatResult('default-roster', $items, $activeRoster->count(), 0);
    }

    /**
     * Format ringkasan dan metrik keluaran.
     */
    private static function formatResult(string $hash, array $items, int $totalRoster, int $totalInFile): array
    {
        $countNaik = 0;
        $countTinggalKelas = 0;
        $countUnverified = 0;
        $countMasalah = 0;

        foreach ($items as $item) {
            if (! empty($item['errors'])) {
                $countMasalah++;
            }
            if ($item['decision'] === 'naik') {
                $countNaik++;
            } elseif ($item['decision'] === 'tinggal_kelas') {
                $countTinggalKelas++;
            } else {
                $countUnverified++;
            }
        }

        return [
            'file_hash' => $hash,
            'items' => $items,
            'summary' => [
                'total_roster' => $totalRoster,
                'total_in_file' => $totalInFile,
                'naik' => $countNaik,
                'tinggal_kelas' => $countTinggalKelas,
                'unverified' => $countUnverified,
                'masalah' => $countMasalah,
            ],
        ];
    }
}
