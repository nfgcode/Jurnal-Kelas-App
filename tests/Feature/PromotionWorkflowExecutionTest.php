<?php

namespace Tests\Feature;

use App\AcademicWorkflows\Operation;
use App\AcademicWorkflows\WorkflowService;
use App\Models\Alumni;
use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PromotionWorkflowExecutionTest extends TestCase
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
        $cXI1 = $this->buatKelas(['nama_kelas' => 'XI TKJ 1', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);

        // Target classes
        $targetX1 = $this->buatKelas(['nama_kelas' => 'X TKJ 1 (27/28)', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $targetXI1 = $this->buatKelas(['nama_kelas' => 'XI TKJ 1 (27/28)', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $targetXI2 = $this->buatKelas(['nama_kelas' => 'XI TKJ 2 (27/28)', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 2, 'tahun_ajaran_kode' => '2027/2028']);
        $targetXII1 = $this->buatKelas(['nama_kelas' => 'XII TKJ 1 (27/28)', 'tingkat' => 'XII', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        // Siswa X yang naik ke XI TKJ 1
        $s1 = Siswa::factory()->create(['nis' => '00101', 'nama' => 'Siswa X Naik', 'kelas_id' => $cX1->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s1, $cX1, '2026/2027', '2026-07-01');

        // Siswa X yang tinggal kelas di X TKJ 1 (27/28)
        $s2 = Siswa::factory()->create(['nis' => '00102', 'nama' => 'Siswa X Tinggal', 'kelas_id' => $cX1->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s2, $cX1, '2026/2027', '2026-07-01');

        // Siswa X yang naik dengan override paralel ke XI TKJ 2
        $s3 = Siswa::factory()->create(['nis' => '00103', 'nama' => 'Siswa X Override Paralel', 'kelas_id' => $cX1->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s3, $cX1, '2026/2027', '2026-07-01');

        // Siswa XI yang naik ke XII TKJ 1
        $s4 = Siswa::factory()->create(['nis' => '00201', 'nama' => 'Siswa XI Naik', 'kelas_id' => $cXI1->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s4, $cXI1, '2026/2027', '2026-07-01');

        // Jadikan s1 ketua kelas di cX1 untuk menguji rekonsiliasi jabatan
        $cX1->update(['ketua_nis' => $s1->nis]);

        return [
            $source,
            $target,
            [$cX1, $cXI1],
            [$targetX1, $targetXI1, $targetXI2, $targetXII1],
            [$s1, $s2, $s3, $s4],
        ];
    }

    public function test_executes_promotion_and_updates_memberships_and_reconciles_ketua(): void
    {
        $actor = $this->actor();
        $service = new WorkflowService;
        [$source, $target, [$cX1, $cXI1], [$targetX1, $targetXI1, $targetXI2, $targetXII1], [$s1, $s2, $s3, $s4]] = $this->setupAcademicData();

        $items = [
            ['student_ref' => '00101', 'decision' => 'naik', 'target_class_id' => $targetXI1->id],
            ['student_ref' => '00102', 'decision' => 'tinggal_kelas', 'target_class_id' => $targetX1->id],
            ['student_ref' => '00103', 'decision' => 'naik', 'target_class_id' => $targetXI2->id], // override parallel
            ['student_ref' => '00201', 'decision' => 'naik', 'target_class_id' => $targetXII1->id],
        ];
        $metadata = ['effective_date' => '2027-07-01'];

        $op = $service->prepare('promotion', '2026/2027', '2027/2028', $items, $metadata, $actor);
        $ready = $service->validate($op->id, $actor);
        $this->assertSame('ready', $ready->state);

        $confirmation = $service->confirm($op->id, $ready->version, $ready->snapshot_hash, $actor);
        $result = $service->execute($op->id, $confirmation, $actor);

        $this->assertSame(['processed' => 4, 'skipped' => 0, 'failed' => 0], $result);
        $this->assertSame('completed', $op->fresh()->state);

        // 1. Verifikasi Siswa 1 (00101) Naik:
        $s1Fresh = $s1->fresh();
        $this->assertSame($targetXI1->id, $s1Fresh->kelas_id);
        $this->assertSame('aktif', $s1Fresh->status);
        // Ketua kelas lama dilepas
        $this->assertNull($cX1->fresh()->ketua_nis);

        // Riwayat lama ditutup pada tanggal akhir tahun sumber
        $oldRiwayat1 = RiwayatKelasSiswa::where('siswa_nis', '00101')->where('kelas_id', $cX1->id)->first();
        $this->assertFalse((bool) $oldRiwayat1->aktif);
        $this->assertSame('aktif', $oldRiwayat1->status);
        $this->assertSame('2027-06-30', $oldRiwayat1->tanggal_selesai->toDateString());

        // Riwayat baru dibuka pada tanggal mulai tahun tujuan
        $newRiwayat1 = RiwayatKelasSiswa::where('siswa_nis', '00101')->where('kelas_id', $targetXI1->id)->first();
        $this->assertTrue((bool) $newRiwayat1->aktif);
        $this->assertSame('aktif', $newRiwayat1->status);
        $this->assertSame('XI', $newRiwayat1->tingkat);
        $this->assertSame('2027-07-01', $newRiwayat1->tanggal_mulai->toDateString());

        // 2. Verifikasi Siswa 2 (00102) Tinggal Kelas:
        $s2Fresh = $s2->fresh();
        $this->assertSame($targetX1->id, $s2Fresh->kelas_id);
        $this->assertSame('aktif', $s2Fresh->status);

        $oldRiwayat2 = RiwayatKelasSiswa::where('siswa_nis', '00102')->where('kelas_id', $cX1->id)->first();
        $this->assertFalse((bool) $oldRiwayat2->aktif);
        $this->assertSame('tinggal_kelas', $oldRiwayat2->status);

        $newRiwayat2 = RiwayatKelasSiswa::where('siswa_nis', '00102')->where('kelas_id', $targetX1->id)->first();
        $this->assertTrue((bool) $newRiwayat2->aktif);
        $this->assertSame('tinggal_kelas', $newRiwayat2->status);
        $this->assertSame('X', $newRiwayat2->tingkat);

        // 3. Verifikasi Siswa 3 (00103) Override Paralel:
        $s3Fresh = $s3->fresh();
        $this->assertSame($targetXI2->id, $s3Fresh->kelas_id);

        // 4. Verifikasi Siswa 4 (00201) XI Naik XII:
        $s4Fresh = $s4->fresh();
        $this->assertSame($targetXII1->id, $s4Fresh->kelas_id);

        // 5. Verifikasi bahwa tidak ada entitas Alumni yang terbuat
        $this->assertSame(0, Alumni::count());

        // 6. Verifikasi tahun tujuan tetap nonaktif
        $this->assertFalse((bool) $target->fresh()->aktif);
    }

    public function test_replay_completed_operation_returns_persisted_result_without_second_mutation(): void
    {
        $actor = $this->actor();
        $service = new WorkflowService;
        [$source, $target, [$cX1, $cXI1], [$targetX1, $targetXI1, $targetXI2, $targetXII1]] = $this->setupAcademicData();

        $items = [
            ['student_ref' => '00101', 'decision' => 'naik', 'target_class_id' => $targetXI1->id],
            ['student_ref' => '00102', 'decision' => 'tinggal_kelas', 'target_class_id' => $targetX1->id],
            ['student_ref' => '00103', 'decision' => 'naik', 'target_class_id' => $targetXI2->id],
            ['student_ref' => '00201', 'decision' => 'naik', 'target_class_id' => $targetXII1->id],
        ];
        $metadata = ['effective_date' => '2027-07-01'];

        $op = $service->prepare('promotion', '2026/2027', '2027/2028', $items, $metadata, $actor);
        $ready = $service->validate($op->id, $actor);
        $confirmation = $service->confirm($op->id, $ready->version, $ready->snapshot_hash, $actor);

        $firstResult = $service->execute($op->id, $confirmation, $actor);
        $this->assertSame(['processed' => 4, 'skipped' => 0, 'failed' => 0], $firstResult);

        $riwayatCountAfterFirst = RiwayatKelasSiswa::count();

        // Replay dengan token yang sama atau retry request
        $secondResult = $service->execute($op->id, 'any-token', $actor);
        $this->assertSame($firstResult, $secondResult);
        $this->assertSame($riwayatCountAfterFirst, RiwayatKelasSiswa::count());
    }

    public function test_stale_confirmation_is_rejected_if_student_deleted_or_mutated(): void
    {
        $actor = $this->actor();
        $service = new WorkflowService;
        [$source, $target, [$cX1, $cXI1], [$targetX1, $targetXI1, $targetXI2, $targetXII1]] = $this->setupAcademicData();

        $items = [
            ['student_ref' => '00101', 'decision' => 'naik', 'target_class_id' => $targetXI1->id],
            ['student_ref' => '00102', 'decision' => 'tinggal_kelas', 'target_class_id' => $targetX1->id],
            ['student_ref' => '00103', 'decision' => 'naik', 'target_class_id' => $targetXI2->id],
            ['student_ref' => '00201', 'decision' => 'naik', 'target_class_id' => $targetXII1->id],
        ];
        $metadata = ['effective_date' => '2027-07-01'];

        $op = $service->prepare('promotion', '2026/2027', '2027/2028', $items, $metadata, $actor);
        $ready = $service->validate($op->id, $actor);
        $confirmation = $service->confirm($op->id, $ready->version, $ready->snapshot_hash, $actor);

        // Mutasi NISN siswa di luar workflow
        Siswa::where('nis', '00101')->update(['nisn' => '1122334455']);

        try {
            $service->execute($op->id, $confirmation, $actor);
            $this->fail('Stale confirmation must be rejected');
        } catch (DomainException $e) {
            $this->assertSame('workflow_confirmation_stale', $e->getMessage());
        }

        $this->assertSame('confirmed', $op->fresh()->state);
    }
}
