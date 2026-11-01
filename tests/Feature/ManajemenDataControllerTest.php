<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use App\Support\BackupEngine;
use App\Support\ImporEngine;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManajemenDataControllerTest extends TestCase
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

    private function nonAdmin(): User
    {
        $user = User::factory()->create(['role' => 'guru', 'status' => 'aktif']);
        $this->actingAs($user);

        return $user;
    }

    public function test_non_admin_cannot_access_manajemen_data(): void
    {
        $this->nonAdmin();

        $res = $this->get(route('admin.manajemen-data.index'));
        $res->assertStatus(403);
    }

    public function test_admin_can_access_landing_hub_and_panduan(): void
    {
        $this->admin();

        $resLanding = $this->get(route('admin.manajemen-data.index'));
        $resLanding->assertStatus(200);
        $resLanding->assertSee('Hub Manajemen Data');
        $resLanding->assertSee('Impor Data Spreadsheet');
        $resLanding->assertSee('Ekspor');
        $resLanding->assertSee('Cadangkan Data');

        $resPanduan = $this->get(route('admin.manajemen-data.panduan'));
        $resPanduan->assertStatus(200);
        $resPanduan->assertSee('Panduan');
        $resPanduan->assertSee('Format Manajemen Data');
    }

    public function test_impor_commit_executes_valid_subset_and_respects_exclusions(): void
    {
        $admin = $this->admin();
        Storage::fake('local');

        $kelas = $this->buatKelas(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X', 'jurusan_kode' => 'RPL', 'tahun_ajaran_kode' => '2026/2027']);

        $csvContent = "Nama Lengkap,Email,NIS,Kelas\nSiswa Baru 1,s1@sekolah.sch.id,S101,X RPL 1\nSiswa Baru 2,s2@sekolah.sch.id,S102,X RPL 1\n";
        $tempPath = 'temp_impor/test_preview.csv';
        Storage::disk('local')->put($tempPath, $csvContent);
        $fullPath = Storage::disk('local')->path($tempPath);
        $preview = ImporEngine::periksa($fullPath, 'siswa', 'tambah', null, [3]);

        $this->assertNull($preview['fatal']);
        $this->assertSame(1, $preview['ringkas']['baru']);
        $this->assertSame(1, $preview['ringkas']['dikecualikan']);

        // 3. Commit langsung via ImporEngine
        $hasil = ImporEngine::commit(
            $fullPath,
            'siswa',
            'tambah',
            $preview['fingerprint'],
            [3],
            null,
            $admin
        );

        $this->assertSame(1, $hasil['baru']);
        $this->assertSame(1, $hasil['dilewati']);

        // Verifikasi S101 tersimpan, S102 dikecualikan
        $this->assertDatabaseHas('users', ['nis' => 'S101', 'nama' => 'Siswa Baru 1']);
        $this->assertDatabaseMissing('users', ['nis' => 'S102']);
    }

    public function test_mode_ganti_keseluruhan_requires_typed_confirmation(): void
    {
        $admin = $this->admin();
        Storage::fake('local');

        $kelas = $this->buatKelas(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X', 'jurusan_kode' => 'RPL', 'tahun_ajaran_kode' => '2026/2027']);

        $csvContent = "Nama Lengkap,Email,NIS,Kelas\nSiswa Baru,s1@sekolah.sch.id,S101,X RPL 1\n";
        $file = UploadedFile::fake()->createWithContent('siswa.csv', $csvContent);

        $resPreview = $this->post(route('admin.manajemen-data.impor.pratinjau'), [
            'kategori' => 'siswa',
            'mode' => 'ganti_keseluruhan',
            'berkas' => $file,
        ]);

        $viewData = $resPreview->original->getData();

        // Commit tanpa mengetik konfirmasi yang benar -> Ditolak
        $resCommitFail = $this->post(route('admin.manajemen-data.impor.commit'), [
            'temp_path' => $viewData['tempPath'],
            'kategori' => 'siswa',
            'mode' => 'ganti_keseluruhan',
            'fingerprint' => $viewData['preview']['fingerprint'],
            'konfirmasi_ganti' => 'SALAH_KETIK',
        ]);

        $resCommitFail->assertSessionHasErrors('konfirmasi_ganti');
    }

    public function test_cadangan_management_create_pin_download_and_delete_protection(): void
    {
        $admin = $this->admin();
        Storage::fake(BackupEngine::DISK);

        // 1. Buat cadangan manual
        $resStore = $this->post(route('admin.manajemen-data.cadangan.store'), [
            'format' => 'sql_gz',
            'catatan' => 'Uji manual cadangan',
        ]);

        $resStore->assertRedirect();
        $arsip = DB::table('cadangan_arsip')->latest('id')->first();
        $this->assertNotNull($arsip);

        // 2. Unduh cadangan privat
        $resDownload = $this->get(route('admin.manajemen-data.cadangan.download', $arsip->id));
        $resDownload->assertStatus(200);
        $this->assertStringContainsString('no-store', $resDownload->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $resDownload->headers->get('Cache-Control'));

        // 3. Pin cadangan
        $resPin = $this->post(route('admin.manajemen-data.cadangan.pin', $arsip->id));
        $resPin->assertRedirect();
        $this->assertSame(1, (int) DB::table('cadangan_arsip')->where('id', $arsip->id)->value('penting'));

        // 4. Hapus saat masih dipin -> Ditolak
        $resDelFail = $this->delete(route('admin.manajemen-data.cadangan.destroy', $arsip->id));
        $resDelFail->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('cadangan_arsip', ['id' => $arsip->id]);

        // 5. Unpin cadangan
        $this->post(route('admin.manajemen-data.cadangan.pin', $arsip->id));
        $this->assertSame(0, (int) DB::table('cadangan_arsip')->where('id', $arsip->id)->value('penting'));

        // 6. Buat cadangan kedua agar arsip pertama bukan lagi last-valid
        DB::table('cadangan_arsip')->insert([
            'nama_berkas' => 'second.sql.gz',
            'format' => 'sql_gz',
            'tipe' => 'manual',
            'lokasi_privat' => 'backups/second.sql.gz',
            'ukuran_bytes' => 10,
            'checksum_sha256' => hash('sha256', 'x'),
            'status_validasi' => 'terverifikasi',
            'penting' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sekarang arsip pertama boleh dihapus
        $resDelSuccess = $this->delete(route('admin.manajemen-data.cadangan.destroy', $arsip->id));
        $resDelSuccess->assertRedirect();
        $this->assertDatabaseMissing('cadangan_arsip', ['id' => $arsip->id]);
    }
}
