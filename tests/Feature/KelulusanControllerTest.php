<?php

namespace Tests\Feature;

use App\AcademicWorkflows\Operation;
use App\Models\Alumni;
use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KelulusanControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
    }

    private function setupData(): array
    {
        Carbon::setTestNow('2027-05-20 10:00:00');

        $source = TahunAjaran::firstOrCreate(
            ['kode' => '2026/2027'],
            ['aktif' => true, 'mulai' => '2026-07-01', 'selesai' => '2027-06-30']
        );
        $source->update(['mulai' => '2026-07-01', 'selesai' => '2027-06-30', 'aktif' => true]);

        $target = TahunAjaran::firstOrCreate(
            ['kode' => '2027/2028'],
            ['aktif' => false, 'mulai' => '2027-07-01', 'selesai' => '2028-06-30']
        );
        $target->update(['mulai' => '2027-07-01', 'selesai' => '2028-06-30', 'aktif' => false]);

        $classSource = $this->buatKelas(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $classTarget = $this->buatKelas(['nama_kelas' => 'XII IPA 1 (27/28)', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        $s1 = Siswa::factory()->create(['nis' => '00121', 'nama' => 'Siswa Satu', 'kelas_id' => $classSource->id, 'status' => 'aktif']);
        $s2 = Siswa::factory()->create(['nis' => '00122', 'nama' => 'Siswa Dua', 'kelas_id' => $classSource->id, 'status' => 'aktif']);

        RiwayatKelasSiswa::daftarkan($s1, $classSource, '2026/2027', '2026-07-01');
        RiwayatKelasSiswa::daftarkan($s2, $classSource, '2026/2027', '2026-07-01');

        return [$source, $target, $classSource, $classTarget, $s1, $s2];
    }

    public function test_admin_can_access_kelulusan_index(): void
    {
        $this->setupData();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.kelulusan.index'));
        $response->assertOk();
        $response->assertSee('Kelulusan');
        $response->assertSee('2026/2027');
    }

    public function test_non_admin_cannot_access_kelulusan(): void
    {
        $this->setupData();
        $guru = User::factory()->create(['role' => 'guru', 'status' => 'aktif']);

        $response = $this->actingAs($guru)->get(route('admin.kelulusan.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_download_template(): void
    {
        $this->setupData();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.kelulusan.template', ['tahun_asal' => '2026/2027']));
        $response->assertOk();
        $this->assertStringContainsString('template-kelulusan-xii', (string) $response->headers->get('content-disposition'));
    }

    public function test_admin_can_upload_excel_and_redirect_to_review(): void
    {
        [$source, $target, $classSource, $classTarget, $s1, $s2] = $this->setupData();
        $admin = $this->admin();

        $content = "NIS,Nama Siswa,Status Kelulusan\n";
        $content .= "00121,Siswa Satu,LULUS\n";
        $content .= "00122,Siswa Dua,TIDAK LULUS\n";

        $file = UploadedFile::fake()->createWithContent('hasil-kelulusan.csv', $content);

        $response = $this->actingAs($admin)->post(route('admin.kelulusan.upload'), [
            'berkas' => $file,
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'effective_date' => '2027-05-15',
            'official_reference' => 'SK-KELULUSAN-2027-01',
        ]);

        $op = Operation::where('kind', 'graduation')->first();
        $this->assertNotNull($op);

        $response->assertRedirect(route('admin.kelulusan.review', $op->id));
        $this->assertSame(2, $op->items()->count());
    }

    public function test_review_page_renders_grouping_and_summary(): void
    {
        [$source, $target, $classSource, $classTarget, $s1, $s2] = $this->setupData();
        $admin = $this->admin();

        $content = "NIS,Nama Siswa,Status Kelulusan\n";
        $content .= "00121,Siswa Satu,LULUS\n";
        $content .= "00122,Siswa Dua,TIDAK LULUS\n";

        $file = UploadedFile::fake()->createWithContent('hasil-kelulusan.csv', $content);
        $this->actingAs($admin)->post(route('admin.kelulusan.upload'), [
            'berkas' => $file,
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'effective_date' => '2027-05-15',
            'official_reference' => 'SK-KELULUSAN-2027-01',
        ]);

        $op = Operation::where('kind', 'graduation')->first();

        $response = $this->actingAs($admin)->get(route('admin.kelulusan.review', $op->id));
        $response->assertOk();
        $response->assertSee('Pratinjau Kelulusan');
        $response->assertSee('00121');
        $response->assertSee('00122');
    }

    public function test_admin_can_update_item_decision(): void
    {
        [$source, $target, $classSource, $classTarget, $s1, $s2] = $this->setupData();
        $admin = $this->admin();

        // s2 is omitted from upload, will be unverified
        $content = "NIS,Nama Siswa,Status Kelulusan\n";
        $content .= "00121,Siswa Satu,LULUS\n";

        $file = UploadedFile::fake()->createWithContent('hasil-kelulusan.csv', $content);
        $this->actingAs($admin)->post(route('admin.kelulusan.upload'), [
            'berkas' => $file,
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'effective_date' => '2027-05-15',
            'official_reference' => 'SK-KELULUSAN-2027-01',
        ]);

        $op = Operation::where('kind', 'graduation')->first();
        $item2 = $op->items()->where('student_ref', '00122')->first();
        $this->assertSame('unverified', $item2->decision);

        // Admin resolves it to lulus
        $response = $this->actingAs($admin)->post(route('admin.kelulusan.update-item', [$op->id, $item2->id]), [
            'decision' => 'lulus',
            'alasan' => 'Dikonfirmasi dari surat fisik susulan',
        ]);

        $response->assertRedirect(route('admin.kelulusan.review', $op->id));
        $this->assertSame('lulus', $op->items()->where('student_ref', '00122')->first()->decision);
    }

    public function test_admin_can_confirm_and_execute_graduation(): void
    {
        [$source, $target, $classSource, $classTarget, $s1, $s2] = $this->setupData();
        $admin = $this->admin();

        $content = "NIS,Nama Siswa,Status Kelulusan\n";
        $content .= "00121,Siswa Satu,LULUS\n";
        $content .= "00122,Siswa Dua,TIDAK LULUS\n";

        $file = UploadedFile::fake()->createWithContent('hasil-kelulusan.csv', $content);
        $this->actingAs($admin)->post(route('admin.kelulusan.upload'), [
            'berkas' => $file,
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'effective_date' => '2027-05-15',
            'official_reference' => 'SK-KELULUSAN-2027-01',
        ]);

        $op = Operation::where('kind', 'graduation')->first();

        // Execute
        $response = $this->actingAs($admin)->post(route('admin.kelulusan.execute', $op->id));
        $response->assertRedirect(route('admin.kelulusan.show', $op->id));

        $this->assertSame('completed', $op->fresh()->state);
        $this->assertSame('lulus', $s1->fresh()->status);
        $this->assertTrue(Alumni::where('siswa_nis', '00121')->exists());
    }

    public function test_execution_blocked_if_unresolved_items_exist(): void
    {
        [$source, $target, $classSource, $classTarget, $s1, $s2] = $this->setupData();
        $admin = $this->admin();

        // Omit s2
        $content = "NIS,Nama Siswa,Status Kelulusan\n";
        $content .= "00121,Siswa Satu,LULUS\n";

        $file = UploadedFile::fake()->createWithContent('hasil-kelulusan.csv', $content);
        $this->actingAs($admin)->post(route('admin.kelulusan.upload'), [
            'berkas' => $file,
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'effective_date' => '2027-05-15',
            'official_reference' => 'SK-KELULUSAN-2027-01',
        ]);

        $op = Operation::where('kind', 'graduation')->first();

        $response = $this->actingAs($admin)->post(route('admin.kelulusan.execute', $op->id));
        $response->assertSessionHasErrors();
        $this->assertNotSame('completed', $op->fresh()->state);
    }
}
