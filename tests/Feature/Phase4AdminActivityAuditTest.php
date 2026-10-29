<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase4AdminActivityAuditTest extends TestCase
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

        $kelas = Kelas::create([
            'nama_kelas' => 'X TKJ 1',
            'tingkat' => 'X',
            'jurusan_kode' => 'TKJ',
            'paralel' => '1',
            'tahun_ajaran_kode' => '2026/2027',
        ]);

        Siswa::create([
            'nis' => '10001',
            'nisn' => '0000000001',
            'nama' => 'Siswa Pengujian',
            'kelas_id' => $kelas->id,
            'status' => 'aktif',
        ]);
    }

    public function test_admin_dapat_mengakses_tab_aktivitas_admin(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        AdminAuditLog::create([
            'user_id' => $admin->id,
            'nama_user' => 'Administrator Utama',
            'role_user' => 'admin',
            'aksi' => 'siswa.tambah',
            'tipe_entitas' => 'siswa',
            'entitas_id' => '10001',
            'status' => 'berhasil',
            'deskripsi' => 'Menambahkan siswa baru untuk pengujian',
            'metadata' => ['nis' => '10001', 'nama' => 'Siswa Pengujian'],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.sistem.index', ['tab' => 'aktivitas-admin']));

        $response->assertOk();
        $response->assertSee('Aktivitas Admin');
        $response->assertSee('Log Aktivitas & Audit Administratif');
        $response->assertSee('Menambahkan siswa baru untuk pengujian');
        $response->assertSee('siswa.tambah');
    }

    public function test_non_admin_dilarang_mengakses_tab_aktivitas_admin(): void
    {
        $this->siapkanDataDasar();
        $guru = $this->guru();

        $response = $this->actingAs($guru)->get(route('admin.sistem.index', ['tab' => 'aktivitas-admin']));

        $response->assertForbidden();
    }

    public function test_paginasi_aktivitas_admin_berfungsi(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        for ($i = 1; $i <= 25; $i++) {
            AdminAuditLog::create([
                'user_id' => $admin->id,
                'nama_user' => 'Administrator Utama',
                'role_user' => 'admin',
                'aksi' => 'pengujian.aksi',
                'tipe_entitas' => 'uji',
                'entitas_id' => (string) $i,
                'status' => 'berhasil',
                'deskripsi' => "Log audit pengujian nomor {$i}",
                'created_at' => now()->subMinutes(30 - $i),
            ]);
        }

        // Halaman 1 (default 20 item per halaman)
        $res1 = $this->actingAs($admin)->get(route('admin.sistem.index', ['tab' => 'aktivitas-admin']));
        $res1->assertOk();
        $res1->assertSee('Menampilkan 1–20');

        // Halaman 2 (sisa 5 item)
        $res2 = $this->actingAs($admin)->get(route('admin.sistem.index', [
            'tab' => 'aktivitas-admin',
            'audit_page' => 2,
        ]));
        $res2->assertOk();
        $res2->assertSee('Menampilkan 21–25');
    }

    public function test_filter_rentang_tanggal_berfungsi(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        // Rekaman Mei 2026
        AdminAuditLog::create([
            'user_id' => $admin->id,
            'nama_user' => 'Administrator Utama',
            'role_user' => 'admin',
            'aksi' => 'siswa.tambah',
            'tipe_entitas' => 'siswa',
            'entitas_id' => '10001',
            'status' => 'berhasil',
            'deskripsi' => 'Aktivitas bulan Mei 2026',
            'created_at' => Carbon::parse('2026-05-10 10:00:00'),
        ]);

        // Rekaman Juni 2026
        AdminAuditLog::create([
            'user_id' => $admin->id,
            'nama_user' => 'Administrator Utama',
            'role_user' => 'admin',
            'aksi' => 'siswa.ubah',
            'tipe_entitas' => 'siswa',
            'entitas_id' => '10001',
            'status' => 'berhasil',
            'deskripsi' => 'Aktivitas bulan Juni 2026',
            'created_at' => Carbon::parse('2026-06-15 10:00:00'),
        ]);

        // Filter rentang Juni 2026
        $response = $this->actingAs($admin)->get(route('admin.sistem.index', [
            'tab' => 'aktivitas-admin',
            'audit_dari' => '2026-06-01',
            'audit_sampai' => '2026-06-30',
        ]));

        $response->assertOk();
        $response->assertSee('Aktivitas bulan Juni 2026');
        $response->assertDontSee('Aktivitas bulan Mei 2026');
    }

    public function test_filter_aksi_dan_status_berfungsi(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        AdminAuditLog::create([
            'user_id' => $admin->id,
            'nama_user' => 'Administrator Utama',
            'role_user' => 'admin',
            'aksi' => 'siswa.tambah',
            'tipe_entitas' => 'siswa',
            'entitas_id' => '10001',
            'status' => 'berhasil',
            'deskripsi' => 'Operasi tambah berhasil',
            'created_at' => now(),
        ]);

        AdminAuditLog::create([
            'user_id' => $admin->id,
            'nama_user' => 'Administrator Utama',
            'role_user' => 'admin',
            'aksi' => 'siswa.hapus',
            'tipe_entitas' => 'siswa',
            'entitas_id' => '10001',
            'status' => 'gagal',
            'deskripsi' => 'Operasi hapus gagal',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.sistem.index', [
            'tab' => 'aktivitas-admin',
            'audit_aksi' => 'siswa.tambah',
            'audit_status' => 'berhasil',
        ]));

        $response->assertOk();
        $response->assertSee('Operasi tambah berhasil');
        $response->assertDontSee('Operasi hapus gagal');
    }

    public function test_filter_pelaksana_dan_kata_kunci_berfungsi(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        AdminAuditLog::create([
            'user_id' => $admin->id,
            'nama_user' => 'AdminSatu',
            'role_user' => 'admin',
            'aksi' => 'impor.siswa',
            'tipe_entitas' => 'impor',
            'entitas_id' => 'siswa_2026.xlsx',
            'status' => 'berhasil',
            'deskripsi' => 'Impor batch excel siswa baru',
            'created_at' => now(),
        ]);

        AdminAuditLog::create([
            'user_id' => $admin->id,
            'nama_user' => 'AdminDua',
            'role_user' => 'admin',
            'aksi' => 'cadangan.buat',
            'tipe_entitas' => 'cadangan',
            'entitas_id' => 'dump.sql',
            'status' => 'berhasil',
            'deskripsi' => 'Cadangan database berkala',
            'created_at' => now(),
        ]);

        // Filter kata kunci
        $resCari = $this->actingAs($admin)->get(route('admin.sistem.index', [
            'tab' => 'aktivitas-admin',
            'audit_q' => 'excel',
        ]));
        $resCari->assertOk();
        $resCari->assertSee('Impor batch excel siswa baru');
        $resCari->assertDontSee('Cadangan database berkala');

        // Filter pelaksana
        $resUser = $this->actingAs($admin)->get(route('admin.sistem.index', [
            'tab' => 'aktivitas-admin',
            'audit_user' => 'AdminDua',
        ]));
        $resUser->assertOk();
        $resUser->assertSee('Cadangan database berkala');
        $resUser->assertDontSee('Impor batch excel siswa baru');
    }

    public function test_metadata_sensitif_tidak_diekspos_pada_antarmuka_dan_model(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        $log = AdminAuditLog::create([
            'user_id' => $admin->id,
            'nama_user' => 'Administrator Utama',
            'role_user' => 'admin',
            'aksi' => 'uji.sensitif',
            'tipe_entitas' => 'keamanan',
            'entitas_id' => '999',
            'status' => 'berhasil',
            'deskripsi' => 'Pengujian keamanan metadata',
            'metadata' => [
                'nis' => '10001',
                'nama' => 'Siswa Aman',
                'password' => 'supersecretpassword',
                'token' => 'access_token_rahasia_123',
                'secret_key' => 'kunci_rahasia_sistem',
                'catatan' => 'Catatan pribadi sensitif siswa',
                'nested' => [
                    'info_publik' => 'publik',
                    'session_id' => 'sess_secret_xyz',
                ],
            ],
            'created_at' => now(),
        ]);

        // 1. Verifikasi pada tingkat model accessor metadataSanitasi()
        $sanitasi = $log->metadataSanitasi();
        $this->assertArrayHasKey('nis', $sanitasi);
        $this->assertArrayHasKey('nama', $sanitasi);
        $this->assertArrayNotHasKey('password', $sanitasi);
        $this->assertArrayNotHasKey('token', $sanitasi);
        $this->assertArrayNotHasKey('secret_key', $sanitasi);
        $this->assertArrayNotHasKey('catatan', $sanitasi);
        $this->assertArrayHasKey('info_publik', $sanitasi['nested']);
        $this->assertArrayNotHasKey('session_id', $sanitasi['nested']);

        // 2. Verifikasi pada respons HTTP view antarmuka
        $response = $this->actingAs($admin)->get(route('admin.sistem.index', ['tab' => 'aktivitas-admin']));
        $response->assertOk();
        $response->assertSee('Siswa Aman');
        $response->assertDontSee('supersecretpassword');
        $response->assertDontSee('access_token_rahasia_123');
        $response->assertDontSee('kunci_rahasia_sistem');
        $response->assertDontSee('Catatan pribadi sensitif siswa');
        $response->assertDontSee('sess_secret_xyz');
    }

    public function test_kondisi_kosong_menampilkan_pesan_yang_jelas(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.sistem.index', [
            'tab' => 'aktivitas-admin',
            'audit_q' => 'pencarian_yang_tidak_mungkin_ada_123456',
        ]));

        $response->assertOk();
        $response->assertSee('Belum ada catatan aktivitas admin yang sesuai dengan filter.');
    }

    public function test_tab_log_sistem_tetap_berfungsi_dan_dapat_diakses(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.sistem.index', ['tab' => 'log-sistem']));

        $response->assertOk();
        $response->assertSee('Log Sistem & Error');
        $response->assertSee('Laporan Error dari Pengguna');
        $response->assertSee('Log Aplikasi Terbaru');
    }

    public function test_widget_pengingat_dan_status_komponen_tetap_terlihat_di_kedua_tab(): void
    {
        $this->siapkanDataDasar();
        $admin = $this->admin();

        // Periksa tab log-sistem
        $resLog = $this->actingAs($admin)->get(route('admin.sistem.index', ['tab' => 'log-sistem']));
        $resLog->assertOk();
        $resLog->assertSee('Status Komponen');
        $resLog->assertSee('Pengingat Administrasi Data');

        // Periksa tab aktivitas-admin
        $resAudit = $this->actingAs($admin)->get(route('admin.sistem.index', ['tab' => 'aktivitas-admin']));
        $resAudit->assertOk();
        $resAudit->assertSee('Status Komponen');
        $resAudit->assertSee('Pengingat Administrasi Data');
    }
}
