<?php

namespace App\AcademicWorkflows;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use App\Support\XlsxExport;
use App\Support\XlsxReader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class IntakeSpreadsheet
{
    public const CANONICAL_HEADERS = [
        'NIS',
        'NISN',
        'Nama Lengkap',
        'Email',
        'Kelas X Tujuan',
        'Jenis Kelamin',
    ];

    /**
     * Unduh berkas template resmi intake siswa baru tingkat X.
     */
    public static function templateXlsx(string $targetYear = '2027/2028'): BinaryFileResponse
    {
        $header = self::CANONICAL_HEADERS;
        $rows = [
            ['00301', '0081234567', 'Ahmad Fadillah', 'ahmad.fadillah@sekolah.sch.id', 'X TKJ 1', 'L'],
            ['00302', '0087654321', 'Siti Rahmawati', 'siti.rahmawati@sekolah.sch.id', 'X TKJ 1', 'P'],
            ['00303', '0089988776', 'Budi Pratama', 'budi.pratama@sekolah.sch.id', 'X RPL 1', 'L'],
        ];

        $filename = 'template-intake-siswa-baru-' . str_replace('/', '-', $targetYear) . '.xlsx';

        return XlsxExport::download($filename, $header, $rows);
    }

    /**
     * Generator panduan prompt AI yang menjaga privasi data siswa.
     */
    public static function aiPrompt(string $targetYear): string
    {
        return <<<PROMPT
[PERINGATAN PERLINDUNGAN PRIVASI & KEAMANAN DATA SEKOLAH]
PERHATIAN KETAT: Dilarang mengunggah berkas yang memuat data pribadi sensitif (seperti Nomor Induk Kependudukan / NIK, nomor telepon pribadi, alamat rumah lengkap, data finansial orang tua, atau rekam medis) ke platform AI publik. Gunakan hanya data identitas dasar sekolah (NIS, NISN, Nama, Email, Rombel Tujuan, Jenis Kelamin).

---
TUGAS:
Format ulang data calon peserta didik baru Tingkat X Tahun Ajaran {$targetYear} ke dalam tabel tab-separated values (TSV) persis 6 kolom agar siap disalin ke lembar kerja spreadsheet (Excel / Google Sheets).

KOLOM WAJIB (Persis 6 kolom):
1. NIS (Wajib angka/string, pertahankan nol di depan seperti 00301, jangan sampai terpotong menjadi 301)
2. NISN (Wajib persis 10 digit angka nasional, contoh: 0081234567)
3. Nama Lengkap (Sesuai dokumen resmi ijazah SMP/MTs)
4. Email (Format email aktif yang sah dan unik untuk akun portal belajar siswa)
5. Kelas X Tujuan (Nama rombel tingkat X yang sudah terdaftar di sekolah, contoh: X TKJ 1, X RPL 1)
6. Jenis Kelamin (L atau P)

ATURAN INTEGRITAS DATA:
- Jangan mengarang data identitas atau rombel tujuan yang tidak tercantum dalam berkas asli.
- Rombel tujuan hanya untuk Tingkat X pada tahun ajaran {$targetYear}.
- Hasilkan DUA KELUARAN:
  [OUTPUT A]: Tabel TSV data bersih tanpa teks pengantar, siap disalin langsung ke lembar kerja.
  [OUTPUT B]: Laporan ringkasan anomali/catatan jika ditemukan baris dengan NISN bukan 10 digit, email salah format, atau rombel tidak jelas.
PROMPT;
    }

    /**
     * Parse berkas spreadsheet XLSX atau CSV dan lakukan validasi integritas intake.
     *
     * @param array<string, array{excluded: bool, exclusion_reason: string}> $manualExclusions
     */
    public static function parse(
        string $path,
        string $targetYear,
        ?string $originalName = null,
        array $manualExclusions = []
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
        $colNisn = -1;
        $colNama = -1;
        $colEmail = -1;
        $colKelas = -1;
        $colJk = -1;

        foreach ($rawRows as $idx => $row) {
            $normalizedRow = array_map(fn ($c) => strtolower(trim((string) $c)), $row);
            foreach ($normalizedRow as $cIdx => $cell) {
                if (in_array($cell, ['nis', 'nomor induk', 'no induk'], true)) {
                    $colNis = $cIdx;
                } elseif (in_array($cell, ['nisn', 'nomor induk siswa nasional'], true)) {
                    $colNisn = $cIdx;
                } elseif (in_array($cell, ['nama', 'nama lengkap', 'nama siswa'], true)) {
                    $colNama = $cIdx;
                } elseif (in_array($cell, ['email', 'alamat email', 'surel'], true)) {
                    $colEmail = $cIdx;
                } elseif (in_array($cell, ['kelas', 'kelas tujuan', 'kelas x tujuan', 'rombel', 'rombel tujuan'], true)) {
                    $colKelas = $cIdx;
                } elseif (in_array($cell, ['jenis kelamin', 'jk', 'gender'], true)) {
                    $colJk = $cIdx;
                }
            }
            if ($colNis !== -1 && $colNisn !== -1 && $colNama !== -1 && $colEmail !== -1 && $colKelas !== -1) {
                $headerIndex = $idx;
                break;
            }
        }

        if ($headerIndex === -1) {
            throw new \RuntimeException('Format berkas tidak sesuai. Kolom "NIS", "NISN", "Nama Lengkap", "Email", dan "Kelas X Tujuan" wajib ada.');
        }

        // Muat seluruh rombel tingkat X yang sah pada tahun tujuan
        $availableClasses = Kelas::where('tahun_ajaran_kode', $targetYear)->get();

        // Koleksi data database untuk deteksi duplikasi global
        $existingSiswaNis = Siswa::pluck('nis')->flip()->all();
        $existingSiswaNisn = Siswa::whereNotNull('nisn')->pluck('nisn')->flip()->all();
        $existingUserEmails = User::pluck('email')->map(fn ($e) => strtolower(trim($e)))->flip()->all();
        $existingUserNis = User::whereNotNull('nis')->pluck('nis')->flip()->all();

        $rawEntries = [];
        for ($i = $headerIndex + 1; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            $nis = isset($row[$colNis]) ? trim((string) $row[$colNis]) : '';
            if ($nis === '') {
                continue;
            }

            $nisn = isset($row[$colNisn]) ? trim((string) $row[$colNisn]) : '';
            $nama = isset($row[$colNama]) ? trim((string) $row[$colNama]) : '';
            $email = isset($row[$colEmail]) ? trim((string) $row[$colEmail]) : '';
            $kelasNama = isset($row[$colKelas]) ? trim((string) $row[$colKelas]) : '';
            $jk = ($colJk !== -1 && isset($row[$colJk])) ? strtoupper(trim((string) $row[$colJk])) : '';

            $rawEntries[] = [
                'row_num' => $i + 1,
                'nis' => $nis,
                'nisn' => $nisn,
                'nama' => $nama,
                'email' => $email,
                'kelas_nama' => $kelasNama,
                'jk' => $jk,
            ];
        }

        // Hitung frekuensi internal di dalam file untuk deteksi duplikasi internal
        $countsNis = [];
        $countsNisn = [];
        $countsEmail = [];

        foreach ($rawEntries as $entry) {
            $n = $entry['nis'];
            $nn = $entry['nisn'];
            $em = strtolower($entry['email']);

            $countsNis[$n] = ($countsNis[$n] ?? 0) + 1;
            if ($nn !== '') {
                $countsNisn[$nn] = ($countsNisn[$nn] ?? 0) + 1;
            }
            if ($em !== '') {
                $countsEmail[$em] = ($countsEmail[$em] ?? 0) + 1;
            }
        }

        $items = [];
        foreach ($rawEntries as $entry) {
            $nis = $entry['nis'];
            $nisn = $entry['nisn'];
            $nama = $entry['nama'];
            $email = $entry['email'];
            $emailLower = strtolower($email);
            $kelasNama = $entry['kelas_nama'];
            $jk = $entry['jk'];

            $errors = [];

            // 1. Validasi identifier dasar
            if (! preg_match('/\A[A-Za-z0-9_-]{1,20}\z/D', $nis)) {
                $errors[] = 'format_nis_tidak_valid';
            }

            // 2. Validasi NISN (wajib 10 digit angka)
            if (! preg_match('/\A[0-9]{10}\z/D', $nisn)) {
                $errors[] = 'format_nisn_tidak_valid';
            }

            // 3. Validasi Email
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'format_email_tidak_valid';
            }

            // 4. Validasi Nama Lengkap
            if ($nama === '' || mb_strlen($nama) > 255) {
                $errors[] = 'nama_lengkap_tidak_valid';
            }

            // 5. Validasi Jenis Kelamin (opsional, jika diisi harus L atau P)
            if ($jk !== '' && ! in_array($jk, ['L', 'P'], true)) {
                $errors[] = 'jenis_kelamin_tidak_valid';
            }

            // 6. Deteksi duplikasi internal di dalam file
            if (($countsNis[$nis] ?? 0) > 1) {
                $errors[] = 'nis_duplikat_internal';
            }
            if ($nisn !== '' && ($countsNisn[$nisn] ?? 0) > 1) {
                $errors[] = 'nisn_duplikat_internal';
            }
            if ($emailLower !== '' && ($countsEmail[$emailLower] ?? 0) > 1) {
                $errors[] = 'email_duplikat_internal';
            }

            // 7. Deteksi duplikasi terhadap database yang sudah ada
            if (isset($existingSiswaNis[$nis]) || isset($existingUserNis[$nis])) {
                $errors[] = 'nis_sudah_terdaftar';
            }
            if ($nisn !== '' && isset($existingSiswaNisn[$nisn])) {
                $errors[] = 'nisn_sudah_terdaftar';
            }
            if ($emailLower !== '' && isset($existingUserEmails[$emailLower])) {
                $errors[] = 'email_sudah_terdaftar';
            }

            // 8. Resolusi rombel kelas X tujuan
            $targetClassId = null;
            $matchedClasses = $availableClasses->filter(function ($c) use ($kelasNama) {
                return strcasecmp($c->nama_kelas, $kelasNama) === 0;
            });

            if ($matchedClasses->count() === 1) {
                $targetClass = $matchedClasses->first();
                if ($targetClass->tingkat !== 'X') {
                    $errors[] = 'kelas_tujuan_bukan_tingkat_x';
                } else {
                    $targetClassId = $targetClass->id;
                }
            } elseif ($matchedClasses->count() > 1) {
                $errors[] = 'kelas_tujuan_ambigu';
            } else {
                $errors[] = 'kelas_tujuan_tidak_ditemukan';
            }

            // 9. Status pengecualian manual (exclusion)
            $isExcluded = false;
            $exclusionReason = '';
            if (isset($manualExclusions[$nis])) {
                $isExcluded = (bool) ($manualExclusions[$nis]['excluded'] ?? false);
                $exclusionReason = trim((string) ($manualExclusions[$nis]['exclusion_reason'] ?? ''));

                if ($isExcluded && $exclusionReason === '') {
                    $errors[] = 'alasan_pengecualian_wajib';
                }
            }

            $items[] = [
                'student_ref' => $nis,
                'nisn' => $nisn,
                'nama' => $nama,
                'email' => $email,
                'jenis_kelamin' => in_array($jk, ['L', 'P'], true) ? $jk : null,
                'target_class_id' => $targetClassId,
                'kelas_nama_input' => $kelasNama,
                'excluded' => $isExcluded,
                'exclusion_reason' => $exclusionReason,
                'exclusion_reason_present' => ($isExcluded && $exclusionReason !== ''),
                'errors' => array_values(array_unique($errors)),
            ];
        }

        $countValid = 0;
        $countMasalah = 0;
        $countExcluded = 0;

        foreach ($items as $item) {
            if ($item['excluded']) {
                $countExcluded++;
            } elseif (! empty($item['errors'])) {
                $countMasalah++;
            } else {
                $countValid++;
            }
        }

        return [
            'file_hash' => $contentHash,
            'target_year' => $targetYear,
            'items' => $items,
            'summary' => [
                'total_baris' => count($items),
                'siap_proses' => $countValid,
                'masalah' => $countMasalah,
                'dikecualikan' => $countExcluded,
            ],
        ];
    }
}
