<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\PresensiHarian;
use App\Models\User;
use App\Support\Ringkasan;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A meeting may carry at most two journals — the guru's own and the one a ketua
 * kelas files on their behalf — and never two from the same side. The lesson was
 * taught once, so its attendance roster lives on exactly one of them, and
 * meeting-based figures must count the meeting once.
 */
class JurnalGandaTest extends TestCase
{
    use RefreshDatabase;

    private Jadwal $jadwal;

    private User $guru;

    private User $ketua;

    /** A date with no journal yet, so each test starts from a clean meeting. */
    private string $tanggal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);

        $this->jadwal = Jadwal::with('kelas')->firstOrFail();
        $this->guru = User::findOrFail($this->jadwal->guru_id);
        $this->ketua = User::where('role', 'siswa')
            ->where('kelas_id', $this->jadwal->kelas_id)
            ->where('is_ketua_kelas', true)
            ->firstOrFail();

        $this->tanggal = now()->addMonth()->toDateString();
        Jurnal::where('jadwal_id', $this->jadwal->id)->whereDate('tanggal', $this->tanggal)->delete();
    }

    /** @param  array<string, mixed>  $ganti */
    private function kirim(User $sebagai, array $ganti = [])
    {
        return $this->actingAs($sebagai)->post('/jurnal', array_merge([
            'jadwal_id' => $this->jadwal->id,
            'tanggal' => $this->tanggal,
            'materi' => 'Materi uji',
            'kehadiran_guru' => 'hadir',
        ], $ganti));
    }

    private function jumlahJurnal(): int
    {
        return Jurnal::where('jadwal_id', $this->jadwal->id)
            ->whereDate('tanggal', $this->tanggal)
            ->count();
    }

    public function test_a_guru_cannot_file_two_journals_for_one_meeting(): void
    {
        $this->kirim($this->guru)->assertRedirect();
        $this->assertSame(1, $this->jumlahJurnal());

        $this->kirim($this->guru, ['materi' => 'Kirim kedua'])
            ->assertSessionHasErrors('jadwal_id');

        $this->assertSame(1, $this->jumlahJurnal(), 'Kiriman kedua guru seharusnya ditolak.');
    }

    public function test_a_ketua_cannot_file_two_journals_for_one_meeting(): void
    {
        $this->kirim($this->ketua)->assertRedirect();
        $this->kirim($this->ketua, ['materi' => 'Kirim kedua'])
            ->assertSessionHasErrors('jadwal_id');

        $this->assertSame(1, $this->jumlahJurnal());
    }

    public function test_a_guru_and_a_ketua_may_each_file_one_for_the_same_meeting(): void
    {
        $this->kirim($this->guru)->assertRedirect();
        $this->kirim($this->ketua, ['materi' => 'Versi ketua'])->assertRedirect();

        $this->assertSame(2, $this->jumlahJurnal());
        $this->assertSame(
            ['guru', 'siswa'],
            Jurnal::where('jadwal_id', $this->jadwal->id)->whereDate('tanggal', $this->tanggal)
                ->orderBy('diisi_oleh_peran')->pluck('diisi_oleh_peran')->all(),
        );
    }

    public function test_the_database_itself_refuses_a_duplicate(): void
    {
        $this->kirim($this->guru);

        // The controller check can be lost to a concurrent submit, so the unique
        // index is the real guarantee.
        $this->expectException(QueryException::class);

        Jurnal::create([
            'jadwal_id' => $this->jadwal->id,
            'tanggal' => $this->tanggal,
            'materi' => 'Tembus langsung',
            'guru_id' => $this->guru->id,
            'diisi_oleh_id' => $this->guru->id,
            'diisi_oleh_peran' => 'guru',
        ]);
    }

    public function test_the_api_rejects_a_duplicate_with_422(): void
    {
        $this->kirim($this->guru);

        $this->actingAs($this->guru)
            ->postJson('/api/jurnal', [
                'jadwal_id' => $this->jadwal->id,
                'tanggal' => $this->tanggal,
                'materi' => 'Lewat API',
                'kehadiran_guru_status' => 'hadir',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.jadwal_id.0', fn ($p) => str_contains($p, 'sudah diisi'));

        $this->assertSame(1, $this->jumlahJurnal());
    }

    /**
     * A meeting may still carry two journals (the guru's and the ketua's), but
     * attendance is no longer attached to either: it is one roll call for the
     * class's whole day. Filing it twice must land on the same single record,
     * whichever journal the day happens to hold.
     */
    public function test_only_one_attendance_roster_exists_per_class_day(): void
    {
        $this->kirim($this->guru);
        $this->kirim($this->ketua, ['materi' => 'Versi ketua']);

        $kelas = $this->jadwal->kelas;
        $roster = $kelas->siswa()->pluck('id');
        // The journals above sit a month ahead to stay clear of seeded data, but
        // a ketua may only ever file today's roll call.
        $hariIni = now()->toDateString();

        $payload = ['tanggal' => $hariIni, 'presensi' => []];
        foreach ($roster as $i => $id) {
            $payload['presensi'][$i] = ['siswa_id' => $id, 'status' => 'hadir'];
        }

        $this->actingAs($this->ketua)
            ->post(route('presensi-harian.store', $kelas), $payload)
            ->assertRedirect(route('presensi-harian.show', [$kelas, 'tanggal' => $hariIni]));

        // A second save replaces the day rather than adding a parallel set.
        $this->actingAs($this->ketua)->post(route('presensi-harian.store', $kelas), $payload);

        $this->assertSame($roster->count(), PresensiHarian::where('kelas_id', $kelas->id)
            ->whereDate('tanggal', $hariIni)->count());
    }

    public function test_a_doubled_meeting_is_counted_once(): void
    {
        $kelasId = $this->jadwal->kelas_id;

        // Asserted on the meeting count rather than the completeness percentage:
        // the percentage is rounded, so one extra meeting out of many need not
        // move it, which would make the test prove nothing.
        $pertemuanKelas = fn () => Jurnal::hitungPertemuan(
            Jurnal::query()
                ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
                ->where('jadwal.kelas_id', $kelasId)
        );

        $sebelum = $pertemuanKelas();

        $this->kirim($this->guru);
        $satu = $pertemuanKelas();

        $this->kirim($this->ketua, ['materi' => 'Versi ketua']);
        $dua = $pertemuanKelas();

        $this->assertSame($sebelum + 1, $satu, 'Jurnal pertama menambah satu pertemuan.');
        $this->assertSame($satu, $dua, 'Jurnal kedua untuk pertemuan yang sama tidak menambah hitungan.');

        // Two journals exist, but they describe one meeting.
        $this->assertSame(2, $this->jumlahJurnal());
        $this->assertLessThanOrEqual(100.0, Ringkasan::kelengkapanKelas($kelasId));
    }
}
