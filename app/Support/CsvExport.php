<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a CSV download without buffering the whole file in memory — rows are
 * written to the output stream as they are yielded, so a report over the whole
 * ~110k-row attendance table costs one row of memory at a time. Admin-only; the
 * caller is responsible for authorization.
 */
class CsvExport
{
    /**
     * @param  array<int, string>  $header  column titles, first CSV line
     * @param  iterable<int, array<int, string|int|null>>  $rows  data rows
     */
    public static function stream(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');

            // BOM so Excel opens UTF-8 (Indonesian names) correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
