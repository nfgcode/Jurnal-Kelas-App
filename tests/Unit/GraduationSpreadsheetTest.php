<?php

namespace Tests\Unit;

use App\AcademicWorkflows\GraduationSpreadsheet;
use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class GraduationSpreadsheetTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
    }

    private function setupData(): array
    {
        $source = TahunAjaran::firstOrCreate(
            ['kode' => '2026/2027'],
            ['aktif' => true, 'mulai' => '2026-07-01', 'selesai' => '2027-06-30']
        );
        $source->update(['mulai' => '2026-07-01', 'selesai' => '2027-06-30', 'aktif' => true]);

        $target = TahunAjaran::firstOrCreate(
            ['kode' => '2027/2028'],
            ['aktif' => false, 'mulai' => '2027-07-01', 'selesai' => '2028-06-30']
        );
        $target->update(['mulai' => '2027-07-01', 'selesai' => '2028-06-30', 'aktif' => false]);

        $c12 = $this->buatKelas(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $c11 = $this->buatKelas(['nama_kelas' => 'XI IPA 1', 'tingkat' => 'XI', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $targetC12 = $this->buatKelas(['nama_kelas' => 'XII IPA 1 (27/28)', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        $s1 = Siswa::factory()->create(['nis' => '00121', 'nama' => 'Siswa Satu', 'kelas_id' => $c12->id, 'status' => 'aktif']);
        $s2 = Siswa::factory()->create(['nis' => '00122', 'nama' => 'Siswa Dua', 'kelas_id' => $c12->id, 'status' => 'aktif']);
        $s3 = Siswa::factory()->create(['nis' => '00123', 'nama' => 'Siswa Tiga', 'kelas_id' => $c12->id, 'status' => 'aktif']);
        $sOther = Siswa::factory()->create(['nis' => '00199', 'nama' => 'Siswa Kelas Lain', 'kelas_id' => $c11->id, 'status' => 'aktif']);

        RiwayatKelasSiswa::daftarkan($s1, $c12, '2026/2027', '2026-07-01');
        RiwayatKelasSiswa::daftarkan($s2, $c12, '2026/2027', '2026-07-01');
        RiwayatKelasSiswa::daftarkan($s3, $c12, '2026/2027', '2026-07-01');
        RiwayatKelasSiswa::daftarkan($sOther, $c11, '2026/2027', '2026-07-01');

        return [$source, $target, $c12, $targetC12, [$s1, $s2, $s3, $sOther]];
    }

    public function test_parses_csv_preserving_leading_zeros_and_mapping_statuses(): void
    {
        [$source, $target, $c12, $targetC12] = $this->setupData();

        $csv = "NIS,Nama Siswa,Status Kelulusan\n";
        $csv .= "00121,Siswa Satu,LULUS\n";
        $csv .= "00122,Siswa Dua,TIDAK LULUS\n";
        $csv .= "00123,Siswa Tiga,BELUM TERVERIFIKASI\n";

        $tmp = tempnam(sys_get_temp_dir(), 'test_grad_') . '.csv';
        file_put_contents($tmp, $csv);

        $parsed = GraduationSpreadsheet::parse($tmp, '2026/2027', '2027/2028');
        @unlink($tmp);

        $this->assertCount(3, $parsed['items']);
        $this->assertSame(3, $parsed['summary']['total_roster']);
        $this->assertSame(1, $parsed['summary']['lulus']);
        $this->assertSame(1, $parsed['summary']['tidak_lulus']);
        $this->assertSame(1, $parsed['summary']['unverified']);
        $this->assertSame(0, $parsed['summary']['masalah']);

        $item2 = collect($parsed['items'])->firstWhere('student_ref', '00122');
        $this->assertSame('tidak_lulus', $item2['decision']);
        $this->assertSame($targetC12->id, $item2['target_class_id']);
    }

    public function test_detects_omitted_students_and_marks_them_unverified(): void
    {
        [$source, $target] = $this->setupData();

        // 00123 is omitted from the file
        $csv = "NIS,Nama Siswa,Status Kelulusan\n";
        $csv .= "00121,Siswa Satu,LULUS\n";
        $csv .= "00122,Siswa Dua,LULUS\n";

        $tmp = tempnam(sys_get_temp_dir(), 'test_grad_') . '.csv';
        file_put_contents($tmp, $csv);

        $parsed = GraduationSpreadsheet::parse($tmp, '2026/2027', '2027/2028');
        @unlink($tmp);

        $this->assertCount(3, $parsed['items']);
        $omitted = collect($parsed['items'])->firstWhere('student_ref', '00123');
        $this->assertNotNull($omitted);
        $this->assertSame('unverified', $omitted['decision']);
        $this->assertTrue($omitted['omitted']);
        $this->assertContains('siswa_tidak_tercantum', $omitted['errors']);
        $this->assertSame(1, $parsed['summary']['masalah']);
    }

    public function test_detects_duplicate_nis_unknown_nis_and_wrong_grade(): void
    {
        [$source, $target] = $this->setupData();

        $csv = "NIS,Nama Siswa,Status Kelulusan\n";
        $csv .= "00121,Siswa Satu,LULUS\n";
        $csv .= "00121,Siswa Satu Duplikat,LULUS\n"; // duplicate
        $csv .= "99999,Orang Asing,LULUS\n"; // unknown
        $csv .= "00199,Siswa Kelas XI,LULUS\n"; // wrong grade (XI)

        $tmp = tempnam(sys_get_temp_dir(), 'test_grad_') . '.csv';
        file_put_contents($tmp, $csv);

        $parsed = GraduationSpreadsheet::parse($tmp, '2026/2027', '2027/2028');
        @unlink($tmp);

        $items = collect($parsed['items']);
        $dup = $items->filter(fn($i) => in_array('nis_duplikat', $i['errors']));
        $this->assertNotEmpty($dup);

        $unknown = $items->firstWhere('student_ref', '99999');
        $this->assertNotNull($unknown);
        $this->assertContains('nis_tidak_dikenal', $unknown['errors']);

        $wrongGrade = $items->firstWhere('student_ref', '00199');
        $this->assertNotNull($wrongGrade);
        $this->assertContains('bukan_tingkat_xii', $wrongGrade['errors']);
    }

    public function test_generates_ai_prompt_with_privacy_warning(): void
    {
        $prompt = GraduationSpreadsheet::aiPrompt('2026/2027');
        $this->assertStringContainsString('2026/2027', $prompt);
        $this->assertStringContainsString('PERINGATAN PRIVASI', $prompt);
        $this->assertStringContainsString('NIS', $prompt);
        $this->assertStringContainsString('Status Kelulusan', $prompt);
        $this->assertStringContainsString('LULUS', $prompt);
        $this->assertStringContainsString('TIDAK LULUS', $prompt);
    }

    public function test_template_contains_canonical_headers(): void
    {
        $response = GraduationSpreadsheet::templateXlsx('2026/2027');
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
        $this->assertStringContainsString('template-kelulusan-xii-2026-2027.xlsx', (string) $response->headers->get('content-disposition'));
    }
}
