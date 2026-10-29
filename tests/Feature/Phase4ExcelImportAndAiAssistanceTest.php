<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\BantuanImporAi;
use App\Support\ImporPengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase4ExcelImportAndAiAssistanceTest extends TestCase
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

    private function siapkanDataDasar(): void
    {
        TahunAjaran::create([
            'kode' => '2026/2027',
            'aktif' => true,
            'mulai' => '2026-07-01',
            'selesai' => '2027-06-30',
        ]);

        Jurusan::create([
            'kode' => 'TKJ',
            'nama' => 'Teknik Komputer dan Jaringan',
        ]);

        Kelas::create([
            'nama_kelas' => 'X TKJ 1',
            'tingkat' => 'X',
            'jurusan_kode' => 'TKJ',
            'paralel' => '1',
            'tahun_ajaran_kode' => '2026/2027',
        ]);
    }

    public function test_halaman_impor_dapat_diakses_oleh_admin(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.impor.index'));

        $response->assertOk();
        $response->assertSee('Impor Data Siswa & Guru');
        $response->assertSee('Panduan Persiapan Data Excel');
        $response->assertSee('Bantuan Format Excel dengan AI');
        $response->assertSee('Unduh Template Siswa');
        $response->assertSee('Unduh Template Guru');
    }

    public function test_pengguna_non_admin_dilarang_mengakses_halaman_impor(): void
    {
        $this->siapkanDataDasar();
        $guru = $this->guru();

        $response = $this->actingAs($guru)->get(route('admin.impor.index'));

        $response->assertForbidden();
    }

    public function test_header_template_excel_resmi_sesuai_kontrak(): void
    {
        $kolomSiswa = ImporPengguna::kolom('siswa');
        $headerSiswa = array_values(array_column($kolomSiswa, 'judul'));
        $expectedSiswa = ['Nama Lengkap', 'Email', 'NIS', 'Kelas', 'Ketua Kelas', 'Password', 'Status'];
        $this->assertSame($expectedSiswa, $headerSiswa);

        $kolomGuru = ImporPengguna::kolom('guru');
        $headerGuru = array_values(array_column($kolomGuru, 'judul'));
        $expectedGuru = ['Nama Lengkap', 'Email', 'NIP', 'Jenis Kelamin', 'No. HP', 'Alamat', 'Mata Pelajaran', 'Wali Kelas', 'Password', 'Status'];
        $this->assertSame($expectedGuru, $headerGuru);
    }

    public function test_prompt_ai_memuat_urutan_header_dan_kolom_resmi_secara_persis(): void
    {
        $promptSiswa = BantuanImporAi::promptSiswa('X TKJ 1');
        $expectedHeaderSiswa = implode("\t", ['Nama Lengkap', 'Email', 'NIS', 'Kelas', 'Ketua Kelas', 'Password', 'Status']);
        $this->assertStringContainsString($expectedHeaderSiswa, $promptSiswa);

        $promptGuru = BantuanImporAi::promptGuru('Matematika', 'X TKJ 1');
        $expectedHeaderGuru = implode("\t", ['Nama Lengkap', 'Email', 'NIP', 'Jenis Kelamin', 'No. HP', 'Alamat', 'Mata Pelajaran', 'Wali Kelas', 'Password', 'Status']);
        $this->assertStringContainsString($expectedHeaderGuru, $promptGuru);
    }

    public function test_prompt_ai_mendokumentasikan_bidang_wajib_dan_nilai_yang_didukung(): void
    {
        $promptSiswa = BantuanImporAi::promptSiswa();

        $this->assertStringContainsString('Kolom Nama Lengkap [WAJIB]', $promptSiswa);
        $this->assertStringContainsString('Kolom Email [WAJIB]', $promptSiswa);
        $this->assertStringContainsString('Kolom NIS [WAJIB]', $promptSiswa);
        $this->assertStringContainsString('Kolom Kelas [WAJIB]', $promptSiswa);
        $this->assertStringContainsString('Kolom Ketua Kelas [OPSIONAL]', $promptSiswa);
        $this->assertStringContainsString('Kolom Password [OPSIONAL]', $promptSiswa);
        $this->assertStringContainsString('Kolom Status [OPSIONAL]', $promptSiswa);
        $this->assertStringContainsString('aktif / nonaktif / pending', $promptSiswa);
    }

    public function test_prompt_ai_menegaskan_aturan_format_pengenal_dan_leading_zeros(): void
    {
        $promptSiswa = BantuanImporAi::promptSiswa();
        $this->assertStringContainsString('leading zeros', strtolower($promptSiswa));
        $this->assertStringContainsString('00123', $promptSiswa);
    }

    public function test_prompt_ai_memuat_aturan_ketat_anti_halusinasi(): void
    {
        $promptSiswa = BantuanImporAi::promptSiswa();
        $this->assertStringContainsString('HANYA data mentah yang diberikan', $promptSiswa);
        $this->assertStringContainsString('DILARANG KERAS mengarang/menciptakan', $promptSiswa);
        $this->assertStringContainsString('Jangan pernah menggabungkan dua siswa yang berbeda', $promptSiswa);
    }

    public function test_prompt_ai_memisahkan_data_siap_impor_dan_laporan_validasi(): void
    {
        $promptSiswa = BantuanImporAi::promptSiswa();

        $this->assertStringContainsString('Output A: Data Siap Impor', $promptSiswa);
        $this->assertStringContainsString('Output B: Laporan Validasi', $promptSiswa);
        $this->assertStringContainsString('PERLU KONFIRMASI', $promptSiswa);
        $this->assertStringContainsString('DILARANG KERAS memasukkan teks "PERLU KONFIRMASI" ke dalam tabel "Output A', $promptSiswa);
        $this->assertStringContainsString('TSV (Tab-Separated Values)', $promptSiswa);
    }

    public function test_panduan_dan_prompt_memuat_peringatan_privasi_dan_keamanan(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.impor.index'));
        $response->assertOk();
        $response->assertSee(BantuanImporAi::PERINGATAN_PRIVASI);
        $response->assertSee('Jangan pernah meminta atau mencatat password asli, token akses');

        $promptSiswa = BantuanImporAi::promptSiswa();
        $this->assertStringContainsString(BantuanImporAi::PERINGATAN_PRIVASI, $promptSiswa);
        $this->assertStringContainsString('Jangan pernah meminta atau mencatat password asli', $promptSiswa);
    }

    public function test_panduan_menegaskan_bahwa_impor_bukan_kelulusan_otomatis(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.impor.index'));
        $response->assertOk();
        $response->assertSee('Mengunggah data siswa Kelas XII tidak memproses kelulusan secara otomatis');
        $response->assertSee('Kenaikan & Kelulusan');

        $promptSiswa = BantuanImporAi::promptSiswa();
        $this->assertStringContainsString('Impor spreadsheet siswa ini BUKAN proses kelulusan', $promptSiswa);
    }

    public function test_antarmuka_menyediakan_tombol_salin_dan_skrip_fallback(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.impor.index'));
        $response->assertOk();
        $response->assertSee('Salin Prompt AI Siswa');
        $response->assertSee('Salin Prompt AI Guru');
        $response->assertSee('salinPrompt(');
        $response->assertSee('salinFallback(');
    }

    public function test_unduh_template_siswa_dan_guru_berfungsi(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        $resSiswa = $this->actingAs($admin)->get(route('admin.impor.template', ['jenis' => 'siswa']));
        $resSiswa->assertOk();
        $resSiswa->assertHeader('content-disposition', 'attachment; filename=template-impor-siswa.xlsx');

        $resGuru = $this->actingAs($admin)->get(route('admin.impor.template', ['jenis' => 'guru']));
        $resGuru->assertOk();
        $resGuru->assertHeader('content-disposition', 'attachment; filename=template-impor-guru.xlsx');
    }
}
