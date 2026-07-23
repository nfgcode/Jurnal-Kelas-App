<?php

namespace Tests\Feature;

use App\Models\Jurnal;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The period filter and its drill-down: every preset renders, a backwards
 * custom range is rejected, and the detail endpoint answers with the meetings
 * behind a clicked figure — or an honest empty state.
 */
class DashboardPeriodeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    public static function presetProvider(): array
    {
        return [
            'hari ini' => ['hari_ini'],
            'minggu ini' => ['minggu_ini'],
            'minggu lalu' => ['minggu_lalu'],
            'bulan ini' => ['bulan_ini'],
            'bulan lalu' => ['bulan_lalu'],
            '30 hari' => ['30_hari'],
            'tahun ini' => ['tahun_ini'],
        ];
    }

    #[DataProvider('presetProvider')]
    public function test_dashboard_renders_for_each_preset(string $preset): void
    {
        $this->actingAs($this->admin)
            ->get("/admin?preset={$preset}")
            ->assertOk()
            ->assertSee('Dashboard Admin');
    }

    public function test_custom_range_is_accepted(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin?preset=custom&mulai=2026-06-01&selesai=2026-07-01')
            ->assertOk();
    }

    public function test_custom_range_with_end_before_start_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin?preset=custom&mulai=2026-07-10&selesai=2026-07-01')
            ->assertSessionHasErrors('selesai');
    }

    public function test_reports_also_take_the_period(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/laporan/presensi?preset=minggu_lalu')
            ->assertOk()
            ->assertSee('Rekap Presensi per Pertemuan');
    }

    public function test_detail_returns_journals_for_a_populated_date(): void
    {
        // The most recent journalled date is, by definition, one with data.
        $tanggal = Jurnal::query()->max('tanggal');

        $this->actingAs($this->admin)
            ->getJson("/admin/dashboard/detail?tipe=jurnal&tanggal={$tanggal}")
            ->assertOk()
            ->assertJson(['kosong' => false])
            ->assertJsonStructure([
                'judul',
                'kosong',
                'baris' => [['tanggal', 'kelas', 'guru', 'statusChip' => ['label', 'tone']]],
            ]);
    }

    public function test_detail_reports_empty_for_a_date_without_journals(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/admin/dashboard/detail?tipe=jurnal&tanggal=2020-01-01')
            ->assertOk()
            ->assertJson(['kosong' => true]);
    }

    public function test_detail_lists_a_teachers_journals(): void
    {
        $guru = Jurnal::query()->firstOrFail()->guru;

        $this->actingAs($this->admin)
            ->getJson("/admin/dashboard/detail?tipe=guru&guru_id={$guru->id}&preset=30_hari")
            ->assertOk()
            ->assertJsonFragment(['judul' => 'Jurnal ' . $guru->name]);
    }

    public function test_dashboard_marks_up_the_drill_targets(): void
    {
        // The user's complaint was that these two figures had no detail; the
        // attributes the front-end listens for must actually reach the HTML.
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-detail-tipe="presensi"', false)
            ->assertSee('data-detail-tipe="kelas"', false);
    }

    public function test_detail_lists_student_attendance_for_a_status(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/admin/dashboard/detail?tipe=presensi&status=hadir&preset=30_hari')
            ->assertOk()
            ->assertJson(['tampilan' => 'presensi', 'kosong' => false])
            ->assertJsonStructure([
                'baris' => [['tanggal', 'siswa', 'kelas', 'statusChip' => ['label', 'tone']]],
            ]);
    }

    public function test_detail_lists_one_class_on_one_date(): void
    {
        $jurnal = Jurnal::query()->with('jadwal')->firstOrFail();

        $this->actingAs($this->admin)
            ->getJson("/admin/dashboard/detail?tipe=kelas&kelas_id={$jurnal->jadwal->kelas_id}&tanggal={$jurnal->tanggal->toDateString()}")
            ->assertOk()
            ->assertJson(['tampilan' => 'pertemuan', 'kosong' => false]);
    }

    public function test_detail_lists_filled_journals(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/admin/dashboard/detail?tipe=terisi&preset=30_hari')
            ->assertOk()
            ->assertJson(['tampilan' => 'pertemuan', 'kosong' => false]);
    }

    public function test_detail_lists_unfilled_meetings_with_their_teacher(): void
    {
        // The seeder skips roughly one meeting in eight, so gaps exist.
        $this->actingAs($this->admin)
            ->getJson('/admin/dashboard/detail?tipe=belum&preset=tahun_ini')
            ->assertOk()
            ->assertJson(['tampilan' => 'belum', 'kosong' => false])
            ->assertJsonStructure(['baris' => [['tanggal', 'kelas', 'mapel', 'guru']]]);
    }

    public function test_detail_lists_completeness_per_class(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/admin/dashboard/detail?tipe=kelengkapan&preset=30_hari')
            ->assertOk()
            ->assertJson(['tampilan' => 'kelengkapan', 'kosong' => false])
            ->assertJsonStructure(['baris' => [['kelas', 'persen']]]);
    }

    public function test_detail_late_journals_render(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/admin/dashboard/detail?tipe=telat&preset=tahun_ini')
            ->assertOk()
            ->assertJson(['tampilan' => 'pertemuan']);
    }

    public function test_reports_mark_up_their_card_drills_and_teacher_links(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/laporan/jurnal?preset=30_hari')
            ->assertOk()
            ->assertSee('data-detail-tipe="belum"', false)
            ->assertSee('data-detail-tipe="kelengkapan"', false)
            ->assertSee('name-cell text-reset', false);   // guru profile link

        $this->actingAs($this->admin)
            ->get('/admin/laporan/presensi?preset=30_hari')
            ->assertOk()
            ->assertSee('data-detail-tipe="presensi"', false);
    }

    public function test_detail_is_forbidden_for_non_admin(): void
    {
        $guru = User::where('role', 'guru')->firstOrFail();

        $this->actingAs($guru)
            ->get('/admin/dashboard/detail?tipe=jurnal')
            ->assertForbidden();
    }
}
