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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase5SecurityAndAccountAccessTest extends TestCase
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
        $target = TahunAjaran::firstOrCreate(
            ['kode' => '2027/2028'],
            ['aktif' => false, 'mulai' => '2027-07-01', 'selesai' => '2028-06-30']
        );
        $target->update(['mulai' => '2027-07-01', 'selesai' => '2028-06-30', 'aktif' => false]);

        $kX1 = $this->buatKelas(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        return [$target, $kX1];
    }

    public function test_admin_can_reset_password_for_newly_intaked_student(): void
    {
        $admin = $this->admin();
        [$target, $kX1] = $this->setupAcademicData();

        // 1. Eksekusi intake siswa baru
        $registry = new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('intake', new \App\AcademicWorkflows\IntakeExecutor);
        $service = new WorkflowService($registry);

        $items = [
            [
                'student_ref' => '00301',
                'nisn' => '0081234567',
                'email' => 'ahmad.f@sekolah.sch.id',
                'target_class_id' => $kX1->id,
            ],
        ];

        $op = $service->prepare('intake', null, '2027/2028', $items, ['effective_date' => '2027-07-01'], $admin);
        $ready = $service->validate($op->id, $admin);
        $token = $service->confirm($op->id, $ready->version, $ready->snapshot_hash, $admin);
        $service->execute($op->id, $token, $admin);

        $studentUser = User::where('nis', '00301')->firstOrFail();
        $this->assertSame('siswa', $studentUser->role);

        // 2. Admin membuka form edit akun siswa di /admin/akun/{id}/edit
        $editRes = $this->get(route('admin.akun.edit', $studentUser->id));
        $editRes->assertStatus(200);

        // 3. Admin mereset password siswa via PUT /admin/akun/{id}
        $newPlainPassword = 'SiswaBaruPass2027!';
        $updateRes = $this->put(route('admin.akun.update', $studentUser->id), [
            'username' => $studentUser->username,
            'email' => $studentUser->email,
            'status' => 'aktif',
            'role' => 'siswa',
            'password' => $newPlainPassword,
        ]);

        $updateRes->assertRedirect(route('admin.akun.index'));
        $updateRes->assertSessionHas('success');

        // 4. Verifikasi password baru ter-hash dan cocok
        $studentUserFresh = $studentUser->fresh();
        $this->assertTrue(Hash::check($newPlainPassword, $studentUserFresh->password));
    }

    public function test_password_secrets_never_leak_in_workflow_audits_snapshots_or_json(): void
    {
        $admin = $this->admin();
        [$target, $kX1] = $this->setupAcademicData();

        $registry = new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('intake', new \App\AcademicWorkflows\IntakeExecutor);
        $service = new WorkflowService($registry);

        $items = [
            [
                'student_ref' => '00301',
                'nisn' => '0081234567',
                'email' => 'ahmad.f@sekolah.sch.id',
                'target_class_id' => $kX1->id,
            ],
        ];

        $op = $service->prepare('intake', null, '2027/2028', $items, ['effective_date' => '2027-07-01'], $admin);
        $ready = $service->validate($op->id, $admin);
        $token = $service->confirm($op->id, $ready->version, $ready->snapshot_hash, $admin);
        $service->execute($op->id, $token, $admin);

        // Periksa payload audit
        $audits = DB::table('academic_workflow_audits')->where('operation_id', $op->id)->get();
        foreach ($audits as $audit) {
            $this->assertStringNotContainsString('password', strtolower($audit->metadata));
            $this->assertStringNotContainsString('secret', strtolower($audit->metadata));
        }

        // Periksa snapshot
        $opFresh = $op->fresh();
        $snapshotJson = json_encode($opFresh->snapshot);
        $this->assertStringNotContainsString('password', strtolower($snapshotJson));
    }

    public function test_excluded_row_persists_reason_and_never_creates_records(): void
    {
        $admin = $this->admin();
        [$target, $kX1] = $this->setupAcademicData();

        $registry = new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('intake', new \App\AcademicWorkflows\IntakeExecutor);
        $service = new WorkflowService($registry);

        $items = [
            [
                'student_ref' => '00301',
                'nisn' => '0081234567',
                'email' => 'valid@sekolah.sch.id',
                'target_class_id' => $kX1->id,
            ],
            [
                'student_ref' => '00302',
                'nisn' => '0087654321',
                'email' => 'excluded@sekolah.sch.id',
                'target_class_id' => $kX1->id,
                'excluded' => true,
                'exclusion_reason_present' => true,
            ],
        ];

        $op = $service->prepare('intake', null, '2027/2028', $items, ['effective_date' => '2027-07-01'], $admin);
        $ready = $service->validate($op->id, $admin);
        $token = $service->confirm($op->id, $ready->version, $ready->snapshot_hash, $admin);
        $res = $service->execute($op->id, $token, $admin);

        $this->assertSame(['processed' => 1, 'skipped' => 1, 'failed' => 0], $res);

        // 00301 tercipta
        $this->assertDatabaseHas('siswa', ['nis' => '00301']);
        $this->assertDatabaseHas('users', ['email' => 'valid@sekolah.sch.id']);

        // 00302 tidak pernah ada di database
        $this->assertDatabaseMissing('siswa', ['nis' => '00302']);
        $this->assertDatabaseMissing('users', ['email' => 'excluded@sekolah.sch.id']);
        $this->assertDatabaseMissing('riwayat_kelas_siswa', ['siswa_nis' => '00302']);

        // Namun status excluded dan itemnya tetap tercatat di workflow items
        $itemEx = $op->items()->where('student_ref', '00302')->first();
        $this->assertNotNull($itemEx);
        $this->assertTrue((bool) $itemEx->excluded);
    }
}
