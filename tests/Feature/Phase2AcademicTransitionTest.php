<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Alumni;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\PresensiHarian;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TransisiTahunAjaran;
use App\Models\User;
use App\Support\TransisiAkademik;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase2AcademicTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@jurnalkelas.app'],
            [
                'username' => 'admin',
                'nama' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
    }

    private function tahunAjaran(string $kode = '2026/2027', bool $aktif = true): TahunAjaran
    {
        return TahunAjaran::firstOrCreate(
            ['kode' => $kode],
            ['aktif' => $aktif, 'mulai' => '2026-07-01', 'selesai' => '2027-06-30']
        );
    }

    private function jurusan(string $kode = 'TKJ'): Jurusan
    {
        return Jurusan::firstOrCreate(
            ['kode' => $kode],
            ['nama' => 'Teknik Komputer dan Jaringan', 'aktif' => true]
        );
    }

    private function kelas(string $nama, string $tingkat, string $tahunKode, int $paralel = 1): Kelas
    {
        return Kelas::firstOrCreate(
            ['nama_kelas' => $nama, 'tahun_ajaran_kode' => $tahunKode],
            [
                'tingkat' => $tingkat,
                'jurusan_kode' => $this->jurusan()->kode,
                'paralel' => $paralel,
                'tahun_ajaran_kode' => $tahunKode,
            ]
        );
    }

    private function siswa(string $nis, string $nama, Kelas $kelas): Siswa
    {
        $siswa = Siswa::create([
            'nis' => $nis,
            'nama' => $nama,
            'kelas_id' => $kelas->id,
            'status' => 'aktif',
        ]);

        RiwayatKelasSiswa::create([
            'siswa_nis' => $siswa->nis,
            'kelas_id' => $kelas->id,
            'tahun_ajaran_kode' => $kelas->tahun_ajaran_kode,
            'tingkat' => $kelas->tingkat,
            'status' => 'aktif',
            'aktif' => true,
            'tanggal_mulai' => '2026-07-15',
        ]);

        return $siswa;
    }

    public function test_academic_year_creation_validation_and_single_active_enforcement(): void
    {
        $th1 = $this->tahunAjaran('2026/2027', aktif: true);
        $this->assertTrue($th1->aktif);

        // Validasi format kode
        $this->assertTrue(TahunAjaran::formatValid('2026/2027'));
        $this->assertTrue(TahunAjaran::formatValid('2027/2028'));
        $this->assertFalse(TahunAjaran::formatValid('2026-2027'));
        $this->assertFalse(TahunAjaran::formatValid('2026/2028'));

        // Buat tahun ajaran baru tanpa mengaktifkannya
        $th2 = TahunAjaran::create([
            'kode' => '2027/2028',
            'aktif' => false,
            'mulai' => '2027-07-01',
            'selesai' => '2028-06-30',
        ]);

        $th1->refresh();
        $this->assertTrue($th1->aktif, 'Tahun lama tetap aktif saat tahun baru dibuat');
        $this->assertFalse($th2->aktif);

        // Aktifkan tahun baru -> tahun lama otomatis nonaktif
        $th2->aktifkan();
        $th1->refresh();
        $th2->refresh();

        $this->assertFalse($th1->aktif, 'Tahun lama nonaktif setelah tahun baru diaktifkan');
        $this->assertTrue($th2->aktif);
        $this->assertEquals(1, TahunAjaran::where('aktif', true)->count());
    }

    public function test_destination_class_preparation_preserves_department_and_parallel(): void
    {
        $thAsal = $this->tahunAjaran('2026/2027', aktif: true);
        $thTujuan = TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false]);

        $this->kelas('X TKJ 1', 'X', $thAsal->kode, paralel: 1);
        $this->kelas('X TKJ 2', 'X', $thAsal->kode, paralel: 2);
        $this->kelas('XI TKJ 1', 'XI', $thAsal->kode, paralel: 1);
        $this->kelas('XII TKJ 1', 'XII', $thAsal->kode, paralel: 1);

        $hasil = TransisiAkademik::siapkanKelasTujuan($thTujuan->kode, $thAsal->kode);

        // Kelas XI TKJ 1 & XI TKJ 2 (dari promosi X)
        $this->assertDatabaseHas('kelas', [
            'tahun_ajaran_kode' => '2027/2028',
            'nama_kelas' => 'XI TKJ 1',
            'tingkat' => 'XI',
            'paralel' => 1,
        ]);
        $this->assertDatabaseHas('kelas', [
            'tahun_ajaran_kode' => '2027/2028',
            'nama_kelas' => 'XI TKJ 2',
            'tingkat' => 'XI',
            'paralel' => 2,
        ]);

        // Kelas XII TKJ 1 (dari promosi XI)
        $this->assertDatabaseHas('kelas', [
            'tahun_ajaran_kode' => '2027/2028',
            'nama_kelas' => 'XII TKJ 1',
            'tingkat' => 'XII',
            'paralel' => 1,
        ]);

        // Template Kelas X TKJ 1 & X TKJ 2 (untuk siswa baru)
        $this->assertDatabaseHas('kelas', [
            'tahun_ajaran_kode' => '2027/2028',
            'nama_kelas' => 'X TKJ 1',
            'tingkat' => 'X',
            'paralel' => 1,
        ]);

        // Verifikasi idempotensi penyiapan ulang
        $hasilUlang = TransisiAkademik::siapkanKelasTujuan($thTujuan->kode, $thAsal->kode);
        $this->assertEquals(0, $hasilUlang['dibuat']);
        $this->assertGreaterThan(0, $hasilUlang['sudah_ada']);
    }

    public function test_normal_grade_promotion_and_historical_membership(): void
    {
        $thAsal = $this->tahunAjaran('2026/2027', aktif: true);
        $thTujuan = TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false]);

        $kX = $this->kelas('X TKJ 1', 'X', $thAsal->kode, paralel: 1);
        $kXI = $this->kelas('XI TKJ 1', 'XI', $thAsal->kode, paralel: 1);

        $siswaX = $this->siswa('1001', 'Siswa Sepuluh', $kX);
        $siswaXI = $this->siswa('2001', 'Siswa Sebelas', $kXI);

        // Siapkan kelas tujuan
        TransisiAkademik::siapkanKelasTujuan($thTujuan->kode, $thAsal->kode);

        $kelasDestXI = Kelas::where('tahun_ajaran_kode', $thTujuan->kode)->where('nama_kelas', 'XI TKJ 1')->firstOrFail();
        $kelasDestXII = Kelas::where('tahun_ajaran_kode', $thTujuan->kode)->where('nama_kelas', 'XII TKJ 1')->firstOrFail();

        // Eksekusi transisi
        $admin = $this->admin();
        $res = TransisiAkademik::eksekusi($thAsal->kode, $thTujuan->kode, [], $admin);

        $this->assertEquals(2, $res['siswa_dipromosikan']);

        $siswaX->refresh();
        $siswaXI->refresh();

        // Verifikasi kelas baru siswa
        $this->assertEquals($kelasDestXI->id, $siswaX->kelas_id);
        $this->assertEquals($kelasDestXII->id, $siswaXI->kelas_id);

        // Verifikasi riwayat keanggotaan kelas siswa X
        $riwayatX = RiwayatKelasSiswa::where('siswa_nis', $siswaX->nis)->orderBy('id')->get();
        $this->assertCount(2, $riwayatX);
        $this->assertFalse($riwayatX[0]->aktif); // Riwayat lama di X
        $this->assertTrue($riwayatX[1]->aktif);  // Riwayat baru di XI
        $this->assertEquals($kelasDestXI->id, $riwayatX[1]->kelas_id);
        $this->assertEquals('2027/2028', $riwayatX[1]->tahun_ajaran_kode);

        // Verifikasi audit log
        $this->assertDatabaseHas('admin_audit_log', [
            'aksi' => 'akademik.transisi',
            'status' => 'berhasil',
        ]);
    }

    public function test_grade_retention_and_student_transfer_exceptions(): void
    {
        $thAsal = $this->tahunAjaran('2026/2027', aktif: true);
        $thTujuan = TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false]);

        $kX = $this->kelas('X TKJ 1', 'X', $thAsal->kode, paralel: 1);

        $siswaTinggal = $this->siswa('1002', 'Siswa Tinggal Kelas', $kX);
        $siswaKeluar = $this->siswa('1003', 'Siswa Pindah Sekolah', $kX);

        TransisiAkademik::siapkanKelasTujuan($thTujuan->kode, $thAsal->kode);
        $destKelasX = Kelas::where('tahun_ajaran_kode', $thTujuan->kode)->where('nama_kelas', 'X TKJ 1')->firstOrFail();

        $pengecualian = [
            $siswaTinggal->nis => ['aksi' => 'tinggal_kelas', 'catatan' => 'Nilai belum tuntas'],
            $siswaKeluar->nis => ['aksi' => 'keluar', 'catatan' => 'Pindah domisili ke luar kota'],
        ];

        $admin = $this->admin();
        $res = TransisiAkademik::eksekusi($thAsal->kode, $thTujuan->kode, ['pengecualian' => $pengecualian], $admin);

        $this->assertEquals(1, $res['siswa_tinggal']);
        $this->assertEquals(1, $res['siswa_pindah']);

        $siswaTinggal->refresh();
        $siswaKeluar->refresh();

        // Siswa tinggal kelas: tetap di kelas tingkat X pada tahun ajaran baru
        $this->assertEquals($destKelasX->id, $siswaTinggal->kelas_id);
        $riwayatTinggal = RiwayatKelasSiswa::where('siswa_nis', $siswaTinggal->nis)->where('aktif', true)->firstOrFail();
        $this->assertEquals('tinggal_kelas', $riwayatTinggal->status);
        $this->assertEquals('X', $riwayatTinggal->tingkat);

        // Siswa keluar: nonaktif dan kelas_id null
        $this->assertEquals('nonaktif', $siswaKeluar->status);
        $this->assertNull($siswaKeluar->kelas_id);
        $riwayatKeluar = RiwayatKelasSiswa::where('siswa_nis', $siswaKeluar->nis)->get();
        $this->assertFalse($riwayatKeluar->contains('aktif', true));
    }

    public function test_grade_xii_graduation_and_alumni_creation(): void
    {
        $thAsal = $this->tahunAjaran('2026/2027', aktif: true);
        $thTujuan = TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false]);

        $kXII = $this->kelas('XII TKJ 1', 'XII', $thAsal->kode, paralel: 1);
        $siswaXII = $this->siswa('3001', 'Siswa Dua Belas', $kXII);

        // Buat rekaman presensi historis untuk memastikan presensi tidak hilang
        PresensiHarian::create([
            'kelas_id' => $kXII->id,
            'tanggal' => '2027-04-10',
            'siswa_nis' => $siswaXII->nis,
            'status' => 'hadir',
        ]);

        TransisiAkademik::siapkanKelasTujuan($thTujuan->kode, $thAsal->kode);

        $admin = $this->admin();
        $res = TransisiAkademik::eksekusi($thAsal->kode, $thTujuan->kode, [
            'tanggal_lulus' => '2027-05-15',
            'nonaktifkan_akun' => false,
        ], $admin);

        $this->assertEquals(1, $res['siswa_lulus']);

        $siswaXII->refresh();

        // Siswa berstatus lulus dan kelas_id null
        $this->assertEquals('lulus', $siswaXII->status);
        $this->assertNull($siswaXII->kelas_id);

        // Catatan alumni terbentuk dengan benar
        $alumni = Alumni::where('siswa_nis', $siswaXII->nis)->firstOrFail();
        $this->assertEquals('2026/2027', $alumni->tahun_lulus);
        $this->assertEquals('2027-05-15', $alumni->tanggal_lulus->toDateString());
        $this->assertEquals($kXII->id, $alumni->kelas_terakhir_id);

        // Keanggotaan kelas aktif tertutup
        $riwayatLulus = RiwayatKelasSiswa::where('siswa_nis', $siswaXII->nis)->firstOrFail();
        $this->assertFalse($riwayatLulus->aktif);
        $this->assertEquals('lulus', $riwayatLulus->status);

        // Riwayat presensi historis tetap ada
        $this->assertTrue(
            PresensiHarian::where('siswa_nis', $siswaXII->nis)->whereDate('tanggal', '2027-04-10')->exists(),
            'Presensi historis tetap tersimpan setelah kelulusan'
        );
    }

    public function test_double_execution_prevention_and_idempotency(): void
    {
        $thAsal = $this->tahunAjaran('2026/2027', aktif: true);
        $thTujuan = TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false]);

        $kX = $this->kelas('X TKJ 1', 'X', $thAsal->kode, paralel: 1);
        $this->siswa('1005', 'Siswa Idempotent', $kX);

        TransisiAkademik::siapkanKelasTujuan($thTujuan->kode, $thAsal->kode);

        $admin = $this->admin();
        TransisiAkademik::eksekusi($thAsal->kode, $thTujuan->kode, [], $admin);

        // Percobaan eksekusi kedua pada tahun ajaran yang sama harus melempar DomainException
        $this->expectException(DomainException::class);
        TransisiAkademik::eksekusi($thAsal->kode, $thTujuan->kode, [], $admin);
    }

    public function test_transisi_web_controller_integration(): void
    {
        $admin = $this->admin();
        $this->tahunAjaran('2026/2027', aktif: true);

        // 1. Akses halaman index
        $resp = $this->actingAs($admin)->get('/admin/transisi');
        $resp->assertOk();
        $resp->assertSee('Tahun Ajaran & Transisi Akademik');

        // 2. Tambah tahun ajaran baru via POST
        $resp = $this->actingAs($admin)->post('/admin/transisi/tahun-ajaran', [
            'kode' => '2027/2028',
            'mulai' => '2027-07-01',
            'selesai' => '2028-06-30',
        ]);
        $resp->assertRedirect('/admin/transisi');
        $this->assertDatabaseHas('tahun_ajaran', ['kode' => '2027/2028']);

        // 3. Siapkan rombel kelas
        $this->kelas('X TKJ 1', 'X', '2026/2027');
        $resp = $this->actingAs($admin)->post('/admin/transisi/siapkan-kelas', [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
        ]);
        $resp->assertRedirect('/admin/transisi');
        $this->assertDatabaseHas('kelas', ['nama_kelas' => 'XI TKJ 1', 'tahun_ajaran_kode' => '2027/2028']);

        // 4. Buka pratinjau
        $resp = $this->actingAs($admin)->get('/admin/transisi/pratinjau?tahun_asal=2026/2027&tahun_tujuan=2027/2028');
        $resp->assertOk();
        $resp->assertSee('Pratinjau Transisi');

        // 5. Eksekusi transisi via web
        $resp = $this->actingAs($admin)->post('/admin/transisi/eksekusi', [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'konfirmasi' => '1',
            'aktifkan_tahun_tujuan' => '1',
        ]);
        $resp->assertRedirect('/admin/transisi');
        $resp->assertSessionHas('success');

        $this->assertDatabaseHas('transisi_tahun_ajaran', [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'status' => 'selesai',
        ]);
    }
}
