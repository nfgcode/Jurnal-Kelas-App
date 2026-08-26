<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PresensiHarian;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every page in the app, rendered against real seeded data.
 *
 * These views were written against variable and column names that never
 * existed (`$jadwal` vs `$jadwals`, `kelas->nama` vs `nama_kelas`,
 * `siswa->nama` vs `name`), which only blew up at request time. Rendering
 * each route here turns that class of bug into a failing test.
 */
class CrudPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();

        // DemoSeeder skips roughly one meeting in eight, so pin a jurnal of
        // our own to today's first slot rather than hunting for one.
        $jadwal = Jadwal::first();

        // DemoSeeder also journals *today*, so whenever the suite runs on the
        // weekday this slot is taught the seeded journal covers the very same
        // meeting. Clear it first so the pinned journal below is its only one.
        Jurnal::where('jadwal_id', $jadwal->id)
            ->whereDate('tanggal', now()->toDateString())
            ->delete();

        $jurnal = Jurnal::create([
            'jadwal_id' => $jadwal->id,
            'tanggal' => now()->toDateString(),
            'materi' => 'Persamaan kuadrat',
            'kegiatan' => 'Diskusi kelompok dan latihan soal',
            'catatan' => null,
            'guru_nip' => $jadwal->guru_nip,
        ]);

        // The class's roll call for that day — one row per student, which is
        // what attendance is now (see PresensiHarian).
        foreach ($jadwal->kelas->siswa as $i => $siswa) {
            PresensiHarian::updateOrCreate([
                'kelas_id' => $jadwal->kelas_id,
                'tanggal' => $jurnal->tanggal->toDateString(),
                'siswa_nis' => $siswa->nis,
            ], [
                'status' => ['hadir', 'sakit', 'izin', 'alpa'][$i % 4],
            ]);
        }
    }

    public static function pageProvider(): array
    {
        return [
            'dashboard admin' => ['/admin'],
            'kelas index' => ['/kelas'],
            'kelas create' => ['/kelas/create'],
            'mata pelajaran index' => ['/mata-pelajaran'],
            'mata pelajaran create' => ['/mata-pelajaran/create'],
            'jadwal index' => ['/jadwal'],
            'jadwal create' => ['/jadwal/create'],
            'jurnal index' => ['/jurnal'],
            'jurnal create' => ['/jurnal/create'],
            'presensi index' => ['/presensi'],
            'admin akun' => ['/admin/akun'],
            'admin akun create' => ['/admin/akun/create'],
            'admin guru' => ['/admin/guru'],
            'admin guru create' => ['/admin/guru/create'],
            'admin siswa' => ['/admin/siswa'],
            'admin siswa create' => ['/admin/siswa/create'],
            'ruangan index' => ['/ruangan'],
            'ruangan create' => ['/ruangan/create'],
            'laporan jurnal' => ['/admin/laporan/jurnal'],
            'laporan presensi' => ['/admin/laporan/presensi'],
        ];
    }

    #[DataProvider('pageProvider')]
    public function test_static_page_renders(string $url): void
    {
        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    public function test_record_pages_render(): void
    {
        $kelas = Kelas::first();
        $mapel = MataPelajaran::first();
        $jadwal = Jadwal::first();
        $jurnal = Jurnal::first();
        $siswa = Siswa::firstOrFail();

        $urls = [
            "/kelas/{$kelas->id}",
            "/kelas/{$kelas->id}/edit",
            "/mata-pelajaran/{$mapel->id}",
            "/mata-pelajaran/{$mapel->id}/edit",
            "/jadwal/{$jadwal->id}",
            "/jadwal/{$jadwal->id}/edit",
            "/jurnal/{$jurnal->public_id}",
            "/jurnal/{$jurnal->public_id}/edit",
            route('presensi-harian.show', [$kelas, 'tanggal' => $jurnal->tanggal->toDateString()]),
            route('presensi-harian.edit', [$kelas, 'tanggal' => $jurnal->tanggal->toDateString()]),
            "/admin/siswa/{$siswa->nis}",
            "/admin/siswa/{$siswa->nis}/edit",
            "/admin/guru/{$jadwal->guru_nip}",
            '/ruangan',
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk("GET {$url} did not render");
        }
    }

    public function test_kelas_can_be_created_from_the_form_fields(): void
    {
        $guru = Guru::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/kelas', [
                'nama_kelas' => 'XII TKJ 3',
                'tingkat' => 'XII',
                'jurusan_kode' => Jurusan::value('kode'),
                'paralel' => 3,
                'kapasitas' => 34,
                'tahun_ajaran_kode' => TahunAjaran::value('kode'),
                'wali_kelas_nip' => $guru->nip,
            ])
            ->assertRedirect(route('kelas.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kelas', [
            'nama_kelas' => 'XII TKJ 3',
            'wali_kelas_nip' => $guru->nip,
        ]);
    }

    /**
     * A school year has one "XII TKJ 3". `kelas_rombel_unique` refuses a second,
     * and this pins the validation that reports it as a message rather than
     * letting the constraint surface as a 500.
     */
    public function test_a_duplicate_rombel_is_refused(): void
    {
        $payload = [
            'nama_kelas' => 'XII TKJ 3',
            'tingkat' => 'XII',
            'jurusan_kode' => Jurusan::value('kode'),
            'paralel' => 3,
            'kapasitas' => 34,
            'tahun_ajaran_kode' => TahunAjaran::value('kode'),
        ];

        $this->actingAs($this->admin)->post('/kelas', $payload)->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post('/kelas', ['nama_kelas' => 'Nama lain'] + $payload)
            ->assertSessionHasErrors('paralel');

        $this->assertSame(1, Kelas::where('paralel', 3)->where('tingkat', 'XII')->count());
    }

    public function test_kelas_index_shows_the_class_name(): void
    {
        $this->actingAs($this->admin)
            ->get('/kelas')
            ->assertOk()
            ->assertSee('X IPA 1');
    }

    public function test_jadwal_can_be_created(): void
    {
        $kelas = Kelas::first();
        // A teacher may only be timetabled for a subject they are recorded as
        // teaching, so the pair is taken from the pivot rather than invented.
        $guru = Guru::has('mataPelajaran')->firstOrFail();
        $mapel = $guru->mataPelajaran->first();

        // JP 11 is outside the seeded blocks, so the slot is free for everyone.
        $this->actingAs($this->admin)
            ->post('/jadwal', [
                'kelas_id' => $kelas->id,
                'mata_pelajaran_id' => $mapel->id,
                'guru_nip' => $guru->nip,
                'hari' => 'Jumat',
                'jam_ke_mulai' => 11,
                'jam_ke_selesai' => 12,
            ])
            ->assertRedirect(route('jadwal.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('jadwal', ['hari' => 'Jumat', 'kelas_id' => $kelas->id, 'jam_ke_mulai' => 11]);
    }

    public function test_jadwal_create_form_lists_the_dropdown_options(): void
    {
        $this->actingAs($this->admin)
            ->get('/jadwal/create')
            ->assertOk()
            ->assertSee('X IPA 1')        // kelasList
            ->assertSee('Matematika')     // mataPelajaranList
            ->assertSee('Budi Santoso');  // gurus
    }

    public function test_presensi_can_be_submitted_with_the_form_field_names(): void
    {
        $kelas = Kelas::first();
        $siswa = $kelas->siswa;
        $tanggal = now()->toDateString();

        $payload = ['tanggal' => $tanggal, 'presensi' => []];

        foreach ($siswa as $i => $s) {
            $payload['presensi'][$i] = [
                'siswa_nis' => $s->nis,
                'status' => 'hadir',
                'keterangan' => null,
            ];
        }

        $this->actingAs($this->admin)
            ->post(route('presensi-harian.store', $kelas), $payload)
            ->assertRedirect(route('presensi-harian.show', [$kelas, 'tanggal' => $tanggal]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $siswa->count(),
            PresensiHarian::where('kelas_id', $kelas->id)
                ->whereDate('tanggal', $tanggal)
                ->where('status', 'hadir')
                ->count(),
        );
    }

    public function test_presensi_form_lists_the_students_and_a_submit_button(): void
    {
        $kelas = Kelas::first();
        $siswa = $kelas->siswa->first();

        $this->actingAs($this->admin)
            ->get(route('presensi-harian.edit', $kelas))
            ->assertOk()
            ->assertSee($siswa->nama)
            ->assertSee($siswa->nis)
            ->assertSee('Simpan Presensi')
            ->assertSee('presensi[0][siswa_nis]', false);
    }

    /**
     * The two exports a guru actually downloads. Both are real .xlsx, both are
     * refused to a student.
     */
    public function test_the_guru_attendance_export_downloads_for_both_modes(): void
    {
        // The export is downloaded by a signed-in teacher, so this is the
        // account, not the person register row.
        $guru = User::where('role', 'guru')->firstOrFail();
        $xlsx = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        $this->actingAs($guru)
            ->get(route('presensi.ekspor', ['mode' => 'harian', 'tanggal' => now()->toDateString()]))
            ->assertOk()
            ->assertDownload()
            ->assertHeader('content-type', $xlsx);

        $this->actingAs($guru)
            ->get(route('presensi.ekspor', ['mode' => 'bulanan', 'bulan' => now()->format('Y-m')]))
            ->assertOk()
            ->assertDownload()
            ->assertHeader('content-type', $xlsx);

        $siswa = User::where('role', 'siswa')->firstOrFail();
        $this->actingAs($siswa)->get(route('presensi.ekspor'))->assertForbidden();
    }
}
