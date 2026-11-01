<?php

namespace App\AcademicWorkflows;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Support\XlsxExport;
use App\Support\XlsxReader;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class GraduationSpreadsheet
{
    public const CANONICAL_HEADERS = ['NIS', 'Nama Siswa', 'Status Kelulusan'];

    public static function templateXlsx(string $sourceYear = '2026/2027'): BinaryFileResponse
    {
        $header = self::CANONICAL_HEADERS;
        $rows = [
            ['00123', 'Contoh Siswa Lulus', 'LULUS'],
            ['00124', 'Contoh Siswa Tidak Lulus', 'TIDAK LULUS'],
            ['00125', 'Contoh Siswa Perlu Verifikasi', 'BELUM TERVERIFIKASI'],
        ];

        $filename = 'template-kelulusan-xii-' . str_replace('/', '-', $sourceYear) . '.xlsx';

        return XlsxExport::download($filename, $header, $rows);
    }

    public static function aiPrompt(string $sourceYear): string
    {
        return <<<PROMPT
[PERINGATAN PRIVASI & KEAMANAN DATA SEKOLAH]
PERHATIAN: Jangan mengunggah dokumen yang memuat data pribadi sensitif (seperti NIK, nomor kontak pribadi, alamat rumah, atau data medis keluarga) ke layanan AI pihak ketiga tanpa izin resmi sekolah. Gunakan data dengan nama dan NIS saja atau lakukan anonimisasi jika ragu.

---
TUGAS:
Format ulang data hasil kelulusan resmi Kelas XII Tahun Ajaran {$sourceYear} ke dalam tabel tab-separated values (TSV) 3 kolom agar siap disalin ke spreadsheet (Excel/Google Sheets).

KOLOM WAJIB (Persis 3 kolom):
1. NIS (Pertahankan semua angka dan leading zeros / nol di depan seperti 00123, jangan ubah jadi angka 123)
2. Nama Siswa (Sebagai data pembanding)
3. Status Kelulusan (Wajib bernilai salah satu dari: LULUS, TIDAK LULUS, atau BELUM TERVERIFIKASI)

ATURAN KETAT:
- Jangan mengarang NIS atau nama siswa yang tidak ada di dokumen sumber.
- Jika status siswa pada dokumen asli tidak jelas atau ditunda, isi dengan BELUM TERVERIFIKASI.
- Hasilkan DUA KELUARAN:
  [OUTPUT A]: Tabel TSV data bersih tanpa komentar apapun, siap di-copy langsung ke lembar kerja.
  [OUTPUT B]: Laporan ringkasan pengecualian/catatan siswa yang bermasalah.
PROMPT;
    }

    public static function parse(string $path, string $sourceYear, ?string $targetYear = null, ?string $originalName = null): array
    {
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

        foreach ($rawRows as $idx => $row) {
            $normalizedRow = array_map(fn ($c) => strtolower(trim((string) $c)), $row);
            foreach ($normalizedRow as $cIdx => $cell) {
                if (in_array($cell, ['nis', 'nomor induk', 'no induk'], true)) {
                    $colNis = $cIdx;
                } elseif (in_array($cell, ['nama', 'nama siswa', 'nama lengkap'], true)) {
                    $colNama = $cIdx;
                } elseif (in_array($cell, ['status', 'status kelulusan', 'kelulusan', 'keterangan'], true)) {
                    $colStatus = $cIdx;
                }
            }
            if ($colNis !== -1 && $colStatus !== -1) {
                $headerIndex = $idx;
                break;
            }
        }

        if ($headerIndex === -1) {
            throw new \RuntimeException('Format berkas tidak sesuai. Kolom "NIS" dan "Status Kelulusan" wajib ada.');
        }

        $activeRoster = DB::table('siswa')
            ->join('kelas', 'kelas.id', '=', 'siswa.kelas_id')
            ->where('siswa.status', 'aktif')
            ->where('kelas.tahun_ajaran_kode', $sourceYear)
            ->where('kelas.tingkat', 'XII')
            ->select('siswa.nis', 'siswa.nama', 'siswa.kelas_id', 'kelas.jurusan_kode', 'kelas.paralel')
            ->orderBy('siswa.nis')
            ->get()
            ->keyBy('nis');

        $targetClasses = collect();
        if ($targetYear) {
            $targetClasses = Kelas::where('tahun_ajaran_kode', $targetYear)
                ->where('tingkat', 'XII')
                ->get();
        }

        $items = [];
        $seenNis = [];
        $fileNisList = [];

        for ($i = $headerIndex + 1; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            $nis = isset($row[$colNis]) ? trim((string) $row[$colNis]) : '';
            if ($nis === '') {
                continue;
            }

            $nama = ($colNama !== -1 && isset($row[$colNama])) ? trim((string) $row[$colNama]) : '';
            $statusRaw = trim((string) ($row[$colStatus] ?? ''));
            $statusUpper = strtoupper($statusRaw);

            $errors = [];

            if (isset($seenNis[$nis])) {
                $errors[] = 'nis_duplikat';
            }
            $seenNis[$nis] = true;
            $fileNisList[] = $nis;

            if (in_array($statusUpper, ['LULUS'], true)) {
                $decision = 'lulus';
            } elseif (in_array($statusUpper, ['TIDAK LULUS', 'TIDAK_LULUS', 'TL'], true)) {
                $decision = 'tidak_lulus';
            } elseif (in_array($statusUpper, ['BELUM TERVERIFIKASI', 'BELUM_TERVERIFIKASI', 'UNVERIFIED', 'TUNDA'], true)) {
                $decision = 'unverified';
            } else {
                $decision = 'unverified';
                $errors[] = 'status_tidak_dikenal';
            }

            $student = $activeRoster->get($nis);
            $targetClassId = null;

            if (! $student) {
                $existing = Siswa::with('kelas')->where('nis', $nis)->first();
                if (! $existing) {
                    $errors[] = 'nis_tidak_dikenal';
                } else {
                    if ($existing->kelas?->tingkat !== 'XII') {
                        $errors[] = 'bukan_tingkat_xii';
                    } elseif ($existing->kelas?->tahun_ajaran_kode !== $sourceYear) {
                        $errors[] = 'bukan_tahun_sumber';
                    } elseif ($existing->status !== 'aktif') {
                        $errors[] = 'status_siswa_nonaktif';
                    } else {
                        $errors[] = 'bukan_anggota_roster_xii';
                    }
                }
            } else {
                if ($decision === 'tidak_lulus' && $targetClasses->isNotEmpty()) {
                    $matchedClass = $targetClasses->first(function ($c) use ($student) {
                        return $c->jurusan_kode === $student->jurusan_kode && (int) $c->paralel === (int) $student->paralel;
                    }) ?? $targetClasses->firstWhere('jurusan_kode', $student->jurusan_kode) ?? $targetClasses->first();

                    if ($matchedClass) {
                        $targetClassId = $matchedClass->id;
                    }
                }
            }

            $items[] = [
                'student_ref' => $nis,
                'nama' => $student ? $student->nama : $nama,
                'decision' => $decision,
                'target_class_id' => $targetClassId,
                'omitted' => false,
                'errors' => array_values(array_unique($errors)),
            ];
        }

        foreach ($activeRoster as $nis => $student) {
            if (! in_array($nis, $fileNisList, true)) {
                $items[] = [
                    'student_ref' => $nis,
                    'nama' => $student->nama,
                    'decision' => 'unverified',
                    'target_class_id' => null,
                    'omitted' => true,
                    'errors' => ['siswa_tidak_tercantum'],
                ];
            }
        }

        $countLulus = 0;
        $countTidakLulus = 0;
        $countUnverified = 0;
        $countMasalah = 0;

        foreach ($items as $item) {
            if (! empty($item['errors'])) {
                $countMasalah++;
            }
            if ($item['decision'] === 'lulus') {
                $countLulus++;
            } elseif ($item['decision'] === 'tidak_lulus') {
                $countTidakLulus++;
            } else {
                $countUnverified++;
            }
        }

        return [
            'file_hash' => $contentHash,
            'items' => $items,
            'summary' => [
                'total_roster' => $activeRoster->count(),
                'total_in_file' => count($fileNisList),
                'lulus' => $countLulus,
                'tidak_lulus' => $countTidakLulus,
                'unverified' => $countUnverified,
                'masalah' => $countMasalah,
            ],
        ];
    }
}
