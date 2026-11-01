<?php

namespace Tests\Unit;

use App\Support\XlsxReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

class XlsxReaderSafetyTest extends TestCase
{
    private function reject(string $xml, array $extra = [], bool $compressed = false): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->setCompressionName('xl/worksheets/sheet1.xml', $compressed ? ZipArchive::CM_DEFLATE : ZipArchive::CM_STORE);
        foreach ($extra as $name => $value) {
            $zip->addFromString($name, $value);
            $zip->setCompressionName($name, $compressed ? ZipArchive::CM_DEFLATE : ZipArchive::CM_STORE);
        }
        $zip->close();
        try {
            $this->expectException(RuntimeException::class);
            XlsxReader::baca($path);
        } finally {
            unlink($path);
        }
    }

    public function test_xml_part_size_is_bounded_even_when_uncompressed(): void
    {
        $this->reject('<worksheet/>', ['stored.xml' => str_repeat('x', 4 * 1024 * 1024 + 1)]);
    }

    public function test_xlsx_rows_are_rejected_not_truncated(): void
    {
        $this->reject('<worksheet><sheetData>'.str_repeat('<row><c r="A1"><v>x</v></c></row>', 5001).'</sheetData></worksheet>');
    }

    public function test_cell_text_is_bounded(): void
    {
        $this->reject('<worksheet><sheetData><row><c r="A1"><v>'.str_repeat('x', 32769).'</v></c></row></sheetData></worksheet>');
    }

    public function test_malformed_xml_has_only_generic_error(): void
    {
        $this->reject('<worksheet><secret>');
    }

    public function test_zip_ratio_is_bounded_before_inflation(): void
    {
        $this->reject('<worksheet/>', ['unused.xml' => str_repeat('a', 100000)], compressed: true);
    }

    public function test_zip_part_count_is_bounded(): void
    {
        $extra = [];
        for ($i = 0; $i < 129; $i++) {
            $extra['part'.$i] = 'x';
        }
        $this->reject('<worksheet/>', $extra);
    }

    public function test_dtd_is_rejected_without_parser_diagnostics(): void
    {
        $this->reject('<!DOCTYPE worksheet [<!ENTITY secret "private">]><worksheet/>');
    }

    public function test_csv_rows_are_rejected_not_silently_truncated(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
        file_put_contents($path, str_repeat("a,b\n", 5001));
        try {
            $this->expectException(RuntimeException::class);
            XlsxReader::baca($path);
        } finally {
            unlink($path);
        }
    }

    public function test_csv_columns_are_bounded(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
        file_put_contents($path, implode(',', array_fill(0, 65, 'x')));
        try {
            $this->expectException(RuntimeException::class);
            XlsxReader::baca($path);
        } finally {
            unlink($path);
        }
    }

    public function test_sparse_extreme_column_is_rejected_before_dense_allocation(): void
    {
        $this->reject('<worksheet><sheetData><row><c r="ZZ1"><v>1</v></c></row></sheetData></worksheet>');
    }
}
