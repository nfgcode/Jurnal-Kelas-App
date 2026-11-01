<?php

namespace App\Support;

use App\Models\User;
use DomainException;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Service validasi arsip dan pemulihan database staged terisolasi.
 * Menguji restorasi dump SQL/SQL.GZ hanya ke database staging/disposable terisolasi.
 * Restore produksi dan restore legacy tetap DITUTUP (DISABLED).
 */
class RestoreStagingEngine
{
    public const MAX_FILE_SIZE = 52428800; // 50 MB
    public const MAX_DECOMPRESSED_SIZE = 209715200; // 200 MB
    public const MAX_STATEMENTS = 25000;

    /**
     * Statement DDL/DML yang diizinkan dalam format dump BackupEngine.
     */
    public const STATEMENT_ALLOWLIST_PATTERNS = [
        '/^SET\s+(FOREIGN_KEY_CHECKS|SQL_MODE|NAMES)\s*=/i',
        '/^SET\s+NAMES\s+[a-zA-Z0-9_\-]+/i',
        '/^DROP\s+TABLE\s+IF\s+EXISTS\s+[`"][a-zA-Z0-9_]+[`"]/i',
        '/^CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?[`"][a-zA-Z0-9_]+[`"]/i',
        '/^INSERT\s+INTO\s+[`"][a-zA-Z0-9_]+[`"]\s*\([^\)]+\)\s*VALUES/i',
    ];

    /**
     * Pola berbahaya yang mutlak ditolak.
     */
    public const DANGEROUS_PATTERNS = [
        '/\b(CREATE|DROP|ALTER)\s+(DATABASE|SCHEMA|USER|ROLE)\b/i',
        '/\b(GRANT|REVOKE)\b/i',
        '/\b(SHUTDOWN|RELOAD|RESET|SUPER)\b/i',
        '/\b(INTO\s+OUTFILE|INTO\s+DUMPFILE|LOAD_FILE|LOAD\s+DATA)\b/i',
        '/\bINFORMATION_SCHEMA\b/i',
        '/\bMYSQL\./i',
        '/\bPERFORMANCE_SCHEMA\b/i',
        '/\bSYS\./i',
    ];

