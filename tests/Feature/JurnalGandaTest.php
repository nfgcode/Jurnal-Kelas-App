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
 * A meeting may carry at most two journals — the class's own, filed by its ketua
 * kelas, and an administrative one — and never two from the same side. The
 * lesson was taught once, so meeting-based figures must count it once however
 * many journals describe it.
 *
 * A guru writes no journal at all now; the admin stands in for that side here,
 * which is the only way a `guru`-side row is created any more.
 */
class JurnalGandaTest extends TestCase
{
    use RefreshDatabase;

    private Jadwal $jadwal;

    private User $admin;

    private User $guru;

    private User $ketua;

    /** A date with no journal yet, so each test starts from a clean meeting. */
    private string $tanggal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);

        $this->jadwal = Jadwal::with('kelas')->firstOrFail();
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->guru = $this->akunGuru($this->jadwal->guru_nip);
        $this->ketua = $this->akunSiswaKelas($this->jadwal->kelas_id, true);

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

    public function test_an_admin_cannot_file_two_journals_for_one_meeting(): void
    {
        $this->kirim($this->admin)->assertRedirect();
        $this->assertSame(1, $this->jumlahJurnal());

        $this->kirim($this->admin, ['materi' => 'Kirim kedua'])
            ->assertSessionHasErrors('jadwal_id');

        $this->assertSame(1, $this->jumlahJurnal(), 'Kiriman kedua seharusnya ditolak.');
    }

    /** The class no longer shares authorship with the teacher: they have none. */
    public function test_a_guru_cannot_file_a_journal_at_all(): void
    {
        $this->kirim($this->guru)->assertForbidden();

        $this->assertSame(0, $this->jumlahJurnal());
    }

    public function test_a_ketua_cannot_file_two_journals_for_one_meeting(): void
    {
        $this->kirim($this->ketua)->assertRedirect();
        $this->kirim($this->ketua, ['materi' => 'Kirim kedua'])
            ->assertSessionHasErrors('jadwal_id');

        $this->assertSame(1, $this->jumlahJurnal());
    }

    public function test_an_admin_and_a_ketua_may_each_file_one_for_the_same_meeting(): void
    {
        $this->kirim($this->admin)->assertRedirect();
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
        $this->kirim($this->admin);

        // The controller check can be lost to a concurrent submit, so the unique
        // index is the real guarantee.
        $this->expectException(QueryException::class);

        Jurnal::create([
            'jadwal_id' => $this->jadwal->id,
            'tanggal' => $this->tanggal,
            'materi' => 'Tembus langsung',
            'diisi_oleh_id' => $this->admin->id,
            'diisi_oleh_peran' => 'guru',
        ]);
    }

    public function test_the_api_rejects_a_duplicate_with_422(): void
    {
        $this->kirim($this->admin);

        $this->actingAs($this->admin)
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
     * A meeting may carry two journals, but only one of them is the record the
     * teacher marks against — the class's own, if it exists. Whichever the guru
     * is handed, the class's day must still end up with one row per student.
     */
    public function test_a_doubled_meeting_still_derives_one_row_per_student(): void
    {
        $this->kirim($this->admin);
        $this->kirim($this->ketua, ['materi' => 'Versi ketua']);

        $kelas = $this->jadwal->kelas;
        $roster = $kelas->siswa()->pluck('nis');

        $jurnals = Jurnal::where('jadwal_id', $this->jadwal->id)
            ->whereDate('tanggal', $this->tanggal)
            ->get();

        $this->assertCount(2, $jurnals);

        $payload = ['presensi' => []];
        foreach ($roster as $i => $id) {
            $payload['presensi'][$i] = ['siswa_nis' => $id, 'status' => 'hadir'];
        }

        // Both journals describe the same lesson, so marking both is possible and
        // must not double the day.
        foreach ($jurnals as $jurnal) {
            $this->actingAs($this->guru)
                ->post(route('presensi-jurnal.store', $jurnal), $payload)
                ->assertRedirect(route('jurnal.show', $jurnal));
        }

        $this->assertSame($roster->count(), PresensiHarian::where('kelas_id', $kelas->id)
            ->whereDate('tanggal', $this->tanggal)->count());
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

        $this->kirim($this->admin);
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
