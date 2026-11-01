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
 *    archival format; legacy restore is disabled;
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
     * (users.kelas_id ↔ kelas.wali_kelas_nip) is why the restore also drops
     * foreign-key enforcement — no single order satisfies a circular reference.
     *
     * Deliberately excluded: framework/transient tables (cache, jobs, sessions,
     * migrations, password_reset_tokens) and the DB views (v_*). Also excluded is
     * jurnal_audit — it is maintained by AFTER UPDATE/DELETE triggers on jurnal,
     * so restoring it (and restoring jurnal over existing rows) would fight the
     * triggers; on a fresh server the triggers simply rebuild it as data changes.
     */
    private const TABEL = [
        // Ordered so a restore reads referenced tables before the ones that
        // point at them. The restore disables foreign keys anyway, but a file
        // that reads in dependency order is one a human can also apply by hand.
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
        // presensi is the live per-meeting roster the teachers mark;
        // presensi_harian is the class-day rollup derived from it, kept in the
        // backup so a restore does not have to recompute a year of school days.
        // presensi_log is the pre-daily-roster audit trail, no longer written to
        // but not worth throwing away.
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
    ];

    /**
     * Tables worth reading in a spreadsheet. The per-meeting presensi (100k+
     * rows) stays JSON-only; the day-level rollup is one row per student per
     * school day, which is small enough and is what anyone opening the workbook
     * is actually looking for. The per-subject detail is on screen, per journal.
     */
    private const TABEL_XLSX = [
        'guru',
        'siswa',
        'users',
        'kelas',
        'ruangan',
        'jurusan',
        'mata_pelajaran',
        'jadwal',
        'jurnal',
        'presensi_harian',
        'pengumuman',
        'laporan_error',
        'hari_libur',
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
        $this->validasiPilihan($only);
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
            $this->urut(DB::table($tabel), $tabel)->chunk(2000, function ($rows) use ($tulis, &$barisPertama) {
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

    /** Legacy restore is hard-blocked in every environment pending Fase 7–8. */
    public function pulihkan(array $data, string $mode): array
    {
        // Legacy restore bypasses identity/lifecycle invariants. No runtime opt-in.
        throw new \RuntimeException('Pemulihan dinonaktifkan sampai pipeline staging Fase 7–8 tersedia.');
    }

    /**
     * The readable workbook: one sheet per master table, sans password hashes.
     * $only narrows it to the chosen tables (intersected with the readable set).
     *
     * @param  array<int, string>|null  $only
     */
    public function unduhXlsx(string $filename, ?array $only = null): BinaryFileResponse
    {
        $this->validasiPilihan($only);
        $pilih = $only !== null ? array_intersect(self::TABEL_XLSX, $only) : self::TABEL_XLSX;
        if ($pilih === []) {
            throw new InvalidArgumentException('Tidak ada tabel Excel yang dipilih.');
        }
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

        $response = XlsxExport::downloadWorkbook($filename, $sheets);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /** Lazily stream a table's rows as ordered value arrays for the sheet writer. */
    private function barisXlsx(string $tabel, array $cols): Generator
    {
        foreach ($this->urut(DB::table($tabel), $tabel)->cursor() as $row) {
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
    /**
     * Order a table by its own primary key. Not every table has an `id`: guru
     * and siswa are keyed by nip/nis, and orderBy('id') on them threw a 500 on
     * every backup download.
     */
    private function urut($query, string $tabel)
    {
        $primer = collect(Schema::getIndexes($tabel))->firstWhere('primary', true)['columns']
            ?? [Schema::getColumnListing($tabel)[0]];

        foreach ($primer as $kolom) {
            $query->orderBy($kolom);
        }

        return $query;
    }

    private function tabelDipilih(?array $only): array
    {
        $this->validasiPilihan($only);
        $dipilih = $only !== null ? array_intersect(self::TABEL, $only) : self::TABEL;

        return array_values(array_filter($dipilih, fn ($t) => Schema::hasTable($t)));
    }

    private function validasiPilihan(?array $only): void
    {
        if ($only !== null && ($only === [] || ! array_is_list($only)
            || count(array_filter($only, 'is_string')) !== count($only)
            || array_diff($only, self::TABEL) !== [] || count(array_unique($only)) !== count($only))) {
            throw new InvalidArgumentException('Pilihan tabel tidak valid.');
        }
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
