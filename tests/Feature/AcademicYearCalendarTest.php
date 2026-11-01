<?php

namespace Tests\Feature;

use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_crafted_store_cannot_activate_and_rejects_overlapping_dates(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true, 'mulai' => '2026-07-01', 'selesai' => '2027-06-30']);
        $this->post(route('admin.tahun-ajaran.store'), ['kode' => '2027/2028', 'aktif' => true, 'mulai' => '2027-07-01', 'selesai' => '2028-06-30'])->assertRedirect();
        $this->assertFalse(TahunAjaran::find('2027/2028')->aktif);
        $this->assertTrue(TahunAjaran::find('2026/2027')->aktif);
        $this->post(route('admin.tahun-ajaran.store'), ['kode' => '2028/2029', 'mulai' => '2028-06-30', 'selesai' => '2029-06-30'])->assertSessionHasErrors('mulai');
        $this->assertDatabaseMissing('tahun_ajaran', ['kode' => '2028/2029']);
    }

    public function test_admin_prepares_edits_dates_and_explicitly_confirms_activation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true]);
        $this->post(route('admin.tahun-ajaran.siapkan-berikutnya'))->assertRedirect(route('admin.tahun-ajaran.index'));
        $this->get(route('admin.tahun-ajaran.index'))->assertOk()->assertSee('Simpan Tanggal')->assertSee('Usulan kalender')->assertSee('Ubah Tanggal')->assertSee('max-width: 1024px', false)->assertSee('min-width: 220px', false);
        $url = route('admin.tahun-ajaran.update', '2027/2028');
        $this->patch($url, ['mulai' => '2027-08-01', 'selesai' => '2028-07-31', 'aktif' => true])->assertRedirect();
        $this->assertFalse(TahunAjaran::find('2027/2028')->aktif);
        $this->assertSame('2027-08-01', TahunAjaran::find('2027/2028')->mulai->toDateString());
        $this->assertDatabaseHas('admin_audit_log', ['aksi' => 'tahun_ajaran.ubah_tanggal']);
        $this->patch($url, ['mulai' => '2028-08-01', 'selesai' => '2028-07-31'])->assertSessionHasErrors('selesai');
        $this->patch($url, ['mulai' => '2027-08-01'])->assertSessionHasErrors('selesai');
        $activate = route('admin.tahun-ajaran.aktifkan', '2027/2028');
        $this->post($activate)->assertSessionHasErrors('konfirmasi');
        $this->assertFalse(TahunAjaran::find('2027/2028')->aktif);
        // 2A.6: calendar confirmation alone is insufficient; require verified active placement.
        $kelas = $this->buatKelas(['nama_kelas' => 'X Aktivasi', 'tingkat' => 'X', 'tahun_ajaran_kode' => '2027/2028']);
        $this->travelTo(now()->setDate(2027, 8, 2));
        $student = \App\Models\Siswa::create(['nis' => 'activate-1', 'nama' => 'Placed', 'status' => 'aktif', 'kelas_id' => $kelas->id]);
        \App\Models\RiwayatKelasSiswa::daftarkan($student, $kelas, '2027/2028', '2027-08-01');
        $this->post($activate, ['konfirmasi' => '1'])->assertRedirect();
        $this->assertTrue(TahunAjaran::find('2027/2028')->aktif);
        $events = app(Schedule::class)->events();
        $this->assertTrue(collect($events)->contains(fn ($e) => str_contains($e->command ?? '', 'tahun-ajaran:siapkan-berikutnya') && $e->expression === '15 0 * * *' && $e->withoutOverlapping));
    }

    public function test_inactive_only_year_is_not_presented_as_active(): void
    {
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false]);
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.tahun-ajaran.index'));
        $response->assertOk()->assertViewHas('tahunAktif', null)->assertSee('Belum Ada Tahun Aktif');
        if (getenv('EXPORT_YEAR_QA')) {
            file_put_contents(storage_path('app/year-calendar-qa.html'), $response->getContent());
        }
    }

    public function test_prepare_is_admin_only_and_get_never_mutates(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'guru']));
        $this->post(route('admin.tahun-ajaran.siapkan-berikutnya'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.tahun-ajaran.siapkan-berikutnya'))->assertStatus(405);
        $this->get(route('admin.tahun-ajaran.index'))->assertOk();
        $this->assertDatabaseCount('tahun_ajaran', 0);
    }

    public function test_configured_calendar_before_boundary_and_existing_successor_are_preserved(): void
    {
        $this->travelTo(now()->setDate(2027, 8, 31));
        config(['kalender_akademik.mulai' => '09-01', 'kalender_akademik.selesai' => '08-31']);
        $this->artisan('tahun-ajaran:siapkan-berikutnya')->assertSuccessful();
        $this->assertSame('2026-09-01', TahunAjaran::find('2026/2027')->mulai->toDateString());
        TahunAjaran::find('2026/2027')->update(['aktif' => true]);
        TahunAjaran::create(['kode' => '2027/2028', 'mulai' => '2027-09-03', 'selesai' => '2028-08-30', 'aktif' => false]);
        $this->artisan('tahun-ajaran:siapkan-berikutnya')->assertSuccessful();
        $this->assertSame('2027-09-03', TahunAjaran::find('2027/2028')->mulai->toDateString());
        $this->assertDatabaseCount('tahun_ajaran', 2);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->patch(route('admin.tahun-ajaran.update', '2027/2028'), ['mulai' => '2027-08-31', 'selesai' => '2028-08-31'])->assertSessionHasErrors('mulai');
    }

    public function test_invalid_calendar_pattern_fails_without_creating_period(): void
    {
        config(['kalender_akademik.mulai' => '02-30']);
        $this->artisan('tahun-ajaran:siapkan-berikutnya')->assertFailed();
        $this->assertDatabaseCount('tahun_ajaran', 0);
    }

    public function test_empty_database_bootstrap_is_idempotent_at_calendar_boundary(): void
    {
        $this->travelTo(now()->setDate(2027, 7, 1));
        config(['kalender_akademik.mulai' => '07-01', 'kalender_akademik.selesai' => '06-30']);
        $this->artisan('tahun-ajaran:siapkan-berikutnya')->assertSuccessful();
        $this->artisan('tahun-ajaran:siapkan-berikutnya')->assertSuccessful();
        $this->assertDatabaseCount('tahun_ajaran', 1);
        $this->assertDatabaseHas('tahun_ajaran', ['kode' => '2027/2028', 'aktif' => false, 'mulai' => '2027-07-01 00:00:00', 'selesai' => '2028-06-30 00:00:00']);
    }

    public function test_daily_preparation_is_idempotent_inactive_and_derives_dates(): void
    {
        $this->travelTo(now()->setDate(2027, 6, 30));
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true, 'mulai' => '2026-08-03', 'selesai' => '2027-07-30']);
        $this->artisan('tahun-ajaran:siapkan-berikutnya')->assertSuccessful();
        $this->artisan('tahun-ajaran:siapkan-berikutnya')->assertSuccessful();
        $this->assertDatabaseCount('tahun_ajaran', 2);
        $this->assertDatabaseHas('tahun_ajaran', ['kode' => '2027/2028', 'aktif' => false, 'mulai' => '2027-08-03 00:00:00', 'selesai' => '2028-07-30 00:00:00']);
        $this->assertTrue(TahunAjaran::find('2026/2027')->aktif);
        $this->assertDatabaseHas('admin_audit_log', ['aksi' => 'tahun_ajaran.siapkan', 'entitas_id' => '2027/2028']);
        $this->assertDatabaseCount('transisi_tahun_ajaran', 0);
    }
}
