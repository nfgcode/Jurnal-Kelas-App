<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads a .xlsx (OOXML SpreadsheetML) worksheet back into plain rows, without
 * any third-party library — the mirror image of {@see XlsxExport}. An xlsx is a
 * ZIP of XML parts, so PHP's built-in ZipArchive and SimpleXML are enough.
 *
 * Only what an import template needs is supported: the first worksheet, cell
 * values as strings, shared strings and inline strings. Formulas are read as
 * their last cached value, which is what a spreadsheet stores anyway.
 *
 * .csv is accepted too, because "save as CSV" is what half of the people
 * filling in a template will actually do.
 */
class XlsxReader
{
    /** Refuse absurd files early rather than exhausting memory on them. */
    private const MAX_BARIS = 5000;

    /**
     * Every row of the first sheet, as arrays of trimmed strings. Blank cells
     * are preserved as empty strings so column positions stay aligned, and
     * entirely empty rows are dropped (a template usually has a few).
     *
     * @return array<int, array<int, string>>
     */
    public static function baca(string $path): array
    {
        $rows = str_ends_with(strtolower($path), '.csv')
            ? self::bacaCsv($path)
            : self::bacaXlsx($path);

        return array_values(array_filter(
            $rows,
            fn (array $row) => implode('', array_map('trim', $row)) !== ''
        ));
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function bacaCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Berkas CSV tidak dapat dibuka.');
        }

        // Excel on an Indonesian/European locale writes semicolon-separated CSV;
        // sniffing the header line keeps both dialects working.
        $baris = (string) fgets($handle);
        $pemisah = substr_count($baris, ';') > substr_count($baris, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];

        while (($data = fgetcsv($handle, 0, $pemisah)) !== false && count($rows) < self::MAX_BARIS) {
            $rows[] = array_map(fn ($v) => trim((string) $v), $data);
        }

        fclose($handle);

        // Strip a UTF-8 BOM off the very first cell, or the first header never
        // matches the column name it plainly shows.
        if (isset($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]) ?? $rows[0][0];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function bacaXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Berkas Excel tidak dapat dibuka. Pastikan formatnya .xlsx.');
        }

        try {
            $shared = self::sharedStrings($zip);
            $sheetXml = $zip->getFromName(self::sheetPertama($zip));

            if ($sheetXml === false) {
                throw new RuntimeException('Berkas Excel tidak memiliki lembar kerja yang dapat dibaca.');
            }

            return self::baris($sheetXml, $shared);
        } finally {
            $zip->close();
        }
    }

    /**
     * The workbook's shared string table — where Excel actually puts most text.
     *
     * @return array<int, string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $doc = self::xml($xml);
        $hasil = [];

        foreach ($doc->si as $si) {
            // A run-formatted cell splits its text across <r><t> children, so the
            // pieces are concatenated rather than only the first one taken.
            $teks = '';

            foreach ($si->xpath('.//*[local-name()="t"]') ?: [] as $t) {
                $teks .= (string) $t;
            }

            $hasil[] = $teks;
        }

        return $hasil;
    }

    /**
     * The part name of the first worksheet. Read from the workbook rels rather
     * than assumed to be sheet1.xml, which is only true for files this app wrote.
     */
    private static function sheetPertama(ZipArchive $zip): string
    {
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $workbook = $zip->getFromName('xl/workbook.xml');

        if ($rels === false || $workbook === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $wb = self::xml($workbook);
        $sheet = $wb->sheets->sheet[0] ?? null;
        $rid = $sheet ? (string) $sheet->attributes('r', true)->id : '';

        foreach (self::xml($rels)->Relationship as $rel) {
            if ((string) $rel['Id'] === $rid) {
                $target = ltrim((string) $rel['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * Cell values row by row, positioned by each cell's column letter so a gap
     * in the middle of a row does not shift everything after it left.
     *
     * @param  array<int, string>  $shared
     * @return array<int, array<int, string>>
     */
    private static function baris(string $sheetXml, array $shared): array
    {
        $doc = self::xml($sheetXml);
        $rows = [];

        foreach ($doc->sheetData->row as $row) {
            $cells = [];
            $lebar = 0;

            foreach ($row->c as $c) {
                $kolom = self::indeksKolom((string) $c['r']);
                $cells[$kolom] = self::nilai($c, $shared);
                $lebar = max($lebar, $kolom + 1);
            }

            // Fill the holes so every row is a dense list the caller can index.
            $rows[] = array_map(
                fn ($i) => $cells[$i] ?? '',
                range(0, max(0, $lebar - 1))
            );

            if (count($rows) >= self::MAX_BARIS) {
                break;
            }
        }

        return $rows;
    }

    /**
     * One cell's text. `t="s"` points into the shared table, `t="inlineStr"`
     * carries its own <is><t>, everything else is the literal <v>.
     *
     * @param  array<int, string>  $shared
     */
    private static function nilai(SimpleXMLElement $c, array $shared): string
    {
        $tipe = (string) $c['t'];

        if ($tipe === 's') {
            return trim($shared[(int) $c->v] ?? '');
        }

        if ($tipe === 'inlineStr') {
            $teks = '';

            foreach ($c->xpath('.//*[local-name()="t"]') ?: [] as $t) {
                $teks .= (string) $t;
            }

            return trim($teks);
        }

        return trim((string) $c->v);
    }

    /** "BC12" -> 54. The row number is ignored; only the letters matter. */
    private static function indeksKolom(string $ref): int
    {
        $huruf = preg_replace('/[^A-Z]/', '', strtoupper($ref)) ?? '';
        $indeks = 0;

        for ($i = 0; $i < strlen($huruf); $i++) {
            $indeks = $indeks * 26 + (ord($huruf[$i]) - 64);
        }

        return max(0, $indeks - 1);
    }

    /**
     * Parse a part with network access off and entity substitution left at its
     * default (disabled). An uploaded workbook is untrusted input, and XML
     * external entities are how a spreadsheet gets to read /etc/passwd.
     */
    private static function xml(string $raw): SimpleXMLElement
    {
        $doc = simplexml_load_string($raw, SimpleXMLElement::class, LIBXML_NONET);

        if ($doc === false) {
            throw new RuntimeException('Isi berkas Excel tidak dapat dibaca.');
        }

        return $doc;
    }
}
