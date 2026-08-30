<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
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
        $this->guru = $this->akunGuru($jadwal->guru_nip);
        $this->ketua = $this->akunSiswaKelas($jadwal->kelas_id, true);
    }

    /** The next weekday on which this writer's own timetable has a lesson. */
    private function tanggalDenganJadwal(User $user): Carbon
    {
        $hari = Jadwal::untukPengguna($user)->value('hari');
        $tanggal = today();

        for ($i = 0; $i < 7; $i++) {
            if ((Ringkasan::HARI[$tanggal->dayOfWeekIso - 1] ?? null) === $hari) {
                return $tanggal;
            }
            $tanggal = $tanggal->copy()->addDay();
        }

        $this->fail("Tidak menemukan tanggal untuk hari {$hari}.");
    }

    /**
     * A timetable cannot put anyone in two places at once, and saying so must
     * reach the admin as a message on the field — not as a crash page.
     *
     * The unique keys on `jadwal` refuse the clash at the database level, which
     * used to surface as a raw 1062 error and a 500. These pin the validation
     * that turns it into an answer.
     */
    private function slotKosong(): array
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $guru = Guru::has('mataPelajaran')->firstOrFail();

        return [
            'admin' => $admin,
            'payload' => [
                'kelas_id' => Kelas::value('id'),
                'mata_pelajaran_id' => $guru->mataPelajaran->first()->id,
                'guru_nip' => $guru->nip,
                'hari' => 'Jumat',
                // Outside the seeded blocks, so the slot starts out free.
                'jam_ke_mulai' => 11,
                'jam_ke_selesai' => 12,
            ],
        ];
    }

    public function test_a_clashing_slot_is_refused_with_a_field_error(): void
    {
        ['admin' => $admin, 'payload' => $payload] = $this->slotKosong();

        $this->actingAs($admin)->post('/jadwal', $payload)
            ->assertRedirect(route('jadwal.index'))
            ->assertSessionHasNoErrors();

        // Same class, same day, same period: the class would be in two rooms.
        $this->actingAs($admin)->post('/jadwal', $payload)
            ->assertSessionHasErrors('kelas_id');

        $this->assertSame(1, Jadwal::where('jam_ke_mulai', 11)->count());
    }

    public function test_a_teacher_cannot_be_booked_into_two_classes_at_once(): void
    {
        ['admin' => $admin, 'payload' => $payload] = $this->slotKosong();

        $this->actingAs($admin)->post('/jadwal', $payload)->assertSessionHasNoErrors();

        $lain = Kelas::where('id', '!=', $payload['kelas_id'])->firstOrFail();

        $this->actingAs($admin)
            ->post('/jadwal', ['kelas_id' => $lain->id] + $payload)
            ->assertSessionHasErrors('guru_nip');

        $this->assertSame(1, Jadwal::where('jam_ke_mulai', 11)->count());
    }

    public function test_an_overlapping_period_range_is_refused_too(): void
    {
        ['admin' => $admin, 'payload' => $payload] = $this->slotKosong();

        $this->actingAs($admin)->post('/jadwal', $payload)->assertSessionHasNoErrors();

        // JP 10-11 shares JP 11 with the slot above without sharing its start,
        // which a unique key on (…, jam_ke_mulai) cannot see at all. JP 10 is
        // itself free: the seeded blocks stop at 8-9.
        $this->actingAs($admin)
            ->post('/jadwal', ['jam_ke_mulai' => 10, 'jam_ke_selesai' => 11] + $payload)
            ->assertSessionHasErrors('kelas_id');

        $this->assertSame(1, Jadwal::where('jam_ke_mulai', '>=', 10)->count());
    }

    public function test_the_dropdown_only_offers_that_days_lessons(): void
    {
        $tanggal = $this->tanggalDenganJadwal($this->ketua);
        $hari = Ringkasan::HARI[$tanggal->dayOfWeekIso - 1];

        $this->actingAs($this->ketua)
            ->get('/jurnal/create?tanggal='.$tanggal->toDateString())
            ->assertOk()
            ->assertViewHas('jadwalList', fn ($list) => $list->isNotEmpty()
                && $list->every(fn ($j) => $j->hari === $hari));
    }

    public function test_changing_the_date_changes_the_list(): void
    {
        $tanggal = $this->tanggalDenganJadwal($this->ketua);

        $hariIni = $this->actingAs($this->ketua)
            ->get('/jurnal/create?tanggal='.$tanggal->toDateString())
            ->viewData('jadwalList')->pluck('id')->sort()->values();

        $besok = $this->actingAs($this->ketua)
            ->get('/jurnal/create?tanggal='.$tanggal->copy()->addDay()->toDateString())
            ->viewData('jadwalList')->pluck('id')->sort()->values();

        $this->assertNotEquals($hariIni->all(), $besok->all());
    }

    public function test_a_day_without_lessons_explains_itself_instead_of_offering_nothing(): void
    {
        // Sunday: `jadwal.hari` only ever holds Senin–Sabtu.
        $minggu = today()->next(Carbon::SUNDAY);

        $this->actingAs($this->ketua)
            ->get('/jurnal/create?tanggal='.$minggu->toDateString())
            ->assertOk()
            ->assertViewHas('jadwalList', fn ($list) => $list->isEmpty())
            ->assertSee('hubungi admin', false)
            // No save button: the post would only bounce off the required jadwal_id.
            ->assertDontSee('Simpan Jurnal', false);
    }

    public function test_slots_already_written_up_are_marked(): void
    {
        $tanggal = $this->tanggalDenganJadwal($this->ketua);
        $jadwal = Jadwal::where('kelas_id', $this->ketua->kelas_id)
            ->where('hari', Ringkasan::HARI[$tanggal->dayOfWeekIso - 1])
            ->firstOrFail();

        Jurnal::create([
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal->toDateString(),
            'materi' => 'Sudah ditulis',
            'diisi_oleh_id' => $this->ketua->id,
            'diisi_oleh_peran' => 'siswa',
        ]);

        $this->actingAs($this->ketua)
            ->get('/jurnal/create?tanggal='.$tanggal->toDateString())
            ->assertOk()
            ->assertViewHas('jadwalTerisi', fn ($terisi) => in_array($jadwal->id, $terisi, true))
            ->assertSee('sudah diisi', false);
    }

    /**
     * The journal is the class's record, so the form is closed to a guru
     * outright rather than merely narrowed to their own slots.
     */
    public function test_a_guru_is_not_offered_the_journal_form_at_all(): void
    {
        $this->actingAs($this->guru)->get('/jurnal/create')->assertForbidden();
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
        $this->actingAs($this->ketua)
            ->get('/jurnal/create?tanggal=bukan-tanggal')
            ->assertSessionHasErrors('tanggal');
    }

    public function test_the_duplicate_message_names_the_meeting(): void
    {
        $tanggal = $this->tanggalDenganJadwal($this->ketua);
        $jadwal = Jadwal::with(['kelas', 'mataPelajaran'])
            ->where('kelas_id', $this->ketua->kelas_id)
            ->where('hari', Ringkasan::HARI[$tanggal->dayOfWeekIso - 1])
            ->firstOrFail();

        Jurnal::create([
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal->toDateString(),
            'materi' => 'Yang pertama',
            'diisi_oleh_id' => $this->ketua->id,
            'diisi_oleh_peran' => 'siswa',
        ]);

        // With a whole day of slots in the dropdown, "this meeting" alone would
        // leave the writer guessing which one was refused.
        $this->actingAs($this->ketua)
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
