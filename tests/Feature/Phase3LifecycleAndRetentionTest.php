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
use App\Models\TinjauanRetensi;
use App\Models\User;
use App\Support\KebijakanRetensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase3LifecycleAndRetentionTest extends TestCase
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
            ['email' => 'guru.test@jurnalkelas.app'],
            [
                'username' => 'guru.test',
                'nama' => 'Guru Penguji',
                'password' => Hash::make('password'),
                'role' => 'guru',
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

    private function kelas(string $nama = 'XII TKJ 1', string $tingkat = 'XII'): Kelas
    {
        return Kelas::firstOrCreate(
            ['nama_kelas' => $nama],
            [
                'tingkat' => $tingkat,
                'jurusan_kode' => $this->jurusan()->kode,
                'paralel' => 1,
                'tahun_ajaran_kode' => $this->tahunAjaran()->kode,
            ]
        );
    }

    private function siswa(string $nis, string $nama, string $status = 'aktif'): Siswa
    {
        return Siswa::create([
            'nis' => $nis,
            'nama' => $nama,
            'status' => $status,
            'kelas_id' => $status === 'aktif' ? $this->kelas()->id : null,
        ]);
    }

    public function test_active_student_lifecycle_classification(): void
    {
        $siswa = $this->siswa('1001', 'Siswa Aktif Belajar', 'aktif');

        $eval = KebijakanRetensi::evaluasi($siswa);

        $this->assertEquals('siswa_aktif', $eval['status_siklus_hidup']);
        $this->assertEquals('Siswa Aktif', $eval['label_siklus_hidup']);
        $this->assertEquals(0, $eval['usia_inaktif_hari']);
        $this->assertFalse($eval['perlu_tinjauan']);
        $this->assertFalse($eval['dapat_dihapus_permanen']);
        $this->assertStringContainsString('Siswa masih berstatus aktif', $eval['alasan_pemblokiran'][0]);
    }

    public function test_alumni_lifecycle_and_retention_threshold_boundaries(): void
    {
        $tahun = $this->tahunAjaran('2026/2027');
        $kelas = $this->kelas();

        // Alumni 1: Lulus baru (2 bulan lalu, < 1 tahun)
        $siswaBaru = $this->siswa('2001', 'Alumni Baru', 'lulus');
        $alumniBaru = Alumni::create([
            'siswa_nis' => $siswaBaru->nis,
            'tahun_lulus' => $tahun->kode,
            'tanggal_lulus' => now()->subMonths(2)->toDateString(),
            'kelas_terakhir_id' => $kelas->id,
        ]);

        $evalBaru = KebijakanRetensi::evaluasi($siswaBaru);
        $this->assertEquals('alumni_aktif', $evalBaru['status_siklus_hidup']);
        $this->assertEquals('kurang_dari_1_tahun', $evalBaru['ambang_retensi']);
        $this->assertFalse($evalBaru['perlu_tinjauan']);

        // Alumni 2: Lulus 1.5 tahun lalu (antara 1 dan 2 tahun -> tinjauan retensi)
        $this->tahunAjaran('2025/2026', aktif: false);
        $siswaMid = $this->siswa('2002', 'Alumni Satu Setengah Tahun', 'lulus');
        $alumniMid = Alumni::create([
            'siswa_nis' => $siswaMid->nis,
            'tahun_lulus' => '2025/2026',
            'tanggal_lulus' => now()->subDays(500)->toDateString(),
            'kelas_terakhir_id' => $kelas->id,
        ]);

        $evalMid = KebijakanRetensi::evaluasi($siswaMid);
        $this->assertEquals('tinjauan_retensi', $evalMid['status_siklus_hidup']);
        $this->assertEquals('1_sampai_2_tahun', $evalMid['ambang_retensi']);
        $this->assertTrue($evalMid['perlu_tinjauan']);
    }

    public function test_records_older_than_two_years_blocked_by_historical_dependencies(): void
    {
        $tahun = $this->tahunAjaran('2024/2025');
        $kelas = $this->kelas();

        // Alumni lulus 3 tahun lalu (> 2 tahun), memiliki riwayat presensi harian
        $siswaLama = $this->siswa('3001', 'Alumni Bersejarah', 'lulus');
        Alumni::create([
            'siswa_nis' => $siswaLama->nis,
            'tahun_lulus' => $tahun->kode,
            'tanggal_lulus' => now()->subDays(1100)->toDateString(),
            'kelas_terakhir_id' => $kelas->id,
        ]);

        PresensiHarian::create([
            'kelas_id' => $kelas->id,
            'tanggal' => now()->subDays(1150)->toDateString(),
            'siswa_nis' => $siswaLama->nis,
            'status' => 'hadir',
        ]);

        $eval = KebijakanRetensi::evaluasi($siswaLama);

        $this->assertEquals('lebih_dari_2_tahun', $eval['ambang_retensi']);
        $this->assertTrue($eval['memiliki_dependensi']);
        $this->assertEquals('terblokir_dependensi', $eval['status_siklus_hidup']);
        $this->assertStringContainsString('DIBLOKIR: Memiliki Riwayat Pendidikan Historis', $eval['status_kelayakan']);
        $this->assertFalse($eval['dapat_dihapus_permanen']);
    }

    public function test_records_older_than_two_years_without_dependencies_show_safe_review_message(): void
    {
        // Siswa nonaktif 3 tahun lalu tanpa presensi/jurnal (misal akun uji/salah daftar)
        $siswaTanpaJejak = $this->siswa('4001', 'Siswa Tanpa Riwayat', 'nonaktif');
        $siswaTanpaJejak->created_at = now()->subDays(900);
        $siswaTanpaJejak->updated_at = now()->subDays(900);
        $siswaTanpaJejak->saveQuietly();

        $eval = KebijakanRetensi::evaluasi($siswaTanpaJejak);

        $this->assertEquals('lebih_dari_2_tahun', $eval['ambang_retensi']);
        $this->assertFalse($eval['memiliki_dependensi']);
        $this->assertEquals('layak_tinjau_hapus', $eval['status_siklus_hidup']);
        $this->assertStringContainsString('Memenuhi syarat untuk ditinjau lebih lanjut. Penghapusan permanen belum diaktifkan.', $eval['status_kelayakan']);
        $this->assertFalse($eval['dapat_dihapus_permanen']);
    }

    public function test_unauthorized_users_forbidden_from_alumni_and_lifecycle_review(): void
    {
        $guru = $this->guru();
        $admin = $this->admin();
        $kelas = $this->kelas();

        $siswa = $this->siswa('5001', 'Alumni Otorisasi', 'lulus');
        $alumni = Alumni::create([
            'siswa_nis' => $siswa->nis,
            'tahun_lulus' => '2026/2027',
            'kelas_terakhir_id' => $kelas->id,
        ]);

        // Guru dilarang akses index alumni
        $this->actingAs($guru)->get('/admin/alumni')->assertForbidden();

        // Guru dilarang akses detail inspeksi retensi
        $this->actingAs($guru)->get("/admin/alumni/{$alumni->id}")->assertForbidden();

        // Guru dilarang mencatat tinjauan administratif
        $this->actingAs($guru)->post("/admin/alumni/{$alumni->id}/tinjauan", [
            'status_tinjauan' => 'ditinjau',
        ])->assertForbidden();

        // Admin diizinkan akses
        $this->actingAs($admin)->get('/admin/alumni')->assertOk();
        $this->actingAs($admin)->get("/admin/alumni/{$alumni->id}")->assertOk();
    }

    public function test_administrative_review_recording_and_audit_log(): void
    {
        $admin = $this->admin();
        $kelas = $this->kelas();

        $siswa = $this->siswa('6001', 'Alumni Ditinjau', 'lulus');
        $alumni = Alumni::create([
            'siswa_nis' => $siswa->nis,
            'tahun_lulus' => '2026/2027',
            'kelas_terakhir_id' => $kelas->id,
        ]);

        $response = $this->actingAs($admin)->post("/admin/alumni/{$alumni->id}/tinjauan", [
            'status_tinjauan' => TinjauanRetensi::STATUS_DISETUJUI_ARSIP,
            'catatan' => 'Data riwayat lengkap, disetujui untuk diarsipkan secara aman.',
        ]);

        $response->assertRedirect("/admin/alumni/{$alumni->id}");
        $response->assertSessionHas('success');

        // Verifikasi audit log admin
        $this->assertDatabaseHas('admin_audit_log', [
            'aksi' => 'retensi.tinjauan',
            'tipe_entitas' => 'alumni',
            'entitas_id' => $siswa->nis,
            'status' => 'berhasil',
        ]);

        $log = AdminAuditLog::where('aksi', 'retensi.tinjauan')->latest()->firstOrFail();
        $this->assertEquals(TinjauanRetensi::STATUS_DISETUJUI_ARSIP, $log->metadata['status_tinjauan']);
    }

    public function test_explicit_account_deactivation_for_alumni(): void
    {
        $admin = $this->admin();
        $kelas = $this->kelas();

        $siswa = $this->siswa('7001', 'Alumni Akun', 'lulus');
        $alumni = Alumni::create([
            'siswa_nis' => $siswa->nis,
            'tahun_lulus' => '2026/2027',
            'kelas_terakhir_id' => $kelas->id,
        ]);

        $akun = User::create([
            'username' => 'alumni.7001',
            'email' => 'alumni7001@jurnalkelas.app',
            'password' => Hash::make('password'),
            'role' => 'siswa',
            'nis' => $siswa->nis,
            'status' => 'aktif',
        ]);

        // Nonaktifkan akun secara eksplisit
        $this->actingAs($admin)->post("/admin/alumni/{$alumni->id}/akun");
        $akun->refresh();
        $this->assertEquals('nonaktif', $akun->status);

        // Aktifkan kembali akun
        $this->actingAs($admin)->post("/admin/alumni/{$alumni->id}/akun");
        $akun->refresh();
        $this->assertEquals('aktif', $akun->status);
    }
}