    /**
     * Validasi berkas cadangan (checksum SHA-256, ukuran, dekompresi GZIP, dan manifest).
     */
    public function validasiArsip(string $filePath, ?string $expectedSha256 = null, ?array $manifest = null): array
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("Berkas cadangan tidak ditemukan.");
        }

        clearstatcache(true, $filePath);
        $fileSize = filesize($filePath);
        if ($fileSize === 0 || $fileSize === false) {
            throw new DomainException("Berkas cadangan kosong (0 bytes).");
        }

        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new DomainException("Ukuran berkas melebihi batas maksimum 50 MB.");
        }

        $actualSha256 = hash_file('sha256', $filePath);
        if ($expectedSha256 !== null && ! hash_equals($expectedSha256, $actualSha256)) {
            throw new DomainException("Checksum SHA-256 tidak cocok. Berkas kemungkinan rusak atau termanipulasi.");
        }

        $isGzip = str_ends_with($filePath, '.gz');
        $decompressedBytes = 0;
        $statementCount = 0;

        // Validasi dekompresi dan integritas seluruh stream
        if ($isGzip) {
            $handle = @gzopen($filePath, 'rb');
            if (! $handle) {
                throw new DomainException("Berkas GZIP rusak atau tidak dapat dibuka.");
            }

            while (! gzeof($handle)) {
                $chunk = gzread($handle, 65536);
                if ($chunk === false) {
                    gzclose($handle);
                    throw new DomainException("Stream GZIP mengalami kerusakan di tengah pembacaan (truncated/corrupt).");
                }
                $decompressedBytes += strlen($chunk);
                if ($decompressedBytes > self::MAX_DECOMPRESSED_SIZE) {
                    gzclose($handle);
                    throw new DomainException("Ukuran dekompresi melebihi batas maksimum 200 MB (indikasi ZIP/GZ Bomb).");
                }
            }
            gzclose($handle);
        } else {
            $decompressedBytes = $fileSize;
        }

        // Validasi manifest jika tersedia
        if ($manifest !== null) {
            if (($manifest['format'] ?? '') !== 'sql_gz' && ($manifest['format'] ?? '') !== 'sql') {
                throw new DomainException("Format manifest tidak didukung untuk restorasi database.");
            }
        }

        return [
            'valid' => true,
            'file_size' => $fileSize,
            'decompressed_size' => $decompressedBytes,
            'sha256' => $actualSha256,
            'is_gzip' => $isGzip,
        ];
    }

    /**
     * Membaca stream SQL dan memvalidasi setiap pernyataan terhadap allowlist ketat.
     * Parser cerdas yang sadar konteks string literal ('...' dan "..."), escape karakter (\'),
     * komentar baris (-- ...), dan komentar blok (/* ... * /).
     */
    public function validasiStrukturSql(string $filePath): array
    {
        $isGzip = str_ends_with($filePath, '.gz');
        $handle = $isGzip ? @gzopen($filePath, 'rb') : @fopen($filePath, 'rb');

        if (! $handle) {
            throw new RuntimeException("Tidak dapat membaca berkas SQL.");
        }

        $statements = [];
        $currentStmt = '';
        $statementCount = 0;
        $tablesFound = [];

        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;
        $isEscaped = false;
        $inLineComment = false;
        $inBlockComment = false;

        $readChunk = $isGzip ? fn() => gzread($handle, 8192) : fn() => fread($handle, 8192);
        $isEof = $isGzip ? fn() => gzeof($handle) : fn() => feof($handle);

        try {
            while (! $isEof()) {
                $buffer = $readChunk();
                if ($buffer === false || $buffer === '') {
                    break;
                }

                $len = strlen($buffer);
                for ($i = 0; $i < $len; $i++) {
                    $char = $buffer[$i];
                    $nextChar = ($i + 1 < $len) ? $buffer[$i + 1] : '';

                    // 1. Penanganan komentar baris (-- ...)
                    if ($inLineComment) {
                        if ($char === "\n" || $char === "\r") {
                            $inLineComment = false;
                        }
                        continue;
                    }

                    // 2. Penanganan komentar blok (/* ... */)
                    if ($inBlockComment) {
                        if ($char === '*' && $nextChar === '/') {
                            $inBlockComment = false;
                            $i++; // lewati /
                        }
                        continue;
                    }

                    // 3. Deteksi awal komentar jika di luar string
                    if (! $inSingleQuote && ! $inDoubleQuote && ! $inBacktick) {
                        if ($char === '-' && $nextChar === '-') {
                            $inLineComment = true;
                            $i++;
                            continue;
                        }
                        if ($char === '/' && $nextChar === '*') {
                            $inBlockComment = true;
                            $i++;
                            continue;
                        }
                    }

                    // 4. Penanganan escape karakter (\)
                    if ($isEscaped) {
                        $currentStmt .= $char;
                        $isEscaped = false;
                        continue;
                    }

                    if ($char === '\\' && ($inSingleQuote || $inDoubleQuote)) {
                        $currentStmt .= $char;
                        $isEscaped = true;
                        continue;
                    }

                    // 5. Penanganan state string literal & quote
                    if ($char === "'" && ! $inDoubleQuote && ! $inBacktick) {
                        $inSingleQuote = ! $inSingleQuote;
                        $currentStmt .= $char;
                        continue;
                    }
                    if ($char === '"' && ! $inSingleQuote && ! $inBacktick) {
                        $inDoubleQuote = ! $inDoubleQuote;
                        $currentStmt .= $char;
                        continue;
                    }
                    if ($char === '`' && ! $inSingleQuote && ! $inDoubleQuote) {
                        $inBacktick = ! $inBacktick;
                        $currentStmt .= $char;
                        continue;
                    }

                    // 6. Deteksi delimiter statement (;) hanya jika di luar quote dan komentar
                    if ($char === ';' && ! $inSingleQuote && ! $inDoubleQuote && ! $inBacktick) {
                        $trimmed = trim($currentStmt);
                        $currentStmt = '';

                        if ($trimmed === '') {
                            continue;
                        }

                        $statementCount++;
                        if ($statementCount > self::MAX_STATEMENTS) {
                            throw new DomainException("Jumlah pernyataan SQL melebihi batas " . self::MAX_STATEMENTS . ".");
                        }

                        $this->periksaPernyataanAman($trimmed);

                        // Catat tabel yang dicakup
                        if (preg_match('/^(?:CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|DROP\s+TABLE\s+IF\s+EXISTS|INSERT\s+INTO)\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $trimmed, $m)) {
                            $tableName = strtolower($m[1]);
                            $tablesFound[$tableName] = true;
                        }

                        $statements[] = $trimmed;
                        continue;
                    }

                    $currentStmt .= $char;
                }
            }

            // Sisa buffer jika ada yang tidak diakhiri semicolon
            $finalTrim = trim($currentStmt);
            if ($finalTrim !== '') {
                $statementCount++;
                $this->periksaPernyataanAman($finalTrim);
                $statements[] = $finalTrim;
            }
        } finally {
            if ($isGzip) {
                @gzclose($handle);
            } else {
                @fclose($handle);
            }
        }

        return [
            'valid' => true,
            'statement_count' => count($statements),
            'tables_count' => count($tablesFound),
            'tables' => array_keys($tablesFound),
            'statements' => $statements,
        ];
    }

    /**
     * Memeriksa satu statement SQL terhadap pola berbahaya dan allowlist.
     */
    protected function periksaPernyataanAman(string $sql): void
    {
        // 1. Cek pola berbahaya mutlak
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $sql)) {
                throw new DomainException("Pernyataan SQL berbahaya terdeteksi dan ditolak: " . substr($sql, 0, 80));
            }
        }

        // 2. Cek apakah cocok dengan salah satu pola allowlist
        $allowed = false;
        foreach (self::STATEMENT_ALLOWLIST_PATTERNS as $pattern) {
            if (preg_match($pattern, $sql)) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            throw new DomainException("Pernyataan SQL di luar format dump yang diizinkan ditolak: " . substr($sql, 0, 80));
        }
    }

    /**
     * Menjalankan pemulihan staged hanya ke koneksi database disposable/staging yang diisolasi.
     * Tidak pernah dijalankan pada koneksi database pengguna.
     */
    public function pulihkanKeStagingTerisolasi(\Illuminate\Database\ConnectionInterface $stagingConnection, array $validatedStatements): array
    {
        $driver = $stagingConnection->getDriverName();
        $startTime = microtime(true);

        // Pastikan stagingConnection bukan koneksi default produksi jika di MySQL
        if ($driver === 'mysql') {
            $currentDb = $stagingConnection->select("SELECT DATABASE() as db")[0]->db ?? '';
            if ($currentDb === 'jurnal_kelas') {
                throw new DomainException("DITOLAK: Upaya restorasi mengarah ke database produksi 'jurnal_kelas'.");
            }
        }

        $executed = 0;
        // Pada MySQL, statement DDL (DROP TABLE, CREATE TABLE) otomatis memicu IMPLICIT COMMIT.
        // Oleh karena itu, kita tidak membungkus DDL dalam PDO transaction yang rapuh,
        // melainkan mengandalkan isolasi basis data disposable staging.
        if ($driver !== 'mysql') {
            $stagingConnection->beginTransaction();
        }

        try {
            foreach ($validatedStatements as $stmt) {
                // Di SQLite in-memory untuk testing, abaikan instruksi MySQL SET
                if ($driver === 'sqlite' && preg_match('/^SET\s+/i', $stmt)) {
                    continue;
                }
                $stagingConnection->unprepared($stmt);
                $executed++;
            }
            if ($driver !== 'mysql') {
                $stagingConnection->commit();
            }
        } catch (Throwable $e) {
            if ($driver !== 'mysql') {
                $stagingConnection->rollBack();
            }
            throw new RuntimeException("Gagal mengeksekusi dump pada database staging: " . $e->getMessage(), 0, $e);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        // Ambil daftar tabel yang berhasil terisi di staging
        $tables = [];
        if ($driver === 'sqlite') {
            $res = $stagingConnection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $tables = array_map(fn($r) => $r->name, $res);
        } else {
            $res = $stagingConnection->select("SHOW TABLES");
            $tables = array_map(fn($r) => array_values((array) $r)[0], $res);
        }

        return [
            'status' => 'berhasil_staging',
            'statements_executed' => $executed,
            'tables_created_count' => count($tables),
            'tables_created' => $tables,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Memverifikasi integritas relasional, foreign keys, views, dan menghasilkan laporan dampak read-only.
     */
    public function verifikasiIntegritasDanDampak(\Illuminate\Database\ConnectionInterface $stagingConnection, ?array $manifest = null): array
    {
        $driver = $stagingConnection->getDriverName();
        $report = [
            'status' => 'relationally_verified',
            'tables_checked' => 0,
            'tables_valid' => 0,
            'orphan_records' => [],
            'row_counts' => [],
            'warnings' => [],
            'errors' => [],
        ];

        // 1. Cek keberadaan 31 tabel database
        foreach (BackupEngine::TABEL_DATABASE as $tabel) {
            $report['tables_checked']++;
            $exists = false;
            if ($driver === 'sqlite') {
                $exists = ! empty($stagingConnection->select("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$tabel]));
            } else {
                $escapedTbl = addslashes($tabel);
                $exists = ! empty($stagingConnection->select("SHOW TABLES LIKE '{$escapedTbl}'"));
            }

            if (! $exists) {
                $report['errors'][] = "Tabel `{$tabel}` hilang dari basis data staging.";
                $report['status'] = 'integrity_failed';
            } else {
                $report['tables_valid']++;
                $count = (int) $stagingConnection->table($tabel)->count();
                $report['row_counts'][$tabel] = $count;
            }
        }

        // 2. Cek Orphan Records pada Relasi Kunci
        // Siswa tanpa User (relasi lewat nis)
        if (isset($report['row_counts']['siswa']) && isset($report['row_counts']['users'])) {
            $orphanSiswa = $stagingConnection->table('siswa')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))->from('users')->whereColumn('users.nis', 'siswa.nis');
                })->count();
            if ($orphanSiswa > 0) {
                $report['orphan_records']['siswa_without_user'] = $orphanSiswa;
                $report['warnings'][] = "Terdapat {$orphanSiswa} siswa tanpa akun login (belum diaktivasi).";
            }
        }

        // Guru tanpa User (relasi lewat nip)
        if (isset($report['row_counts']['guru']) && isset($report['row_counts']['users'])) {
            $orphanGuru = $stagingConnection->table('guru')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))->from('users')->whereColumn('users.nip', 'guru.nip');
                })->count();
            if ($orphanGuru > 0) {
                $report['orphan_records']['guru_without_user'] = $orphanGuru;
                $report['warnings'][] = "Terdapat {$orphanGuru} guru tanpa akun login.";
            }
        }

        // Riwayat kelas tanpa Siswa
        if (isset($report['row_counts']['riwayat_kelas_siswa']) && isset($report['row_counts']['siswa'])) {
            $orphanRiwayat = $stagingConnection->table('riwayat_kelas_siswa')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))->from('siswa')->whereColumn('siswa.nis', 'riwayat_kelas_siswa.siswa_nis');
                })->count();
            if ($orphanRiwayat > 0) {
                $report['orphan_records']['riwayat_without_siswa'] = $orphanRiwayat;
                $report['errors'][] = "Ditemukan {$orphanRiwayat} riwayat kelas dengan NIS tidak terdaftar.";
                $report['status'] = 'integrity_failed';
            }
        }

        return $report;
    }
}
