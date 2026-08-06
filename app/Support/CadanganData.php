<?php

namespace App\Support;

use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Full backup and restore of the app's data — so a school can move the app to a
 * new server, or recover after one fails, without shell access to the database.
 * Admin-only; every caller is behind the admin route group.
 *
 * Two shapes come out of here:
 *  - a single **JSON** file with every table, ids and relations intact — the
 *    restore format (round-trips exactly, both "merge" and "replace" modes);
 *  - a readable **XLSX** workbook (one sheet per master table) for people who
 *    just want to look at or edit the data in a spreadsheet.
 *
 * Everything is done with the query builder ({@see DB}), never Eloquent, so no
 * model events fire during a restore — a re-imported class must keep its stored
 * qr_token, not have booted() mint a new one.
 */
class CadanganData
{
    /**
     * All backed-up tables in dependency (parent → child) order, so a "replace"
     * restore can delete in reverse and insert forward. The users ↔ kelas cycle
     * (users.kelas_id ↔ kelas.wali_kelas_id) is why the restore also drops
     * foreign-key enforcement — no single order satisfies a circular reference.
     *
     * Deliberately excluded: framework/transient tables (cache, jobs, sessions,
     * migrations, password_reset_tokens) and the DB views (v_*). Also excluded is
     * jurnal_audit — it is maintained by AFTER UPDATE/DELETE triggers on jurnal,
     * so restoring it (and restoring jurnal over existing rows) would fight the
     * triggers; on a fresh server the triggers simply rebuild it as data changes.
     */
    private const TABEL = [
        'mata_pelajaran',
        'users',
        'kelas',
        'jadwal',
        'jurnal',
        'presensi',
        'presensi_log',
        'pengumuman',
        'laporan_error',
        'personal_access_tokens',
    ];

    /** Tables worth reading in a spreadsheet. presensi (100k+ rows) is JSON-only. */
    private const TABEL_XLSX = [
        'users',
        'kelas',
        'mata_pelajaran',
        'jadwal',
        'jurnal',
        'pengumuman',
        'laporan_error',
    ];

    /** Never written to the human-readable XLSX (the JSON backup still keeps them). */
    private const KOLOM_SENSITIF = ['password', 'remember_token', 'token'];

    /** The full set of backup-able tables — used by the controller to validate a picked subset. */
    public static function semuaTabel(): array
    {
        return self::TABEL;
    }

    /** Current row count per table — shown on the backup page. */
    public function ringkasan(): array
    {
        $out = [];

        foreach (self::TABEL as $tabel) {
            $out[$tabel] = Schema::hasTable($tabel) ? DB::table($tabel)->count() : 0;
        }

        return $out;
    }

    /**
     * Stream the database to a JSON file at $path. Streamed table-by-table and
     * chunked so the presensi table (100k+ rows) never sits in memory at once.
     * $only limits the export to a chosen subset of tables (null = every table).
     *
     * With $gzip the output is written through gzip as it goes — the file lands
     * ~20x smaller (18 MB → ~0.8 MB) with memory still bounded, and no second
     * pass to compress it. {@see pulihkan()} auto-detects gzip on the way back in.
     *
     * @param  array<int, string>|null  $only
     */
    public function tulisJson(string $path, ?array $only = null, bool $gzip = false): void
    {
        $fh = $gzip ? gzopen($path, 'wb6') : fopen($path, 'w');
        $tulis = $gzip
            ? fn (string $s) => gzwrite($fh, $s)
            : fn (string $s) => fwrite($fh, $s);

        $tulis('{"meta":'.$this->encode($this->meta($only)).',"tabel":{');

        $tabelPertama = true;

        foreach ($this->tabelDipilih($only) as $tabel) {
            $tulis(($tabelPertama ? '' : ',').$this->encode($tabel).':[');
            $tabelPertama = false;

            $barisPertama = true;
            DB::table($tabel)->orderBy('id')->chunk(2000, function ($rows) use ($tulis, &$barisPertama) {
                foreach ($rows as $row) {
                    $tulis(($barisPertama ? '' : ',').$this->encode($row));
                    $barisPertama = false;
                }
            });

            $tulis(']');
        }

        $tulis('}}');
        $gzip ? gzclose($fh) : fclose($fh);
    }

