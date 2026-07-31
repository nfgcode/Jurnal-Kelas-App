<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The period picker on the journal and attendance screens used to be a dead
 * <span> styled to look like a dropdown — it could not be clicked at all. These
 * tests pin the working control: the preset must actually narrow the rows, an
 * invented preset must be refused rather than silently ignored, and no period
 * may widen what a role is allowed to see.
 */
class PeriodeFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;

    private User $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);

        $jadwal = Jadwal::firstOrFail();
        $this->guru = $jadwal->guru;
        $this->siswa = User::where('role', 'siswa')
            ->where('kelas_id', $jadwal->kelas_id)
            ->where('is_ketua_kelas', false)
            ->firstOrFail();
    }

    public function test_a_preset_narrows_the_journal_rows_to_its_window(): void
    {
        $this->actingAs($this->guru)
            ->get('/jurnal?preset=hari_ini&per=100')
            ->assertOk()
            ->assertViewHas('jurnals', fn ($jurnals) => $jurnals
                ->every(fn ($jurnal) => $jurnal->tanggal->isToday()));

        $this->actingAs($this->guru)
            ->get('/jurnal?preset=bulan_lalu&per=100')
            ->assertOk()
            ->assertViewHas('jurnals', fn ($jurnals) => $jurnals
                ->every(fn ($jurnal) => $jurnal->tanggal->between(
                    now()->subMonthNoOverflow()->startOfMonth(),
                    now()->subMonthNoOverflow()->endOfMonth()->endOfDay(),
                )));
    }

    public function test_a_narrower_preset_returns_no_more_rows_than_a_wider_one(): void
    {
        $hitung = function (string $preset) {
            $response = $this->actingAs($this->guru)->get("/jurnal?preset={$preset}&per=100")->assertOk();

            return $response->viewData('jurnals')->total();
        };

        $this->assertLessThanOrEqual($hitung('bulan_ini'), $hitung('minggu_ini'));
        $this->assertLessThanOrEqual($hitung('tahun_ini'), $hitung('bulan_ini'));
    }

    public function test_a_custom_range_is_honoured_and_an_incomplete_one_refused(): void
    {
        $mulai = now()->subDays(9)->toDateString();
        $selesai = now()->toDateString();

        $this->actingAs($this->guru)
            ->get("/jurnal?preset=custom&mulai={$mulai}&selesai={$selesai}&per=100")
            ->assertOk()
            ->assertViewHas('jurnals', fn ($jurnals) => $jurnals
                ->every(fn ($jurnal) => $jurnal->tanggal->between(
                    now()->subDays(9)->startOfDay(),
                    now()->endOfDay(),
                )));

        // "custom" without dates is a malformed filter, not an empty one.
        $this->actingAs($this->guru)
            ->get('/jurnal?preset=custom')
            ->assertSessionHasErrors(['mulai', 'selesai']);
    }

    public function test_an_invented_preset_is_refused(): void
    {
        $this->actingAs($this->guru)
            ->get('/jurnal?preset=ngawur')
            ->assertSessionHasErrors('preset');
    }

    public function test_the_period_never_widens_a_role_scope(): void
    {
        // The widest window still shows a guru only their own journals...
        $this->actingAs($this->guru)
            ->get('/jurnal?preset=tahun_ini&per=100')
            ->assertOk()
            ->assertViewHas('jurnals', fn ($jurnals) => $jurnals
                ->every(fn ($jurnal) => $jurnal->guru_id === $this->guru->id));

        // ...and a student only their own class's.
        $this->actingAs($this->siswa)
            ->get('/jurnal?preset=tahun_ini&per=100')
            ->assertOk()
            ->assertViewHas('jurnals', fn ($jurnals) => $jurnals
                ->every(fn ($jurnal) => $jurnal->jadwal->kelas_id === $this->siswa->kelas_id));
    }

    public function test_attendance_screens_follow_the_period_too(): void
    {
        $this->actingAs($this->guru)
            ->get('/presensi?preset=hari_ini&per=100')
            ->assertOk()
            ->assertViewHas('pertemuan', fn ($pertemuan) => $pertemuan
                ->every(fn ($jurnal) => $jurnal->tanggal->isToday()));

        // The student's personal recap is a different screen; it must carry the
        // period as well, otherwise its cards contradict the table.
        $this->actingAs($this->siswa)
            ->get('/presensi?preset=hari_ini')
            ->assertOk()
            ->assertViewHas('periode', fn ($periode) => $periode->preset === 'hari_ini');
    }

    public function test_other_filters_survive_a_change_of_period(): void
    {
        // The picker re-submits as a GET form, so every active filter has to be
        // carried as a hidden field or it is silently dropped.
        $this->actingAs($this->guru)
            ->get('/jurnal?q=aljabar&per=50')
            ->assertOk()
            ->assertSee('name="q" value="aljabar"', false)
            ->assertSee('name="per" value="50"', false);
    }

    /**
     * The mirror of the above, and the one that was actually broken: a filter form
     * posts only its own inputs, so sorting, the period and the page size used to
     * be wiped the moment the reader typed a search term.
     */
    public function test_a_filter_form_carries_the_sort_period_and_page_size(): void
    {
        $respons = $this->actingAs($this->guru)
            ->get('/jurnal?preset=minggu_lalu&sort=kelas&dir=desc&per=50')
            ->assertOk();

        foreach ([
            'name="sort" value="kelas"',
            'name="dir" value="desc"',
            'name="per" value="50"',
            'name="preset" value="minggu_lalu"',
        ] as $medan) {
            $respons->assertSee($medan, false);
            // Exactly once per form, never a duplicate of a field the form owns.
            $this->assertLessThanOrEqual(
                substr_count($respons->getContent(), '<form'),
                substr_count($respons->getContent(), $medan),
            );
        }
    }

    public function test_sorting_still_applies_alongside_a_search(): void
    {
        $urut = fn (string $dir) => $this->actingAs($this->guru)
            ->get("/jurnal?preset=tahun_ini&q=a&sort=kelas&dir={$dir}")
            ->assertOk()
            ->viewData('jurnals')
            ->map(fn ($j) => $j->jadwal?->kelas?->nama_kelas)
            ->all();

        $naik = $urut('asc');
        $turun = $urut('desc');

        $this->assertNotSame([], $naik, 'Pencarian harus menyisakan baris untuk diurutkan.');
        $this->assertNotSame($naik, $turun);
    }
}
