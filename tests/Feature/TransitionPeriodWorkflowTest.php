<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TransisiTahunAjaran;
use App\Models\User;
use App\Support\KesiapanTransisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransitionPeriodWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_page_has_one_shared_period_selector_and_compact_steps(): void
    {
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true]);
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false]);
        $response = $this->actingAs($this->admin())->get(route('admin.transisi.index'));
        $response->assertOk()->assertSee('Periode Transisi')->assertSee('Buka Simulasi')->assertDontSee('pemeriksaan terpenuhi');
        $this->assertSame(1, substr_count($response->getContent(), '<select name="tahun_asal"'));
        $this->assertSame(1, substr_count($response->getContent(), '<select name="tahun_tujuan"'));
        if (getenv('EXPORT_TRANSITION_QA')) {
            file_put_contents(storage_path('app/transition-ready-qa.html'), $response->getContent());
            TahunAjaran::where('kode', '2027/2028')->delete();
            $empty = $this->get(route('admin.transisi.index'));
            file_put_contents(storage_path('app/transition-empty-qa.html'), $empty->getContent());
        }
    }

    public function test_historical_destination_is_rejected_before_class_preparation(): void
    {
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true]);
        TahunAjaran::create(['kode' => '2025/2026', 'aktif' => false]);
        $this->actingAs($this->admin())->post(route('admin.transisi.siapkan-kelas'), ['tahun_asal' => '2026/2027', 'tahun_tujuan' => '2025/2026'])
            ->assertSessionHasErrors('tahun_tujuan');
        $this->assertDatabaseCount('kelas', 0);
    }

    public function test_completed_transition_remains_viewable_after_destination_activation(): void
    {
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => false]);
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => true]);
        TransisiTahunAjaran::create(['tahun_asal' => '2026/2027', 'tahun_tujuan' => '2027/2028', 'status' => 'selesai']);
        $this->actingAs($this->admin())->get(route('admin.transisi.index', ['tahun_asal' => '2026/2027', 'tahun_tujuan' => '2027/2028']))->assertOk()->assertSee('Transisi periode ini sudah selesai.');
    }

    public function test_preparation_preserves_selected_period_after_success(): void
    {
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true]);
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false]);
        $this->actingAs($this->admin())->post(route('admin.transisi.siapkan-kelas'), ['tahun_asal' => '2026/2027', 'tahun_tujuan' => '2027/2028'])
            ->assertRedirect(route('admin.transisi.index', ['tahun_asal' => '2026/2027', 'tahun_tujuan' => '2027/2028']));
    }

    public function test_inactive_period_is_not_reported_as_active(): void
    {
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => false]);
        $checklist = KesiapanTransisi::evaluasi();
        $this->assertSame('terhambat', $checklist['status_keseluruhan']);
        $this->actingAs($this->admin())->get(route('admin.transisi.index'))->assertViewHas('tahunAktif', null);
    }

    public function test_selected_period_controls_statistics_and_destination_candidates(): void
    {
        TahunAjaran::create(['kode' => '2025/2026', 'aktif' => false]);
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true]);
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false]);
        TahunAjaran::create(['kode' => '2028/2029', 'aktif' => false]);
        Jurusan::create(['kode' => 'TKJ', 'nama' => 'TKJ']);
        foreach (['2026/2027', '2027/2028'] as $i => $kode) {
            $kelas = Kelas::create(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'paralel' => '1', 'tahun_ajaran_kode' => $kode]);
            Siswa::create(['nis' => '001'.$i, 'nama' => 'Siswa '.$i, 'kelas_id' => $kelas->id, 'status' => 'aktif']);
        }
        $response = $this->actingAs($this->admin())->get(route('admin.transisi.index', ['tahun_asal' => '2027/2028', 'tahun_tujuan' => '2028/2029']));
        $response->assertOk()->assertViewHas('statistik', fn ($s) => $s['total_siswa'] === 1 && $s['tahun_kode'] === '2027/2028');
        $response->assertViewHas('tahunTujuanList', fn ($list) => $list->pluck('kode')->all() === ['2028/2029']);
    }
}
