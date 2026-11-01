<?php

namespace Tests\Unit;

use App\AcademicWorkflows\PromotionSpreadsheet;
use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PromotionSpreadsheetTest extends TestCase
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

        $cX1 = $this->buatKelas(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $cXI1 = $this->buatKelas(['nama_kelas' => 'XI TKJ 1', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $cXII1 = $this->buatKelas(['nama_kelas' => 'XII TKJ 1', 'tingkat' => 'XII', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);

        // Target classes
        $targetX1 = $this->buatKelas(['nama_kelas' => 'X TKJ 1 (27/28)', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $targetXI1 = $this->buatKelas(['nama_kelas' => 'XI TKJ 1 (27/28)', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $targetXII1 = $this->buatKelas(['nama_kelas' => 'XII TKJ 1 (27/28)', 'tingkat' => 'XII', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        $s1 = Siswa::factory()->create(['nis' => '00101', 'nama' => 'Siswa X Satu', 'kelas_id' => $cX1->id, 'status' => 'aktif']);
        $s2 = Siswa::factory()->create(['nis' => '00102', 'nama' => 'Siswa X Dua', 'kelas_id' => $cX1->id, 'status' => 'aktif']);
        $s3 = Siswa::factory()->create(['nis' => '00201', 'nama' => 'Siswa XI Satu', 'kelas_id' => $cXI1->id, 'status' => 'aktif']);
        $sXII = Siswa::factory()->create(['nis' => '00301', 'nama' => 'Siswa Kelas XII', 'kelas_id' => $cXII1->id, 'status' => 'aktif']);

        RiwayatKelasSiswa::daftarkan($s1, $cX1, '2026/2027', '2026-07-01');
        RiwayatKelasSiswa::daftarkan($s2, $cX1, '2026/2027', '2026-07-01');
        RiwayatKelasSiswa::daftarkan($s3, $cXI1, '2026/2027', '2026-07-01');
        RiwayatKelasSiswa::daftarkan($sXII, $cXII1, '2026/2027', '2026-07-01');

        return [$source, $target, [$cX1, $cXI1, $cXII1], [$targetX1, $targetXI1, $targetXII1], [$s1, $s2, $s3, $sXII]];
    }

    public function test_parses_csv_preserving_leading_zeros_and_mapping_promotion_statuses(): void
    {
        [$source, $target, $sourceClasses, $targetClasses] = $this->setupData();
        [$targetX1, $targetXI1, $targetXII1] = $targetClasses;

        $csv = "NIS,Nama Siswa,Status Kenaikan,Alasan / Catatan\n";
        $csv .= "00101,Siswa X Satu,NAIK,\n";
        $csv .= "00102,Siswa X Dua,TINGGAL KELAS,Kehadiran kurang dari 75%\n";
        $csv .= "00201,Siswa XI Satu,NAIK,\n";

        $tmp = tempnam(sys_get_temp_dir(), 'test_prom_') . '.csv';
        file_put_contents($tmp, $csv);

        $parsed = PromotionSpreadsheet::parse($tmp, '2026/2027', '2027/2028');
        @unlink($tmp);

        $this->assertCount(3, $parsed['items']);
        $this->assertSame(3, $parsed['summary']['total_roster']);
        $this->assertSame(2, $parsed['summary']['naik']);
        $this->assertSame(1, $parsed['summary']['tinggal_kelas']);
        $this->assertSame(0, $parsed['summary']['unverified']);
        $this->assertSame(0, $parsed['summary']['masalah']);

        // Check target classes
        $item1 = collect($parsed['items'])->firstWhere('student_ref', '00101');
        $this->assertSame('naik', $item1['decision']);
        $this->assertSame($targetXI1->id, $item1['target_class_id']); // X -> XI

        $item2 = collect($parsed['items'])->firstWhere('student_ref', '00102');
        $this->assertSame('tinggal_kelas', $item2['decision']);
        $this->assertSame($targetX1->id, $item2['target_class_id']); // X stays X
        $this->assertSame('Kehadiran kurang dari 75%', $item2['alasan']);

        $item3 = collect($parsed['items'])->firstWhere('student_ref', '00201');
        $this->assertSame('naik', $item3['decision']);
        $this->assertSame($targetXII1->id, $item3['target_class_id']); // XI -> XII
    }

    public function test_detects_empty_reason_for_tinggal_kelas(): void
    {
        $this->setupData();

        $csv = "NIS,Nama Siswa,Status Kenaikan,Alasan / Catatan\n";
        $csv .= "00101,Siswa X Satu,TINGGAL KELAS,\n"; // empty reason

        $tmp = tempnam(sys_get_temp_dir(), 'test_prom_') . '.csv';
        file_put_contents($tmp, $csv);

        $parsed = PromotionSpreadsheet::parse($tmp, '2026/2027', '2027/2028');
        @unlink($tmp);

        $item = collect($parsed['items'])->firstWhere('student_ref', '00101');
        $this->assertContains('alasan_tinggal_kelas_wajib', $item['errors']);
        $this->assertGreaterThanOrEqual(1, $parsed['summary']['masalah']);
    }

    public function test_detects_duplicate_nis_unknown_nis_and_grade_xii(): void
    {
        $this->setupData();

        $csv = "NIS,Nama Siswa,Status Kenaikan,Alasan / Catatan\n";
        $csv .= "00101,Siswa X Satu,NAIK,\n";
        $csv .= "00101,Siswa X Satu Duplikat,NAIK,\n"; // duplicate in file
        $csv .= "99999,Orang Asing,NAIK,\n"; // unknown
        $csv .= "00301,Siswa Kelas XII,NAIK,\n"; // grade XII not allowed in promotion

        $tmp = tempnam(sys_get_temp_dir(), 'test_prom_') . '.csv';
        file_put_contents($tmp, $csv);

        $parsed = PromotionSpreadsheet::parse($tmp, '2026/2027', '2027/2028');
        @unlink($tmp);

        $items = collect($parsed['items']);

        // Duplicate
        $dups = $items->where('student_ref', '00101');
        $this->assertTrue($dups->contains(fn ($it) => in_array('nis_duplikat_di_berkas', $it['errors'], true)));

        // Unknown
        $unknown = $items->firstWhere('student_ref', '99999');
        $this->assertNotNull($unknown);
        $this->assertContains('nis_tidak_dikenal', $unknown['errors']);

        // Grade XII
        $gradeXII = $items->firstWhere('student_ref', '00301');
        $this->assertNotNull($gradeXII);
        $this->assertContains('siswa_tingkat_xii_tidak_termasuk', $gradeXII['errors']);
    }

    public function test_detects_invalid_status_value(): void
    {
        $this->setupData();

        $csv = "NIS,Nama Siswa,Status Kenaikan,Alasan / Catatan\n";
        $csv .= "00101,Siswa X Satu,LULUS,\n"; // LULUS is invalid for promotion workflow

        $tmp = tempnam(sys_get_temp_dir(), 'test_prom_') . '.csv';
        file_put_contents($tmp, $csv);

        $parsed = PromotionSpreadsheet::parse($tmp, '2026/2027', '2027/2028');
        @unlink($tmp);

        $item = collect($parsed['items'])->firstWhere('student_ref', '00101');
        $this->assertSame('unverified', $item['decision']);
        $this->assertContains('status_tidak_dikenal', $item['errors']);
    }

    public function test_detects_missing_target_class_when_target_year_has_no_matching_grade(): void
    {
        [$source, $target, $sourceClasses, $targetClasses] = $this->setupData();
        // Remove target class for XI
        Kelas::where('tahun_ajaran_kode', '2027/2028')->where('tingkat', 'XI')->delete();

        $csv = "NIS,Nama Siswa,Status Kenaikan,Alasan / Catatan\n";
        $csv .= "00101,Siswa X Satu,NAIK,\n"; // X -> XI, but target XI does not exist

        $tmp = tempnam(sys_get_temp_dir(), 'test_prom_') . '.csv';
        file_put_contents($tmp, $csv);

        $parsed = PromotionSpreadsheet::parse($tmp, '2026/2027', '2027/2028');
        @unlink($tmp);

        $item = collect($parsed['items'])->firstWhere('student_ref', '00101');
        $this->assertNull($item['target_class_id']);
        $this->assertContains('target_class_invalid', $item['errors']);
    }

    public function test_merges_manual_overrides_and_flags_conflicts(): void
    {
        $this->setupData();

        // File says 00101 is NAIK, but manual override says TINGGAL KELAS
        $csv = "NIS,Nama Siswa,Status Kenaikan,Alasan / Catatan\n";
        $csv .= "00101,Siswa X Satu,NAIK,\n";
        $csv .= "00102,Siswa X Dua,NAIK,\n";

        $tmp = tempnam(sys_get_temp_dir(), 'test_prom_') . '.csv';
        file_put_contents($tmp, $csv);

        $manual = [
            '00101' => [
                'decision' => 'tinggal_kelas',
                'alasan' => 'Rekomendasi guru BK',
            ],
            // 00201 is omitted from file, but manual provides decision
            '00201' => [
                'decision' => 'tinggal_kelas',
                'alasan' => 'Sakit berkepanjangan',
            ],
        ];

        $parsed = PromotionSpreadsheet::parse($tmp, '2026/2027', '2027/2028', null, $manual);
        @unlink($tmp);

        $items = collect($parsed['items']);

        // 00101: Conflict between file and manual
        $item1 = $items->firstWhere('student_ref', '00101');
        $this->assertSame('unverified', $item1['decision']);
        $this->assertContains('konflik_manual_dan_berkas', $item1['errors']);

        // 00201: Omitted from file, but manual override applied
        $item3 = $items->firstWhere('student_ref', '00201');
        $this->assertSame('tinggal_kelas', $item3['decision']);
        $this->assertSame('Sakit berkepanjangan', $item3['alasan']);
        $this->assertNotContains('alasan_tinggal_kelas_wajib', $item3['errors']);
    }

    public function test_supports_manual_target_class_override(): void
    {
        [$source, $target, $sourceClasses, $targetClasses] = $this->setupData();
        // Create an alternative parallel target class: XI TKJ 2
        $targetXI2 = $this->buatKelas(['nama_kelas' => 'XI TKJ 2 (27/28)', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 2, 'tahun_ajaran_kode' => '2027/2028']);

        $csv = "NIS,Nama Siswa,Status Kenaikan,Alasan / Catatan\n";
        $csv .= "00101,Siswa X Satu,NAIK,\n";

        $tmp = tempnam(sys_get_temp_dir(), 'test_prom_') . '.csv';
        file_put_contents($tmp, $csv);

        $manual = [
            '00101' => [
                'decision' => 'naik',
                'target_class_id' => $targetXI2->id,
            ],
        ];

        $parsed = PromotionSpreadsheet::parse($tmp, '2026/2027', '2027/2028', null, $manual);
        @unlink($tmp);

        $item1 = collect($parsed['items'])->firstWhere('student_ref', '00101');
        $this->assertSame('naik', $item1['decision']);
        $this->assertSame($targetXI2->id, $item1['target_class_id']);
    }

    public function test_builds_default_roster_with_all_students_promoted(): void
    {
        [$source, $target, $sourceClasses, $targetClasses] = $this->setupData();
        [$targetX1, $targetXI1, $targetXII1] = $targetClasses;

        $result = PromotionSpreadsheet::buildDefaultRoster('2026/2027', '2027/2028');

        $this->assertSame(3, $result['summary']['total_roster']);
        $this->assertSame(3, $result['summary']['naik']);
        $this->assertSame(0, $result['summary']['tinggal_kelas']);
        $this->assertSame(0, $result['summary']['masalah']);

        $items = collect($result['items']);
        $s1 = $items->firstWhere('student_ref', '00101');
        $this->assertSame($targetXI1->id, $s1['target_class_id']);

        $s3 = $items->firstWhere('student_ref', '00201');
        $this->assertSame($targetXII1->id, $s3['target_class_id']);
    }
}
