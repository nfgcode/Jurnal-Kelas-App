<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\User;
use App\Support\CadanganData;
use App\Support\PencatatanAkademik;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JurusanTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $data = []): array
    {
        return $data + ['kode' => 'TKJ', 'nama' => 'Teknik Komputer dan Jaringan', 'aktif' => 1];
    }

    private function panggung(): array
    {
        Jurusan::create($this->payload());
        $kelas = $this->buatKelas(['jurusan_kode' => 'TKJ']);
        $ketua = $this->buatSiswa(['kelas_id' => $kelas->id]);
        $kelas->update(['ketua_nis' => $ketua->nis]);
        $guru = $this->buatGuru();
        $mapel = MataPelajaran::create(['kode' => 'UJI', 'nama' => 'Pelajaran Uji']);
        $jadwal = Jadwal::create([
            'kelas_id' => $kelas->id, 'guru_nip' => $guru->nip, 'mata_pelajaran_id' => $mapel->id,
            'hari' => today()->locale('id')->dayName, 'jam_ke_mulai' => 1, 'jam_ke_selesai' => 2,
        ]);

        return [$kelas, $ketua, $guru, $jadwal];
    }

    public function test_jurusan_pages_and_writes_are_admin_only(): void
    {
        $admin = User::factory()->create();
        $jurusan = Jurusan::create($this->payload());

        foreach (['/admin/jurusan', '/admin/jurusan/create', '/admin/jurusan/TKJ/edit'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
            $this->actingAs($this->buatGuru())->get($url)->assertForbidden();
        }

        $this->actingAs($this->buatGuru())->post('/admin/jurusan', $this->payload(['kode' => 'RPL']))->assertForbidden();
        $this->actingAs($this->buatGuru())->put('/admin/jurusan/TKJ', $this->payload())->assertForbidden();
        $this->actingAs($this->buatGuru())->delete('/admin/jurusan/TKJ')->assertForbidden();
        auth()->logout();
        $this->get('/admin/jurusan')->assertRedirect('/login');
        $this->assertDatabaseHas('jurusan', ['kode' => $jurusan->kode]);
    }

    public function test_create_normalizes_code_and_update_ignores_its_own_unique_key(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->post('/admin/jurusan', $this->payload(['kode' => 'tkj']))
            ->assertRedirect(route('admin.jurusan.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jurusan', ['kode' => 'TKJ', 'aktif' => 1]);
        $this->put('/admin/jurusan/TKJ', $this->payload(['nama' => 'Nama Baru']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jurusan', ['kode' => 'TKJ', 'nama' => 'Nama Baru']);
        $this->post('/admin/jurusan', $this->payload(['kode' => 'tkj']))->assertSessionHasErrors('kode');
    }

    public function test_jurusan_pages_expose_processing_and_confirmation_states(): void
    {
        Jurusan::create($this->payload());
        $this->actingAs(User::factory()->create());
        $this->get('/admin/jurusan')->assertOk()
            ->assertSee('data-page-processing="Memuat daftar jurusan..."', false)
            ->assertSee('data-processing="Menerapkan filter jurusan..."', false)
            ->assertSee('data-processing="Mengubah jumlah baris..."', false)
            ->assertSee('this.form.requestSubmit()', false)
            ->assertSee('data-processing-status role="status"', false);
        $this->get('/admin/jurusan/create')->assertOk()
            ->assertSee('data-processing="Menyimpan jurusan..."', false);
        $this->get('/admin/jurusan/TKJ/edit')->assertOk()
            ->assertSee('data-processing="Menyimpan perubahan jurusan..."', false)
            ->assertSee('data-processing="Menghapus jurusan..."', false)
            ->assertSee('class="field-confirm mb-3"', false);
    }

    public static function invalidCodes(): array
    {
        return [[''], ['TK J'], ["TK\tJ"], ["TK\nJ"], [' TKJ'], ['TKJ '], ["TKJ\n"], ['ABCDEFGHIJKLMNOPQRSTU']];
    }

    #[DataProvider('invalidCodes')]
    public function test_invalid_codes_are_refused(string $kode): void
    {
        $this->actingAs(User::factory()->create())->post('/admin/jurusan', $this->payload(['kode' => $kode]))
            ->assertSessionHasErrors('kode');
        $this->assertDatabaseCount('jurusan', 0);
    }

    public function test_other_fields_and_missing_checkbox_are_validated(): void
    {
        $this->actingAs(User::factory()->create())->post('/admin/jurusan', [
            'kode' => 'TKJ', 'nama' => '', 'bidang' => str_repeat('b', 256), 'aktif' => 'invalid',
        ])->assertSessionHasErrors(['nama', 'bidang', 'aktif']);
        $this->post('/admin/jurusan', ['kode' => 'TKJ', 'nama' => 'Jaringan'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jurusan', ['kode' => 'TKJ', 'aktif' => 0]);
    }

    public function test_filters_sort_and_pagination_preserve_inactive_filter(): void
    {
        Jurusan::create($this->payload(['bidang' => 'TI']));
        Jurusan::create($this->payload(['kode' => 'RPL', 'nama' => 'Rekayasa', 'bidang' => 'TI', 'aktif' => 0]));
        $this->actingAs(User::factory()->create())->get('/admin/jurusan?aktif=0&bidang=TI&q=Rekayasa&sort=nama&dir=desc')
            ->assertOk()->assertViewHas('jurusan', fn ($rows) => $rows->total() === 1 && $rows->first()->kode === 'RPL');
        $this->get('/admin/jurusan?sort=kode&dir=desc')
            ->assertViewHas('jurusan', fn ($rows) => $rows->pluck('kode')->all() === ['TKJ', 'RPL']);
    }

    public function test_unused_jurusan_can_be_deleted_and_used_jurusan_cannot(): void
    {
        [$kelas, $ketua] = $this->panggung();
        $this->actingAs(User::factory()->create())->delete('/admin/jurusan/TKJ')->assertSessionHas('error');
        $this->assertSame('TKJ', $kelas->fresh()->jurusan_kode);
        $this->assertSame('TKJ', $ketua->siswa->fresh()->jurusan_kode);
        Jurusan::create($this->payload(['kode' => 'RPL']));
        $this->delete('/admin/jurusan/RPL')->assertSessionHas('success');
        $this->assertDatabaseMissing('jurusan', ['kode' => 'RPL']);
    }

    public function test_code_rename_cascades_and_inactive_jurusan_remains_in_class_edit(): void
    {
        [$kelas, $ketua] = $this->panggung();
        $this->actingAs(User::factory()->create())->put('/admin/jurusan/TKJ', $this->payload(['kode' => 'tjkt', 'aktif' => 0]))
            ->assertSessionHasNoErrors();
        $this->assertSame('TJKT', $kelas->fresh()->jurusan_kode);
        $this->assertSame('TJKT', $ketua->siswa->fresh()->jurusan_kode);
        $this->get("/kelas/{$kelas->id}/edit")->assertOk()->assertSee('value="TJKT"', false);
    }

    public function test_raw_student_writes_and_class_changes_always_follow_class(): void
    {
        [$kelas, $ketua] = $this->panggung();
        Jurusan::create($this->payload(['kode' => 'RPL']));
        $lain = $this->buatKelas(['jurusan_kode' => 'RPL', 'nama_kelas' => 'X RPL']);
        DB::table('siswa')->where('nis', $ketua->nis)->update(['jurusan_kode' => 'RPL']);
        $this->assertSame('TKJ', $ketua->siswa->fresh()->jurusan_kode);
        DB::table('siswa')->where('nis', $ketua->nis)->update(['kelas_id' => $lain->id]);
        $this->assertSame('RPL', $ketua->siswa->fresh()->jurusan_kode);
        $lain->update(['jurusan_kode' => null]);
        $this->assertNull($ketua->siswa->fresh()->jurusan_kode);
        $lain->update(['jurusan_kode' => 'RPL']);
        $lain->delete();
        $this->assertNull($ketua->siswa->fresh()->kelas_id);
        $this->assertNull($ketua->siswa->fresh()->jurusan_kode);
        $this->assertDatabaseHas('kelas', ['id' => $kelas->id]);
    }

    public function test_restore_recomputes_student_jurusan_and_uses_natural_keys(): void
    {
        [$kelas, $ketua] = $this->panggung();
        $row = $ketua->siswa->fresh()->getAttributes();
        $row['jurusan_kode'] = null;
        $row['nama'] = 'Nama dari cadangan';
        app(CadanganData::class)->pulihkan(['tabel' => ['siswa' => [$row]]], 'gabung');
        $this->assertDatabaseHas('siswa', ['nis' => $ketua->nis, 'nama' => 'Nama dari cadangan', 'jurusan_kode' => 'TKJ']);
    }

    public function test_partial_class_replace_restore_retains_enrolment_for_restored_classes(): void
    {
        [$kelas, $ketua] = $this->panggung();
        app(CadanganData::class)->pulihkan(['tabel' => ['kelas' => [$kelas->getAttributes()]]], 'ganti');
        $this->assertDatabaseHas('siswa', ['nis' => $ketua->nis, 'kelas_id' => $kelas->id, 'jurusan_kode' => 'TKJ']);
        app(CadanganData::class)->pulihkan(['tabel' => ['kelas' => []]], 'ganti');
        $this->assertDatabaseHas('siswa', ['nis' => $ketua->nis, 'kelas_id' => null, 'jurusan_kode' => null]);
    }

    public function test_portable_procedure_refuses_failure_without_partial_data(): void
    {
        $service = app(PencatatanAkademik::class);
        $service->jurusan($this->payload(['kode' => 'tkj']));
        try {
            $service->jurusan($this->payload(['kode' => 'TK J']));
            $this->fail('Invalid code accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kode', $e->errors());
        }
        $this->assertDatabaseCount('jurusan', 1);
        $service->jurusan($this->payload(['kode' => 'RPL']));
        $this->assertDatabaseCount('jurusan', 2);
    }

    public function test_journal_keeps_original_student_and_reads_current_jurusan(): void
    {
        [$kelas, $ketua, $guru, $jadwal] = $this->panggung();
        $payload = ['jadwal_id' => $jadwal->id, 'tanggal' => today()->toDateString(), 'materi' => 'Materi', 'kehadiran_guru' => 'hadir'];
        $this->actingAs($ketua)->post('/jurnal', $payload + ['siswa_nis' => 'palsu'])->assertSessionHasNoErrors();
        $jurnal = Jurnal::firstOrFail();
        $this->assertSame($ketua->nis, $jurnal->siswa_nis);
        $this->getJson('/api/jurnal/'.$jurnal->public_id)->assertOk()->assertJsonPath('data.jurusan.kode', 'TKJ');
        $this->get('/jurnal/'.$jurnal->public_id)->assertOk()->assertSee('Jurusan Siswa Pengisi');
        $this->put('/jurnal/'.$jurnal->public_id, $payload)->assertForbidden();
        $this->delete('/jurnal/'.$jurnal->public_id)->assertForbidden();
        $this->actingAs($guru)->put('/jurnal/'.$jurnal->public_id, $payload + ['siswa_nis' => 'palsu'])->assertSessionHasNoErrors();
        $this->assertSame($ketua->nis, $jurnal->fresh()->siswa_nis);
        $this->assertSame($ketua->id, $jurnal->fresh()->diisi_oleh_id);
        $kelas->update(['jurusan_kode' => null]);
        $this->getJson('/api/jurnal/'.$jurnal->public_id)->assertJsonPath('data.jurusan', null);
        $this->actingAs(User::factory()->create())->delete('/admin/siswa/'.$ketua->nis)->assertSessionHas('error');
        $this->assertDatabaseHas('siswa', ['nis' => $ketua->nis]);
    }

    public function test_teacher_journal_does_not_fall_back_to_class_jurusan(): void
    {
        [, , $guru, $jadwal] = $this->panggung();
        $this->actingAs($guru)->postJson('/api/jurnal', [
            'jadwal_id' => $jadwal->id, 'tanggal' => today()->toDateString(), 'materi' => 'Guru',
            'siswa_nis' => 'palsu',
        ])->assertCreated()->assertJsonPath('data.siswa_nis', null)->assertJsonPath('data.jurusan', null);
    }

    public function test_api_chair_reads_today_only_and_teacher_edits_preserve_student_reference(): void
    {
        [$kelas, $ketua, $guru, $jadwal] = $this->panggung();
        Jurnal::create(['jadwal_id' => $jadwal->id, 'tanggal' => today()->subDay(), 'materi' => 'Kemarin']);
        $payload = ['jadwal_id' => $jadwal->id, 'tanggal' => today()->toDateString(), 'materi' => 'Hari ini'];
        $this->actingAs($ketua)->postJson('/api/jurnal', $payload + ['siswa_nis' => 'palsu'])
            ->assertCreated()->assertJsonPath('data.siswa_nis', $ketua->nis);
        $jurnal = Jurnal::whereDate('tanggal', today())->firstOrFail();
        $this->getJson('/api/jurnal')->assertJsonCount(1, 'data');
        $this->putJson('/api/jurnal/'.$jurnal->public_id, $payload)->assertForbidden();
        $this->deleteJson('/api/jurnal/'.$jurnal->public_id)->assertForbidden();
        $this->actingAs($guru)->putJson('/api/jurnal/'.$jurnal->public_id, $payload + ['siswa_nis' => 'palsu'])
            ->assertOk()->assertJsonPath('data.siswa_nis', $ketua->nis)->assertJsonPath('data.jurusan.kode', 'TKJ');
        $this->assertSame($ketua->id, $jurnal->fresh()->diisi_oleh_id);
        $this->actingAs($this->buatSiswa(['kelas_id' => $kelas->id]))->getJson('/api/jurnal')->assertJsonCount(2, 'data');
    }

    public function test_admin_api_reports_include_current_student_jurusan(): void
    {
        [$kelas, $ketua, , $jadwal] = $this->panggung();
        Jurnal::create([
            'jadwal_id' => $jadwal->id, 'tanggal' => today()->subDay()->toDateString(), 'materi' => 'Laporan',
            'diisi_oleh_id' => $ketua->id, 'diisi_oleh_peran' => 'siswa', 'siswa_nis' => $ketua->nis,
        ]);
        $this->actingAs(User::factory()->create());

        foreach (['/api/laporan/jurnal', '/api/laporan/presensi'] as $url) {
            $this->getJson($url.'?preset=30_hari')
                ->assertOk()->assertJsonPath('data.0.siswa_nis', $ketua->nis)->assertJsonPath('data.0.jurusan.kode', 'TKJ');
        }

        $kelas->update(['jurusan_kode' => null]);
        $this->getJson('/api/laporan/jurnal?preset=30_hari')->assertJsonPath('data.0.jurusan', null);
    }

    public function test_legacy_backup_infers_student_reference_from_author_and_keeps_it_after_account_deletion(): void
    {
        [, $ketua, , $jadwal] = $this->panggung();
        $jurnal = Jurnal::create([
            'jadwal_id' => $jadwal->id, 'tanggal' => today(), 'materi' => 'Lama',
            'diisi_oleh_id' => $ketua->id, 'diisi_oleh_peran' => 'siswa',
        ]);
        $row = $jurnal->getAttributes();
        unset($row['siswa_nis']);
        $jurnal->delete();
        app(CadanganData::class)->pulihkan(['tabel' => ['jurnal' => [$row]]], 'gabung');
        $jurnal = Jurnal::findOrFail($row['id']);
        $this->assertSame($ketua->nis, $jurnal->fresh()->siswa_nis);
        $jurnal->update(['siswa_nis' => null]);
        app(CadanganData::class)->pulihkan(['tabel' => ['jurnal' => [$row]]], 'gabung');
        $this->assertSame($ketua->nis, $jurnal->fresh()->siswa_nis);
        $ketua->delete();
        $this->assertNull($jurnal->fresh()->diisi_oleh_id);
        $this->assertSame('TKJ', $jurnal->fresh()->siswa->jurusan->kode);
    }

    public function test_database_restricts_deleting_jurusan_and_journal_student(): void
    {
        [, $ketua, , $jadwal] = $this->panggung();
        Jurnal::create(['jadwal_id' => $jadwal->id, 'tanggal' => today(), 'materi' => 'Uji', 'siswa_nis' => $ketua->nis]);
        foreach ([fn () => Jurusan::whereKey('TKJ')->delete(), fn () => Siswa::whereKey($ketua->nis)->delete()] as $delete) {
            try {
                $delete();
                $this->fail('Referenced row was deleted');
            } catch (QueryException $e) {
                $this->assertStringContainsString('FOREIGN KEY', $e->getMessage());
            }
        }
    }
}
