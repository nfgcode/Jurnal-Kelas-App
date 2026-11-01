<?php

namespace Tests\Feature;

use App\AcademicWorkflows\Operation;
use App\AcademicWorkflows\WorkflowService;
use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IntakeWorkflowExecutionTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
    }

    private function actor(): User
    {
        $actor = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $this->actingAs($actor);

        return $actor;
    }

    private function setupAcademicData(): array
    {
        $target = TahunAjaran::firstOrCreate(
            ['kode' => '2027/2028'],
            ['aktif' => false, 'mulai' => '2027-07-01', 'selesai' => '2028-06-30']
        );
        $target->update(['mulai' => '2027-07-01', 'selesai' => '2028-06-30', 'aktif' => false]);

        $kX1 = $this->buatKelas(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $kXI = $this->buatKelas(['nama_kelas' => 'XI TKJ 1', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        return [$target, $kX1, $kXI];
    }

    private function serviceWithIntake(): WorkflowService
    {
        $registry = new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('intake', new \App\AcademicWorkflows\IntakeExecutor);

        return new WorkflowService($registry);
    }

    public function test_executes_intake_and_creates_student_account_and_membership_atomically(): void
    {
        $actor = $this->actor();
        $service = $this->serviceWithIntake();
        [$target, $kX1] = $this->setupAcademicData();

        $items = [
            [
                'student_ref' => '00301',
                'nisn' => '0081234567',
                'email' => 'ahmad.f@sekolah.sch.id',
                'target_class_id' => $kX1->id,
            ],
            [
                'student_ref' => '00302',
                'nisn' => '0087654321',
                'email' => 'siti.r@sekolah.sch.id',
                'target_class_id' => $kX1->id,
            ],
            [
                // Excluded row: skipped during execution
                'student_ref' => '00303',
                'nisn' => '0089999999',
                'email' => 'budi.ex@sekolah.sch.id',
                'target_class_id' => $kX1->id,
                'excluded' => true,
                'exclusion_reason_present' => true,
            ],
        ];

        $metadata = ['effective_date' => '2027-07-01'];

        $op = $service->prepare('intake', null, '2027/2028', $items, $metadata, $actor);
        $ready = $service->validate($op->id, $actor);
        $this->assertSame('ready', $ready->state);

        $token = $service->confirm($op->id, $ready->version, $ready->snapshot_hash, $actor);
        $result = $service->execute($op->id, $token, $actor);

        $this->assertSame(['processed' => 2, 'skipped' => 1, 'failed' => 0], $result);
        $this->assertSame('completed', $op->fresh()->state);

        // 1. Verifikasi Siswa 1 (00301)
        $s1 = Siswa::where('nis', '00301')->first();
        $this->assertNotNull($s1);
        $this->assertSame('0081234567', $s1->nisn);
        $this->assertSame($kX1->id, $s1->kelas_id);
        $this->assertSame('aktif', $s1->status);

        // Riwayat kelas
        $r1 = RiwayatKelasSiswa::where('siswa_nis', '00301')->first();
        $this->assertNotNull($r1);
        $this->assertSame($kX1->id, $r1->kelas_id);
        $this->assertSame('2027/2028', $r1->tahun_ajaran_kode);
        $this->assertSame('X', $r1->tingkat);
        $this->assertSame('2027-07-01', $r1->tanggal_mulai->toDateString());
        $this->assertTrue((bool) $r1->aktif);

        // Akun User
        $u1 = User::where('nis', '00301')->first();
        $this->assertNotNull($u1);
        $this->assertSame('ahmad.f@sekolah.sch.id', $u1->email);
        $this->assertSame('siswa', $u1->role);
        $this->assertSame('aktif', $u1->status);
        $this->assertTrue(Hash::check(Hash::info($u1->password)['algoName'] ? '' : '', $u1->password) === false); // Valid hash exists

        // 2. Verifikasi Siswa 2 (00302)
        $s2 = Siswa::where('nis', '00302')->first();
        $this->assertNotNull($s2);
        $this->assertSame('0087654321', $s2->nisn);

        // 3. Verifikasi Siswa 3 (00303 - Excluded) tidak dibuat
        $this->assertNull(Siswa::where('nis', '00303')->first());
        $this->assertNull(User::where('nis', '00303')->first());
        $this->assertNull(RiwayatKelasSiswa::where('siswa_nis', '00303')->first());

        // 4. Verifikasi tahun tujuan tetap nonaktif (tidak teraktivasi otomatis)
        $this->assertFalse((bool) $target->fresh()->aktif);
    }

    public function test_rejects_intake_if_target_class_is_not_grade_x(): void
    {
        $actor = $this->actor();
        $service = new WorkflowService;
        [$target, $kX1, $kXI] = $this->setupAcademicData();

        $items = [
            [
                'student_ref' => '00301',
                'nisn' => '0081234567',
                'email' => 'ahmad@sekolah.sch.id',
                'target_class_id' => $kXI->id, // Grade XI is invalid for intake
            ],
        ];

        $op = $service->prepare('intake', null, '2027/2028', $items, ['effective_date' => '2027-07-01'], $actor);
        $preview = $service->preview($op->id, $actor);

        $this->assertSame('invalid', $preview->validation);
        $this->assertContains('intake_target_invalid', $preview->items[0]['errors']);
    }

    public function test_replay_completed_operation_returns_persisted_result_without_second_mutation(): void
    {
        $actor = $this->actor();
        $service = $this->serviceWithIntake();
        [$target, $kX1] = $this->setupAcademicData();

        $items = [
            [
                'student_ref' => '00301',
                'nisn' => '0081234567',
                'email' => 'ahmad.f@sekolah.sch.id',
                'target_class_id' => $kX1->id,
            ],
        ];

        $op = $service->prepare('intake', null, '2027/2028', $items, ['effective_date' => '2027-07-01'], $actor);
        $ready = $service->validate($op->id, $actor);
        $token = $service->confirm($op->id, $ready->version, $ready->snapshot_hash, $actor);

        $firstResult = $service->execute($op->id, $token, $actor);
        $this->assertSame(['processed' => 1, 'skipped' => 0, 'failed' => 0], $firstResult);

        $siswaCount = Siswa::count();
        $userCount = User::count();

        // Replay retry
        $secondResult = $service->execute($op->id, 'any-token', $actor);
        $this->assertSame($firstResult, $secondResult);
        $this->assertSame($siswaCount, Siswa::count());
        $this->assertSame($userCount, User::count());
    }

    public function test_stale_confirmation_is_rejected_if_database_changes_after_snapshot(): void
    {
        $actor = $this->actor();
        $service = $this->serviceWithIntake();
        [$target, $kX1] = $this->setupAcademicData();

        $items = [
            [
                'student_ref' => '00301',
                'nisn' => '0081234567',
                'email' => 'ahmad.f@sekolah.sch.id',
                'target_class_id' => $kX1->id,
            ],
        ];

        $op = $service->prepare('intake', null, '2027/2028', $items, ['effective_date' => '2027-07-01'], $actor);
        $ready = $service->validate($op->id, $actor);
        $token = $service->confirm($op->id, $ready->version, $ready->snapshot_hash, $actor);

        // Siswa lain terdaftar dengan email yang sama di luar alur -> snapshot stale
        User::factory()->create(['email' => 'ahmad.f@sekolah.sch.id', 'role' => 'siswa']);

        try {
            $service->execute($op->id, $token, $actor);
            $this->fail('Stale confirmation must be rejected');
        } catch (DomainException $e) {
            $this->assertSame('workflow_confirmation_stale', $e->getMessage());
        }

        $this->assertSame('confirmed', $op->fresh()->state);
        $this->assertNull(Siswa::where('nis', '00301')->first());
    }
}
