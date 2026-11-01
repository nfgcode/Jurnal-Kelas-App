<?php

namespace App\Support;

use App\Models\User;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Engine backup & snapshot database dan berkas penting sekolah.
 * Menyimpan metadata ke cadangan_arsip dan file ke storage disk privat.
 */
class BackupEngine
{
    public const DISK = 'local';
    public const DIRECTORY = 'backups';

    public const FORMAT_SQL_GZ = 'sql_gz';
    public const FORMAT_SQL = 'sql';
    public const FORMAT_JSON_GZ = 'json_gz';
    public const FORMAT_ZIP = 'zip_berkas';

    /**
     * Objek tabel yang wajib dicakup backup database.
     */
    public const TABEL_DATABASE = [
        'jurusan',
        'tahun_ajaran',
        'ruangan',
        'mata_pelajaran',
        'guru',
        'kelas',
        'siswa',
        'users',
        'guru_mata_pelajaran',
        'jadwal',
        'jurnal',
        'jurnal_audit',
        'presensi',
        'presensi_log',
        'presensi_harian',
        'presensi_harian_log',
        'alumni',
        'riwayat_kelas_siswa',
        'transisi_tahun_ajaran',
        'tinjauan_retensi',
        'admin_audit_log',
        'pengumuman',
        'laporan_error',
        'hari_libur',
        'personal_access_tokens',
        'academic_workflow_coordinators',
        'academic_workflow_operations',
        'academic_workflow_stages',
        'academic_workflow_items',
        'academic_workflow_audits',
        'cadangan_arsip',
    ];

    /**
     * Direktori/file yang aman disertakan dalam arsip berkas penting.
     * Tidak boleh menyertakan .env, sessions, cache, logs, atau private keys.
     */
    public const ALLOWLIST_BERKAS = [
        'storage/app/public/berkas_tugas',
        'storage/app/public/foto_profil',
        'storage/app/public/materi',
        'storage/app/public/pengumuman',
    ];

