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
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase6CoordinatorResilienceAndLinkingTest extends TestCase
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

        $target12 = $this->buatKelas(['nama_kelas' => 'XII TKJ 1 (27/28)', 'tingkat' => 'XII', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $target11 = $this->buatKelas(['nama_kelas' => 'XI TKJ 1 (27/28)', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $target10 = $this->buatKelas(['nama_kelas' => 'X TKJ 1 (27/28)', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        $sGrad = Siswa::factory()->create(['nis' => 'G001', 'kelas_id' => $k12->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($sGrad, $k12, '2026/2027', '2026-07-01');

        $sProm = Siswa::factory()->create(['nis' => 'P001', 'kelas_id' => $k10->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($sProm, $k10, '2026/2027', '2026-07-01');

        return [$source, $target, [$k12, $k10], [$target12, $target11, $target10], [$sGrad, $sProm]];
    }

    private function service(): WorkflowService
    {
        $registry = new ExecutorRegistry;
        $registry->register('graduation', new GraduationExecutor);
        $registry->register('promotion', new PromotionExecutor);
        $registry->register('intake', new IntakeExecutor);

        return new WorkflowService($registry);
    }

    public function test_seamless_auto_linking_from_workflow_form_to_coordinator(): void
    {
        $admin = $this->admin();
        [$source, $target, [$k12, $k10], [$target12, $target11, $target10]] = $this->setupAcademicData();

        // 1. Setup session via TransisiTerpaduController
        $this->post(route('admin.transisi-terpadu.store'), [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'stages' => ['graduation', 'promotion', 'intake'],
        ]);
        $coord = DB::table('academic_workflow_coordinators')->latest('id')->first();

        // 2. Upload berkas kelulusan dengan membawa parameter coordinator_id
        $csvGrad = "NIS,Nama Siswa,Status Kelulusan\nG001,Siswa Lulus,LULUS\n";
        $fileGrad = UploadedFile::fake()->createWithContent('grad.csv', $csvGrad);

        $resUpload = $this->post(route('admin.kelulusan.upload'), [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'effective_date' => '2026-10-31',
            'official_reference' => 'SK-GRAD-01',
            'coordinator_id' => $coord->id,
            'berkas' => $fileGrad,
        ]);
        $resUpload->assertRedirect();

        // Verifikasi tahap 1 ter-attach secara otomatis tanpa input manual ID di dashboard
        $stageGrad = DB::table('academic_workflow_stages')
            ->where('coordinator_id', $coord->id)
            ->where('kind', 'graduation')
            ->first();
        $this->assertNotNull($stageGrad->operation_id);

        $gradOp = Operation::find($stageGrad->operation_id);
        $this->assertSame('ready', $gradOp->state);

        // 3. Inisiasi kenaikan dengan membawa coordinator_id
        $resProm = $this->post(route('admin.kenaikan.inisiasi'), [
            'tahun_asal' => '2026/2027',
            'tahun_tujuan' => '2027/2028',
            'metode' => 'default',
            'coordinator_id' => $coord->id,
        ]);
        $resProm->assertRedirect();

        $stageProm = DB::table('academic_workflow_stages')
            ->where('coordinator_id', $coord->id)
            ->where('kind', 'promotion')
            ->first();
        $this->assertNotNull($stageProm->operation_id);

        // 4. Inisiasi intake dengan membawa coordinator_id
        $csvIntake = "NIS,NISN,Nama Lengkap,Email,Kelas X Tujuan,Jenis Kelamin\n00901,0090190101,Siswa Baru,baru@sekolah.sch.id,X TKJ 1 (27/28),L\n";
        $fileIntake = UploadedFile::fake()->createWithContent('intake.csv', $csvIntake);

        $resIntake = $this->post(route('admin.intake.upload'), [
            'tahun_tujuan' => '2027/2028',
            'effective_date' => '2027-07-01',
            'official_reference' => 'PPDB-2027',
            'coordinator_id' => $coord->id,
            'berkas' => $fileIntake,
        ]);
        $resIntake->assertRedirect();

        $stageIntake = DB::table('academic_workflow_stages')
            ->where('coordinator_id', $coord->id)
            ->where('kind', 'intake')
            ->first();
        $this->assertNotNull($stageIntake->operation_id);

        // Dashboard progres sekarang menampilkan semua tahap terhubung
        $dashRes = $this->get(route('admin.transisi-terpadu.show', $coord->id));
        $dashRes->assertStatus(200);
        $dashRes->assertSee('#' . $stageGrad->operation_id);
        $dashRes->assertSee('#' . $stageProm->operation_id);
        $dashRes->assertSee('#' . $stageIntake->operation_id);

        // Verifikasi tombol navigasi "Kembali ke Transisi Terpadu" pada halaman show modul mandiri
        $gradShowRes = $this->get(route('admin.kelulusan.show', $stageGrad->operation_id));
        // Operasi kelulusan belum completed maka redirect ke review
        $this->assertTrue($gradShowRes->isRedirect());
    }

    public function test_checkpoint_resume_across_different_admin_and_time(): void
    {
        $admin1 = $this->admin();
        $service = $this->service();
        $coordinator = new Coordinator($service);
        [$source, $target, [$k12, $k10], [$target12, $target11]] = $this->setupAcademicData();

        $id = $coordinator->create(['graduation', 'promotion'], $admin1, '2026/2027', '2027/2028');

        $gradOp = $service->prepare('graduation', '2026/2027', null, [['student_ref' => 'G001', 'decision' => 'lulus']], ['official_verified' => true, 'official_reference' => 'REF-01', 'effective_date' => '2026-10-31', 'source_version' => 'v1', 'source_content_hash' => hash('sha256', 'hash1')], $admin1);
        $coordinator->attach($id, 'graduation', $gradOp->id, $admin1);

        $readyG = $service->validate($gradOp->id, $admin1);
        $tokenG = $service->confirm($gradOp->id, $readyG->version, $readyG->snapshot_hash, $admin1);
        $coordinator->executeStage($id, 'graduation', $tokenG, $admin1);

        // Simulasi logout admin 1, waktu berjalan 1 bulan kemudian (Juni), admin 2 login
        $admin2 = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $this->actingAs($admin2);

        // Admin 2 membuka kembali dashboard koordinator dari database persisten
        $freshCoord = new Coordinator($service);
        $status = $freshCoord->status($id, $admin2);

        $this->assertSame('completed', $status[0]['state']);
        $this->assertSame('not_started', $status[1]['state']);

        // Admin 2 melanjutkan tahap 2 (Kenaikan)
        $promOp = $service->prepare('promotion', '2026/2027', '2027/2028', [['student_ref' => 'P001', 'decision' => 'naik', 'target_class_id' => $target11->id]], ['effective_date' => '2027-07-01'], $admin2);
        $freshCoord->attach($id, 'promotion', $promOp->id, $admin2);

        $readyP = $service->validate($promOp->id, $admin2);
        $tokenP = $service->confirm($promOp->id, $readyP->version, $readyP->snapshot_hash, $admin2);
        $resP = $freshCoord->executeStage($id, 'promotion', $tokenP, $admin2);

        $this->assertSame(['processed' => 1, 'skipped' => 0, 'failed' => 0], $resP);
        $this->assertSame(['completed', 'completed'], array_column($freshCoord->status($id, $admin2), 'state'));
    }

    public function test_stage_two_failure_does_not_rollback_stage_one_and_retry_is_resilient(): void
    {
        $admin = $this->admin();
        $service = $this->service();
        $coordinator = new Coordinator($service);
        [$source, $target, [$k12, $k10], [$target12, $target11]] = $this->setupAcademicData();

        $id = $coordinator->create(['graduation', 'promotion'], $admin, '2026/2027', '2027/2028');

        // Stage 1 selesai
        $gradOp = $service->prepare('graduation', '2026/2027', null, [['student_ref' => 'G001', 'decision' => 'lulus']], ['official_verified' => true, 'official_reference' => 'REF-01', 'effective_date' => '2026-10-31', 'source_version' => 'v1', 'source_content_hash' => hash('sha256', 'hash1')], $admin);
        $coordinator->attach($id, 'graduation', $gradOp->id, $admin);
        $readyG = $service->validate($gradOp->id, $admin);
        $tokenG = $service->confirm($gradOp->id, $readyG->version, $readyG->snapshot_hash, $admin);
        $coordinator->executeStage($id, 'graduation', $tokenG, $admin);

        // Stage 2 disiapkan tapi salah target class (misal kelas terhapus sebelum eksekusi)
        $promOp = $service->prepare('promotion', '2026/2027', '2027/2028', [['student_ref' => 'P001', 'decision' => 'naik', 'target_class_id' => $target11->id]], ['effective_date' => '2027-07-01'], $admin);
        $coordinator->attach($id, 'promotion', $promOp->id, $admin);
        $readyP = $service->validate($promOp->id, $admin);
        $tokenP = $service->confirm($promOp->id, $readyP->version, $readyP->snapshot_hash, $admin);

        // Double promotion dummy to simulate executor-level transient failure
        $failingRegistry = new ExecutorRegistry;
        $failingRegistry->register('graduation', new GraduationExecutor);
        $failPromotion = true;
        $failingRegistry->register('promotion', new class($failPromotion) implements \App\AcademicWorkflows\Executor {
            public function __construct(private &$fail) {}
            public function execute(Operation $op, \App\AcademicWorkflows\Preview $prev): array {
                if ($this->fail) {
                    throw new \RuntimeException('Simulated executor failure in stage 2');
                }
                return (new PromotionExecutor)->execute($op, $prev);
            }
        });

        $failingService = new WorkflowService($failingRegistry);
        $failingCoordinator = new Coordinator($failingService);

        try {
            $failingCoordinator->executeStage($id, 'promotion', $tokenP, $admin);
            $this->fail('Should fail due to simulated executor error');
        } catch (DomainException $e) {
            $this->assertSame('workflow_execution_failed', $e->getMessage());
        }

        // Verifikasi Stage 1 tetap COMPLETED dan siswa G001 tetap lulus
        $status = $coordinator->status($id, $admin);
        $this->assertSame('completed', $status[0]['state']);
        $this->assertSame('failed', $status[1]['state']);
        $this->assertSame('lulus', Siswa::where('nis', 'G001')->value('status'));

        // Retry stage 2 setelah perbaikan error
        $failPromotion = false;
        $service->retry($promOp->id, $admin);
        $newReady = $service->validate($promOp->id, $admin);
        $newToken = $service->confirm($promOp->id, $newReady->version, $newReady->snapshot_hash, $admin);

        $resRetry = $failingCoordinator->executeStage($id, 'promotion', $newToken, $admin);
        $this->assertSame(['processed' => 1, 'skipped' => 0, 'failed' => 0], $resRetry);

        $this->assertSame(['completed', 'completed'], array_column($coordinator->status($id, $admin), 'state'));
    }
}
