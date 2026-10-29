<?php

namespace Tests\Feature;

use App\Models\Alumni;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TransisiTahunAjaran;
use App\Models\User;
use App\Support\KesiapanTransisi;
use App\Support\TransisiAkademik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase4AcademicTransitionChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@jurnalkelas.app'],
            [
                'username' => 'admin',
                'nama' => 'Administrator Utama',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
    }

    private function guru(): User
    {
        return User::firstOrCreate(
            ['email' => 'guru@jurnalkelas.app'],
            [
                'username' => 'guru',
                'nama' => 'Guru Penguji',
                'password' => Hash::make('password'),
                'role' => 'guru',
            ]
        );
    }

    private function siapkanSiklusLengkap(): array
    {
        $taAsal = TahunAjaran::create([
            'kode' => '2026/2027',
            'aktif' => true,
            'mulai' => '2026-07-01',
            'selesai' => '2027-06-30',
        ]);

        $taTujuan = TahunAjaran::create([
            'kode' => '2027/2028',
            'aktif' => false,
            'mulai' => '2027-07-01',
            'selesai' => '2028-06-30',
        ]);

        $jurusan = Jurusan::create([
            'kode' => 'TKJ',
            'nama' => 'Teknik Komputer dan Jaringan',
        ]);

        // Kelas asal: X, XI, XII
        $kelasX = Kelas::create(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => '1', 'tahun_ajaran_kode' => '2026/2027']);
        $kelasXI = Kelas::create(['nama_kelas' => 'XI TKJ 1', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => '1', 'tahun_ajaran_kode' => '2026/2027']);
        $kelasXII = Kelas::create(['nama_kelas' => 'XII TKJ 1', 'tingkat' => 'XII', 'jurusan_kode' => 'TKJ', 'paralel' => '1', 'tahun_ajaran_kode' => '2026/2027']);

        // Siswa aktif di tiap kelas
        $s1 = Siswa::create(['nis' => '10001', 'nama' => 'Siswa X', 'kelas_id' => $kelasX->id, 'status' => 'aktif']);
        $s2 = Siswa::create(['nis' => '10002', 'nama' => 'Siswa XI', 'kelas_id' => $kelasXI->id, 'status' => 'aktif']);
        $s3 = Siswa::create(['nis' => '10003', 'nama' => 'Siswa XII', 'kelas_id' => $kelasXII->id, 'status' => 'aktif']);

        return compact('taAsal', 'taTujuan', 'jurusan', 'kelasX', 'kelasXI', 'kelasXII', 's1', 's2', 's3');
    }

    public function test_admin_dapat_melihat_checklist_kesiapan_transisi(): void
    {
        $this->siapkanSiklusLengkap();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.transisi.index'));

        $response->assertOk();
        $response->assertSee('Kesiapan Transisi Akademik');
        $response->assertSee('Promosi X &rarr; XI', false);
        $response->assertSee('Promosi XI &rarr; XII', false);
        $response->assertSee('Kelulusan XII');
        $response->assertSee('Konfigurasi Tahun Ajaran');
    }

    public function test_banner_panduan_awal_muncul_saat_belum_ada_tahun_tujuan(): void
    {
        // Hanya ada tahun aktif, belum ada tahun tujuan
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true, 'mulai' => '2026-07-01', 'selesai' => '2027-06-30']);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.transisi.index'));

        $response->assertOk();
        $response->assertSee('Tahun ajaran tujuan belum tersedia.');
        $response->assertSee('Tambah Tahun Ajaran');
        $response->assertSee('disabled');
    }

    public function test_pengguna_non_admin_dilarang_mengakses_halaman_transisi(): void
    {
        $this->siapkanSiklusLengkap();
        $guru = $this->guru();

        $response = $this->actingAs($guru)->get(route('admin.transisi.index'));

        $response->assertForbidden();
    }

    public function test_konfigurasi_lengkap_dan_kelas_terpenuhi_menghasilkan_status_siap(): void
    {
        $data = $this->siapkanSiklusLengkap();

        // Siapkan kelas tujuan lengkap menggunakan TransisiAkademik
        TransisiAkademik::siapkanKelasTujuan('2027/2028', '2026/2027');

        $checklist = KesiapanTransisi::evaluasi('2026/2027', '2027/2028');

        $this->assertSame(KesiapanTransisi::STATUS_SIAP, $checklist['promosi_x_ke_xi']['status']);
        $this->assertSame(KesiapanTransisi::STATUS_SIAP, $checklist['promosi_xi_ke_xii']['status']);
        $this->assertSame(KesiapanTransisi::STATUS_SIAP, $checklist['kelulusan_xii']['status']);
        $this->assertSame(0, $checklist['jumlah_terhambat']);
        $this->assertSame('siap', $checklist['status_keseluruhan']);
    }

    public function test_deteksi_ketiadaan_tahun_ajaran_aktif(): void
    {
        // Tidak ada tahun ajaran aktif sama sekali
        $checklist = KesiapanTransisi::evaluasi();

        $this->assertSame(KesiapanTransisi::STATUS_TERHAMBAT, $checklist['status_keseluruhan']);
        $this->assertGreaterThanOrEqual(1, $checklist['jumlah_terhambat']);

        $itemAktif = collect($checklist['item'])->firstWhere('kriteria', 'Tahun Ajaran Berjalan Aktif');
        $this->assertNotNull($itemAktif);
        $this->assertSame(KesiapanTransisi::STATUS_TERHAMBAT, $itemAktif['status']);
        $this->assertTrue($itemAktif['pemblokir']);
    }

    public function test_deteksi_ketiadaan_tahun_ajaran_tujuan(): void
    {
        // Hanya ada tahun aktif, belum ada tahun tujuan
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true, 'mulai' => '2026-07-01', 'selesai' => '2027-06-30']);

        $checklist = KesiapanTransisi::evaluasi('2026/2027', null);

        $this->assertSame(KesiapanTransisi::STATUS_BELUM_DIKONFIGURASI, $checklist['status_keseluruhan']);
        $itemTujuan = collect($checklist['item'])->firstWhere('kriteria', 'Ketersediaan Tahun Ajaran Tujuan');
        $this->assertNotNull($itemTujuan);
        $this->assertSame(KesiapanTransisi::STATUS_BELUM_DIKONFIGURASI, $itemTujuan['status']);
    }

    public function test_deteksi_kekurangan_rombel_kelas_tujuan(): void
    {
        $this->siapkanSiklusLengkap();

        // Tahun asal dan tujuan ada, tapi kelas tujuan belum dibuat
        $checklist = KesiapanTransisi::evaluasi('2026/2027', '2027/2028');

        $this->assertSame(KesiapanTransisi::STATUS_TERHAMBAT, $checklist['status_keseluruhan']);
        $this->assertGreaterThan(0, $checklist['jumlah_terhambat']);

        $itemXI = collect($checklist['item'])->firstWhere('kriteria', 'Ketersediaan Rombel Tingkat XI');
        $this->assertSame(KesiapanTransisi::STATUS_TERHAMBAT, $itemXI['status']);
        $this->assertSame(1, $itemXI['jumlah_terdampak']);
    }

    public function test_deteksi_siswa_aktif_tanpa_penugasan_kelas(): void
    {
        $this->siapkanSiklusLengkap();
        TransisiAkademik::siapkanKelasTujuan('2027/2028', '2026/2027');

        // Buat siswa tanpa kelas
        Siswa::create(['nis' => '10009', 'nama' => 'Siswa Tanpa Kelas', 'kelas_id' => null, 'status' => 'aktif']);

        $checklist = KesiapanTransisi::evaluasi('2026/2027', '2027/2028');

        $itemSiswa = collect($checklist['item'])->firstWhere('kriteria', 'Penugasan Rombel Siswa Aktif');
        $this->assertSame(KesiapanTransisi::STATUS_PERLU_DITINJAU, $itemSiswa['status']);
        $this->assertSame(1, $itemSiswa['jumlah_terdampak']);
        $this->assertFalse($itemSiswa['pemblokir']);
    }

    public function test_kesiapan_kelulusan_xii_dievaluasi_secara_mandiri_dari_promosi(): void
    {
        $this->siapkanSiklusLengkap();

        // Rombel kelas tujuan belum dibuat (sehingga promosi X dan XI terhambat)
        $checklist = KesiapanTransisi::evaluasi('2026/2027', '2027/2028');

        $this->assertSame(KesiapanTransisi::STATUS_TERHAMBAT, $checklist['promosi_x_ke_xi']['status']);
        $this->assertSame(KesiapanTransisi::STATUS_TERHAMBAT, $checklist['promosi_xi_ke_xii']['status']);
        // Kelulusan XII tetap Siap karena siswa XII ada dan tidak memerlukan kelas tujuan baru
        $this->assertSame(KesiapanTransisi::STATUS_SIAP, $checklist['kelulusan_xii']['status']);
    }

    public function test_peringatan_non_blocking_tidak_salah_memblokir_operasi(): void
    {
        $this->siapkanSiklusLengkap();

        // Siapkan kelas tujuan XI dan XII saja, sengaja lewatkan template X baru
        Kelas::create(['nama_kelas' => 'XI TKJ 1', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => '1', 'tahun_ajaran_kode' => '2027/2028']);
        Kelas::create(['nama_kelas' => 'XII TKJ 1', 'tingkat' => 'XII', 'jurusan_kode' => 'TKJ', 'paralel' => '1', 'tahun_ajaran_kode' => '2027/2028']);

        $checklist = KesiapanTransisi::evaluasi('2026/2027', '2027/2028');

        // Template rombel X baru berstatus non-blocking warning (perlu ditinjau)
        $itemXBaru = collect($checklist['item'])->firstWhere('kriteria', 'Template Rombel Tingkat X Baru');
        $this->assertSame(KesiapanTransisi::STATUS_PERLU_DITINJAU, $itemXBaru['status']);
        $this->assertFalse($itemXBaru['pemblokir']);

        // Promosi X -> XI dan XI -> XII tetap siap
        $this->assertSame(KesiapanTransisi::STATUS_SIAP, $checklist['promosi_x_ke_xi']['status']);
        $this->assertSame(KesiapanTransisi::STATUS_SIAP, $checklist['promosi_xi_ke_xii']['status']);
    }

    public function test_simulasi_pratinjau_transisi_tetap_berfungsi(): void
    {
        $this->siapkanSiklusLengkap();
        TransisiAkademik::siapkanKelasTujuan('2027/2028', '2026/2027');

        $pratinjau = TransisiAkademik::pratinjau('2026/2027', '2027/2028');

        $this->assertTrue($pratinjau['siap_eksekusi']);
        $this->assertEmpty($pratinjau['masalah_validasi']);
        $this->assertSame(1, $pratinjau['statistik']['total_lulusan']);
        $this->assertSame(2, $pratinjau['statistik']['total_promosi']);
    }

    public function test_evaluasi_checklist_bersifat_read_only_dan_tidak_mengubah_data(): void
    {
        $this->siapkanSiklusLengkap();

        $jumlahSiswaAwal = Siswa::count();
        $jumlahKelasAwal = Kelas::count();
        $jumlahAlumniAwal = Alumni::count();
        $jumlahTransisiAwal = TransisiTahunAjaran::count();

        // Jalankan evaluasi berulang kali
        KesiapanTransisi::evaluasi('2026/2027', '2027/2028');
        KesiapanTransisi::evaluasi('2026/2027', '2027/2028');

        $this->assertSame($jumlahSiswaAwal, Siswa::count());
        $this->assertSame($jumlahKelasAwal, Kelas::count());
        $this->assertSame($jumlahAlumniAwal, Alumni::count());
        $this->assertSame($jumlahTransisiAwal, TransisiTahunAjaran::count());
    }

    public function test_dataset_kosong_ditangani_dengan_anggun(): void
    {
        // Database bersih kosong
        $checklist = KesiapanTransisi::evaluasi(null, null);

        $this->assertIsArray($checklist);
        $this->assertSame(KesiapanTransisi::STATUS_TERHAMBAT, $checklist['status_keseluruhan']);
        $this->assertNotEmpty($checklist['item']);
    }
}
