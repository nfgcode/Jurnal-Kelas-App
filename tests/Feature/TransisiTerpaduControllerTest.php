<?php

namespace Tests\Feature;

use App\AcademicWorkflows\Coordinator;
use App\AcademicWorkflows\ExecutorRegistry;
use App\AcademicWorkflows\GraduationExecutor;
use App\AcademicWorkflows\IntakeExecutor;
use App\AcademicWorkflows\Operation;
use App\AcademicWorkflows\PromotionExecutor;
use App\AcademicWorkflows\WorkflowService;
use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransisiTerpaduControllerTest extends TestCase
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

    private function setupAcademicData(): array
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

        $k12 = $this->buatKelas(['nama_kelas' => 'XII TKJ 1', 'tingkat' => 'XII', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $k10 = $this->buatKelas(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $target11 = $this->buatKelas(['nama_kelas' => 'XI TKJ 1 (27/28)', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        $sGrad = Siswa::factory()->create(['nis' => 'G001', 'kelas_id' => $k12->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($sGrad, $k12, '2026/2027', '2026-07-01');

        $sProm = Siswa::factory()->create(['nis' => 'P001', 'kelas_id' => $k10->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($sProm, $k10, '2026/2027', '2026-07-01');

        return [$source, $target, [$k12, $k10], [$target11]];
    }

    public function test_non_admin_cannot_access_transisi_terpadu(): void
    {
        $guru = User::factory()->create(['role' => 'guru', 'status' => 'aktif']);
        $this->actingAs($guru);

        $res = $this->get(route('admin.transisi-terpadu.index'));
        $res->assertStatus(403);
    }

    public function test_index_renders_setup_form_and_session_list(): void
    {
        $this->admin();
        $this->setupAcademicData();

        $res = $this->get(route('admin.transisi-terpadu.index'));
        $res->assertStatus(200);
        $res->assertSee('Transisi Akademik Terpadu');
        $res->assertSee('Setup Sesi Transisi Terpadu Baru');
    }

    public function test_store_creates_coordinated_session_and_redirects_to_show(): void
    {
        $this->admin();
        $this->setupAcademicData();

        $res = $this->post(route('admin.transisi-terpadu.store'), [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'stages' => ['graduation', 'promotion'],
        ]);

        $res->assertRedirect();
        $session = DB::table('academic_workflow_coordinators')->latest('id')->first();
        $this->assertNotNull($session);
        $this->assertSame('2026/2027', $session->source_year);
        $this->assertSame('2027/2028', $session->target_year);

        $showRes = $this->get(route('admin.transisi-terpadu.show', $session->id));
        $showRes->assertStatus(200);
        $showRes->assertSee('Tahap 1: Kelulusan XII');
        $showRes->assertSee('Tahap 2: Kenaikan X–XI');
    }

    public function test_duplicate_session_creation_redirects_to_existing_active_session(): void
    {
        $this->admin();
        $this->setupAcademicData();

        // 1. First creation
        $res1 = $this->post(route('admin.transisi-terpadu.store'), [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'stages' => ['graduation', 'promotion'],
        ]);
        $session1 = DB::table('academic_workflow_coordinators')->latest('id')->first();
        $res1->assertRedirect(route('admin.transisi-terpadu.show', $session1->id));

        // 2. Second creation for the exact same active period
        $res2 = $this->post(route('admin.transisi-terpadu.store'), [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'stages' => ['promotion', 'intake'],
        ]);

        // Redirects to session 1 without creating a duplicate
        $res2->assertRedirect(route('admin.transisi-terpadu.show', $session1->id));
        $this->assertSame(1, DB::table('academic_workflow_coordinators')->count());
    }

    public function test_attaches_operation_via_controller_endpoint(): void
    {
        $admin = $this->admin();
        [$source, $target, [$k12, $k10], [$target11]] = $this->setupAcademicData();

        $this->post(route('admin.transisi-terpadu.store'), [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'stages' => ['graduation', 'promotion'],
        ]);

        $session = DB::table('academic_workflow_coordinators')->latest('id')->first();

        // Buat operasi kelulusan mandiri yang cocok
        $service = new WorkflowService;
        $op = $service->prepare('graduation', '2026/2027', null, [['student_ref' => 'G001', 'decision' => 'lulus']], ['official_verified' => true, 'official_reference' => 'REF-01', 'effective_date' => '2026-10-31', 'source_version' => 'v1', 'source_content_hash' => hash('sha256', 'hash')], $admin);

        $attachRes = $this->post(route('admin.transisi-terpadu.attach', $session->id), [
            'kind' => 'graduation',
            'operation_id' => $op->id,
        ]);

        $attachRes->assertRedirect(route('admin.transisi-terpadu.show', $session->id));

        $stage = DB::table('academic_workflow_stages')
            ->where('coordinator_id', $session->id)
            ->where('kind', 'graduation')
            ->first();

        $this->assertSame($op->id, (int) $stage->operation_id);
    }
}
