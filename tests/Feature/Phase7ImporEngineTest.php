<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use App\Support\ImporEngine;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase7ImporEngineTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $this->actingAs($user);

        return $user;
    }

    private function buatFileCsv(array $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'test_imp_') . '.csv';
        $fh = fopen($path, 'w');
        foreach ($lines as $line) {
            fputcsv($fh, $line);
        }
        fclose($fh);

        return $path;
    }

    public function test_mode_tambah_creates_new_records_and_rejects_duplicate(): void
    {
        $admin = $this->admin();
        $kelas = $this->buatKelas(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X', 'jurusan_kode' => 'RPL', 'tahun_ajaran_kode' => '2026/2027']);

        // Seed satu siswa existing
        $sExisting = Siswa::create(['nis' => 'S001', 'nama' => 'Existing', 'kelas_id' => $kelas->id, 'status' => 'aktif']);
        $uExisting = User::create(['name' => 'Existing', 'username' => 'S001', 'email' => 'exist@sekolah.sch.id', 'nis' => 'S001', 'role' => 'siswa', 'password' => 'secret123']);
        $sExisting->update(['user_id' => $uExisting->id]);

        $csv = $this->buatFileCsv([
            ['Nama Lengkap', 'Email', 'NIS', 'Kelas'],
            ['Siswa Baru', 'baru@sekolah.sch.id', 'S002', 'X RPL 1'],
            ['Siswa Duplikat', 'exist@sekolah.sch.id', 'S001', 'X RPL 1'],
        ]);

        $preview = ImporEngine::periksa($csv, ImporEngine::KATEGORI_SISWA, ImporEngine::MODE_TAMBAH);

        $this->assertNull($preview['fatal']);
        $this->assertSame(1, $preview['ringkas']['baru']);
        $this->assertSame(1, $preview['ringkas']['konflik']);

        // Commit preview
        $res = ImporEngine::commit(
            $csv,
            ImporEngine::KATEGORI_SISWA,
            ImporEngine::MODE_TAMBAH,
            $preview['fingerprint'],
            [],
            'password123',
            $admin
        );

        $this->assertSame(1, $res['baru']);
        $this->assertSame(0, $res['diperbarui']);
        $this->assertSame(1, $res['dilewati']); // Baris konflik dilewati

        $this->assertDatabaseHas('users', ['nis' => 'S002', 'email' => 'baru@sekolah.sch.id']);
        unlink($csv);
    }

    public function test_mode_perbarui_updates_matched_records_and_rejects_unmatched(): void
    {
        $admin = $this->admin();
        $kelas = $this->buatKelas(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X', 'jurusan_kode' => 'RPL', 'tahun_ajaran_kode' => '2026/2027']);

        $s1 = Siswa::create(['nis' => 'S001', 'nama' => 'Old Name', 'status' => 'aktif', 'kelas_id' => $kelas->id]);
        $user = User::create(['nama' => 'Old Name', 'username' => 'S001', 'email' => 'old@sekolah.sch.id', 'nis' => 'S001', 'role' => 'siswa', 'password' => 'secret123', 'status' => 'aktif']);
        $s1->update(['user_id' => $user->id]);

        $csv = $this->buatFileCsv([
            ['Nama Lengkap', 'Email', 'NIS', 'Kelas'],
            ['Updated Name', 'updated@sekolah.sch.id', 'S001', 'X RPL 1'],
            ['Unmatched Student', 'unmatched@sekolah.sch.id', 'S999', 'X RPL 1'],
        ]);

        $preview = ImporEngine::periksa($csv, ImporEngine::KATEGORI_SISWA, ImporEngine::MODE_PERBARUI);

        $this->assertSame(1, $preview['ringkas']['diperbarui']);
        $this->assertSame(1, $preview['ringkas']['konflik']);

        $res = ImporEngine::commit(
            $csv,
            ImporEngine::KATEGORI_SISWA,
            ImporEngine::MODE_PERBARUI,
            $preview['fingerprint'],
            [],
            null,
            $admin
        );

        $this->assertSame(1, $res['diperbarui']);
        $this->assertSame(0, $res['baru']);
        $this->assertSame(1, $res['dilewati']);

        $this->assertDatabaseHas('users', ['nis' => 'S001', 'nama' => 'Updated Name', 'email' => 'updated@sekolah.sch.id']);
        $this->assertDatabaseMissing('users', ['nis' => 'S999']);
        unlink($csv);
    }

    public function test_mode_ganti_keseluruhan_performs_safe_sync_and_deactivates_missing_records(): void
    {
        $admin = $this->admin();
        $kelas = $this->buatKelas(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X', 'jurusan_kode' => 'RPL', 'tahun_ajaran_kode' => '2026/2027']);

        // 2 Siswa aktif di database
        $s1 = Siswa::create(['nis' => 'S001', 'nama' => 'Tetap Ada', 'status' => 'aktif', 'kelas_id' => $kelas->id]);
        $u1 = User::create(['nama' => 'Tetap Ada', 'username' => 'S001', 'email' => 'stay@sekolah.sch.id', 'nis' => 'S001', 'role' => 'siswa', 'password' => 'secret', 'status' => 'aktif']);
        $s1->update(['user_id' => $u1->id]);

        $s2 = Siswa::create(['nis' => 'S002', 'nama' => 'Akan Dideaktivasi', 'status' => 'aktif', 'kelas_id' => $kelas->id]);
        $u2 = User::create(['nama' => 'Akan Dideaktivasi', 'username' => 'S002', 'email' => 'gone@sekolah.sch.id', 'nis' => 'S002', 'role' => 'siswa', 'password' => 'secret', 'status' => 'aktif']);
        $s2->update(['user_id' => $u2->id]);

        // Berkas pengganti hanya memuat S001 dan siswa baru S003
        $csv = $this->buatFileCsv([
            ['Nama Lengkap', 'Email', 'NIS', 'Kelas'],
            ['Tetap Ada Updated', 'stay@sekolah.sch.id', 'S001', 'X RPL 1'],
            ['Siswa Pengganti Baru', 'new@sekolah.sch.id', 'S003', 'X RPL 1'],
        ]);

        $preview = ImporEngine::periksa($csv, ImporEngine::KATEGORI_SISWA, ImporEngine::MODE_GANTI);

        $this->assertSame(1, $preview['ringkas']['baru']);
        $this->assertSame(1, $preview['ringkas']['diperbarui']);
        $this->assertSame(1, $preview['ringkas']['dinonaktifkan']); // S002 dinonaktifkan
        $this->assertSame('S002', $preview['akan_dinonaktifkan'][0]['nis']);

        $res = ImporEngine::commit(
            $csv,
            ImporEngine::KATEGORI_SISWA,
            ImporEngine::MODE_GANTI,
            $preview['fingerprint'],
            [],
            'pass12345',
            $admin
        );

        $this->assertSame(1, $res['baru']);
        $this->assertSame(1, $res['diperbarui']);
        $this->assertSame(1, $res['dinonaktifkan']);

        // Verifikasi S002 TIDAK DI-DELETE, tetapi diubah statusnya menjadi nonaktif
        $this->assertDatabaseHas('users', ['nis' => 'S002', 'status' => 'nonaktif']);
        $this->assertDatabaseHas('siswa', ['nis' => 'S002', 'status' => 'nonaktif']);
        $this->assertDatabaseHas('users', ['nis' => 'S003', 'status' => 'aktif']);
        unlink($csv);
    }

    public function test_manual_row_exclusion_skips_selected_rows(): void
    {
        $admin = $this->admin();
        $kelas = $this->buatKelas(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X', 'jurusan_kode' => 'RPL', 'tahun_ajaran_kode' => '2026/2027']);

        $csv = $this->buatFileCsv([
            ['Nama Lengkap', 'Email', 'NIS', 'Kelas'],
            ['Siswa 1', 's1@sekolah.sch.id', 'S001', 'X RPL 1'],
            ['Siswa 2 (Excluded)', 's2@sekolah.sch.id', 'S002', 'X RPL 1'],
        ]);

        // Baris nomor 3 (Siswa 2) dikecualikan secara manual
        $preview = ImporEngine::periksa($csv, ImporEngine::KATEGORI_SISWA, ImporEngine::MODE_TAMBAH, null, [3]);

        $this->assertSame(1, $preview['ringkas']['baru']);
        $this->assertSame(1, $preview['ringkas']['dikecualikan']);

        $res = ImporEngine::commit(
            $csv,
            ImporEngine::KATEGORI_SISWA,
            ImporEngine::MODE_TAMBAH,
            $preview['fingerprint'],
            [3],
            'pass12345',
            $admin
        );

        $this->assertSame(1, $res['baru']);
        $this->assertSame(1, $res['dilewati']);
        $this->assertDatabaseHas('users', ['nis' => 'S001']);
        $this->assertDatabaseMissing('users', ['nis' => 'S002']);
        unlink($csv);
    }

    public function test_stale_fingerprint_rejects_commit(): void
    {
        $admin = $this->admin();
        $kelas = $this->buatKelas(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X', 'jurusan_kode' => 'RPL', 'tahun_ajaran_kode' => '2026/2027']);

        $csv = $this->buatFileCsv([
            ['Nama Lengkap', 'Email', 'NIS', 'Kelas'],
            ['Siswa 1', 's1@sekolah.sch.id', 'S001', 'X RPL 1'],
        ]);

        $preview = ImporEngine::periksa($csv, ImporEngine::KATEGORI_SISWA, ImporEngine::MODE_TAMBAH);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('workflow_confirmation_stale');

        ImporEngine::commit(
            $csv,
            ImporEngine::KATEGORI_SISWA,
            ImporEngine::MODE_TAMBAH,
            'fake_stale_fingerprint_hash',
            [],
            'pass12345',
            $admin
        );
        unlink($csv);
    }
}
