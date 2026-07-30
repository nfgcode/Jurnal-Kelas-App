<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Reads the tail of storage/logs/laravel.log into structured entries for the admin
 * log viewer. Only the last {@see BATAS_BYTE} bytes are read — the file grows into
 * megabytes and must never be loaded whole into memory to render a page.
 */
class PembacaLog
{
    /** Read at most this much from the end of the log (256 KB). */
    private const BATAS_BYTE = 262144;

    /** Laravel's line prefix: "[2026-07-30 03:19:48] local.ERROR: message". */
    private const POLA = '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\]\s+(\S+?)\.(\w+):\s*(.*)$/m';

    /**
     * Everything the log viewer needs, from a single read and parse: the entries
     * (filtered and capped) plus the levels present in the tail for the filter
     * dropdown, and the file's own stats.
     *
     * Deriving the level list from this same parse matters — asking for it
     * separately used to re-read and re-parse the whole 256KB tail a second time
     * on every page load.
     *
     * @param  string|null  $level  keep only this level (ERROR, WARNING, …)
     * @return array{entri: array<int, array<string, string>>, level: array<int, string>, ukuran: int, terpotong: bool}
     */
    public static function ringkasan(?string $level = null, int $maksEntri = 60): array
    {
        $berkas = storage_path('logs/laravel.log');

        if (! is_file($berkas) || filesize($berkas) === 0) {
            return ['entri' => [], 'level' => [], 'ukuran' => 0, 'terpotong' => false];
        }

        $ukuran = filesize($berkas);
        $terpotong = $ukuran > self::BATAS_BYTE;

        $isi = self::bacaEkor($berkas, $ukuran);

        // A truncated read can start mid-entry; drop everything before the first
        // complete prefix so the first card is not a fragment.
        if ($terpotong && preg_match(self::POLA, $isi, $m, PREG_OFFSET_CAPTURE)) {
            $isi = substr($isi, $m[0][1]);
        }

        // Parse once, unfiltered: the dropdown must offer every level present,
        // not just the one currently selected.
        $semua = self::pisah($isi);

        $terpilih = $level === null
            ? $semua
            : array_values(array_filter($semua, fn ($e) => $e['level'] === strtoupper($level)));

        return [
            'entri' => array_slice($terpilih, 0, $maksEntri),
            'level' => collect($semua)->pluck('level')->unique()->sort()->values()->all(),
            'ukuran' => $ukuran,
            'terpotong' => $terpotong,
        ];
    }

    private static function bacaEkor(string $berkas, int $ukuran): string
    {
        $handle = fopen($berkas, 'rb');

        if ($handle === false) {
            return '';
        }

        if ($ukuran > self::BATAS_BYTE) {
            fseek($handle, -self::BATAS_BYTE, SEEK_END);
        }

        $isi = stream_get_contents($handle);
        fclose($handle);

        return $isi === false ? '' : $isi;
    }

    /**
     * Split the raw tail into entries, newest first: each match of the prefix
     * starts a new one, and everything up to the next prefix is its body (stack
     * traces included).
     *
     * @return array<int, array<string, string>>
     */
    private static function pisah(string $isi): array
    {
        preg_match_all(self::POLA, $isi, $cocok, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $entri = [];
        $jumlah = count($cocok);

        for ($i = 0; $i < $jumlah; $i++) {
            $awal = $cocok[$i][0][1];
            $akhir = $i + 1 < $jumlah ? $cocok[$i + 1][0][1] : strlen($isi);

            $badan = trim(substr($isi, $awal, $akhir - $awal));

            $entri[] = [
                'waktu' => $cocok[$i][1][0],
                'lingkungan' => $cocok[$i][2][0],
                'level' => strtoupper($cocok[$i][3][0]),
                'pesan' => Str::limit(trim($cocok[$i][4][0]), 220),
                // The full entry, for the expandable detail.
                'lengkap' => Str::limit($badan, 4000),
            ];
        }

        // The log grows downward, so the newest entry is the last one parsed.
        return array_reverse($entri);
    }

    /**
     * The tone a level gets in the viewer.
     */
    public static function tone(string $level): string
    {
        return match (strtoupper($level)) {
            'EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR' => 'red',
            'WARNING' => 'yellow',
            'NOTICE', 'INFO' => 'green',
            default => 'neutral',
        };
    }
}
