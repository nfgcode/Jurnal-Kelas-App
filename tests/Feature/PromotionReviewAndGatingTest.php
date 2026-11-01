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
use Tests\TestCase;

class PromotionReviewAndGatingTest extends TestCase
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

        // Target classes
        $targetXI1 = $this->buatKelas(['nama_kelas' => 'XI TKJ 1 (27/28)', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $targetXI2 = $this->buatKelas(['nama_kelas' => 'XI TKJ 2 (27/28)', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 2, 'tahun_ajaran_kode' => '2027/2028']);
        $targetX1 = $this->buatKelas(['nama_kelas' => 'X TKJ 1 (27/28)', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        // Invalid target classes
        $targetWrongYear = $this->buatKelas(['nama_kelas' => 'XI TKJ 1 (28/29)', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $targetWrongDept = $this->buatKelas(['nama_kelas' => 'XI RPL 1 (27/28)', 'tingkat' => 'XI', 'jurusan_kode' => 'RPL', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $targetWrongGrade = $this->buatKelas(['nama_kelas' => 'XII TKJ 1 (27/28)', 'tingkat' => 'XII', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        $s1 = Siswa::factory()->create(['nis' => 'P001', 'nama' => 'Siswa Satu', 'kelas_id' => $cX1->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s1, $cX1, '2026/2027', '2026-07-01');

        return [
            $source,
            $target,
            $cX1,
            [
                'targetXI1' => $targetXI1,
                'targetXI2' => $targetXI2,
                'targetX1' => $targetX1,
                'targetWrongYear' => $targetWrongYear,
                'targetWrongDept' => $targetWrongDept,
                'targetWrongGrade' => $targetWrongGrade,
            ],
            $s1,
        ];
    }

    public function test_accepts_valid_target_class_parallel_override(): void
    {
        $actor = $this->actor();
        $service = new WorkflowService;
        [$source, $target, $cX1, $targets, $s1] = $this->setupAcademicData();

        // Source class is X TKJ 1. Override target class to XI TKJ 2 (different parallel, same major and target year)
        $items = [
            ['student_ref' => 'P001', 'decision' => 'naik', 'target_class_id' => $targets['targetXI2']->id],
        ];
        $metadata = ['effective_date' => '2027-07-01'];

        $op = $service->prepare('promotion', '2026/2027', '2027/2028', $items, $metadata, $actor);
        $preview = $service->preview($op->id, $actor);

        $this->assertSame('valid', $preview->validation);
        $this->assertSame($targets['targetXI2']->id, $preview->items[0]['target_class_id']);
        $this->assertEmpty($preview->items[0]['errors']);
    }

    public function test_rejects_target_class_with_wrong_year_wrong_department_or_wrong_grade(): void
    {
        $actor = $this->actor();
        $service = new WorkflowService;
        [$source, $target, $cX1, $targets, $s1] = $this->setupAcademicData();
        $metadata = ['effective_date' => '2027-07-01'];

        // 1. Wrong year
        $op1 = $service->prepare('promotion', '2026/2027', '2027/2028', [
            ['student_ref' => 'P001', 'decision' => 'naik', 'target_class_id' => $targets['targetWrongYear']->id],
        ], $metadata, $actor);
        $preview1 = $service->preview($op1->id, $actor);
        $this->assertSame('invalid', $preview1->validation);
        $this->assertContains('target_class_invalid', $preview1->items[0]['errors']);

        // 2. Wrong department (RPL instead of TKJ)
        $op2 = $service->prepare('promotion', '2026/2027', '2027/2028', [
            ['student_ref' => 'P001', 'decision' => 'naik', 'target_class_id' => $targets['targetWrongDept']->id],
        ], $metadata, $actor);
        $preview2 = $service->preview($op2->id, $actor);
        $this->assertSame('invalid', $preview2->validation);
        $this->assertContains('target_class_invalid', $preview2->items[0]['errors']);

        // 3. Wrong grade (X naik directly to XII)
        $op3 = $service->prepare('promotion', '2026/2027', '2027/2028', [
            ['student_ref' => 'P001', 'decision' => 'naik', 'target_class_id' => $targets['targetWrongGrade']->id],
        ], $metadata, $actor);
        $preview3 = $service->preview($op3->id, $actor);
        $this->assertSame('invalid', $preview3->validation);
        $this->assertContains('target_class_invalid', $preview3->items[0]['errors']);
    }

    public function test_confirmation_is_bound_to_snapshot_and_invalidated_by_roster_or_class_change(): void
    {
        $actor = $this->actor();
        $service = new WorkflowService;
        [$source, $target, $cX1, $targets, $s1] = $this->setupAcademicData();
        $metadata = ['effective_date' => '2027-07-01'];

        $op = $service->prepare('promotion', '2026/2027', '2027/2028', [
            ['student_ref' => 'P001', 'decision' => 'naik', 'target_class_id' => $targets['targetXI1']->id],
        ], $metadata, $actor);

        $ready = $service->validate($op->id, $actor);
        $this->assertSame('ready', $ready->state);

        $token = $service->confirm($op->id, $ready->version, $ready->snapshot_hash, $actor);
        $this->assertSame('confirmed', $op->fresh()->state);

        // Add a new active student to the source roster -> snapshot changed
        $s2 = Siswa::factory()->create(['nis' => 'P002', 'kelas_id' => $cX1->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s2, $cX1, '2026/2027', '2026-07-01');

        $previewAfterRosterChange = $service->preview($op->id, $actor);
        $this->assertNotSame($ready->snapshot_hash, $previewAfterRosterChange->hash);

        // Re-confirm with stale token must fail
        try {
            $service->confirm($op->id, $ready->version, $ready->snapshot_hash, $actor);
            $this->fail('Confirmation should be rejected when snapshot changes');
        } catch (\DomainException $e) {
            $this->assertSame('workflow_confirmation_stale', $e->getMessage());
        }
    }

    public function test_preview_is_strictly_read_only(): void
    {
        $actor = $this->actor();
        $service = new WorkflowService;
        [$source, $target, $cX1, $targets, $s1] = $this->setupAcademicData();
        $metadata = ['effective_date' => '2027-07-01'];

        $op = $service->prepare('promotion', '2026/2027', '2027/2028', [
            ['student_ref' => 'P001', 'decision' => 'naik', 'target_class_id' => $targets['targetXI1']->id],
        ], $metadata, $actor);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();

        $preview = $service->preview($op->id, $actor);

        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        foreach ($queries as $query) {
            $sql = strtolower(ltrim($query['query']));
            $this->assertStringStartsWith('select', $sql);
        }

        $this->assertSame('draft', $op->fresh()->state);
    }
}
