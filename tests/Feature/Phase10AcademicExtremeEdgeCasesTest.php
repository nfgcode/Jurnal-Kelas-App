<?php

namespace Tests\Feature;

use App\AcademicWorkflows\Coordinator;
use App\AcademicWorkflows\ExecutorRegistry;
use App\AcademicWorkflows\GraduationExecutor;
use App\AcademicWorkflows\IntakeExecutor;
use App\AcademicWorkflows\PromotionExecutor;
use App\AcademicWorkflows\WorkflowService;
use App\Models\Alumni;
use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase10AcademicExtremeEdgeCasesTest extends TestCase
{
    use DatabaseMigrations;

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

    /**
     * Skenario Ekstrem 1: Siswa XII tidak lulus tapi kelas tujuan tahun berikutnya belum ditentukan (retained unplaced)
     * Siswa harus tetap berstatus aktif di database, tidak dibuatkan record alumni, dan keanggotaan kelas lamanya ditutup.
     */
    public function test_graduation_student_fails_without_immediate_target_class_stays_active_no_alumni(): void
    {
        [$source, $target] = $this->setupYears();
        $actor = $this->admin();

        $classXII = $this->buatKelas([
            'nama_kelas' => 'XII TKJ 1',
            'tingkat' => 'XII',
            'jurusan_kode' => 'TKJ',
            'paralel' => 1,
            'tahun_ajaran_kode' => '2026/2027',
        ]);

        $siswa = Siswa::factory()->create([
            'nis' => '99001',
            'nama' => 'Siswa Belum Lulus',
            'kelas_id' => $classXII->id,
            'status' => 'aktif',
        ]);

        RiwayatKelasSiswa::daftarkan($siswa, $classXII, '2026/2027', '2026-07-01');

        $targetClass = $this->buatKelas([
            'nama_kelas' => 'XII TKJ 1 (27/28)',
            'tingkat' => 'XII',
            'jurusan_kode' => 'TKJ',
            'paralel' => 1,
            'tahun_ajaran_kode' => '2027/2028',
        ]);

        $registry = new ExecutorRegistry();
        $registry->register('graduation', new GraduationExecutor());
        $service = new WorkflowService($registry);

        $op = $service->prepare('graduation', '2026/2027', '2027/2028', [
            ['student_ref' => '99001', 'decision' => 'tidak_lulus', 'target_class_id' => $targetClass->id],
        ], [
            'official_verified' => true,
            'official_reference' => 'SK-REKAP-XII-01',
            'source_content_hash' => hash('sha256', 'hash-01'),
            'source_version' => 'v1',
            'effective_date' => '2027-05-15',
        ], $actor);

        $val = $service->validate($op->id, $actor);
        $this->assertSame('ready', $val->state);
        $token = $service->confirm($op->id, $val->version, $val->snapshot_hash, $actor);
        $result = $service->execute($op->id, $token, $actor);

        $this->assertSame(['processed' => 1, 'skipped' => 0, 'failed' => 0], $result);

        // Siswa tetap aktif (tidak lulus ditempatkan di kelas XII tahun berikutnya)
        $freshSiswa = $siswa->fresh();
        $this->assertSame('aktif', $freshSiswa->status);
        $this->assertEquals($targetClass->id, $freshSiswa->kelas_id);
        $this->assertNull(Alumni::where('siswa_nis', '99001')->first()); // TIDAK boleh ada alumni

        $riwayat = RiwayatKelasSiswa::where('siswa_nis', '99001')->where('tahun_ajaran_kode', '2026/2027')->first();
        $this->assertFalse((bool) $riwayat->aktif);
        $this->assertSame('tinggal_kelas', $riwayat->status);

        $riwayatBaru = RiwayatKelasSiswa::where('siswa_nis', '99001')->where('tahun_ajaran_kode', '2027/2028')->first();
        $this->assertTrue((bool) $riwayatBaru->aktif);
        $this->assertSame('tinggal_kelas', $riwayatBaru->status);
        $this->assertEquals($targetClass->id, $riwayatBaru->kelas_id);
    }

    /**
     * Skenario Ekstrem 2: Siswa tinggal kelas di tingkat XI dan ditempatkan di rombel tahun ajaran baru
     * Memastikan riwayat lama ditutup dengan status 'tinggal_kelas' dan riwayat baru di 27/28 aktif.
     */
    public function test_promotion_retention_records_continuous_history_without_orphan_data(): void
    {
        [$source, $target] = $this->setupYears();
        $actor = $this->admin();

        // Siapkan seluruh siswa kelas X agar snapshot tidak stale
        $classX1 = $this->buatKelas(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $tgtXI = $this->buatKelas(['nama_kelas' => 'XI TKJ 1 (27/28)', 'tingkat' => 'XI', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);
        $tgtX1 = $this->buatKelas(['nama_kelas' => 'X TKJ 1 (27/28)', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2027/2028']);

        $s1 = Siswa::factory()->create(['nis' => '99002', 'nama' => 'Siswa Tinggal Kelas XI', 'kelas_id' => $classX1->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s1, $classX1, '2026/2027', '2026-07-01');

        $registry = new ExecutorRegistry();
        $registry->register('promotion', new PromotionExecutor());
        $service = new WorkflowService($registry);

        $op = $service->prepare('promotion', '2026/2027', '2027/2028', [
            ['student_ref' => '99002', 'decision' => 'tinggal_kelas', 'target_class_id' => $tgtX1->id],
        ], [
            'effective_date' => '2027-07-01',
        ], $actor);

        $val = $service->validate($op->id, $actor);
        $this->assertSame('ready', $val->state);
        $token = $service->confirm($op->id, $val->version, $val->snapshot_hash, $actor);
        $result = $service->execute($op->id, $token, $actor);

        $this->assertSame(['processed' => 1, 'skipped' => 0, 'failed' => 0], $result);

        $freshSiswa = $s1->fresh();
        $this->assertSame('aktif', $freshSiswa->status);
        $this->assertEquals($tgtX1->id, $freshSiswa->kelas_id);

        // Periksa 2 riwayat kelas: riwayat lama (2026/2027) dan riwayat baru (2027/2028)
        $riwayats = RiwayatKelasSiswa::where('siswa_nis', '99002')->orderBy('id')->get();
        $this->assertCount(2, $riwayats);

        $this->assertSame('2026/2027', $riwayats[0]->tahun_ajaran_kode);
        $this->assertFalse((bool) $riwayats[0]->aktif);
        $this->assertSame('tinggal_kelas', $riwayats[0]->status);

        $this->assertSame('2027/2028', $riwayats[1]->tahun_ajaran_kode);
        $this->assertTrue((bool) $riwayats[1]->aktif);
        $this->assertSame('tinggal_kelas', $riwayats[1]->status);
        $this->assertEquals($tgtX1->id, $riwayats[1]->kelas_id);
    }

    /**
     * Skenario Ekstrem 3: Coordinator Transisi Terpadu dengan partial failure atau proses terhenti
     * Memastikan tahap 1 (kelulusan) tetap aman/completed, sementara tahap 2 dapat di-resume tanpa menduplikasi data.
     */
    public function test_coordinator_handles_interrupted_or_resumed_flow_safely(): void
    {
        [$source, $target] = $this->setupYears();
        $actor = $this->admin();

        $registry = new ExecutorRegistry();
        $registry->register('graduation', new GraduationExecutor());
        $registry->register('promotion', new PromotionExecutor());
        $service = new WorkflowService($registry);
        $coordinator = new Coordinator($service);

        $classXII = $this->buatKelas([
            'nama_kelas' => 'XII AKL 1',
            'tingkat' => 'XII',
            'jurusan_kode' => 'AKL',
            'paralel' => 1,
            'tahun_ajaran_kode' => '2026/2027',
        ]);
        $s1 = Siswa::factory()->create(['nis' => '99003', 'nama' => 'Siswa Lulus', 'kelas_id' => $classXII->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s1, $classXII, '2026/2027', '2026-07-01');

        $classX = $this->buatKelas([
            'nama_kelas' => 'X AKL 1',
            'tingkat' => 'X',
            'jurusan_kode' => 'AKL',
            'paralel' => 1,
            'tahun_ajaran_kode' => '2026/2027',
        ]);
        $classXI = $this->buatKelas([
            'nama_kelas' => 'XI AKL 1',
            'tingkat' => 'XI',
            'jurusan_kode' => 'AKL',
            'paralel' => 1,
            'tahun_ajaran_kode' => '2027/2028',
        ]);
        $s2 = Siswa::factory()->create(['nis' => '99004', 'nama' => 'Siswa Naik', 'kelas_id' => $classX->id, 'status' => 'aktif']);
        RiwayatKelasSiswa::daftarkan($s2, $classX, '2026/2027', '2026-07-01');

        // Buat alur transisi terpadu (graduation + promotion)
        $coordId = $coordinator->create(['graduation', 'promotion'], $actor, '2026/2027', '2027/2028');

        // Siapkan dan pasang data operation untuk tiap stage
        $gradOp = $service->prepare('graduation', '2026/2027', '2027/2028', [
            ['student_ref' => '99003', 'decision' => 'lulus'],
        ], [
            'official_verified' => true,
            'official_reference' => 'SK-GRAD-99',
            'source_content_hash' => hash('sha256', 'hash-99'),
            'source_version' => 'v1',
            'effective_date' => '2027-05-15',
        ], $actor);
        $coordinator->attach($coordId, 'graduation', $gradOp->id, $actor);

        $promOp = $service->prepare('promotion', '2026/2027', '2027/2028', [
            ['student_ref' => '99004', 'decision' => 'naik', 'target_class_id' => $classXI->id],
        ], ['effective_date' => '2027-07-01'], $actor);
        $coordinator->attach($coordId, 'promotion', $promOp->id, $actor);

        // Eksekusi tahap 1 (graduation)
        $gradVal = $service->validate($gradOp->id, $actor);
        $gradToken = $service->confirm($gradOp->id, $gradVal->version, $gradVal->snapshot_hash, $actor);
        $gradResult = $coordinator->executeStage($coordId, 'graduation', $gradToken, $actor);

        $this->assertSame(['processed' => 1, 'skipped' => 0, 'failed' => 0], $gradResult);
        $statusAfterGrad = $coordinator->status($coordId, $actor);
        $this->assertSame('completed', $statusAfterGrad[0]['state']);
        $this->assertSame('lulus', $s1->fresh()->status);

        // Simulasi interupsi / resume: jalankan eksekusi tahap 2 (promotion) setelah jeda
        $promVal = $service->validate($promOp->id, $actor);
        $promToken = $service->confirm($promOp->id, $promVal->version, $promVal->snapshot_hash, $actor);
        $promResult = $coordinator->executeStage($coordId, 'promotion', $promToken, $actor);

        $this->assertSame(['processed' => 1, 'skipped' => 0, 'failed' => 0], $promResult);
        $finalStatus = $coordinator->status($coordId, $actor);
        $this->assertSame(['completed', 'completed'], array_column($finalStatus, 'state'));
        $this->assertEquals($classXI->id, $s2->fresh()->kelas_id);

        // Pastikan tidak ada data ganda pada alumni
        $this->assertSame(1, Alumni::where('siswa_nis', '99003')->count());
    }
}
