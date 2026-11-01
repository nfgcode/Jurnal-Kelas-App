<?php

namespace Tests\Feature;

use App\Models\{Jadwal, Jurnal, MataPelajaran, Presensi, RiwayatKelasSiswa, Siswa};
use App\Support\RosterPresensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2AHistoricalIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function meeting(string $date = '2025-08-01'): Jurnal
    {
        $kelas = $this->buatKelas();
        $guru = $this->buatGuru();
        $mapel = MataPelajaran::create(['nama' => 'Uji', 'kode' => 'UJI']);
        $jadwal = Jadwal::create(['kelas_id' => $kelas->id, 'guru_nip' => $guru->nip, 'mata_pelajaran_id' => $mapel->id, 'hari' => 'Senin', 'jam_ke_mulai' => 1, 'jam_ke_selesai' => 2]);
        return Jurnal::create(['jadwal_id' => $jadwal->id, 'tanggal' => $date, 'materi' => 'Uji', 'kehadiran_guru_status' => 'hadir', 'diisi_oleh_peran' => 'guru']);
    }

    public function test_partial_correction_keeps_saved_students_and_rejects_current_only_student(): void
    {
        $j = $this->meeting();
        $a = Siswa::factory()->create(['nis' => '8201', 'kelas_id' => null]);
        $b = Siswa::factory()->create(['nis' => '8202', 'kelas_id' => null]);
        foreach ([$a, $b] as $s) {
            Presensi::create(['jurnal_id' => $j->id, 'siswa_nis' => $s->nis, 'status' => 'izin']);
        }
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('presensi-jurnal.edit', $j))->assertOk()
            ->assertViewHas('siswaList', fn ($rows) => $rows->count() === 2);
        $this->post(route('presensi-jurnal.store', $j), ['presensi' => [['siswa_nis' => $a->nis, 'status' => 'hadir']]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('presensi', ['jurnal_id' => $j->id, 'siswa_nis' => $b->nis, 'status' => 'izin']);
        $this->assertDatabaseHas('presensi_harian', ['kelas_id' => $j->jadwal->kelas_id, 'siswa_nis' => $b->nis, 'status' => 'izin']);
        $outsider = Siswa::factory()->create(['nis' => '8299', 'kelas_id' => $j->jadwal->kelas_id]);
        $this->postJson('/api/jurnal/'.$j->getRouteKey().'/presensi', ['presensi' => [['siswa_nis' => $outsider->nis, 'status' => 'hadir']]])->assertUnprocessable();
    }

    public function test_master_deletion_is_safe_validation_and_history_survives(): void
    {
        $j = $this->meeting();
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        foreach ([$j->jadwal, $j->jadwal->kelas, $j->jadwal->guru, $j->jadwal->mataPelajaran] as $master) {
            try {
                $master->delete();
                $this->fail('Historical master deletion allowed');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertArrayHasKey('hapus', $e->errors());
            }
            $this->assertDatabaseHas('jurnal', ['id' => $j->id]);
        }
        $this->delete(route('jadwal.destroy', $j->jadwal))->assertSessionHasErrors('hapus');
    }

    public function test_raw_schedule_deletion_cannot_cascade_journal(): void
    {
        $j = $this->meeting();
        $this->expectException(\Illuminate\Database\QueryException::class);
        \Illuminate\Support\Facades\DB::table('jadwal')->where('id', $j->jadwal_id)->delete();
    }

    public function test_literal_nis_survives_web_api_storage_projection_and_correction(): void
    {
        $j = $this->meeting(today()->toDateString());
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        foreach (['00123', '123', 'A001'] as $nis) {
            Siswa::factory()->create(['nis' => $nis, 'kelas_id' => $j->jadwal->kelas_id]);
        }
        $rows = array_map(fn ($nis) => ['siswa_nis' => $nis, 'status' => 'hadir'], ['00123', '123', 'A001']);
        $this->post(route('presensi-jurnal.store', $j), ['presensi' => $rows])->assertSessionHasNoErrors();
        $this->assertSame(['00123', '123', 'A001'], Presensi::where('jurnal_id', $j->id)->orderBy('siswa_nis')->pluck('siswa_nis')->all());
        $this->postJson('/api/jurnal/'.$j->getRouteKey().'/presensi', ['presensi' => [['siswa_nis' => '00123', 'status' => 'izin']]])
            ->assertOk()->assertJsonFragment(['siswa_nis' => '00123', 'status' => 'izin']);
        foreach (['00123' => 'izin', '123' => 'hadir', 'A001' => 'hadir'] as $nis => $status) {
            $this->assertDatabaseHas('presensi_harian', ['kelas_id' => $j->jadwal->kelas_id, 'siswa_nis' => (string) $nis, 'status' => $status]);
        }
    }

    public function test_service_rejects_unresolved_roster_without_removing_saved_rows(): void
    {
        $j = $this->meeting();
        $outsider = Siswa::factory()->create(['nis' => '8401', 'kelas_id' => $j->jadwal->kelas_id]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        \App\Support\SimpanPresensiJurnal::simpan($j, [['siswa_nis' => $outsider->nis, 'status' => 'hadir']]);
    }

    public function test_numeric_nis_payload_is_not_accepted_as_literal_identifier(): void
    {
        $j = $this->meeting(today()->toDateString());
        Siswa::factory()->create(['nis' => '123', 'kelas_id' => $j->jadwal->kelas_id]);
        $this->actingAs(\App\Models\User::factory()->create(['role' => 'admin']))
            ->postJson('/api/jurnal/'.$j->getRouteKey().'/presensi', ['presensi' => [['siswa_nis' => 123, 'status' => 'hadir']]])
            ->assertUnprocessable()->assertJsonValidationErrors('presensi.0.siswa_nis');
    }

    public function test_database_restricts_all_master_paths_and_preserves_intentional_journal_delete(): void
    {
        $j = $this->meeting();
        $s = Siswa::factory()->create(['nis' => '8501', 'kelas_id' => null]);
        Presensi::create(['jurnal_id' => $j->id, 'siswa_nis' => $s->nis, 'status' => 'hadir']);
        foreach ([$j->jadwal->guru, $j->jadwal->mataPelajaran, $j->jadwal->kelas] as $master) {
            try {
                \Illuminate\Support\Facades\DB::table($master->getTable())->where($master->getKeyName(), $master->getKey())->delete();
                $this->fail('Database permitted historical master deletion');
            } catch (\Illuminate\Database\QueryException $e) {
                $this->assertDatabaseHas('presensi', ['jurnal_id' => $j->id, 'siswa_nis' => $s->nis]);
            }
        }
        $j->delete();
        $this->assertDatabaseMissing('presensi', ['jurnal_id' => $j->id]);
        $this->assertTrue($j->jadwal->delete());
    }

    public function test_xlsx_exports_literal_nis_as_text(): void
    {
        $j = $this->meeting(today()->toDateString());
        $s = Siswa::factory()->create(['nis' => '00123', 'kelas_id' => $j->jadwal->kelas_id]);
        \App\Support\SimpanPresensiJurnal::simpan($j, [['siswa_nis' => $s->nis, 'status' => 'hadir']]);
        $this->actingAs(\App\Models\User::factory()->create(['role' => 'admin']));
        foreach (['harian', 'bulanan'] as $mode) {
            $response = $this->get(route('presensi.ekspor', ['mode' => $mode, 'tanggal' => today()->toDateString(), 'bulan' => today()->format('Y-m')]))->assertOk();
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $this->assertStringContainsString('t="inlineStr"><is><t xml:space="preserve">00123</t>', $xml);
        }
    }

    public function test_membership_boundaries_and_other_meeting_marks_do_not_expand_roster(): void
    {
        $j = $this->meeting('2025-08-01');
        $class = $j->jadwal->kelas;
        foreach (['8601' => ['2025-08-01', '2025-08-01'], '8602' => ['2025-08-02', null], '8603' => ['2025-07-01', '2025-07-31'], '8604' => [null, null]] as $nis => [$start, $end]) {
            $s = Siswa::factory()->create(['nis' => (string) $nis, 'kelas_id' => $class->id]);
            RiwayatKelasSiswa::create(['siswa_nis' => $s->nis, 'kelas_id' => $class->id, 'tahun_ajaran_kode' => $class->tahun_ajaran_kode, 'tingkat' => 'X', 'status' => 'pindah', 'aktif' => false, 'tanggal_mulai' => $start, 'tanggal_selesai' => $end]);
        }
        $other = $j->replicate(['public_id']);
        $other->tanggal = '2025-08-02';
        $other->save();
        Presensi::create(['jurnal_id' => $other->id, 'siswa_nis' => '8604', 'status' => 'izin']);
        $this->assertSame(['8601'], RosterPresensi::untukJurnal($j)->pluck('nis')->all());
        $this->actingAs(\App\Models\User::factory()->create(['role' => 'admin']))
            ->postJson('/api/jurnal/'.$j->getRouteKey().'/presensi', ['presensi' => [['siswa_nis' => '8601', 'status' => 'hadir']]])->assertOk();
    }

    public function test_additive_fk_migration_is_reversible_in_memory(): void
    {
        $migration = require database_path('migrations/2026_10_30_000001_restrict_historical_master_deletion.php');
        $migration->down();
        $migration->up();
        foreach (['jadwal' => 3, 'jurnal' => 1, 'presensi_harian' => 2, 'presensi_harian_log' => 1] as $table => $minimum) {
            $keys = \Illuminate\Support\Facades\Schema::getForeignKeys($table);
            $restrict = array_filter($keys, fn ($key) => strtolower($key['on_delete']) === 'restrict');
            $this->assertGreaterThanOrEqual($minimum, count($restrict));
        }
    }

    public function test_incomplete_history_never_invents_membership_and_current_fallback_is_today_only(): void
    {
        $j = $this->meeting(today()->toDateString());
        $class = $j->jadwal->kelas;
        $current = Siswa::factory()->create(['nis' => '8301', 'kelas_id' => $class->id]);
        $unknown = Siswa::factory()->create(['nis' => '8302', 'kelas_id' => $class->id]);
        RiwayatKelasSiswa::create(['siswa_nis' => $unknown->nis, 'kelas_id' => $class->id, 'tahun_ajaran_kode' => $class->tahun_ajaran_kode, 'tingkat' => 'X', 'aktif' => true, 'status' => 'aktif', 'tanggal_mulai' => null]);
        $this->assertSame([$current->nis], RosterPresensi::untukJurnal($j)->pluck('nis')->all());
        $j->tanggal = today()->subDay();
        $this->assertCount(0, RosterPresensi::untukJurnal($j));
    }

    public function test_historical_roster_uses_dated_membership_and_exact_saved_meeting(): void
    {
        $j = $this->meeting();
        $class = $j->jadwal->kelas;
        foreach (['8101' => 'aktif', '8102' => 'lulus', '8103' => 'nonaktif'] as $nis => $status) {
            $s = Siswa::factory()->create(['nis' => (string) $nis, 'kelas_id' => null, 'status' => $status]);
            RiwayatKelasSiswa::create(['siswa_nis' => $s->nis, 'kelas_id' => $class->id, 'tahun_ajaran_kode' => $class->tahun_ajaran_kode, 'tingkat' => 'X', 'aktif' => false, 'status' => 'pindah', 'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2025-09-01']);
        }
        $saved = Siswa::factory()->create(['nis' => '8104', 'kelas_id' => null]);
        Presensi::create(['jurnal_id' => $j->id, 'siswa_nis' => $saved->nis, 'status' => 'izin']);
        Siswa::factory()->create(['nis' => '8199', 'kelas_id' => $class->id]);
        $this->assertSame(['8101', '8102', '8103', '8104'], RosterPresensi::untukJurnal($j)->pluck('nis')->sort()->values()->all());
    }
}
