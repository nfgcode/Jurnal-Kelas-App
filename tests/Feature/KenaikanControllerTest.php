<?php

namespace Tests\Feature;

use App\AcademicWorkflows\Operation;
use App\AcademicWorkflows\WorkflowService;
use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class KenaikanControllerTest extends TestCase
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

        $cX1 = $this->buatKelas(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $targetXI1 = $this->buatKelas(['nama_kelas' => 'XI TKJ 1 (27/28)', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $targetXI2 = $this->buatKelas(['nama_kelas' => 'XI TKJ 2 (27/28)', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 2, 'tahun_ajaran_kode' => '2027/2028']);
        $targetX1 = $this->buatKelas(['nama_kelas' => 'X TKJ 1 (27/28)', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        $s1 = Siswa::factory()->create(['nis' => '00101', 'nama' => 'Siswa Satu', 'kelas_id' => $cX1->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s1, $cX1, '2026/2027', '2026-07-01');

        return [$source, $target, $cX1, [$targetX1, $targetXI1, $targetXI2], $s1];
    }

    public function test_non_admin_cannot_access_kenaikan(): void
    {
        $guru = User::factory()->create(['role' => 'guru', 'status' => 'aktif']);
        $this->actingAs($guru);

        $response = $this->get(route('admin.kenaikan.index'));
        $response->assertStatus(403);
    }

    public function test_index_renders_roster_summary_and_template_download(): void
    {
        $this->admin();
        $this->setupData();

        $res = $this->get(route('admin.kenaikan.index'));
        $res->assertStatus(200);
        $res->assertSee('Kenaikan Kelas X–XI');
        $res->assertSee('Roster Siswa Tingkat X');

        $download = $this->get(route('admin.kenaikan.template', ['tahun_asal' => '2026/2027']));
        $download->assertStatus(200);
        $this->assertTrue(str_contains($download->headers->get('content-disposition'), 'template-kenaikan-x-xi-2026-2027.xlsx'));
    }

    public function test_inisiasi_default_creates_operation_and_redirects_to_review(): void
    {
        $this->admin();
        $this->setupData();

        $res = $this->post(route('admin.kenaikan.inisiasi'), [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'metode' => 'default',
        ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('academic_workflow_operations', [
            'kind' => 'promotion',
            'source_year' => '2026/2027',
            'target_year' => '2027/2028',
        ]);
    }

    public function test_inisiasi_upload_handles_spreadsheet_and_groups_items(): void
    {
        $this->admin();
        [$source, $target, $cX1, $targets, $s1] = $this->setupData();

        $csv = "NIS,Nama Siswa,Status Kenaikan,Alasan / Catatan\n";
        $csv .= "00101,Siswa Satu,NAIK,\n";

        $file = UploadedFile::fake()->createWithContent('kenaikan.csv', $csv);

        $res = $this->post(route('admin.kenaikan.inisiasi'), [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'metode' => 'upload',
            'berkas' => $file,
        ]);

        $op = Operation::where('kind', 'promotion')->latest('id')->first();
        $this->assertNotNull($op);
        $res->assertRedirect(route('admin.kenaikan.review', $op->id));

        $reviewRes = $this->get(route('admin.kenaikan.review', $op->id));
        $reviewRes->assertStatus(200);
        $reviewRes->assertSee('Siswa Satu');
    }

    public function test_update_item_supports_override_parallel_and_tinggal_kelas_with_reason(): void
    {
        $admin = $this->admin();
        [$source, $target, $cX1, [$targetX1, $targetXI1, $targetXI2], $s1] = $this->setupData();

        $this->post(route('admin.kenaikan.inisiasi'), [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'metode' => 'default',
        ]);

        $op = Operation::where('kind', 'promotion')->latest('id')->first();
        $item = $op->items()->first();

        // 1. Update decision to tinggal_kelas without reason should fail validation in controller
        $resFail = $this->post(route('admin.kenaikan.update-item', ['id' => $op->id, 'itemId' => $item->id]), [
            'decision' => 'tinggal_kelas',
            'target_class_id' => $targetX1->id,
            'alasan' => '',
        ]);
        $resFail->assertSessionHasErrors(['item']);

        // 2. Update to parallel override (XI TKJ 2)
        $resOk = $this->post(route('admin.kenaikan.update-item', ['id' => $op->id, 'itemId' => $item->id]), [
            'decision' => 'naik',
            'target_class_id' => $targetXI2->id,
            'alasan' => '',
        ]);
        $resOk->assertRedirect(route('admin.kenaikan.review', $op->id));

        $itemFresh = $op->items()->where('student_ref', $item->student_ref)->first();
        $this->assertSame($targetXI2->id, $itemFresh->target_class_id);
    }

    public function test_confirm_and_execute_full_workflow_and_show_recap(): void
    {
        $this->admin();
        [$source, $target, $cX1, [$targetX1, $targetXI1, $targetXI2], $s1] = $this->setupData();

        $this->post(route('admin.kenaikan.inisiasi'), [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'metode' => 'default',
        ]);

        $op = Operation::where('kind', 'promotion')->latest('id')->first();

        // 1. Confirm and execute via controller endpoint
        $execRes = $this->post(route('admin.kenaikan.execute', $op->id));
        $execRes->assertRedirect(route('admin.kenaikan.show', $op->id));

        $this->assertSame('completed', $op->fresh()->state);
        $this->assertSame($targetXI1->id, $s1->fresh()->kelas_id);

        // 2. Replay execution on completed operation redirects directly to show
        $replayRes = $this->post(route('admin.kenaikan.execute', $op->id));
        $replayRes->assertRedirect(route('admin.kenaikan.show', $op->id));

        // 3. Show page recap
        $showRes = $this->get(route('admin.kenaikan.show', $op->id));
        $showRes->assertStatus(200);
        $showRes->assertSee('Rekapitulasi Kenaikan Kelas X–XI');
        $showRes->assertSee('Siswa Satu');
    }
}
