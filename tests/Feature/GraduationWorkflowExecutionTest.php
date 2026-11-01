<?php

namespace Tests\Feature;

use App\AcademicWorkflows\ExecutorRegistry;
use App\AcademicWorkflows\GraduationExecutor;
use App\AcademicWorkflows\WorkflowService;
use App\Models\Alumni;
use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class GraduationWorkflowExecutionTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        Auth::login($admin);
        return $admin;
    }

    private function setupYears(): array
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

        return [$source, $target];
    }

    public function test_graduation_executor_graduates_students_creates_alumni_and_closes_membership(): void
    {
        [$source, $target] = $this->setupYears();
        $actor = $this->admin();

        $class = $this->buatKelas(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);

        $s1 = Siswa::factory()->create(['nis' => '00121', 'nama' => 'Siswa Satu', 'kelas_id' => $class->id, 'status' => 'aktif']);
        $s2 = Siswa::factory()->create(['nis' => '00122', 'nama' => 'Siswa Dua', 'kelas_id' => $class->id, 'status' => 'aktif']);
        $class->update(['ketua_nis' => $s1->nis]);

        RiwayatKelasSiswa::daftarkan($s1, $class, '2026/2027', '2026-07-01');
        RiwayatKelasSiswa::daftarkan($s2, $class, '2026/2027', '2026-07-01');

        $registry = new ExecutorRegistry();
        $registry->register('graduation', new GraduationExecutor());
        $service = new WorkflowService($registry);

        $metadata = [
            'official_verified' => true,
            'official_reference' => 'SK-LULUS-2027',
            'source_content_hash' => hash('sha256', 'file-content'),
            'source_version' => 'v1',
            'effective_date' => '2027-05-15',
        ];

        $items = [
            ['student_ref' => '00121', 'decision' => 'lulus'],
            ['student_ref' => '00122', 'decision' => 'lulus'],
        ];

        $op = $service->prepare('graduation', '2026/2027', null, $items, $metadata, $actor);
        $preview = $service->preview($op->id, $actor);
        $this->assertSame('valid', $preview->validation);

        $validated = $service->validate($op->id, $actor);
        $this->assertSame('ready', $validated->state);

        $token = $service->confirm($op->id, $validated->version, $validated->snapshot_hash, $actor);
        $result = $service->execute($op->id, $token, $actor);

        $this->assertSame(['processed' => 2, 'skipped' => 0, 'failed' => 0], $result);
        $this->assertSame('completed', $op->fresh()->state);

        // Assert Siswa status
        $this->assertSame('lulus', $s1->fresh()->status);
        $this->assertNull($s1->fresh()->kelas_id);
        $this->assertSame('lulus', $s2->fresh()->status);
        $this->assertNull($s2->fresh()->kelas_id);

        // Assert Ketua Kelas cleared
        $this->assertNull($class->fresh()->ketua_nis);

        // Assert Riwayat
        $r1 = RiwayatKelasSiswa::where('siswa_nis', '00121')->first();
        $this->assertFalse((bool) $r1->aktif);
        $this->assertSame('lulus', $r1->status);
        $this->assertSame('2027-05-15', $r1->tanggal_selesai->toDateString());
        $this->assertSame('SK-LULUS-2027', $r1->catatan);

        // Assert Alumni
        $a1 = Alumni::where('siswa_nis', '00121')->first();
        $this->assertNotNull($a1);
        $this->assertSame('2026/2027', $a1->tahun_lulus);
        $this->assertSame('2027-05-15', $a1->tanggal_lulus->toDateString());
        $this->assertSame($class->id, $a1->kelas_terakhir_id);
        $this->assertSame('SK-LULUS-2027', $a1->keterangan);
    }

    public function test_graduation_executor_handles_tidak_lulus_and_places_in_target_year_class(): void
    {
        [$source, $target] = $this->setupYears();
        $actor = $this->admin();

        $sourceClass = $this->buatKelas(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $targetClass = $this->buatKelas(['nama_kelas' => 'XII IPA 1 (27/28)', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        $s1 = Siswa::factory()->create(['nis' => '00121', 'nama' => 'Siswa Satu', 'kelas_id' => $sourceClass->id, 'status' => 'aktif']);
        $s2 = Siswa::factory()->create(['nis' => '00122', 'nama' => 'Siswa Dua', 'kelas_id' => $sourceClass->id, 'status' => 'aktif']);

        RiwayatKelasSiswa::daftarkan($s1, $sourceClass, '2026/2027', '2026-07-01');
        RiwayatKelasSiswa::daftarkan($s2, $sourceClass, '2026/2027', '2026-07-01');

        $registry = new ExecutorRegistry();
        $registry->register('graduation', new GraduationExecutor());
        $service = new WorkflowService($registry);

        $metadata = [
            'official_verified' => true,
            'official_reference' => 'SK-LULUS-2027',
            'source_content_hash' => hash('sha256', 'file-content'),
            'source_version' => 'v1',
            'effective_date' => '2027-05-15',
        ];

        $items = [
            ['student_ref' => '00121', 'decision' => 'lulus'],
            ['student_ref' => '00122', 'decision' => 'tidak_lulus', 'target_class_id' => $targetClass->id],
        ];

        $op = $service->prepare('graduation', '2026/2027', '2027/2028', $items, $metadata, $actor);
        $preview = $service->preview($op->id, $actor);
        $this->assertSame('valid', $preview->validation);

        $validated = $service->validate($op->id, $actor);
        $token = $service->confirm($op->id, $validated->version, $validated->snapshot_hash, $actor);
        $result = $service->execute($op->id, $token, $actor);

        $this->assertSame(['processed' => 2, 'skipped' => 0, 'failed' => 0], $result);

        // s1 lulus
        $this->assertSame('lulus', $s1->fresh()->status);
        $this->assertNull($s1->fresh()->kelas_id);
        $this->assertTrue(Alumni::where('siswa_nis', '00121')->exists());

        // s2 tidak lulus
        $this->assertSame('aktif', $s2->fresh()->status);
        $this->assertSame($targetClass->id, $s2->fresh()->kelas_id);
        $this->assertFalse(Alumni::where('siswa_nis', '00122')->exists());

        // s2 memberships
        $oldMembership = RiwayatKelasSiswa::where('siswa_nis', '00122')->where('tahun_ajaran_kode', '2026/2027')->first();
        $this->assertFalse((bool) $oldMembership->aktif);
        $this->assertSame('tinggal_kelas', $oldMembership->status);
        $this->assertSame('2027-06-30', $oldMembership->tanggal_selesai->toDateString());

        $newMembership = RiwayatKelasSiswa::where('siswa_nis', '00122')->where('tahun_ajaran_kode', '2027/2028')->first();
        $this->assertTrue((bool) $newMembership->aktif);
        $this->assertSame('tinggal_kelas', $newMembership->status);
        $this->assertSame($targetClass->id, $newMembership->kelas_id);
        $this->assertSame('2027-07-01', $newMembership->tanggal_mulai->toDateString());
        $this->assertNull($newMembership->tanggal_selesai);
    }

    public function test_graduation_replay_returns_persisted_result_without_duplicate_alumni(): void
    {
        [$source, $target] = $this->setupYears();
        $actor = $this->admin();

        $class = $this->buatKelas(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $s1 = Siswa::factory()->create(['nis' => '00121', 'nama' => 'Siswa Satu', 'kelas_id' => $class->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s1, $class, '2026/2027', '2026-07-01');

        $registry = new ExecutorRegistry();
        $registry->register('graduation', new GraduationExecutor());
        $service = new WorkflowService($registry);

        $metadata = [
            'official_verified' => true,
            'official_reference' => 'SK-LULUS-2027',
            'source_content_hash' => hash('sha256', 'file-content'),
            'source_version' => 'v1',
            'effective_date' => '2027-05-15',
        ];

        $op = $service->prepare('graduation', '2026/2027', null, [['student_ref' => '00121', 'decision' => 'lulus']], $metadata, $actor);
        $validated = $service->validate($op->id, $actor);
        $token = $service->confirm($op->id, $validated->version, $validated->snapshot_hash, $actor);

        $result1 = $service->execute($op->id, $token, $actor);
        $this->assertSame(['processed' => 1, 'skipped' => 0, 'failed' => 0], $result1);
        $this->assertSame(1, Alumni::where('siswa_nis', '00121')->count());

        // Replay with same or any token
        $result2 = $service->execute($op->id, $token, $actor);
        $this->assertSame($result1, $result2);
        $this->assertSame(1, Alumni::where('siswa_nis', '00121')->count());
    }

    public function test_graduation_workflow_rejects_execution_if_omitted_students_remain(): void
    {
        [$source, $target] = $this->setupYears();
        $actor = $this->admin();

        $class = $this->buatKelas(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $s1 = Siswa::factory()->create(['nis' => '00121', 'nama' => 'Siswa Satu', 'kelas_id' => $class->id, 'status' => 'aktif']);
        $s2 = Siswa::factory()->create(['nis' => '00122', 'nama' => 'Siswa Dua', 'kelas_id' => $class->id, 'status' => 'aktif']);

        RiwayatKelasSiswa::daftarkan($s1, $class, '2026/2027', '2026-07-01');
        RiwayatKelasSiswa::daftarkan($s2, $class, '2026/2027', '2026-07-01');

        $registry = new ExecutorRegistry();
        $registry->register('graduation', new GraduationExecutor());
        $service = new WorkflowService($registry);

        $metadata = [
            'official_verified' => true,
            'official_reference' => 'SK-LULUS-2027',
            'source_content_hash' => hash('sha256', 'file-content'),
            'source_version' => 'v1',
            'effective_date' => '2027-05-15',
        ];

        // Only s1 provided; s2 omitted
        $op = $service->prepare('graduation', '2026/2027', null, [['student_ref' => '00121', 'decision' => 'lulus']], $metadata, $actor);
        $validated = $service->validate($op->id, $actor);

        $this->assertSame('needs_attention', $validated->state);
        $this->assertSame('unverified', $validated->validation);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('workflow_confirmation_stale');
        $service->confirm($op->id, $validated->version, $validated->snapshot_hash ?? '', $actor);
    }

    public function test_graduation_does_not_execute_promotion_or_affect_grades_x_and_xi(): void
    {
        [$source, $target] = $this->setupYears();
        $actor = $this->admin();

        $c12 = $this->buatKelas(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $c10 = $this->buatKelas(['nama_kelas' => 'X IPA 1', 'tingkat' => 'X', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);

        $s12 = Siswa::factory()->create(['nis' => '00121', 'kelas_id' => $c12->id, 'status' => 'aktif']);
        $s10 = Siswa::factory()->create(['nis' => '00101', 'kelas_id' => $c10->id, 'status' => 'aktif']);

        RiwayatKelasSiswa::daftarkan($s12, $c12, '2026/2027', '2026-07-01');
        RiwayatKelasSiswa::daftarkan($s10, $c10, '2026/2027', '2026-07-01');

        $registry = new ExecutorRegistry();
        $registry->register('graduation', new GraduationExecutor());
        $service = new WorkflowService($registry);

        $metadata = [
            'official_verified' => true,
            'official_reference' => 'SK-LULUS-2027',
            'source_content_hash' => hash('sha256', 'file-content'),
            'source_version' => 'v1',
            'effective_date' => '2027-05-15',
        ];

        $op = $service->prepare('graduation', '2026/2027', null, [['student_ref' => '00121', 'decision' => 'lulus']], $metadata, $actor);
        $validated = $service->validate($op->id, $actor);
        $token = $service->confirm($op->id, $validated->version, $validated->snapshot_hash, $actor);
        $service->execute($op->id, $token, $actor);

        // Grade XII graduated
        $this->assertSame('lulus', $s12->fresh()->status);
        $this->assertNull($s12->fresh()->kelas_id);

        // Grade X student is completely untouched
        $this->assertSame('aktif', $s10->fresh()->status);
        $this->assertSame($c10->id, $s10->fresh()->kelas_id);
        $r10 = RiwayatKelasSiswa::where('siswa_nis', '00101')->first();
        $this->assertTrue((bool) $r10->aktif);
        $this->assertNull($r10->tanggal_selesai);
    }

    public function test_preview_does_not_modify_academic_or_workflow_tables(): void
    {
        [$source, $target] = $this->setupYears();
        $actor = $this->admin();

        $c12 = $this->buatKelas(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $s12 = Siswa::factory()->create(['nis' => '00121', 'kelas_id' => $c12->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s12, $c12, '2026/2027', '2026-07-01');

        $service = new WorkflowService();
        $metadata = [
            'official_verified' => true,
            'official_reference' => 'SK-LULUS-2027',
            'source_content_hash' => hash('sha256', 'file-content'),
            'source_version' => 'v1',
            'effective_date' => '2027-05-15',
        ];

        $op = $service->prepare('graduation', '2026/2027', null, [['student_ref' => '00121', 'decision' => 'lulus']], $metadata, $actor);

        $alumniBefore = Alumni::count();
        $siswaBefore = $s12->fresh()->getAttributes();
        $riwayatBefore = RiwayatKelasSiswa::first()->getAttributes();

        $preview = $service->preview($op->id, $actor);

        $this->assertSame('valid', $preview->validation);
        $this->assertSame($alumniBefore, Alumni::count());
        $this->assertSame($siswaBefore, $s12->fresh()->getAttributes());
        $this->assertSame($riwayatBefore, RiwayatKelasSiswa::first()->getAttributes());
    }

    public function test_snapshot_change_between_validation_and_execution_invalidates_confirmation(): void
    {
        [$source, $target] = $this->setupYears();
        $actor = $this->admin();

        $c12 = $this->buatKelas(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $s1 = Siswa::factory()->create(['nis' => '00121', 'kelas_id' => $c12->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s1, $c12, '2026/2027', '2026-07-01');

        $registry = new ExecutorRegistry();
        $registry->register('graduation', new GraduationExecutor());
        $service = new WorkflowService($registry);

        $metadata = [
            'official_verified' => true,
            'official_reference' => 'SK-LULUS-2027',
            'source_content_hash' => hash('sha256', 'file-content'),
            'source_version' => 'v1',
            'effective_date' => '2027-05-15',
        ];

        $op = $service->prepare('graduation', '2026/2027', null, [['student_ref' => '00121', 'decision' => 'lulus']], $metadata, $actor);
        $validated = $service->validate($op->id, $actor);
        $token = $service->confirm($op->id, $validated->version, $validated->snapshot_hash, $actor);

        // Database changed: department of class becomes inactive
        \App\Models\Jurusan::where('kode', 'IPA')->update(['aktif' => false]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('workflow_confirmation_stale');
        $service->execute($op->id, $token, $actor);
    }

    public function test_transaction_failure_rolls_back_mutations_and_records_durable_failure(): void
    {
        [$source, $target] = $this->setupYears();
        $actor = $this->admin();

        $c12 = $this->buatKelas(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $s1 = Siswa::factory()->create(['nis' => '00121', 'kelas_id' => $c12->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s1, $c12, '2026/2027', '2026-07-01');

        // Faulty executor that throws halfway
        $faultyExecutor = new class implements \App\AcademicWorkflows\Executor {
            public function execute(\App\AcademicWorkflows\Operation $op, \App\AcademicWorkflows\Preview $prev): array
            {
                Siswa::where('nis', '00121')->update(['status' => 'lulus']);
                throw new \RuntimeException('Database disk full simulation');
            }
        };

        $registry = new ExecutorRegistry();
        $registry->register('graduation', $faultyExecutor);
        $service = new WorkflowService($registry);

        $metadata = [
            'official_verified' => true,
            'official_reference' => 'SK-LULUS-2027',
            'source_content_hash' => hash('sha256', 'file-content'),
            'source_version' => 'v1',
            'effective_date' => '2027-05-15',
        ];

        $op = $service->prepare('graduation', '2026/2027', null, [['student_ref' => '00121', 'decision' => 'lulus']], $metadata, $actor);
        $validated = $service->validate($op->id, $actor);
        $token = $service->confirm($op->id, $validated->version, $validated->snapshot_hash, $actor);

        try {
            $service->execute($op->id, $token, $actor);
            $this->fail('Should have thrown failure');
        } catch (\DomainException $e) {
            $this->assertSame('workflow_execution_failed', $e->getMessage());
        }

        // Student status mutation was rolled back
        $this->assertSame('aktif', $s1->fresh()->status);
        $this->assertSame($c12->id, $s1->fresh()->kelas_id);

        // Durable operation state is failed
        $this->assertSame('failed', $op->fresh()->state);
        $this->assertSame('workflow_execution_failed', $op->fresh()->error_code);
    }

    public function test_individual_correction_after_finalization_via_lifecycle_service(): void
    {
        [$source, $target] = $this->setupYears();
        $actor = $this->admin();

        $c12 = $this->buatKelas(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII', 'jurusan_kode' => 'IPA', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $s1 = Siswa::factory()->create(['nis' => '00121', 'kelas_id' => $c12->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s1, $c12, '2026/2027', '2026-07-01');

        $registry = new ExecutorRegistry();
        $registry->register('graduation', new GraduationExecutor());
        $service = new WorkflowService($registry);

        $metadata = [
            'official_verified' => true,
            'official_reference' => 'SK-LULUS-2027',
            'source_content_hash' => hash('sha256', 'file-content'),
            'source_version' => 'v1',
            'effective_date' => '2027-05-15',
        ];

        $op = $service->prepare('graduation', '2026/2027', null, [['student_ref' => '00121', 'decision' => 'lulus']], $metadata, $actor);
        $validated = $service->validate($op->id, $actor);
        $token = $service->confirm($op->id, $validated->version, $validated->snapshot_hash, $actor);
        $service->execute($op->id, $token, $actor);

        $this->assertSame('lulus', $s1->fresh()->status);

        // Individual post-graduation correction requires reason via SiklusHidupSiswa
        $lifecycle = app(\App\Support\SiklusHidupSiswa::class);

        // Unsafe ungraduation is rejected as protected by SiklusHidupSiswa
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $lifecycle->perbarui($s1->fresh(), [
            'status' => 'aktif',
            'kelas_id' => $c12->id,
            'alasan' => 'Salah input status kelulusan',
        ]);
    }
}