    /**
     * Restore from a decoded backup array. $mode is 'gabung' (upsert by id, keeps
     * everything else) or 'ganti' (wipe the covered tables, then reinsert the
     * snapshot). Only tables the file actually carries are touched, so a partial
     * file can never blank a table it does not mention.
     *
     * Foreign keys are disabled for the duration (the users ↔ kelas cycle, and so
     * a "replace" need not delete in perfect order) and the whole thing runs in a
     * transaction — a failure half-way leaves the data as it was.
     *
     * @return array<string, int> rows written per table
     */
    public function pulihkan(array $data, string $mode): array
    {
        $tabel = $data['tabel'] ?? null;

        if (! is_array($tabel)) {
            throw new InvalidArgumentException('Berkas cadangan tidak berisi bagian "tabel".');
        }

        if (! in_array($mode, ['gabung', 'ganti'], true)) {
            throw new InvalidArgumentException('Mode pemulihan tidak dikenal.');
        }

        // Known tables only, in dependency order; unknown keys in the file are ignored.
        $urut = array_values(array_filter(
            self::TABEL,
            fn ($t) => isset($tabel[$t]) && is_array($tabel[$t]) && Schema::hasTable($t)
        ));

        $hasil = [];

        // Disable FK checks OUTSIDE the transaction: on SQLite `PRAGMA foreign_keys`
        // is a no-op once a transaction is open, so the order here matters.
        Schema::withoutForeignKeyConstraints(function () use ($urut, $tabel, $mode, &$hasil) {
            DB::transaction(function () use ($urut, $tabel, $mode, &$hasil) {
                if ($mode === 'ganti') {
                    // DELETE, not TRUNCATE — TRUNCATE implicitly commits on MySQL and
                    // would break the surrounding transaction.
                    foreach (array_reverse($urut) as $tabelNama) {
                        DB::table($tabelNama)->delete();
                    }
                }

                foreach ($urut as $tabelNama) {
                    $hasil[$tabelNama] = $this->tulisTabel($tabelNama, $tabel[$tabelNama], $mode);
                }
            });
        });

        return $hasil;
    }

    /**
     * The readable workbook: one sheet per master table, sans password hashes.
     * $only narrows it to the chosen tables (intersected with the readable set).
     *
     * @param  array<int, string>|null  $only
     */
    public function unduhXlsx(string $filename, ?array $only = null): BinaryFileResponse
    {
        $pilih = $only ? array_intersect(self::TABEL_XLSX, $only) : self::TABEL_XLSX;
        $sheets = [];

        foreach ($pilih as $tabel) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            $cols = array_values(array_diff(Schema::getColumnListing($tabel), self::KOLOM_SENSITIF));

            $sheets[$tabel] = [
                'header' => $cols,
                'rows' => $this->barisXlsx($tabel, $cols),
            ];
        }

        return XlsxExport::downloadWorkbook($filename, $sheets);
    }

    /**
     * Write one table's rows in the requested mode.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function tulisTabel(string $tabel, array $rows, string $mode): int
    {
        if ($rows === []) {
            return 0;
        }

        $cols = array_keys((array) $rows[0]);
        $update = array_values(array_diff($cols, ['id']));
        $ditulis = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            $chunk = array_map(fn ($r) => (array) $r, $chunk);

            if ($mode === 'ganti') {
                DB::table($tabel)->insert($chunk);
            } elseif ($update === []) {
                DB::table($tabel)->insertOrIgnore($chunk);
            } else {
                DB::table($tabel)->upsert($chunk, ['id'], $update);
            }

            $ditulis += count($chunk);
        }

        return $ditulis;
    }

    /** Lazily stream a table's rows as ordered value arrays for the sheet writer. */
    private function barisXlsx(string $tabel, array $cols): Generator
    {
        foreach (DB::table($tabel)->orderBy('id')->cursor() as $row) {
            $r = (array) $row;

            yield array_map(fn ($c) => $r[$c] ?? null, $cols);
        }
    }

    /**
     * The requested tables, filtered to the known set (order preserved) and to
     * those that actually exist. null means every table.
     *
     * @param  array<int, string>|null  $only
     * @return array<int, string>
     */
    private function tabelDipilih(?array $only): array
    {
        $dipilih = $only ? array_intersect(self::TABEL, $only) : self::TABEL;

        return array_values(array_filter($dipilih, fn ($t) => Schema::hasTable($t)));
    }

    private function meta(?array $only = null): array
    {
        $tabel = $this->tabelDipilih($only);
        $jumlah = [];

        foreach ($tabel as $t) {
            $jumlah[$t] = DB::table($t)->count();
        }

        return [
            'aplikasi' => 'Jurnal Kelas',
            'versi_format' => 1,
            'dibuat_pada' => now()->toIso8601String(),
            'driver' => DB::getDriverName(),
            'tabel' => $tabel,
            'jumlah' => $jumlah,
        ];
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
