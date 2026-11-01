<?php

namespace Tests\Unit;

use App\AcademicWorkflows\IntakeSpreadsheet;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class IntakeSpreadsheetTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
    }

    private function setupAcademicData(): array
    {
        $target = TahunAjaran::firstOrCreate(
            ['kode' => '2027/2028'],
            ['aktif' => false, 'mulai' => '2027-07-01', 'selesai' => '2028-06-30']
        );
        $target->update(['mulai' => '2027-07-01', 'selesai' => '2028-06-30', 'aktif' => false]);

        $otherYear = TahunAjaran::firstOrCreate(
            ['kode' => '2026/2027'],
            ['aktif' => true, 'mulai' => '2026-07-01', 'selesai' => '2027-06-30']
        );

        $kX1 = $this->buatKelas(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $kX2 = $this->buatKelas(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X', 'jurusan_kode' => 'RPL', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $kXI = $this->buatKelas(['nama_kelas' => 'XI TKJ 1', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $kOld = $this->buatKelas(['nama_kelas' => 'X TKJ 1 (Old)', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);

        // Existing siswa & users in DB
        $sExisting = Siswa::factory()->create(['nis' => '00100', 'nisn' => '0011223344', 'nama' => 'Siswa Lama', 'status' => 'aktif']);
        $uExisting = User::factory()->create(['email' => 'terdaftar@sekolah.sch.id', 'role' => 'siswa', 'status' => 'aktif']);

        return [$target, [$kX1, $kX2, $kXI, $kOld], [$sExisting, $uExisting]];
    }

    public function test_parses_valid_candidates_preserving_leading_zeros(): void
    {
        [$target, $classes] = $this->setupAcademicData();
        $targetClass = $classes[0]; // X TKJ 1

        $csv = "NIS,NISN,Nama Lengkap,Email,Kelas X Tujuan,Jenis Kelamin\n";
        $csv .= "00301,0081234567,Ahmad Fadillah,ahmad@sekolah.sch.id,X TKJ 1,L\n";
        $csv .= "00302,0087654321,Siti Rahmawati,siti@sekolah.sch.id,X TKJ 1,P\n";

        $tmp = tempnam(sys_get_temp_dir(), 'test_intake_') . '.csv';
        file_put_contents($tmp, $csv);

        $parsed = IntakeSpreadsheet::parse($tmp, '2027/2028');
        @unlink($tmp);

        $this->assertCount(2, $parsed['items']);
        $this->assertSame(2, $parsed['summary']['siap_proses']);
        $this->assertSame(0, $parsed['summary']['masalah']);

        $item1 = $parsed['items'][0];
        $this->assertSame('00301', $item1['student_ref']); // Leading zeros preserved
        $this->assertSame('0081234567', $item1['nisn']);
        $this->assertSame('Ahmad Fadillah', $item1['nama']);
        $this->assertSame('ahmad@sekolah.sch.id', $item1['email']);
        $this->assertSame('L', $item1['jenis_kelamin']);
        $this->assertSame($targetClass->id, $item1['target_class_id']);
        $this->assertEmpty($item1['errors']);
    }

    public function test_detects_invalid_nisn_and_invalid_email_format(): void
    {
        $this->setupAcademicData();

        $csv = "NIS,NISN,Nama Lengkap,Email,Kelas X Tujuan,Jenis Kelamin\n";
        $csv .= "00301,12345,Budi,budi-bukan-email,X TKJ 1,L\n"; // NISN < 10 digits, invalid email

        $tmp = tempnam(sys_get_temp_dir(), 'test_intake_') . '.csv';
        file_put_contents($tmp, $csv);

        $parsed = IntakeSpreadsheet::parse($tmp, '2027/2028');
        @unlink($tmp);

        $item = $parsed['items'][0];
        $this->assertContains('format_nisn_tidak_valid', $item['errors']);
        $this->assertContains('format_email_tidak_valid', $item['errors']);
        $this->assertSame(1, $parsed['summary']['masalah']);
    }

    public function test_detects_internal_file_duplicates(): void
    {
        $this->setupAcademicData();

        $csv = "NIS,NISN,Nama Lengkap,Email,Kelas X Tujuan,Jenis Kelamin\n";
        $csv .= "00301,0081234567,Ahmad Fadillah,ahmad@sekolah.sch.id,X TKJ 1,L\n";
        $csv .= "00301,0081234567,Ahmad Duplikat,ahmad@sekolah.sch.id,X TKJ 1,L\n";

        $tmp = tempnam(sys_get_temp_dir(), 'test_intake_') . '.csv';
        file_put_contents($tmp, $csv);

        $parsed = IntakeSpreadsheet::parse($tmp, '2027/2028');
        @unlink($tmp);

        $item1 = $parsed['items'][0];
        $item2 = $parsed['items'][1];

        $this->assertContains('nis_duplikat_internal', $item1['errors']);
        $this->assertContains('nisn_duplikat_internal', $item1['errors']);
        $this->assertContains('email_duplikat_internal', $item1['errors']);

        $this->assertContains('nis_duplikat_internal', $item2['errors']);
    }

    public function test_detects_database_duplicates_for_nis_nisn_and_email(): void
    {
        $this->setupAcademicData();

        $csv = "NIS,NISN,Nama Lengkap,Email,Kelas X Tujuan,Jenis Kelamin\n";
        $csv .= "00100,0011223344,Calon Terdaftar,terdaftar@sekolah.sch.id,X TKJ 1,L\n"; // All 3 match existing DB records

        $tmp = tempnam(sys_get_temp_dir(), 'test_intake_') . '.csv';
        file_put_contents($tmp, $csv);

        $parsed = IntakeSpreadsheet::parse($tmp, '2027/2028');
        @unlink($tmp);

        $item = $parsed['items'][0];
        $this->assertContains('nis_sudah_terdaftar', $item['errors']);
        $this->assertContains('nisn_sudah_terdaftar', $item['errors']);
        $this->assertContains('email_sudah_terdaftar', $item['errors']);
    }

    public function test_detects_missing_class_wrong_grade_and_wrong_academic_year(): void
    {
        $this->setupAcademicData();

        $csv = "NIS,NISN,Nama Lengkap,Email,Kelas X Tujuan,Jenis Kelamin\n";
        $csv .= "00301,0081111111,Siswa 1,s1@sekolah.sch.id,X Belum Ada,L\n"; // Missing class
        $csv .= "00302,0082222222,Siswa 2,s2@sekolah.sch.id,XI TKJ 1,L\n"; // Grade XI (not Grade X)
        $csv .= "00303,0083333333,Siswa 3,s3@sekolah.sch.id,X TKJ 1 (Old),L\n"; // Class belongs to 2026/2027, not 2027/2028

        $tmp = tempnam(sys_get_temp_dir(), 'test_intake_') . '.csv';
        file_put_contents($tmp, $csv);

        $parsed = IntakeSpreadsheet::parse($tmp, '2027/2028');
        @unlink($tmp);

        $item1 = $parsed['items'][0];
        $this->assertContains('kelas_tujuan_tidak_ditemukan', $item1['errors']);

        $item2 = $parsed['items'][1];
        $this->assertContains('kelas_tujuan_bukan_tingkat_x', $item2['errors']);

        $item3 = $parsed['items'][2];
        $this->assertContains('kelas_tujuan_tidak_ditemukan', $item3['errors']);
    }

    public function test_supports_explicit_manual_exclusion_with_required_reason(): void
    {
        $this->setupAcademicData();

        $csv = "NIS,NISN,Nama Lengkap,Email,Kelas X Tujuan,Jenis Kelamin\n";
        $csv .= "00301,0081234567,Ahmad Fadillah,ahmad@sekolah.sch.id,X TKJ 1,L\n";
        $csv .= "00302,12345,Siti Masalah,siti-bad,X TKJ 1,P\n"; // Problematic row

        $tmp = tempnam(sys_get_temp_dir(), 'test_intake_') . '.csv';
        file_put_contents($tmp, $csv);

        // Exclude 00302 with explicit administrative reason
        $manual = [
            '00302' => [
                'excluded' => true,
                'exclusion_reason' => 'Berkas pendaftaran fisik belum lengkap',
            ],
        ];

        $parsed = IntakeSpreadsheet::parse($tmp, '2027/2028', null, $manual);
        @unlink($tmp);

        $this->assertSame(1, $parsed['summary']['siap_proses']);
        $this->assertSame(0, $parsed['summary']['masalah']);
        $this->assertSame(1, $parsed['summary']['dikecualikan']);

        $excludedItem = collect($parsed['items'])->firstWhere('student_ref', '00302');
        $this->assertTrue($excludedItem['excluded']);
        $this->assertTrue($excludedItem['exclusion_reason_present']);
        $this->assertSame('Berkas pendaftaran fisik belum lengkap', $excludedItem['exclusion_reason']);
    }

    public function test_flag_error_if_exclusion_reason_is_empty(): void
    {
        $this->setupAcademicData();

        $csv = "NIS,NISN,Nama Lengkap,Email,Kelas X Tujuan,Jenis Kelamin\n";
        $csv .= "00301,0081234567,Ahmad Fadillah,ahmad@sekolah.sch.id,X TKJ 1,L\n";

        $tmp = tempnam(sys_get_temp_dir(), 'test_intake_') . '.csv';
        file_put_contents($tmp, $csv);

        // Excluded but empty reason
        $manual = [
            '00301' => [
                'excluded' => true,
                'exclusion_reason' => '',
            ],
        ];

        $parsed = IntakeSpreadsheet::parse($tmp, '2027/2028', null, $manual);
        @unlink($tmp);

        $item = $parsed['items'][0];
        $this->assertContains('alasan_pengecualian_wajib', $item['errors']);
        $this->assertFalse($item['exclusion_reason_present']);
    }
}
