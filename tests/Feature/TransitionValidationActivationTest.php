<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\AktivasiTahunAjaran;
use App\Support\TransisiAkademik;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransitionValidationActivationTest extends TestCase
{
    use RefreshDatabase;

    private function roster(): array
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-06-15'));
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true, 'mulai' => '2026-07-01', 'selesai' => '2027-06-30']);
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false, 'mulai' => '2027-07-01', 'selesai' => '2028-06-30']);
        Jurusan::create(['kode' => 'TKJ', 'nama' => 'TKJ', 'aktif' => true]);
        $source = Kelas::create(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $student = Siswa::create(['nis' => '00123', 'nama' => 'Test', 'status' => 'aktif', 'kelas_id' => $source->id]);
        \App\Models\RiwayatKelasSiswa::create(['siswa_nis' => $student->nis, 'kelas_id' => $source->id, 'tahun_ajaran_kode' => '2026/2027', 'tingkat' => 'X', 'status' => 'aktif', 'aktif' => true, 'tanggal_mulai' => '2026-07-01']);
        TransisiAkademik::siapkanKelasTujuan('2027/2028', '2026/2027');

        return [$source, $student];
    }

    public static function invalidDecisions(): array
    {
        return [
            'unknown NIS' => [['outsider' => ['aksi' => 'keluar']]],
            'wrong grade action' => [['00123' => ['aksi' => 'lulus']]],
            'missing action' => [['00123' => []]],
            'scalar exception' => [['00123' => 'promosi']],
            'wrong destination year' => [['00123' => ['aksi' => 'promosi', 'kelas_id' => 1]]],
            'wrong destination grade' => [['00123' => ['aksi' => 'promosi', 'kelas_id' => 3]]],
            'unknown key' => [['00123' => ['aksi' => 'promosi', 'unexpected' => 1]]],
            'null destination' => [['00123' => ['aksi' => 'promosi', 'kelas_id' => null]]],
            'malformed destination' => [['00123' => ['aksi' => 'promosi', 'kelas_id' => '2oops']]],
            'invalid note' => [['00123' => ['aksi' => 'promosi', 'catatan' => ['private']]]],
        ];
    }

    #[DataProvider('invalidDecisions')]
    public function test_invalid_decision_rolls_back(array $decisions): void
    {
        [$source, $student] = $this->roster();
        try {
            TransisiAkademik::eksekusi('2026/2027', '2027/2028', ['pengecualian' => $decisions]);
            $this->fail('Invalid decision accepted');
        } catch (DomainException $e) {
            $this->assertSame($source->id, $student->fresh()->kelas_id);
            $this->assertDatabaseCount('transisi_tahun_ajaran', 0);
        }
    }

    public function test_historical_source_preparation_is_read_only(): void
    {
        $this->roster();
        DB::table('tahun_ajaran')->where('kode', '2026/2027')->update(['aktif' => false]);
        $this->expectException(DomainException::class);
        TransisiAkademik::siapkanKelasTujuan('2027/2028', '2026/2027');
    }

    public function test_preview_and_execute_validate_same_edited_decisions(): void
    {
        $this->roster();
        $this->expectException(DomainException::class);
        TransisiAkademik::pratinjau('2026/2027', '2027/2028', ['00123' => ['aksi' => 'lulus']]);
    }

    public function test_source_cannot_execute_again_to_different_destination(): void
    {
        $this->roster();
        TransisiAkademik::eksekusi('2026/2027', '2027/2028');
        TahunAjaran::create(['kode' => '2028/2029', 'aktif' => false]);
        $this->expectException(DomainException::class);
        TransisiAkademik::eksekusi('2026/2027', '2028/2029');
    }

    public function test_invalid_service_options_cannot_default(): void
    {
        $this->roster();
        $this->expectException(DomainException::class);
        TransisiAkademik::eksekusi('2026/2027', '2027/2028', ['nonaktifkan_akun' => 'no', 'tanggal_lulus' => 'nonsense']);
    }

    public function test_snapshot_tampering_rejected(): void
    {
        $this->roster();
        $this->expectException(DomainException::class);
        TransisiAkademik::eksekusi('2026/2027', '2027/2028', ['snapshot' => 'tampered']);
    }

    public function test_snapshot_expiration_rejected(): void
    {
        $this->roster();
        $preview = TransisiAkademik::pratinjau('2026/2027', '2027/2028');
        $this->travel(61)->minutes();
        $this->expectException(DomainException::class);
        TransisiAkademik::eksekusi('2026/2027', '2027/2028', ['snapshot' => $preview['snapshot']]);
    }

    public function test_editable_retention_uses_validated_destination(): void
    {
        [, $student] = $this->roster();
        $dest = Kelas::where('tahun_ajaran_kode', '2027/2028')->where('tingkat', 'X')->firstOrFail();
        $decision = ['00123' => ['aksi' => 'tinggal_kelas', 'kelas_id' => $dest->id]];
        $preview = TransisiAkademik::pratinjau('2026/2027', '2027/2028', $decision);
        $this->assertSame($dest->id, $preview['keputusan']['00123']['kelas_id']);
        $result = TransisiAkademik::eksekusi('2026/2027', '2027/2028', ['snapshot' => $preview['snapshot'], 'pengecualian' => $decision]);
        $this->assertSame(1, $result['siswa_tinggal']);
        $this->assertSame($dest->id, $student->fresh()->kelas_id);
    }

    public function test_dedicated_activation_succeeds_after_transition(): void
    {
        $this->roster();
        $this->travelTo(now()->setDate(2027, 7, 1));
        TransisiAkademik::eksekusi('2026/2027', '2027/2028');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.tahun-ajaran.aktifkan', '2027/2028'), ['konfirmasi' => 1])->assertSessionHasNoErrors();
        $this->assertTrue(TahunAjaran::find('2027/2028')->aktif);
        $this->assertFalse(TahunAjaran::find('2026/2027')->aktif);
        $this->assertDatabaseHas('admin_audit_log', ['aksi' => 'tahun_ajaran.aktifkan']);
    }

    public function test_activation_audit_failure_rolls_back(): void
    {
        $this->roster();
        $this->travelTo(now()->setDate(2027, 7, 1));
        TransisiAkademik::eksekusi('2026/2027', '2027/2028');
        AdminAuditLog::creating(fn () => throw new \RuntimeException('audit unavailable'));
        try {
            app(AktivasiTahunAjaran::class)->aktifkan('2027/2028');
            $this->fail('Audit failure must fail closed');
        } catch (\RuntimeException $e) {
            $this->assertTrue(TahunAjaran::find('2026/2027')->aktif);
            $this->assertFalse(TahunAjaran::find('2027/2028')->aktif);
        }
    }

    public function test_http_requires_snapshot(): void
    {
        $this->roster();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.transisi.eksekusi'), ['tahun_asal' => '2026/2027', 'tahun_tujuan' => '2027/2028', 'konfirmasi' => 1])->assertSessionHasErrors('snapshot');
    }

    public function test_preview_snapshot_rejects_stale_roster(): void
    {
        [, $s] = $this->roster();
        $preview = TransisiAkademik::pratinjau('2026/2027', '2027/2028');
        $this->assertArrayHasKey('snapshot', $preview);
        $s->update(['status' => 'nonaktif']);
        $this->expectException(DomainException::class);
        TransisiAkademik::eksekusi('2026/2027', '2027/2028', ['snapshot' => $preview['snapshot']]);
    }

    public function test_bulk_activation_is_rejected(): void
    {
        $this->roster();
        $this->expectException(DomainException::class);
        TransisiAkademik::eksekusi('2026/2027', '2027/2028', ['aktifkan_tahun_tujuan' => true]);
    }

    public function test_activation_rejects_unplaced_students(): void
    {
        $this->roster();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.tahun-ajaran.aktifkan', '2027/2028'), ['konfirmasi' => 1])->assertSessionHasErrors('tahun_ajaran');
        $this->assertTrue(TahunAjaran::find('2026/2027')->aktif);
    }

    public function test_empty_school_cannot_activate(): void
    {
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.tahun-ajaran.aktifkan', '2027/2028'), ['konfirmasi' => 1])->assertSessionHasErrors('tahun_ajaran');
    }

    public function test_additive_migration_is_reversible_in_sqlite_sandbox(): void
    {
        $this->roster();
        $migration = require database_path('migrations/2026_10_31_000001_guard_academic_activation.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('tahun_ajaran', 'active_slot'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('tahun_ajaran', 'active_slot'));
        $this->assertSame(1, DB::table('academic_operation_lock')->count());
    }

    public function test_database_rejects_multiple_active_periods(): void
    {
        $this->roster();
        $this->expectException(QueryException::class);
        DB::table('tahun_ajaran')->where('kode', '2027/2028')->update(['aktif' => true]);
    }

    public function test_unknown_action_cannot_silently_promote(): void
    {
        [, $s] = $this->roster();
        $this->expectException(DomainException::class);
        TransisiAkademik::eksekusi('2026/2027', '2027/2028', ['pengecualian' => [$s->nis => ['aksi' => 'typo']]]);
    }
}
