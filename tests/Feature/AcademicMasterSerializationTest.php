<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Services\KalenderTahunAjaran;
use App\Support\PencatatanAkademik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicMasterSerializationTest extends TestCase
{
    use RefreshDatabase;

    private function observe(callable $write): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $write();
            return array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
        }
    }

    private function assertSingletonFirst(array $queries): void
    {
        $this->assertNotEmpty($queries);
        $this->assertStringContainsString('academic_operation_lock', $queries[0]);
    }

    public function test_preparation_and_class_service_lock_before_dependency_reads(): void
    {
        $this->assertSingletonFirst($this->observe(fn () => app(KalenderTahunAjaran::class)->siapkanBerikutnya()));
        $year = TahunAjaran::firstOrFail();
        $this->assertSingletonFirst($this->observe(fn () => app(PencatatanAkademik::class)->kelas([
            'nama_kelas' => 'X Service', 'tingkat' => 'X', 'tahun_ajaran_kode' => $year->kode,
        ])));
        $this->assertSingletonFirst($this->observe(fn () => app(PencatatanAkademik::class)->jurusan(['kode' => 'RPL', 'nama' => 'RPL'])));
    }

    public function test_year_overlap_validation_runs_under_lock_and_failed_audit_rolls_back_dates(): void
    {
        $year = TahunAjaran::create(['kode' => '2026/2027', 'mulai' => '2026-07-01', 'selesai' => '2027-06-30']);
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $queries = $this->observe(fn () => $this->patch(route('admin.tahun-ajaran.update', $year->kode), [
            'mulai' => '2026-08-01', 'selesai' => '2027-06-30',
        ])->assertSessionHasNoErrors());
        $lock = collect($queries)->search(fn ($sql) => str_contains($sql, 'academic_operation_lock'));
        $overlap = collect($queries)->search(fn ($sql) => str_contains($sql, 'exists') && str_contains($sql, 'tahun_ajaran'));
        $this->assertNotFalse($lock);
        $this->assertNotFalse($overlap);
        $this->assertLessThan($overlap, $lock);
        \App\Models\AdminAuditLog::creating(fn () => throw new \RuntimeException('audit unavailable'));
        $this->withoutExceptionHandling();
        try {
            $this->patch(route('admin.tahun-ajaran.update', $year->kode), ['mulai' => '2026-09-01', 'selesai' => '2027-06-30']);
            $this->fail('Audit failure accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('audit unavailable', $e->getMessage());
        }
        $this->assertSame('2026-08-01', $year->fresh()->mulai->toDateString());
    }

    public function test_missing_singleton_fails_closed_before_master_mutation(): void
    {
        DB::table('academic_operation_lock')->delete();
        try {
            app(PencatatanAkademik::class)->jurusan(['kode' => 'FAIL', 'nama' => 'Fail']);
            $this->fail('Missing singleton accepted');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Kunci operasi akademik', $e->getMessage());
        }
        $this->assertDatabaseMissing('jurusan', ['kode' => 'FAIL']);
        $this->assertDatabaseCount('academic_operation_lock', 0);
    }

    public function test_master_conflicts_and_historical_deletion_leave_rows_unchanged(): void
    {
        $year = TahunAjaran::create(['kode' => '2026/2027', 'mulai' => '2026-07-01', 'selesai' => '2027-06-30']);
        $service = app(PencatatanAkademik::class);
        $data = ['nama_kelas' => 'X RPL', 'tingkat' => 'X', 'tahun_ajaran_kode' => $year->kode];
        $class = $service->kelas($data);
        try {
            $service->kelas($data);
            $this->fail('Duplicate class accepted');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('paralel', $e->errors());
        }
        $student = \App\Models\Siswa::create(['nis' => 'history', 'nama' => 'History', 'status' => 'aktif', 'kelas_id' => $class->id]);
        \App\Models\RiwayatKelasSiswa::daftarkan($student, $class, $year->kode, '2026-07-01');
        try {
            $class->delete();
            $this->fail('Historical class deleted');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('hapus', $e->errors());
        }
        $this->assertDatabaseCount('kelas', 1);
        $this->assertDatabaseHas('riwayat_kelas_siswa', ['kelas_id' => $class->id]);
    }

    public function test_direct_master_instance_writers_lock_before_events_and_mutation(): void
    {
        foreach ([new TahunAjaran(['kode' => '2026/2027']), new Jurusan(['kode' => 'TKJ', 'nama' => 'TKJ'])] as $model) {
            $this->assertSingletonFirst($this->observe(fn () => $model->save()));
            $this->assertSingletonFirst($this->observe(fn () => $model->update($model instanceof Jurusan ? ['aktif' => false] : ['mulai' => '2026-07-01', 'selesai' => '2027-06-30'])));
        }
        $kelas = new Kelas(['nama_kelas' => 'X TKJ', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'tahun_ajaran_kode' => '2026/2027']);
        $this->assertSingletonFirst($this->observe(fn () => $kelas->save()));
        $this->assertSingletonFirst($this->observe(fn () => $kelas->update(['tingkat' => 'XI'])));
        $this->assertSingletonFirst($this->observe(fn () => $kelas->delete()));
        $jurusan = Jurusan::findOrFail('TKJ');
        $this->assertSingletonFirst($this->observe(fn () => $jurusan->delete()));
    }
}
