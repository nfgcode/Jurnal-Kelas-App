<?php

namespace Tests\Feature;

use App\AcademicWorkflows\Operation;
use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class IntakeControllerTest extends TestCase
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

    private function setupData(): array
    {
        $target = TahunAjaran::firstOrCreate(
            ['kode' => '2027/2028'],
            ['aktif' => false, 'mulai' => '2027-07-01', 'selesai' => '2028-06-30']
        );
        $target->update(['mulai' => '2027-07-01', 'selesai' => '2028-06-30', 'aktif' => false]);

        $kX1 = $this->buatKelas(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $kX2 = $this->buatKelas(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X', 'jurusan_kode' => 'RPL', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        return [$target, $kX1, $kX2];
    }

    public function test_non_admin_cannot_access_intake(): void
    {
        $guru = User::factory()->create(['role' => 'guru', 'status' => 'aktif']);
        $this->actingAs($guru);

        $response = $this->get(route('admin.intake.index'));
        $response->assertStatus(403);
    }

    public function test_index_renders_available_classes_and_template_download(): void
    {
        $this->admin();
        $this->setupData();

        $res = $this->get(route('admin.intake.index', ['tahun_tujuan' => '2027/2028']));
        $res->assertStatus(200);
        $res->assertSee('Penerimaan Siswa Baru Tingkat X');
        $res->assertSee('X TKJ 1');

        $download = $this->get(route('admin.intake.template', ['tahun_tujuan' => '2027/2028']));
        $download->assertStatus(200);
        $this->assertTrue(str_contains($download->headers->get('content-disposition'), 'template-intake-siswa-baru-2027-2028.xlsx'));
    }

    public function test_upload_validates_and_redirects_to_review(): void
    {
        $this->admin();
        [$target, $kX1] = $this->setupData();

        $csv = "NIS,NISN,Nama Lengkap,Email,Kelas X Tujuan,Jenis Kelamin\n";
        $csv .= "00301,0081234567,Ahmad Fadillah,ahmad@sekolah.sch.id,X TKJ 1,L\n";

        $file = UploadedFile::fake()->createWithContent('intake.csv', $csv);

        $res = $this->post(route('admin.intake.upload'), [
            'tahun_tujuan' => '2027/2028',
            'effective_date' => '2027-07-01',
            'official_reference' => 'PPDB-2027',
            'berkas' => $file,
        ]);

        $op = Operation::where('kind', 'intake')->latest('id')->first();
        $this->assertNotNull($op);
        $res->assertRedirect(route('admin.intake.review', $op->id));

        $reviewRes = $this->get(route('admin.intake.review', $op->id));
        $reviewRes->assertStatus(200);
        $reviewRes->assertSee('00301');
    }

    public function test_update_item_supports_class_override_and_explicit_exclusion(): void
    {
        $this->admin();
        [$target, $kX1, $kX2] = $this->setupData();

        $csv = "NIS,NISN,Nama Lengkap,Email,Kelas X Tujuan,Jenis Kelamin\n";
        $csv .= "00301,0081234567,Ahmad Fadillah,ahmad@sekolah.sch.id,X TKJ 1,L\n";

        $file = UploadedFile::fake()->createWithContent('intake.csv', $csv);
        $this->post(route('admin.intake.upload'), [
            'tahun_tujuan' => '2027/2028',
            'effective_date' => '2027-07-01',
            'official_reference' => 'PPDB-2027',
            'berkas' => $file,
        ]);

        $op = Operation::where('kind', 'intake')->latest('id')->first();
        $item = $op->items()->first();

        // 1. Update target class to X RPL 1
        $resClass = $this->post(route('admin.intake.update-item', ['id' => $op->id, 'itemId' => $item->id]), [
            'action' => 'update_class',
            'target_class_id' => $kX2->id,
        ]);
        $resClass->assertRedirect(route('admin.intake.review', $op->id));

        $itemFresh = $op->items()->where('student_ref', $item->student_ref)->first();
        $this->assertSame($kX2->id, $itemFresh->target_class_id);

        // 2. Exclude without reason should fail
        $resExFail = $this->post(route('admin.intake.update-item', ['id' => $op->id, 'itemId' => $item->id]), [
            'action' => 'exclude',
            'exclusion_reason' => '',
        ]);
        $resExFail->assertSessionHasErrors(['item']);

        // 3. Exclude with reason
        $resExOk = $this->post(route('admin.intake.update-item', ['id' => $op->id, 'itemId' => $itemFresh->id]), [
            'action' => 'exclude',
            'exclusion_reason' => 'Berkas belum lengkap',
        ]);
        $resExOk->assertRedirect(route('admin.intake.review', $op->id));

        $itemEx = $op->items()->where('student_ref', $item->student_ref)->first();
        $this->assertTrue((bool) $itemEx->excluded);
    }

    public function test_rejects_duplicate_identity_upload(): void
    {
        $this->admin();
        [$target, $kX1] = $this->setupData();

        // Existing student in database
        Siswa::factory()->create(['nis' => '00301', 'status' => 'aktif']);

        $csv = "NIS,NISN,Nama Lengkap,Email,Kelas X Tujuan,Jenis Kelamin\n";
        $csv .= "00301,0081234567,Ahmad Fadillah,ahmad@sekolah.sch.id,X TKJ 1,L\n";

        $file = UploadedFile::fake()->createWithContent('intake.csv', $csv);
        $this->post(route('admin.intake.upload'), [
            'tahun_tujuan' => '2027/2028',
            'effective_date' => '2027-07-01',
            'official_reference' => 'PPDB-2027',
            'berkas' => $file,
        ]);

        $op = Operation::where('kind', 'intake')->latest('id')->first();
        $this->assertSame('invalid', $op->validation);

        $reviewRes = $this->get(route('admin.intake.review', $op->id));
        $reviewRes->assertSee('identity_duplicate');
    }

    public function test_stale_snapshot_blocks_finalization_until_revalidation(): void
    {
        $this->admin();
        [$target, $kX1] = $this->setupData();

        $csv = "NIS,NISN,Nama Lengkap,Email,Kelas X Tujuan,Jenis Kelamin\n";
        $csv .= "00301,0081234567,Ahmad Fadillah,ahmad.unique@sekolah.sch.id,X TKJ 1,L\n";

        $file = UploadedFile::fake()->createWithContent('intake.csv', $csv);
        $this->post(route('admin.intake.upload'), [
            'tahun_tujuan' => '2027/2028',
            'effective_date' => '2027-07-01',
            'official_reference' => 'PPDB-2027',
            'berkas' => $file,
        ]);

        $op = Operation::where('kind', 'intake')->latest('id')->first();
        $this->assertSame('ready', $op->state);

        // Mutasi user lain dengan email yang sama di luar workflow
        User::factory()->create(['email' => 'ahmad.unique@sekolah.sch.id']);

        // Finalisasi harus gagal karena snapshot stale/invalid
        $resExec = $this->post(route('admin.intake.execute', $op->id));
        $resExec->assertSessionHasErrors(['execute']);

        $this->assertNotSame('completed', $op->fresh()->state);
    }

    public function test_confirm_execute_and_show_recap(): void
    {
        $this->admin();
        [$target, $kX1] = $this->setupData();

        $csv = "NIS,NISN,Nama Lengkap,Email,Kelas X Tujuan,Jenis Kelamin\n";
        $csv .= "00301,0081234567,Ahmad Fadillah,ahmad.fad@sekolah.sch.id,X TKJ 1,L\n";

        $file = UploadedFile::fake()->createWithContent('intake.csv', $csv);
        $this->post(route('admin.intake.upload'), [
            'tahun_tujuan' => '2027/2028',
            'effective_date' => '2027-07-01',
            'official_reference' => 'PPDB-2027',
            'berkas' => $file,
        ]);

        $op = Operation::where('kind', 'intake')->latest('id')->first();

        // Finalize execution
        $execRes = $this->post(route('admin.intake.execute', $op->id));
        $execRes->assertRedirect(route('admin.intake.show', $op->id));

        $this->assertSame('completed', $op->fresh()->state);
        $this->assertNotNull(Siswa::where('nis', '00301')->first());
        $this->assertNotNull(User::where('nis', '00301')->first());
        $this->assertNotNull(RiwayatKelasSiswa::where('siswa_nis', '00301')->first());

        // Replay redirects to show
        $replayRes = $this->post(route('admin.intake.execute', $op->id));
        $replayRes->assertRedirect(route('admin.intake.show', $op->id));

        // Show page recap
        $showRes = $this->get(route('admin.intake.show', $op->id));
        $showRes->assertStatus(200);
        $showRes->assertSee('Rekapitulasi Penerimaan Siswa Baru');
        $showRes->assertSee('00301');
    }
}
