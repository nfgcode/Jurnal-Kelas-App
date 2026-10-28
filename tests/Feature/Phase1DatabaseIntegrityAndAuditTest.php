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
use App\Support\AuditAdmin;
use App\Support\CadanganData;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class Phase1DatabaseIntegrityAndAuditTest extends TestCase
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

    private function tahunAjaran(string $kode = '2026/2027'): TahunAjaran
    {
        return TahunAjaran::firstOrCreate(
            ['kode' => $kode],
            ['aktif' => true, 'mulai' => '2026-07-01', 'selesai' => '2027-06-30']
        );
    }

    private function jurusan(): Jurusan
    {
        return Jurusan::firstOrCreate(
            ['kode' => 'TKJ'],
            ['nama' => 'Teknik Komputer dan Jaringan', 'aktif' => true]
        );
    }

    private function kelas(string $nama = 'X TKJ 1', string $tingkat = 'X'): Kelas
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

    private function siswa(string $nis = '2026001', string $nama = 'Siswa Uji'): Siswa
    {
        return Siswa::create([
            'nis' => $nis,
            'nama' => $nama,
            'kelas_id' => $this->kelas()->id,
            'status' => 'aktif',
        ]);
    }

    public function test_student_with_attendance_is_protected_from_deletion_via_controller(): void
    {
        $admin = $this->admin();
        $siswa = $this->siswa();

        PresensiHarian::create([
            'kelas_id' => $siswa->kelas_id,
            'tanggal' => now()->toDateString(),
            'siswa_nis' => $siswa->nis,
            'status' => 'hadir',
        ]);

        $response = $this->actingAs($admin)
            ->from('/admin/siswa')
            ->delete("/admin/siswa/{$siswa->nis}");

        $response->assertRedirect('/admin/siswa');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('siswa', ['nis' => $siswa->nis]);

        // Audit log should record the rejection
        $this->assertDatabaseHas('admin_audit_log', [
            'aksi' => 'siswa.hapus_ditolak',
            'entitas_id' => $siswa->nis,
            'status' => 'gagal',
        ]);
    }

    public function test_student_with_attendance_is_protected_from_deletion_via_model_hook(): void
    {
        $siswa = $this->siswa('2026002', 'Siswa Dua');

        PresensiHarian::create([
            'kelas_id' => $siswa->kelas_id,
            'tanggal' => now()->toDateString(),
            'siswa_nis' => $siswa->nis,
            'status' => 'hadir',
        ]);

        $this->expectException(RuntimeException::class);
        $siswa->delete();
    }

    public function test_alumni_record_uniqueness_and_relationships(): void
    {
        $siswa = $this->siswa('2026003', 'Siswa Tiga');
        $tahun = $this->tahunAjaran();

        $alumni = Alumni::create([
            'siswa_nis' => $siswa->nis,
            'tahun_lulus' => $tahun->kode,
            'tanggal_lulus' => '2027-05-20',
            'kelas_terakhir_id' => $siswa->kelas_id,
            'keterangan' => 'Kuliah di PTN',
        ]);

        $this->assertTrue($siswa->alumni->is($alumni));
        $this->assertTrue($alumni->siswa->is($siswa));
        $this->assertEquals($tahun->kode, $alumni->tahunLulus->kode);

        // Enforce uniqueness: only 1 alumni record per student
        $this->expectException(QueryException::class);
        Alumni::create([
            'siswa_nis' => $siswa->nis,
            'tahun_lulus' => $tahun->kode,
        ]);
    }

    public function test_alumni_protects_student_from_deletion(): void
    {
        $admin = $this->admin();
        $siswa = $this->siswa('2026004', 'Siswa Empat');

        Alumni::create([
            'siswa_nis' => $siswa->nis,
            'tahun_lulus' => $this->tahunAjaran()->kode,
        ]);

        $response = $this->actingAs($admin)
            ->from('/admin/siswa')
            ->delete("/admin/siswa/{$siswa->nis}");

        $response->assertRedirect('/admin/siswa');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('siswa', ['nis' => $siswa->nis]);
    }

    public function test_historical_class_membership_supports_transfers_and_returns_without_overwriting(): void
    {
        $siswa = $this->siswa('2026005', 'Siswa Lima');
        $tahun = $this->tahunAjaran();
        $kelas1 = $this->kelas('X TKJ 1', 'X');
        $kelas2 = Kelas::create([
            'nama_kelas' => 'X TKJ 2',
            'tingkat' => 'X',
            'jurusan_kode' => $this->jurusan()->kode,
            'paralel' => 2,
            'tahun_ajaran_kode' => $tahun->kode,
        ]);

        // 1. Keanggotaan awal: X TKJ 1
        $r1 = RiwayatKelasSiswa::daftarkan($siswa, $kelas1, $tahun->kode, '2026-07-15', 'Awal masuk');
        $this->assertTrue($r1->aktif);
        $this->assertEquals('aktif', $r1->status);

        // 2. Mutasi tengah tahun: pindah ke X TKJ 2
        $r2 = RiwayatKelasSiswa::mutasiKelas($siswa, $kelas2, $tahun->kode, 'Pindah minat rombel', '2026-10-15');
        $this->assertTrue($r2->aktif);
        $this->assertEquals('aktif', $r2->status);

        $r1->refresh();
        $this->assertFalse($r1->aktif);
        $this->assertEquals('pindah', $r1->status);
        $this->assertEquals('2026-10-15', $r1->tanggal_selesai->toDateString());

        // 3. Kembali lagi ke X TKJ 1 pada semester berikutnya
        $r3 = RiwayatKelasSiswa::mutasiKelas($siswa, $kelas1, $tahun->kode, 'Kembali ke rombel asal', '2027-01-10');
        $this->assertTrue($r3->aktif);
        $this->assertEquals('aktif', $r3->status);

        $r2->refresh();
        $this->assertFalse($r2->aktif);
        $this->assertEquals('pindah', $r2->status);

        // Verifikasi: Seluruh 3 rekaman historis tersimpan utuh dan tidak saling menimpa
        $semuaRiwayat = RiwayatKelasSiswa::where('siswa_nis', $siswa->nis)->orderBy('id')->get();
        $this->assertCount(3, $semuaRiwayat);
        $this->assertEquals(1, $semuaRiwayat->where('aktif', true)->count());
    }

    public function test_historical_class_membership_prevents_overlapping_active_memberships(): void
    {
        $siswa = $this->siswa('2026006', 'Siswa Enam');
        $tahun = $this->tahunAjaran();
        $kelas = $this->kelas();

        RiwayatKelasSiswa::daftarkan($siswa, $kelas, $tahun->kode, '2026-07-15');

        // Mencoba membuat keanggotaan aktif kedua tanpa menutup yang pertama harus melempar DomainException
        $this->expectException(\DomainException::class);
        RiwayatKelasSiswa::create([
            'siswa_nis' => $siswa->nis,
            'kelas_id' => $kelas->id,
            'tahun_ajaran_kode' => $tahun->kode,
            'tingkat' => 'X',
            'status' => 'aktif',
            'aktif' => true,
        ]);
    }

    public function test_audit_admin_catat_kritis_and_metadata_sanitization(): void
    {
        $admin = $this->admin();

        $metadataSensitif = [
            'nis' => '2026001',
            'password' => 'secret123',
            'remember_token' => 'xyz',
            'nested' => [
                'token' => 'bearer-123',
                'aman' => 'informasi publik',
            ],
        ];

        $log = AuditAdmin::catatKritis(
            aksi: 'tes.kritis',
            tipeEntitas: 'tes',
            entitasId: '1',
            deskripsi: 'Pengujian audit kritis',
            metadata: $metadataSensitif,
            user: $admin,
        );

        $this->assertNotNull($log);
        $this->assertEquals('tes.kritis', $log->aksi);
        $this->assertArrayNotHasKey('password', $log->metadata);
        $this->assertArrayNotHasKey('remember_token', $log->metadata);
        $this->assertArrayNotHasKey('token', $log->metadata['nested']);
        $this->assertEquals('informasi publik', $log->metadata['nested']['aman']);
    }

    public function test_backfill_riwayat_kelas_service_and_idempotency(): void
    {
        // Siapkan 3 siswa
        $this->siswa('2026101', 'Siswa Backfill 1');
        $this->siswa('2026102', 'Siswa Backfill 2');
        $siswaTanpaKelas = Siswa::create([
            'nis' => '2026103',
            'nama' => 'Siswa Tanpa Kelas',
            'kelas_id' => null,
            'status' => 'aktif',
        ]);

        // 1. Dry run: verifikasi kalkulasi tanpa penulisan
        $dryRun = \App\Support\BackfillRiwayatKelas::jalankan(dryRun: true);
        $this->assertTrue($dryRun['dry_run']);
        $this->assertEquals(2, $dryRun['siap_ditambahkan']);
        $this->assertEquals(1, $dryRun['siswa_tanpa_kelas']);
        $this->assertEquals(0, RiwayatKelasSiswa::count());

        // 2. Eksekusi nyata: verifikasi penulisan
        $eksekusi = \App\Support\BackfillRiwayatKelas::jalankan(dryRun: false);
        $this->assertFalse($eksekusi['dry_run']);
        $this->assertTrue($eksekusi['eksekusi_berhasil']);
        $this->assertEquals(2, $eksekusi['siap_ditambahkan']);
        $this->assertEquals(2, RiwayatKelasSiswa::count());

        // 3. Eksekusi ulang: verifikasi idempotensi (tidak ada duplikasi)
        $ulang = \App\Support\BackfillRiwayatKelas::jalankan(dryRun: false);
        $this->assertEquals(0, $ulang['siap_ditambahkan']);
        $this->assertEquals(2, $ulang['dilewati_sudah_ada']);
        $this->assertEquals(2, RiwayatKelasSiswa::count());
    }

    public function test_cadangan_replace_restore_preserves_admin_audit_log(): void
    {
        $admin = $this->admin();

        // Buat log audit awal
        AuditAdmin::catat(
            aksi: 'audit.awal',
            tipeEntitas: 'sistem',
            entitasId: null,
            deskripsi: 'Audit sebelum pemulihan cadangan',
            user: $admin,
        );

        $this->assertEquals(1, AdminAuditLog::count());

        // Data cadangan tanpa admin_audit_log (misal cadangan lama)
        $data = [
            'versi' => '1.0',
            'tabel' => [
                'jurusan' => [
                    ['kode' => 'RPL', 'nama' => 'Rekayasa Perangkat Lunak', 'aktif' => 1],
                ],
            ],
        ];

        $cadangan = app(CadanganData::class);
        $cadangan->pulihkan($data, 'ganti');

        // Tabel admin_audit_log TIDAK boleh terhapus oleh restore mode 'ganti'
        $this->assertDatabaseHas('admin_audit_log', [
            'aksi' => 'audit.awal',
        ]);
        $this->assertDatabaseHas('jurusan', [
            'kode' => 'RPL',
        ]);
    }

    public function test_academic_year_transition_uniqueness_and_lifecycle(): void
    {
        $admin = $this->admin();
        $tahunAsal = $this->tahunAjaran('2026/2027');
        $tahunTujuan = TahunAjaran::create([
            'kode' => '2027/2028',
            'aktif' => false,
            'mulai' => '2027-07-01',
            'selesai' => '2028-06-30',
        ]);

        $transisi = TransisiTahunAjaran::create([
            'tahun_asal' => $tahunAsal->kode,
            'tahun_tujuan' => $tahunTujuan->kode,
            'status' => TransisiTahunAjaran::STATUS_PERSIAPAN,
            'diproses_oleh_id' => $admin->id,
        ]);

        $this->assertEquals(TransisiTahunAjaran::STATUS_PERSIAPAN, $transisi->status);
        $this->assertTrue($transisi->diprosesOleh->is($admin));

        // Unique constraint for transition between same source and destination
        $this->expectException(QueryException::class);
        TransisiTahunAjaran::create([
            'tahun_asal' => $tahunAsal->kode,
            'tahun_tujuan' => $tahunTujuan->kode,
            'status' => TransisiTahunAjaran::STATUS_PERSIAPAN,
        ]);
    }

    public function test_admin_audit_log_captures_student_mutations(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/admin/siswa', [
            'nis' => '2026099',
            'nama' => 'Siswa Baru Audit',
            'email' => 'siswa.audit@test.app',
            'username' => 'siswa.audit',
            'password' => 'password123',
            'status' => 'aktif',
            'kelas_id' => $this->kelas()->id,
        ]);

        $response->assertRedirect('/admin/siswa');

        $this->assertDatabaseHas('admin_audit_log', [
            'aksi' => 'siswa.tambah',
            'entitas_id' => '2026099',
            'status' => 'berhasil',
        ]);

        $siswa = Siswa::where('nis', '2026099')->firstOrFail();

        $this->actingAs($admin)->put("/admin/siswa/{$siswa->nis}", [
            'nama' => 'Siswa Baru Audit Diedit',
            'email' => 'siswa.audit.baru@test.app',
            'username' => 'siswa.audit',
            'status' => 'aktif',
            'kelas_id' => $this->kelas()->id,
        ]);

        $this->assertDatabaseHas('admin_audit_log', [
            'aksi' => 'siswa.ubah',
            'entitas_id' => '2026099',
            'status' => 'berhasil',
        ]);
    }

    public function test_cadangan_data_includes_new_phase1_tables(): void
    {
        $tabel = CadanganData::semuaTabel();

        $this->assertContains('alumni', $tabel);
        $this->assertContains('riwayat_kelas_siswa', $tabel);
        $this->assertContains('transisi_tahun_ajaran', $tabel);
        $this->assertContains('admin_audit_log', $tabel);
    }
}
