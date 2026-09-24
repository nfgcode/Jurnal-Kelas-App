<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\User;
use App\Support\Ringkasan;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guru and siswa dashboards follow the Figma MoSCoW layout: a date picker
 * and a week calendar choose the day (?tanggal=), the timetable shows that
 * day's lessons with one action each, and the admin dashboard leads with four
 * action cards.
 */
class DashboardHarianTest extends TestCase
{
    use RefreshDatabase;

    private Jadwal $jadwal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->jadwal = Jadwal::with(['kelas', 'mataPelajaran'])->firstOrFail();
    }

    /** The next date, from $dari, that falls on the jadwal's weekday. */
    private function tanggalUntuk(Jadwal $jadwal, Carbon $dari): Carbon
    {
        $indeks = array_search($jadwal->hari, Ringkasan::HARI, true);
        $tanggal = $dari->copy()->startOfDay();

        while ($tanggal->dayOfWeekIso - 1 !== $indeks) {
            $tanggal->addDay();
        }

        return $tanggal;
    }

    public function test_guru_dashboard_shows_the_chosen_days_lessons_and_the_week(): void
    {
        $guru = $this->akunGuru($this->jadwal->guru_nip);
        $tanggal = $this->tanggalUntuk($this->jadwal, today()->subWeeks(2));

        $this->actingAs($guru)
            ->get('/dashboard?tanggal='.$tanggal->toDateString())
            ->assertOk()
            ->assertSee('Dashboard Guru')
            ->assertSee($this->jadwal->kelas->nama_kelas)
            ->assertSee('Presensi Siswa')
            ->assertSee('Kalender minggu ini', false)
            ->assertSee('aria-current="date"', false)
            ->assertSee(route('presensi-jurnal.mulai'), false);
    }

    public function test_a_future_day_offers_no_action_yet(): void
    {
        $guru = $this->akunGuru($this->jadwal->guru_nip);
        $tanggal = $this->tanggalUntuk($this->jadwal, today()->addWeeks(3));

        $this->actingAs($guru)
            ->get('/dashboard?tanggal='.$tanggal->toDateString())
            ->assertOk()
            ->assertSee('Belum dimulai')
            ->assertDontSee(route('presensi-jurnal.mulai'), false);
    }

    public function test_ketua_sees_a_link_to_the_journal_the_class_already_wrote(): void
    {
        // The demo data files every journal from the guru's side; make one the
        // class's own, which is the entry that discharges the ketua's duty.
        $jurnal = Jurnal::with('jadwal')->firstOrFail();
        $jurnal->update(['diisi_oleh_peran' => 'siswa']);
        $ketua = $this->akunSiswaKelas($jurnal->jadwal->kelas_id, true);

        $this->actingAs($ketua)
            ->get('/dashboard?tanggal='.$jurnal->tanggal->toDateString())
            ->assertOk()
            ->assertSee('Dashboard Siswa')
            ->assertSee('Isi Jurnal Kelas')
            ->assertSee(route('jurnal.show', $jurnal), false);
    }

    public function test_ketua_gets_an_isi_jurnal_button_for_an_unwritten_lesson(): void
    {
        $ketua = $this->akunSiswaKelas($this->jadwal->kelas_id, true);
        // Far enough back that the demo data has nothing written for it.
        $tanggal = $this->tanggalUntuk($this->jadwal, today()->subYears(2));

        $this->actingAs($ketua)
            ->get('/dashboard?tanggal='.$tanggal->toDateString())
            ->assertOk()
            // Escaped match: the query string's & is &amp; in the HTML.
            ->assertSee(route('jurnal.create', [
                'jadwal_id' => $this->jadwal->id,
                'tanggal' => $tanggal->toDateString(),
            ]));
    }

    public function test_an_invalid_date_is_rejected(): void
    {
        $guru = $this->akunGuru($this->jadwal->guru_nip);

        $this->actingAs($guru)
            ->get('/dashboard?tanggal=bukan-tanggal')
            ->assertSessionHasErrors('tanggal');
    }

    public function test_admin_dashboard_leads_with_action_cards(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Cek Jurnal Hari Ini')
            ->assertSee('Pantau Kehadiran')
            ->assertSee('Kelola Pengguna')
            ->assertSee('Atur Jadwal')
            ->assertDontSee('Guru Teraktif')
            ->assertDontSee('Kelengkapan Jurnal per Kelas');
    }
}
