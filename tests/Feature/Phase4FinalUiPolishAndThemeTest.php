<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase4FinalUiPolishAndThemeTest extends TestCase
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

    public function test_halaman_login_dan_aplikasi_memuat_skrip_inisialisasi_default_light_mode(): void
    {
        // 1. Halaman Login
        $resLogin = $this->get('/login');
        $resLogin->assertOk();
        $resLogin->assertSee("ls.getItem('pref-theme')", false);
        $resLogin->assertSee("d.setAttribute('data-theme', 'light')", false);

        // 2. Halaman Aplikasi (Admin)
        $admin = $this->admin();
        $resApp = $this->actingAs($admin)->get('/admin/transisi');
        $resApp->assertOk();
        $resApp->assertSee("ls.getItem('pref-theme')", false);
        $resApp->assertSee("d.setAttribute('data-theme', 'light')", false);
        $resApp->assertSee("get('pref-theme', 'light')", false);
    }

    public function test_halaman_transisi_menampilkan_format_ringkasan_pemeriksaan_terpenuhi(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.transisi.index'));

        $response->assertOk();
        // Format "X dari Y pemeriksaan terpenuhi"
        $response->assertSee('pemeriksaan terpenuhi');
        $response->assertSee('Status:');
        $response->assertSee('Persiapan belum lengkap — tahun ajaran tujuan belum tersedia.');
    }

    public function test_halaman_transisi_menggunakan_penamaan_langkah_konsisten(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.transisi.index'));

        $response->assertOk();
        $response->assertSee('Langkah 1 — Siapkan Kelas Tahun Ajaran Baru');
        $response->assertSee('Langkah 2 — Lihat Simulasi Kenaikan & Kelulusan');
        $response->assertSee('Sistem akan menyiapkan kelas untuk tahun ajaran baru');
        $response->assertSee('Simulasi aman dijalankan dan tidak mengubah data siswa');
    }

    public function test_empty_state_dropdown_dan_tombol_disabled_saat_belum_ada_tahun_tujuan(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.transisi.index'));

        $response->assertOk();
        $response->assertSee('Belum ada tahun ajaran tujuan (tambahkan di menu Tahun Ajaran)');
        $response->assertSee('disabled');
        $response->assertSee('Tambahkan tahun ajaran baru terlebih dahulu untuk mengaktifkan tombol ini');
        $response->assertSee('+ Daftarkan Tahun Ajaran Baru');
    }
}
