<?php

namespace Tests\Feature;

use App\Models\HariLibur;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Presensi;
use App\Models\User;
use App\Support\Ringkasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Hari Libur: dates the school does not meet. Without them the nightly backfill
 * records every teacher as absent on a national holiday and every completeness
 * figure counts lessons that were never going to happen.
 */
class HariLiburTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@jurnalkelas.app'],
            ['username' => 'admin', 'nama' => 'Administrator', 'password' => Hash::make('password'), 'role' => 'admin'],
        );
    }

    private function guru(): User
    {
        return $this->buatGuru(['nip' => '1986'.str_pad((string) ++self::$seq, 8, '0', STR_PAD_LEFT)]);
    }

    private function kelas(): Kelas
    {
        return $this->buatKelas(['nama_kelas' => 'X LIBUR '.++self::$seq, 'tingkat' => 'X']);
    }

    private function jadwal(Kelas $kelas, User $guru, string $tanggal): Jadwal
    {
        $mapel = new MataPelajaran;
        $mapel->forceFill(['nama' => 'Mapel '.++self::$seq, 'kode' => 'L'.self::$seq, 'kelompok' => 'wajib', 'jp_per_minggu' => 2])->save();

        return Jadwal::create([
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'guru_nip' => $guru->nip,
            'hari' => Ringkasan::HARI[Carbon::parse($tanggal)->dayOfWeekIso - 1],
            // A class holds one lesson per slot, so each helper call takes the next.
            'jam_ke_mulai' => self::$seq % 8 + 1,
            'jam_ke_selesai' => self::$seq % 8 + 1,
        ]);
    }

    /** A past weekday (Mon–Sat) inside the backfill's 14-day window. */
    private function tanggalLampau(int $mundur = 3): string
    {
        $t = Carbon::today()->subDays($mundur);

        while ($t->dayOfWeekIso === 7) {
            $t = $t->subDay();
        }

        return $t->toDateString();
    }

    private function placeholder(Jadwal $jadwal, string $tanggal): Jurnal
    {
        return Jurnal::create([
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal,
            'materi' => '',
            'kehadiran_guru_status' => 'tidak_hadir',
            'kehadiran_guru_ada_tugas' => false,
            'diisi_oleh_id' => null,
            'diisi_oleh_peran' => Jurnal::PERAN_SISTEM,
        ]);
    }

    public function test_the_nightly_backfill_skips_a_hari_libur(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $libur = $this->tanggalLampau(3);
        $sekolah = $this->tanggalLampau(6);
        $jadwalLibur = $this->jadwal($kelas, $guru, $libur);
        $jadwalSekolah = $this->jadwal($kelas, $guru, $sekolah);

        HariLibur::create(['tanggal' => $libur, 'keterangan' => 'Libur nasional']);

        $this->artisan('jurnal:isi-otomatis', ['--sekarang' => true])->assertSuccessful();

        $this->assertFalse(Jurnal::where('jadwal_id', $jadwalLibur->id)->whereDate('tanggal', $libur)->exists());
        $this->assertTrue(Jurnal::where('jadwal_id', $jadwalSekolah->id)->whereDate('tanggal', $sekolah)->exists());
    }

    public function test_a_hari_libur_is_not_a_school_day(): void
    {
        $senin = Carbon::today()->startOfWeek(Carbon::MONDAY)->subWeek();
        HariLibur::create(['tanggal' => $senin->copy()->addDays(2)->toDateString(), 'keterangan' => 'Libur']);

        $hari = collect(Ringkasan::hariSekolahAntara($senin, $senin->copy()->addDays(6)))
            ->map->toDateString()
            ->all();

        $this->assertCount(5, $hari); // Mon–Sat minus the holiday
        $this->assertNotContains($senin->copy()->addDays(2)->toDateString(), $hari);
    }

    public function test_the_admin_marks_a_range_and_stale_placeholders_are_removed(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau(3);
        $kosong = $this->placeholder($this->jadwal($kelas, $guru, $tanggal), $tanggal);
        $berpresensi = $this->placeholder($this->jadwal($kelas, $guru, $tanggal), $tanggal);
        $siswa = $this->buatSiswa(['nis' => '2027'.str_pad((string) ++self::$seq, 6, '0', STR_PAD_LEFT), 'kelas_id' => $kelas->id]);
        Presensi::create(['jurnal_id' => $berpresensi->id, 'siswa_nis' => $siswa->nis, 'status' => 'hadir']);

        $selesai = Carbon::parse($tanggal)->addDays(2)->toDateString();

        $this->actingAs($this->admin())
            ->post(route('admin.hari-libur.store'), [
                'tanggal_mulai' => $tanggal,
                'tanggal_selesai' => $selesai,
                'keterangan' => 'Ujian Tengah Semester',
            ])
            ->assertRedirect(route('admin.hari-libur.index'))
            ->assertSessionHas('success');

        $this->assertSame(3, HariLibur::count());
        $this->assertModelMissing($kosong);
        $this->assertModelExists($berpresensi);
    }

    public function test_saving_an_existing_date_only_renames_it(): void
    {
        $tanggal = Carbon::today()->addDays(10)->toDateString();
        HariLibur::create(['tanggal' => $tanggal, 'keterangan' => 'Lama']);

        $this->actingAs($this->admin())
            ->post(route('admin.hari-libur.store'), ['tanggal_mulai' => $tanggal, 'keterangan' => 'Baru'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, HariLibur::count());
        $this->assertSame('Baru', HariLibur::sole()->keterangan);
    }

    public function test_a_range_is_validated(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.hari-libur.store'), [
                'tanggal_mulai' => '2026-11-10', 'tanggal_selesai' => '2026-11-01', 'keterangan' => 'Terbalik',
            ])
            ->assertSessionHasErrors('tanggal_selesai');

        $this->actingAs($admin)
            ->post(route('admin.hari-libur.store'), [
                'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-12-31', 'keterangan' => 'Setahun',
            ])
            ->assertSessionHasErrors('tanggal_selesai');

        $this->assertSame(0, HariLibur::count());
    }

    public function test_the_admin_lists_and_removes_a_hari_libur(): void
    {
        $libur = HariLibur::create(['tanggal' => Carbon::today()->addDays(5)->toDateString(), 'keterangan' => 'Maulid Nabi']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.hari-libur.index'))
            ->assertOk()
            ->assertSee('Maulid Nabi');

        $this->actingAs($admin)->delete(route('admin.hari-libur.destroy', $libur))
            ->assertRedirect(route('admin.hari-libur.index'));

        $this->assertModelMissing($libur);
    }

    public function test_only_the_admin_manages_hari_libur(): void
    {
        $this->actingAs($this->guru())->get(route('admin.hari-libur.index'))->assertForbidden();
        $this->actingAs($this->guru())
            ->post(route('admin.hari-libur.store'), ['tanggal_mulai' => '2026-11-10', 'keterangan' => 'X'])
            ->assertForbidden();

        $this->assertSame(0, HariLibur::count());
    }

    public function test_the_guru_dashboard_names_the_holiday(): void
    {
        $guru = $this->guru();
        $tanggal = $this->tanggalLampau(3);
        $this->jadwal($this->kelas(), $guru, $tanggal);
        HariLibur::create(['tanggal' => $tanggal, 'keterangan' => 'Libur Pilkada']);

        $this->actingAs($guru)->get(route('dashboard', ['tanggal' => $tanggal]))
            ->assertOk()
            ->assertSee('Libur · Libur Pilkada')
            ->assertViewHas('minggu', fn ($minggu) => collect($minggu)
                ->firstWhere(fn ($h) => $h['tanggal']->toDateString() === $tanggal)['jumlah'] === 0);
    }
}
