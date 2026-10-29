<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\PengingatPemeliharaan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase4AnnualMaintenanceReminderTest extends TestCase
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

    private function siapkanDataDasar(): array
    {
        $ta = TahunAjaran::create([
            'kode' => '2026/2027',
            'aktif' => true,
            'mulai' => '2026-07-01',
            'selesai' => '2027-06-30',
        ]);

        $jurusan = Jurusan::create([
            'kode' => 'TKJ',
            'nama' => 'Teknik Komputer dan Jaringan',
        ]);

        $kelas = Kelas::create([
            'nama_kelas' => 'X TKJ 1',
            'tingkat' => 'X',
            'jurusan_kode' => 'TKJ',
            'paralel' => '1',
            'tahun_ajaran_kode' => '2026/2027',
        ]);

        $siswa = Siswa::create([
            'nis' => '10001',
            'nisn' => '0000000001',
            'nama' => 'Siswa Pengujian',
            'kelas_id' => $kelas->id,
            'status' => 'aktif',
        ]);

        return compact('ta', 'jurusan', 'kelas', 'siswa');
    }

    public function test_evaluasi_pengingat_periode_mei_menampilkan_persiapan_data(): void
    {
        $this->siapkanDataDasar();

        $waktuMei = Carbon::create(2027, 5, 15, 10, 0, 0);
        $hasil = PengingatPemeliharaan::evaluasiPengingat($waktuMei);

        $this->assertSame('persiapan_mei', $hasil['status']);
        $this->assertSame('Persiapan Data (Mei)', $hasil['label']);
        $this->assertSame('yellow', $hasil['tone']);
        $this->assertStringContainsString('Masa Persiapan Data Siswa & Kelulusan', $hasil['judul']);
        $this->assertNotEmpty($hasil['tautan']);
    }

    public function test_evaluasi_pengingat_periode_juni_menampilkan_finalisasi_data(): void
    {
        $this->siapkanDataDasar();

        $waktuJuni = Carbon::create(2027, 6, 20, 9, 30, 0);
        $hasil = PengingatPemeliharaan::evaluasiPengingat($waktuJuni);

        $this->assertSame('finalisasi_juni', $hasil['status']);
        $this->assertSame('Finalisasi Data (Juni)', $hasil['label']);
        $this->assertSame('yellow', $hasil['tone']);
        $this->assertStringContainsString('Masa Finalisasi & Transisi Tahun Ajaran', $hasil['judul']);
        $this->assertNotEmpty($hasil['tautan']);
    }

    public function test_evaluasi_pengingat_di_luar_mei_juni_menampilkan_belum_masa_persiapan(): void
    {
        $this->siapkanDataDasar();

        $waktuOktober = Carbon::create(2026, 10, 28, 14, 0, 0);
        $hasil = PengingatPemeliharaan::evaluasiPengingat($waktuOktober);

        $this->assertSame('belum_masa_persiapan', $hasil['status']);
        $this->assertSame('Belum Masa Persiapan', $hasil['label']);
        $this->assertSame('neutral', $hasil['tone']);
        $this->assertStringContainsString('Di Luar Periode Pemeliharaan Tahunan', $hasil['judul']);
    }

    public function test_evaluasi_pengingat_mendeteksi_anomali_siswa_tanpa_kelas(): void
    {
        $this->siapkanDataDasar();

        // Buat siswa aktif tapi tanpa penugasan rombel kelas
        Siswa::create([
            'nis' => '10002',
            'nisn' => '0000000002',
            'nama' => 'Siswa Belum Ditempatkan',
            'kelas_id' => null,
            'status' => 'aktif',
        ]);

        $waktuOktober = Carbon::create(2026, 10, 28, 14, 0, 0);
        $hasil = PengingatPemeliharaan::evaluasiPengingat($waktuOktober);

        $this->assertSame('perlu_tindak_lanjut', $hasil['status']);
        $this->assertSame('Perlu Tindak Lanjut', $hasil['label']);
        $this->assertSame('red', $hasil['tone']);
        $this->assertSame(1, $hasil['siswa_tanpa_kelas']);
        $this->assertStringContainsString('belum memiliki penugasan rombel kelas', $hasil['deskripsi']);
    }

    public function test_halaman_sistem_dan_log_menampilkan_widget_pengingat_dan_tautan_terkait(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.sistem.index'));

        $response->assertOk();
        $response->assertSee('Pengingat Administrasi Data');
        $response->assertSee(route('admin.tahun-ajaran.index'));
        $response->assertSee(route('admin.transisi.index'));
        $response->assertSee(route('admin.impor.index'));
    }

    public function test_pengguna_non_admin_dilarang_mengakses_halaman_sistem(): void
    {
        $guru = $this->guru();

        $response = $this->actingAs($guru)->get(route('admin.sistem.index'));

        $response->assertForbidden();
    }
}