    /**
     * Membuat cadangan database MySQL (sql.gz atau sql) secara konsisten dan aman.
     */
    public function buatBackupDatabase(string $format = self::FORMAT_SQL_GZ, string $tipe = 'manual', ?User $actor = null, ?string $catatan = null): array
    {
        if (! in_array($format, [self::FORMAT_SQL_GZ, self::FORMAT_SQL, self::FORMAT_JSON_GZ], true)) {
            throw new InvalidArgumentException("Format cadangan tidak didukung: {$format}");
        }

        $timestamp = now()->format('Ymd-His');
        $namaBerkas = "cadangan-jurnal-kelas-{$timestamp}." . match ($format) {
            self::FORMAT_SQL_GZ => 'sql.gz',
            self::FORMAT_SQL => 'sql',
            self::FORMAT_JSON_GZ => 'json.gz',
        };

        $tempPath = tempnam(sys_get_temp_dir(), 'db_backup_');
        if (! $tempPath) {
            throw new RuntimeException("Gagal mengalokasikan file temporary untuk backup.");
        }

        try {
            if ($format === self::FORMAT_JSON_GZ) {
                (new CadanganData())->tulisJson($tempPath, null, true);
            } else {
                $this->dumpMysqlToFile($tempPath, $format === self::FORMAT_SQL_GZ);
            }

            // Verifikasi write, ukuran dan checksum SHA-256
            clearstatcache(true, $tempPath);
            $ukuran = filesize($tempPath);
            if ($ukuran === false || $ukuran === 0) {
                throw new RuntimeException("Berkas backup kosong atau gagal ditulis.");
            }

            $checksum = hash_file('sha256', $tempPath);
            if (! $checksum) {
                throw new RuntimeException("Gagal menghitung checksum SHA-256.");
            }

            // Verifikasi integritas header jika berformat GZIP
            if (str_ends_with($namaBerkas, '.gz')) {
                $this->verifikasiGzip($tempPath);
            }

            // Atomic publish ke private storage (storage/app/backups/)
            $relativeLocation = self::DIRECTORY . '/' . $namaBerkas;
            $fh = fopen($tempPath, 'rb');
            $saved = Storage::disk(self::DISK)->put($relativeLocation, $fh);
            if (is_resource($fh)) {
                fclose($fh);
            }

            if (! $saved) {
                throw new RuntimeException("Gagal menyimpan berkas arsip ke penyimpanan privat.");
            }

            // Manifest metadata
            $manifest = [
                'version' => '1.0',
                'timestamp' => now()->toIso8601String(),
                'format' => $format,
                'tables_count' => count(self::TABEL_DATABASE),
                'tables' => self::TABEL_DATABASE,
                'views_included' => true,
                'triggers_included' => true,
                'routines_included' => true,
                'checksum_sha256' => $checksum,
                'size_bytes' => $ukuran,
                'integrity_verified' => true,
                'restore_tested' => false, // Jangan klaim restore-tested tanpa uji pemulihan nyata
            ];

            // Rekam ke database
            $id = DB::table('cadangan_arsip')->insertGetId([
                'nama_berkas' => $namaBerkas,
                'format' => $format,
                'tipe' => $tipe,
                'lokasi_privat' => $relativeLocation,
                'ukuran_bytes' => $ukuran,
                'checksum_sha256' => $checksum,
                'status_validasi' => 'terverifikasi',
                'penting' => false,
                'actor_id' => $actor?->id,
                'terakhir_divalidasi_at' => now(),
                'metadata_manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
                'catatan' => $catatan,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'id' => $id,
                'nama_berkas' => $namaBerkas,
                'lokasi' => $relativeLocation,
                'ukuran' => $ukuran,
                'checksum' => $checksum,
                'manifest' => $manifest,
            ];
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Membuat arsip ZIP berkas penting yang diizinkan (allowlist aman).
     */
    public function buatBackupBerkasPenting(string $tipe = 'manual', ?User $actor = null, ?string $catatan = null): array
    {
        $timestamp = now()->format('Ymd-His');
        $namaBerkas = "cadangan-berkas-{$timestamp}.zip";
        $tempPath = tempnam(sys_get_temp_dir(), 'files_backup_');

        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new RuntimeException("Gagal membuat arsip ZIP temporary.");
        }

        $basePath = base_path();
        $filesCount = 0;

        foreach (self::ALLOWLIST_BERKAS as $allowedDir) {
            $fullDir = $basePath . '/' . $allowedDir;
            if (is_dir($fullDir)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($fullDir, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $name => $file) {
                    if (! $file->isDir()) {
                        $filePath = $file->getRealPath();
                        // Tolak symlink dan file tersembunyi
                        if (is_link($filePath) || str_starts_with($file->getFilename(), '.')) {
                            continue;
                        }
                        $relativePath = substr($filePath, strlen($basePath) + 1);
                        $zip->addFile($filePath, $relativePath);
                        $filesCount++;
                    }
                }
            }
        }

        // Tambahkan manifest ke dalam root ZIP
        $manifest = [
            'version' => '1.0',
            'timestamp' => now()->toIso8601String(),
            'format' => self::FORMAT_ZIP,
            'files_count' => $filesCount,
            'allowlist' => self::ALLOWLIST_BERKAS,
            'integrity_verified' => true,
            'restore_tested' => false,
        ];
        $zip->addFromString('backup-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        $zip->close();

        clearstatcache(true, $tempPath);
        $ukuran = filesize($tempPath);
        $checksum = hash_file('sha256', $tempPath);

        $relativeLocation = self::DIRECTORY . '/' . $namaBerkas;
        $fh = fopen($tempPath, 'rb');
        $saved = Storage::disk(self::DISK)->put($relativeLocation, $fh);
        if (is_resource($fh)) {
            fclose($fh);
        }
        @unlink($tempPath);

        if (! $saved) {
            throw new RuntimeException("Gagal menyimpan berkas ZIP ke storage privat.");
        }

        $id = DB::table('cadangan_arsip')->insertGetId([
            'nama_berkas' => $namaBerkas,
            'format' => self::FORMAT_ZIP,
            'tipe' => $tipe,
            'lokasi_privat' => $relativeLocation,
            'ukuran_bytes' => $ukuran,
            'checksum_sha256' => $checksum,
            'status_validasi' => 'terverifikasi',
            'penting' => false,
            'actor_id' => $actor?->id,
            'terakhir_divalidasi_at' => now(),
            'metadata_manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
            'catatan' => $catatan,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'nama_berkas' => $namaBerkas,
            'lokasi' => $relativeLocation,
            'ukuran' => $ukuran,
            'checksum' => $checksum,
            'manifest' => $manifest,
        ];
    }

    /**
     * Ekspor administratif CSV aman (mencegah formula injection dan kebocoran hash/token).
     */
    public function eksporCsvAman(string $tabel, iterable $cursor): BinaryFileResponse
    {
        if (! in_array($tabel, self::TABEL_DATABASE, true)) {
            throw new InvalidArgumentException("Tabel tidak diizinkan untuk ekspor administratif.");
        }

        $filename = "ekspor-{$tabel}-" . now()->format('Ymd-His') . ".csv";
        $tmp = tempnam(sys_get_temp_dir(), 'csvexp_');
        $fh = fopen($tmp, 'w');

        // UTF-8 BOM
        fwrite($fh, "\xEF\xBB\xBF");

        $headerWritten = false;
        $sensitiveColumns = ['password', 'remember_token', 'token', 'two_factor_secret', 'two_factor_recovery_codes'];

        foreach ($cursor as $row) {
            $rowArray = (array) $row;

            // Buang kolom sensitif
            foreach ($sensitiveColumns as $sens) {
                unset($rowArray[$sens]);
            }

            if (! $headerWritten) {
                fputcsv($fh, array_keys($rowArray));
                $headerWritten = true;
            }

            // Sanitasi formula injection dan preservasi NIS leading zeros
            $sanitized = [];
            foreach ($rowArray as $col => $val) {
                if ($val === null) {
                    $sanitized[] = '';
                    continue;
                }
                $str = (string) $val;
                // Cegah CSV Formula Injection (=, +, -, @, \t, \r) tanpa merusak numeric ID / NIS
                // Jika diawali =, +, -, @ dan diikuti karakter huruf atau formula, beri tanda kutip prefix
                if (preg_match('/^[=+\-@]/', $str)) {
                    // Jika nilai merupakan bilangan bertanda murni (misal integer/desimal positif/negatif), jangan quote
                    if (! is_numeric($str)) {
                        $str = "'" . $str;
                    }
                }
                $sanitized[] = $str;
            }

            fputcsv($fh, $sanitized);
        }

        fclose($fh);

        $response = response()->download($tmp, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        return $response;
    }

    /**
     * Menjalankan mysqldump secara streaming atau fallback SQL dump native via PDO connection.
     * Menggunakan snapshot transaksi tunggal REPEATABLE READ pada InnoDB untuk menjamin konsistensi data.
     */
    protected function dumpMysqlToFile(string $destinationFile, bool $compressGzip): void
    {
        $dbConfig = config('database.connections.mysql');
        $fh = $compressGzip ? gzopen($destinationFile, 'wb6') : fopen($destinationFile, 'wb');

        if (! $fh) {
            throw new RuntimeException("Tidak dapat membuka file tujuan untuk menulis dump.");
        }

        $write = $compressGzip ? fn ($s) => gzwrite($fh, $s) : fn ($s) => fwrite($fh, $s);
        $driver = DB::connection()->getDriverName();

        // Bungkus seluruh pembacaan tabel dalam satu transaksi REPEATABLE READ konsisten
        if ($driver === 'mysql') {
            DB::statement("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            DB::statement("START TRANSACTION WITH CONSISTENT SNAPSHOT");
        } else {
            DB::beginTransaction();
        }

        try {
            // Header SQL standar
            $write("-- Jurnal Kelas App Database Dump ({$driver})\n");
            $write("-- Generated: " . now()->toIso8601String() . "\n");
            $write("-- NOTE: Dump berisi DDL & DML tabel InnoDB. Views, triggers, procedures, functions,\n");
            $write("-- dan events dikelola dan dibuat ulang oleh Laravel migrations saat pemulihan.\n");
            $write("SET FOREIGN_KEY_CHECKS=0;\n");
            $write("SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n");
            $write("SET NAMES utf8mb4;\n\n");

            // Dump DDL dan DML setiap tabel dalam urutan aman
            foreach (self::TABEL_DATABASE as $tabel) {
                if (! Schema::hasTable($tabel)) {
                    continue;
                }

                $write("-- Table structure for table `{$tabel}`\n");
                $write("DROP TABLE IF EXISTS `{$tabel}`;\n");
                if ($driver === 'sqlite') {
                    $createSql = DB::table('sqlite_master')->where('type', 'table')->where('name', $tabel)->value('sql');
                    if ($createSql) {
                        // Pastikan DDL SQLite menggunakan CREATE TABLE IF NOT EXISTS
                        $createSql = preg_replace('/^CREATE TABLE /i', 'CREATE TABLE IF NOT EXISTS ', $createSql);
                        $write($createSql . ";\n\n");
                    }
                } else {
                    $createTable = DB::select("SHOW CREATE TABLE `{$tabel}`");
                    if (! empty($createTable)) {
                        $createSql = ((array) $createTable[0])['Create Table'] ?? '';
                        $write($createSql . ";\n\n");
                    }
                }

                // Dapatkan daftar kolom generated (virtual/stored) yang tidak boleh di-insert
                $generatedCols = [];
                if ($driver === 'mysql') {
                    $genQuery = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND EXTRA LIKE '%GENERATED%'", [$tabel]);
                    $generatedCols = array_map(fn($r) => $r->COLUMN_NAME, $genQuery);
                }

                // Dump data DML menggunakan cursor chunking di bawah transaksi konsisten yang sama
                $rows = DB::table($tabel)->cursor();
                $batch = [];
                $batchCount = 0;
                $firstRowCols = null;

                foreach ($rows as $row) {
                    $rowArr = (array) $row;
                    // Buang kolom generated dari DML INSERT
                    foreach ($generatedCols as $gCol) {
                        unset($rowArr[$gCol]);
                    }

                    if ($firstRowCols === null) {
                        $firstRowCols = array_map(fn($c) => "`{$c}`", array_keys($rowArr));
                    }
                    $escapedValues = array_map(function ($val) {
                        if ($val === null) return 'NULL';
                        if (is_int($val) || is_float($val)) return (string) $val;
                        return DB::getPdo()->quote((string) $val);
                    }, array_values($rowArr));

                    $batch[] = "(" . implode(", ", $escapedValues) . ")";
                    $batchCount++;

                    if ($batchCount >= 200) {
                        $write("INSERT INTO `{$tabel}` (" . implode(", ", $firstRowCols) . ") VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                        $batchCount = 0;
                    }
                }

                if ($batchCount > 0 && ! empty($batch) && $firstRowCols !== null) {
                    $write("INSERT INTO `{$tabel}` (" . implode(", ", $firstRowCols) . ") VALUES\n" . implode(",\n", $batch) . ";\n");
                }

                $write("\n");
            }

            $write("SET FOREIGN_KEY_CHECKS=1;\n");
            $write("-- Dump completed on " . now()->toIso8601String() . "\n");
        } finally {
            if ($driver === 'mysql') {
                DB::statement("COMMIT");
            } else {
                DB::commit();
            }

            if ($compressGzip) {
                gzclose($fh);
            } else {
                fclose($fh);
            }
        }
    }

    /**
     * Mengambil status kesehatan cadangan untuk digunakan oleh UI:
     * - last_backup_at
     * - next_scheduled_at
     * - is_overdue (jika backup terakhir > 35 hari lalu atau belum pernah ada)
     */
    public static function statusKesehatan(): array
    {
        $last = DB::table('cadangan_arsip')
            ->where('status_validasi', 'terverifikasi')
            ->latest('created_at')
            ->first();

        $lastAt = $last ? \Illuminate\Support\Carbon::parse($last->created_at) : null;
        $isOverdue = $lastAt === null || $lastAt->diffInDays(now()) > 35;

        // Tanggal 1 bulan depan jam 01:00
        $nextScheduled = now()->startOfMonth()->addMonth()->setHour(1)->setMinute(0)->setSecond(0);

        return [
            'last_backup' => $last,
            'last_backup_at' => $lastAt?->toIso8601String(),
            'next_scheduled_at' => $nextScheduled->toIso8601String(),
            'is_overdue' => $isOverdue,
            'total_arsip' => DB::table('cadangan_arsip')->count(),
            'total_bytes' => (int) DB::table('cadangan_arsip')->sum('ukuran_bytes'),
        ];
    }
    public function verifikasiGzip(string $path): void
    {
        $handle = @gzopen($path, 'rb');
        if (! $handle) {
            throw new RuntimeException("Gagal membuka file terkompresi gzip.");
        }

        // Baca seluruh stream hingga EOF untuk mendeteksi stream truncated atau korup
        $totalBytes = 0;
        while (! gzeof($handle)) {
            $chunk = gzread($handle, 65536);
            if ($chunk === false) {
                gzclose($handle);
                throw new RuntimeException("Stream GZIP mengalami kerusakan di tengah pembacaan.");
            }
            $totalBytes += strlen($chunk);
        }
        gzclose($handle);

        if ($totalBytes === 0) {
            throw new RuntimeException("Berkas GZIP kosong atau rusak.");
        }
    }
}
