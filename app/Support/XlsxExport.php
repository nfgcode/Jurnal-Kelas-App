<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Writes a real .xlsx (OOXML SpreadsheetML) workbook without any third-party
 * library — an xlsx is just a ZIP of XML parts, which PHP's built-in
 * ZipArchive assembles here. The same tabular rows a CSV would hold become a
 * proper Excel file (bold header, numbers as numbers), so Excel opens it with
 * no "different format" warning. Admin-only; the caller authorizes.
 *
 * Reports here are one row per meeting (a few thousand at most), so the sheet
 * is built in memory rather than streamed.
 */
class XlsxExport
{
    /**
     * @param  array<int, string>  $header  column titles (bold first row)
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): BinaryFileResponse
    {
        $sheet = self::sheetXml($header, $rows);

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::CONTENT_TYPES);
        $zip->addFromString('_rels/.rels', self::RELS);
        $zip->addFromString('xl/workbook.xml', self::WORKBOOK);
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::WORKBOOK_RELS);
        $zip->addFromString('xl/styles.xml', self::STYLES);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return response()
            ->download($tmp, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * The worksheet part: the header row (style 1 = bold) then the data rows.
     *
     * @param  array<int, string>  $header
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    private static function sheetXml(array $header, iterable $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        $r = 1;
        $xml .= self::row($r, $header, true);

        foreach ($rows as $row) {
            $r++;
            $xml .= self::row($r, $row, false);
        }

        return $xml.'</sheetData></worksheet>';
    }

    /**
     * One <row>. Numeric PHP values are written as number cells; everything else
     * as inline strings (so a code like "007" keeps its zeros).
     *
     * @param  array<int, string|int|float|null>  $cells
     */
    private static function row(int $r, array $cells, bool $bold): string
    {
        $style = $bold ? ' s="1"' : '';
        $out = '<row r="'.$r.'">';

        $c = 0;
        foreach ($cells as $value) {
            $ref = self::colLetter($c++).$r;

            if (is_int($value) || is_float($value)) {
                $out .= '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>';
            } else {
                $out .= '<c r="'.$ref.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'
                    .self::esc((string) ($value ?? '')).'</t></is></c>';
            }
        }

        return $out.'</row>';
    }

    /** Zero-based column index to its spreadsheet letter (0→A, 26→AA). */
    private static function colLetter(int $index): string
    {
        $letter = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letter = chr($i % 26 + 65).$letter;
        }

        return $letter;
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private const CONTENT_TYPES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        .'</Types>';

    private const RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        .'</Relationships>';

    private const WORKBOOK = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="Laporan" sheetId="1" r:id="rId1"/></sheets></workbook>';

    private const WORKBOOK_RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        .'</Relationships>';

    // Two cell formats: 0 = default, 1 = bold (used by the header row).
    private const STYLES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        .'<borders count="1"><border/></borders>'
        .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
        .'</styleSheet>';
}
