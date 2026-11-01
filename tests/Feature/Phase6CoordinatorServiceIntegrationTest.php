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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase6CoordinatorServiceIntegrationTest extends TestCase
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
        $target10 = $this->buatKelas(['nama_kelas' => 'X TKJ 1 (27/28)', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        $sGrad = Siswa::factory()->create(['nis' => 'G001', 'kelas_id' => $k12->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($sGrad, $k12, '2026/2027', '2026-07-01');

        $sProm = Siswa::factory()->create(['nis' => 'P001', 'kelas_id' => $k10->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($sProm, $k10, '2026/2027', '2026-07-01');

        return [$source, $target, [$k12, $k10], [$target11, $target10], [$sGrad, $sProm]];
    }

    private function service(): WorkflowService
    {
        $registry = new ExecutorRegistry;
        $registry->register('graduation', new GraduationExecutor);
        $registry->register('promotion', new PromotionExecutor);
        $registry->register('intake', new IntakeExecutor);

        return new WorkflowService($registry);
    }

    public function test_creates_coordinator_session_with_strictly_canonical_order(): void
    {
        $actor = $this->admin();
        $coordinator = new Coordinator($this->service());
        [$source, $target] = $this->setupAcademicData();

        // 1. Two-stage combination: Promotion and Intake (Graduation skipped)
        $id2 = $coordinator->create(['intake', 'promotion'], $actor, '2026/2027', '2027/2028');
        $status2 = $coordinator->status($id2, $actor);

        $this->assertSame(['promotion', 'intake'], array_column($status2, 'kind'));
        $this->assertSame(['not_started', 'not_started'], array_column($status2, 'state'));

        // 2. Three-stage combination: All three kinds
        $id3 = $coordinator->create(['intake', 'graduation', 'promotion'], $actor);
        $status3 = $coordinator->status($id3, $actor);

        $this->assertSame(['graduation', 'promotion', 'intake'], array_column($status3, 'kind'));
    }

    public function test_prevents_duplicate_active_coordinator_session_for_same_period_pair(): void
    {
        $actor = $this->admin();
        $coordinator = new Coordinator($this->service());
        [$source, $target] = $this->setupAcademicData();

        $id1 = $coordinator->create(['graduation', 'promotion'], $actor, '2026/2027', '2027/2028');
        $this->assertIsInt($id1);

        // Creating another session for the exact same active period pair must fail closed
        try {
            $coordinator->create(['promotion', 'intake'], $actor, '2026/2027', '2027/2028');
            $this->fail('Duplicate active coordinator session should be rejected');
        } catch (DomainException $e) {
            $this->assertSame('workflow_coordinator_active_session_exists', $e->getMessage());
        }
    }

    public function test_attaches_operation_with_strict_period_and_kind_validation(): void
    {
        $actor = $this->admin();
        $service = $this->service();
        $coordinator = new Coordinator($service);
        [$source, $target, [$k12, $k10], [$target11, $target10]] = $this->setupAcademicData();

        $id = $coordinator->create(['graduation', 'promotion'], $actor, '2026/2027', '2027/2028');

        // 1. Rejects wrong kind attachment
        $promOp = $service->prepare('promotion', '2026/2027', '2027/2028', [['student_ref' => 'P001', 'target_class_id' => $target11->id]], ['effective_date' => '2027-07-01'], $actor);
        try {
            $coordinator->attach($id, 'graduation', $promOp->id, $actor);
            $this->fail('Attaching promotion op to graduation stage must be rejected');
        } catch (DomainException $e) {
            $this->assertSame('workflow_stage_attachment_invalid', $e->getMessage());
        }

        // 2. Rejects wrong period operation
        TahunAjaran::create(['kode' => '2025/2026', 'aktif' => false, 'mulai' => '2025-07-01', 'selesai' => '2026-06-30']);
        $wrongPeriodOp = $service->prepare('graduation', '2025/2026', null, [], ['official_verified' => true, 'official_reference' => 'REF-01', 'effective_date' => '2026-06-30', 'source_version' => 'v1', 'source_content_hash' => hash('sha256', 'hash')], $actor);
        try {
            $coordinator->attach($id, 'graduation', $wrongPeriodOp->id, $actor);
            $this->fail('Attaching operation with mismatched period must be rejected');
        } catch (DomainException $e) {
            $this->assertSame('workflow_stage_attachment_invalid', $e->getMessage());
        }

        // 3. Rejects operation that is already completed
        $standaloneOp = $service->prepare('graduation', '2026/2027', null, [['student_ref' => 'G001', 'decision' => 'lulus']], ['official_verified' => true, 'official_reference' => 'REF-01', 'effective_date' => '2026-10-31', 'source_version' => 'v1', 'source_content_hash' => hash('sha256', 'hash')], $actor);
        $ready = $service->validate($standaloneOp->id, $actor);
        $token = $service->confirm($standaloneOp->id, $ready->version, $ready->snapshot_hash, $actor);
        $service->execute($standaloneOp->id, $token, $actor);

        try {
            $coordinator->attach($id, 'graduation', $standaloneOp->id, $actor);
            $this->fail('Attaching completed operation must be rejected');
        } catch (DomainException $e) {
            $this->assertSame('workflow_stage_attachment_invalid', $e->getMessage());
        }

        // 4. Attaches valid draft operation successfully
        $validGradOp = $service->prepare('graduation', '2026/2027', null, [['student_ref' => 'G001', 'decision' => 'lulus']], ['official_verified' => true, 'official_reference' => 'REF-02', 'effective_date' => '2026-10-31', 'source_version' => 'v1', 'source_content_hash' => hash('sha256', 'hash2')], $actor);
        $coordinator->attach($id, 'graduation', $validGradOp->id, $actor);

        $status = $coordinator->status($id, $actor);
        $this->assertSame($validGradOp->id, (int) $status[0]['operation_id']);
        $this->assertSame('draft', $status[0]['state']);

        // 5. Rejects attaching the same operation to another coordinator
        TahunAjaran::create(['kode' => '2028/2029', 'aktif' => false, 'mulai' => '2028-07-01', 'selesai' => '2029-06-30']);
        $anotherId = $coordinator->create(['graduation', 'promotion'], $actor, '2026/2027', '2028/2029');
        try {
            $coordinator->attach($anotherId, 'graduation', $validGradOp->id, $actor);
            $this->fail('Re-attaching already attached operation must be rejected');
        } catch (DomainException $e) {
            $this->assertSame('workflow_stage_attachment_invalid', $e->getMessage());
        }
    }

    public function test_executes_sequential_stages_preserving_checkpoint_and_independent_commit(): void
    {
        $actor = $this->admin();
        $service = $this->service();
        $coordinator = new Coordinator($service);
        [$source, $target, [$k12, $k10], [$target11, $target10], [$sGrad, $sProm]] = $this->setupAcademicData();

        $id = $coordinator->create(['graduation', 'promotion'], $actor, '2026/2027', '2027/2028');

        $gradOp = $service->prepare('graduation', '2026/2027', null, [['student_ref' => 'G001', 'decision' => 'lulus']], ['official_verified' => true, 'official_reference' => 'REF-01', 'effective_date' => '2026-10-31', 'source_version' => 'v1', 'source_content_hash' => hash('sha256', 'hash1')], $actor);
        $promOp = $service->prepare('promotion', '2026/2027', '2027/2028', [['student_ref' => 'P001', 'decision' => 'naik', 'target_class_id' => $target11->id]], ['effective_date' => '2027-07-01'], $actor);

        $coordinator->attach($id, 'graduation', $gradOp->id, $actor);
        $coordinator->attach($id, 'promotion', $promOp->id, $actor);

        $readyG = $service->validate($gradOp->id, $actor);
        $tokenG = $service->confirm($gradOp->id, $readyG->version, $readyG->snapshot_hash, $actor);

        $readyP = $service->validate($promOp->id, $actor);
        $tokenP = $service->confirm($promOp->id, $readyP->version, $readyP->snapshot_hash, $actor);

        // Attempting to execute Stage 2 (Promotion) first must fail closed
        try {
            $coordinator->executeStage($id, 'promotion', $tokenP, $actor);
            $this->fail('Stage 2 must not execute before Stage 1 completes');
        } catch (DomainException $e) {
            $this->assertSame('workflow_stage_order', $e->getMessage());
        }

        // Execute Stage 1 (Graduation)
        $resultG = $coordinator->executeStage($id, 'graduation', $tokenG, $actor);
        $this->assertSame(['processed' => 1, 'skipped' => 0, 'failed' => 0], $resultG);

        $statusAfterG = $coordinator->status($id, $actor);
        $this->assertSame('completed', $statusAfterG[0]['state']);
        $this->assertSame('confirmed', $statusAfterG[1]['state']);

        // Checkpoint durable: Simulate closing session and resuming Stage 2 later
        $freshCoordinator = new Coordinator($service);
        $resultP = $freshCoordinator->executeStage($id, 'promotion', $tokenP, $actor);
        $this->assertSame(['processed' => 1, 'skipped' => 0, 'failed' => 0], $resultP);

        $finalStatus = $freshCoordinator->status($id, $actor);
        $this->assertSame(['completed', 'completed'], array_column($finalStatus, 'state'));

        // Replay completed stage returns persisted result without second mutation
        $replayG = $freshCoordinator->executeStage($id, 'graduation', 'replay-token', $actor);
        $this->assertSame($resultG, $replayG);

        // Academic year remains inactive
        $this->assertFalse((bool) $target->fresh()->aktif);
    }
}
