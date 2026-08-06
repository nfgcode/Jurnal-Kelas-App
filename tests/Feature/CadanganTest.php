<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\User;
use App\Support\CadanganData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Admin-only backup & restore. These tests exercise the mechanism on the small
 * master tables rather than the whole demo dataset — the restore code is generic
 * across tables, so proving merge/replace on one table proves the machinery
 * without seeding 100k presensi rows into every test.
 */
class CadanganTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private static int $seq = 0;

    private function guru(): User
    {
        return User::factory()->create(['role' => 'guru', 'nip' => '19850000'.str_pad((string) ++self::$seq, 4, '0', STR_PAD_LEFT)]);
    }

    private function siswa(): User
    {
        return User::factory()->create(['role' => 'siswa', 'nis' => '2026'.str_pad((string) ++self::$seq, 4, '0', STR_PAD_LEFT)]);
    }

    private function mapel(string $nama, string $kode): MataPelajaran
    {
        $m = new MataPelajaran;
        $m->forceFill(['nama' => $nama, 'kode' => $kode, 'kelompok' => 'wajib', 'jp_per_minggu' => 3]);
        $m->save();

        return $m;
    }

    /** The current database as a JSON backup string (what a real download holds). */
    private function backupJson(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'baktest');
        app(CadanganData::class)->tulisJson($tmp);
        $isi = file_get_contents($tmp);
        @unlink($tmp);

        return $isi;
    }

    private function berkas(string $json): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cadangan.json', $json);
    }

    /** A backup wrapped in gzip, as the JSON download actually ships it (.json.gz). */
    private function berkasGz(string $json): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cadangan.json.gz', gzencode($json, 6));
    }

    public function test_the_backup_page_is_admin_only(): void
    {
        // Guest first — actingAs() persists onto later requests in the same test.
        $this->get('/admin/cadangan')->assertRedirect('/login');
        $this->actingAs($this->admin())->get('/admin/cadangan')->assertOk()->assertSee('Cadangan', false);
        $this->actingAs($this->guru())->get('/admin/cadangan')->assertForbidden();
        $this->actingAs($this->siswa())->get('/admin/cadangan')->assertForbidden();
    }

    public function test_json_export_downloads_and_carries_every_table(): void
    {
        $admin = $this->admin();
        $this->mapel('Matematika', 'MTK');

        $respons = $this->actingAs($admin)->get('/admin/cadangan/json')
            ->assertOk()
            ->assertHeader('content-type', 'application/gzip');

        // The download itself is gzipped (magic bytes 1f 8b) and decodes back to
        // the same snapshot — proving the compressed file is a valid backup.
        $terunduh = $respons->streamedContent();
        $this->assertStringStartsWith("\x1f\x8b", $terunduh);
        $this->assertIsArray(json_decode(gzdecode($terunduh), true));

        $data = json_decode($this->backupJson(), true);

        $this->assertArrayHasKey('tabel', $data);
        foreach ([
            'users', 'kelas', 'mata_pelajaran', 'jadwal', 'jurnal', 'presensi',
            'presensi_log', 'pengumuman', 'laporan_error', 'personal_access_tokens',
        ] as $tabel) {
            $this->assertArrayHasKey($tabel, $data['tabel'], "Cadangan harus memuat tabel $tabel.");
        }
        // jurnal_audit is trigger-maintained and intentionally left out of the backup.
        $this->assertArrayNotHasKey('jurnal_audit', $data['tabel']);
        $this->assertNotEmpty($data['tabel']['users']);
        $this->assertNotEmpty($data['tabel']['mata_pelajaran']);
    }

    public function test_export_can_be_limited_to_a_chosen_subset_of_tables(): void
    {
        $admin = $this->admin();
        $this->mapel('Sejarah', 'SEJ');

        // Service level: only the requested table is written.
        $tmp = tempnam(sys_get_temp_dir(), 'baktest');
        app(CadanganData::class)->tulisJson($tmp, ['mata_pelajaran']);
        $data = json_decode(file_get_contents($tmp), true);
        @unlink($tmp);
        $this->assertSame(['mata_pelajaran'], array_keys($data['tabel']));

        // Endpoint level: the ?tabel[] selection reaches the (gzipped) download.
        $respons = $this->actingAs($admin)->get('/admin/cadangan/json?tabel[]=mata_pelajaran&tabel[]=kelas');
        $respons->assertOk();
        $terunduh = json_decode(gzdecode($respons->streamedContent()), true);
        $this->assertEqualsCanonicalizing(['mata_pelajaran', 'kelas'], array_keys($terunduh['tabel']));
    }

    public function test_exports_are_admin_only(): void
    {
        $guru = $this->guru();

        $this->actingAs($guru)->get('/admin/cadangan/json')->assertForbidden();
        $this->actingAs($guru)->get('/admin/cadangan/xlsx')->assertForbidden();
    }

    public function test_xlsx_export_is_a_real_workbook(): void
    {
        $this->mapel('Biologi', 'BIO');

        $respons = app(CadanganData::class)->unduhXlsx('data.xlsx');
        $isi = file_get_contents($respons->getFile()->getPathname());

        // A .xlsx is a ZIP; the ZIP local-file-header magic is "PK\x03\x04".
        $this->assertStringStartsWith('PK', $isi);
    }

    public function test_merge_restore_puts_back_changed_rows_but_keeps_newer_ones(): void
    {
        $admin = $this->admin();
        $fisika = $this->mapel('Fisika', 'FIS');

        $json = $this->backupJson();

        // After the snapshot: rename the backed-up row, and add a brand-new one.
        $fisika->update(['nama' => 'Fisika Diubah']);
        $kimia = $this->mapel('Kimia', 'KIM');

        $this->actingAs($admin)->post('/admin/cadangan/pulihkan', [
            'berkas' => $this->berkas($json),
            'mode' => 'gabung',
            'konfirmasi' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        // The snapshot row is restored, and the row added afterwards survives.
        $this->assertSame('Fisika', $fisika->fresh()->nama);
        $this->assertNotNull(MataPelajaran::find($kimia->id));
    }

    public function test_replace_restore_wipes_rows_absent_from_the_snapshot(): void
    {
        $admin = $this->admin();
        $fisika = $this->mapel('Fisika', 'FIS');

        $json = $this->backupJson();

        $kimia = $this->mapel('Kimia', 'KIM'); // not in the snapshot

        $this->actingAs($admin)->post('/admin/cadangan/pulihkan', [
            'berkas' => $this->berkas($json),
            'mode' => 'ganti',
            'konfirmasi' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        // Replace = the database becomes exactly the snapshot again.
        $this->assertNull(MataPelajaran::find($kimia->id));
        $this->assertSame('Fisika', $fisika->fresh()->nama);
        $this->assertNotNull($admin->fresh(), 'Admin dari snapshot harus ikut dipulihkan.');
    }

    public function test_restore_accepts_the_gzipped_download(): void
    {
        $admin = $this->admin();
        $fisika = $this->mapel('Fisika', 'FIS');

        $json = $this->backupJson();
        $fisika->update(['nama' => 'Fisika Diubah']);

        // Feed back the .json.gz shape the download produces — the controller must
        // detect gzip by its magic bytes and decode before restoring.
        $this->actingAs($admin)->post('/admin/cadangan/pulihkan', [
            'berkas' => $this->berkasGz($json),
            'mode' => 'gabung',
            'konfirmasi' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('Fisika', $fisika->fresh()->nama);
    }

    public function test_restore_requires_the_confirmation_box(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/cadangan/pulihkan', [
            'berkas' => $this->berkas($this->backupJson()),
            'mode' => 'gabung',
        ])->assertSessionHasErrors('konfirmasi');
    }

    public function test_a_file_that_is_not_a_backup_is_rejected_cleanly(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/cadangan/pulihkan', [
            'berkas' => UploadedFile::fake()->createWithContent('bukan.json', 'halo bukan json'),
            'mode' => 'gabung',
            'konfirmasi' => '1',
        ])->assertRedirect()->assertSessionHas('error');
    }

    public function test_restore_is_admin_only(): void
    {
        $json = $this->backupJson();

        $this->actingAs($this->guru())->post('/admin/cadangan/pulihkan', [
            'berkas' => $this->berkas($json),
            'mode' => 'gabung',
            'konfirmasi' => '1',
        ])->assertForbidden();
    }
}
