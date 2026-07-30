<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\User;
use App\Support\Ringkasan;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The journal form used to offer every schedule the user had, ever — an
 * unusable list that grows with the timetable and gives no hint which slots are
 * already written up. It now follows the date being filed for.
 */
class JadwalFormTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);

        $jadwal = Jadwal::firstOrFail();
        $this->guru = $jadwal->guru;
        $this->ketua = User::where('role', 'siswa')
            ->where('kelas_id', $jadwal->kelas_id)
            ->where('is_ketua_kelas', true)
            ->firstOrFail();
    }

    /** The next weekday on which this teacher actually has a lesson. */
    private function tanggalDenganJadwal(User $user): Carbon
    {
        $hari = Jadwal::where('guru_id', $user->id)->value('hari');
        $tanggal = today();

        for ($i = 0; $i < 7; $i++) {
            if ((Ringkasan::HARI[$tanggal->dayOfWeekIso - 1] ?? null) === $hari) {
                return $tanggal;
            }
            $tanggal = $tanggal->copy()->addDay();
        }

        $this->fail("Tidak menemukan tanggal untuk hari {$hari}.");
    }

    public function test_the_dropdown_only_offers_that_days_lessons(): void
    {
        $tanggal = $this->tanggalDenganJadwal($this->guru);
        $hari = Ringkasan::HARI[$tanggal->dayOfWeekIso - 1];

        $this->actingAs($this->guru)
            ->get('/jurnal/create?tanggal='.$tanggal->toDateString())
            ->assertOk()
            ->assertViewHas('jadwalList', fn ($list) => $list->isNotEmpty()
                && $list->every(fn ($j) => $j->hari === $hari));
    }

    public function test_changing_the_date_changes_the_list(): void
    {
        $tanggal = $this->tanggalDenganJadwal($this->guru);

        $hariIni = $this->actingAs($this->guru)
            ->get('/jurnal/create?tanggal='.$tanggal->toDateString())
            ->viewData('jadwalList')->pluck('id')->sort()->values();

        $besok = $this->actingAs($this->guru)
            ->get('/jurnal/create?tanggal='.$tanggal->copy()->addDay()->toDateString())
            ->viewData('jadwalList')->pluck('id')->sort()->values();

        $this->assertNotEquals($hariIni->all(), $besok->all());
    }

    public function test_a_day_without_lessons_explains_itself_instead_of_offering_nothing(): void
    {
        // Sunday: `jadwal.hari` only ever holds Senin–Sabtu.
        $minggu = today()->next(Carbon::SUNDAY);

        $this->actingAs($this->guru)
            ->get('/jurnal/create?tanggal='.$minggu->toDateString())
            ->assertOk()
            ->assertViewHas('jadwalList', fn ($list) => $list->isEmpty())
            ->assertSee('hubungi admin', false)
            // No save button: the post would only bounce off the required jadwal_id.
            ->assertDontSee('Simpan Jurnal', false);
    }

    public function test_slots_already_written_up_are_marked(): void
    {
        $tanggal = $this->tanggalDenganJadwal($this->guru);
        $jadwal = Jadwal::where('guru_id', $this->guru->id)
            ->where('hari', Ringkasan::HARI[$tanggal->dayOfWeekIso - 1])
            ->firstOrFail();

        Jurnal::create([
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal->toDateString(),
            'materi' => 'Sudah ditulis',
            'guru_id' => $this->guru->id,
            'diisi_oleh_id' => $this->guru->id,
            'diisi_oleh_peran' => 'guru',
        ]);

        $this->actingAs($this->guru)
            ->get('/jurnal/create?tanggal='.$tanggal->toDateString())
            ->assertOk()
            ->assertViewHas('jadwalTerisi', fn ($terisi) => in_array($jadwal->id, $terisi, true))
            ->assertSee('sudah diisi', false);
    }

    public function test_a_guru_is_never_offered_another_gurus_lesson(): void
    {
        $tanggal = $this->tanggalDenganJadwal($this->guru);

        $this->actingAs($this->guru)
            ->get('/jurnal/create?tanggal='.$tanggal->toDateString())
            ->assertOk()
            ->assertViewHas('jadwalList', fn ($list) => $list
                ->every(fn ($j) => $j->guru_id === $this->guru->id));
    }

    public function test_a_ketua_is_only_offered_their_own_class(): void
    {
        $this->actingAs($this->ketua)
            ->get('/jurnal/create')
            ->assertOk()
            ->assertViewHas('jadwalList', fn ($list) => $list
                ->every(fn ($j) => $j->kelas_id === $this->ketua->kelas_id));
    }

    public function test_a_malformed_date_is_refused(): void
    {
        $this->actingAs($this->guru)
            ->get('/jurnal/create?tanggal=bukan-tanggal')
            ->assertSessionHasErrors('tanggal');
    }

    public function test_the_duplicate_message_names_the_meeting(): void
    {
        $tanggal = $this->tanggalDenganJadwal($this->guru);
        $jadwal = Jadwal::with(['kelas', 'mataPelajaran'])
            ->where('guru_id', $this->guru->id)
            ->where('hari', Ringkasan::HARI[$tanggal->dayOfWeekIso - 1])
            ->firstOrFail();

        Jurnal::create([
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal->toDateString(),
            'materi' => 'Yang pertama',
            'guru_id' => $this->guru->id,
            'diisi_oleh_id' => $this->guru->id,
            'diisi_oleh_peran' => 'guru',
        ]);

        // With a whole day of slots in the dropdown, "this meeting" alone would
        // leave the writer guessing which one was refused.
        $this->actingAs($this->guru)
            ->post('/jurnal', [
                'jadwal_id' => $jadwal->id,
                'tanggal' => $tanggal->toDateString(),
                'materi' => 'Yang kedua',
                'kehadiran_guru' => 'hadir',
            ])
            ->assertSessionHasErrors('jadwal_id');

        $pesan = session('errors')->first('jadwal_id');
        $this->assertStringContainsString($jadwal->kelas->nama_kelas, $pesan);
        $this->assertStringContainsString($jadwal->mataPelajaran->nama, $pesan);
        $this->assertStringContainsString('JP '.$jadwal->jpLabel(), $pesan);
    }
}
