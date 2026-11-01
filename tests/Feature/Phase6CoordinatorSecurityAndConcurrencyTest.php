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

class Phase6CoordinatorSecurityAndConcurrencyTest extends TestCase
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
        $target11 = $this->buatKelas(['nama_kelas' => 'XI TKJ 1 (27/28)', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        $sGrad = Siswa::factory()->create(['nis' => 'G001', 'kelas_id' => $k12->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($sGrad, $k12, '2026/2027', '2026-07-01');

        return [$source, $target, $k12, $target11, $sGrad];
    }

    private function service(): WorkflowService
    {
        $registry = new ExecutorRegistry;
        $registry->register('graduation', new GraduationExecutor);
        $registry->register('promotion', new PromotionExecutor);
        $registry->register('intake', new IntakeExecutor);

        return new WorkflowService($registry);
    }

    public function test_rejects_manipulated_coordinator_id_with_mismatched_period_or_kind(): void
    {
        $admin = $this->admin();
        [$source, $target] = $this->setupAcademicData();

        // Buat koordinator untuk periode 2026/2027 -> 2027/2028
        $coordId = (new Coordinator($this->service()))->create(['graduation', 'promotion'], $admin, '2026/2027', '2027/2028');

        // Buat tahun lain
        TahunAjaran::create(['kode' => '2028/2029', 'aktif' => false, 'mulai' => '2028-07-01', 'selesai' => '2029-06-30']);

        // Upload kelulusan dengan memanipulasi tahun sumber berbeda dari koordinator
        $res = $this->post(route('admin.kelulusan.upload'), [
            'tahun_asal' => '2027/2028',
            'tahun_tujuan' => '2028/2029',
            'effective_date' => '2028-06-30',
            'official_reference' => 'SK-FAKE',
            'coordinator_id' => $coordId,
            'berkas' => \Illuminate\Http\UploadedFile::fake()->createWithContent('g.csv', "NIS,Status Kelulusan\nG001,LULUS\n"),
        ]);

        $res->assertSessionHasErrors(['berkas']);
        // Tidak ada operasi yatim yang terbentuk
        $this->assertSame(0, Operation::count());
    }

    public function test_cannot_bypass_stage_order_by_calling_direct_service_or_unattached_execution(): void
    {
        $admin = $this->admin();
        $service = $this->service();
        $coordinator = new Coordinator($service);
        [$source, $target, $k12, $target11, $sGrad] = $this->setupAcademicData();

        $coordId = $coordinator->create(['graduation', 'promotion'], $admin, '2026/2027', '2027/2028');

        $k10 = $this->buatKelas(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $sProm = Siswa::factory()->create(['nis' => 'P001', 'kelas_id' => $k10->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($sProm, $k10, '2026/2027', '2026-07-01');

        $gradOp = $service->prepare('graduation', '2026/2027', null, [['student_ref' => 'G001', 'decision' => 'lulus']], ['official_verified' => true, 'official_reference' => 'REF-01', 'effective_date' => '2026-10-31', 'source_version' => 'v1', 'source_content_hash' => hash('sha256', 'hash1')], $admin);
        $promOp = $service->prepare('promotion', '2026/2027', '2027/2028', [['student_ref' => 'P001', 'decision' => 'naik', 'target_class_id' => $target11->id]], ['effective_date' => '2027-07-01'], $admin);

        $coordinator->attach($coordId, 'graduation', $gradOp->id, $admin);
        $coordinator->attach($coordId, 'promotion', $promOp->id, $admin);

        $readyP = $service->validate($promOp->id, $admin);
        $tokenP = $service->confirm($promOp->id, $readyP->version, $readyP->snapshot_hash, $admin);

        // Memanggil execute langsung pada service untuk promOp (tahap 2) sebelum tahap 1 selesai
        try {
            $service->execute($promOp->id, $tokenP, $admin);
            $this->fail('Bypassing coordinator order via direct service execute must be rejected');
        } catch (DomainException $e) {
            $this->assertSame('workflow_stage_order', $e->getMessage());
        }

        $this->assertSame('confirmed', $promOp->fresh()->state);
    }
}
