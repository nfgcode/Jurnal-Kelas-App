<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Presensi;
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

        $jurnal = Jurnal::create([
            'jadwal_id' => $jadwal->id,
            'tanggal' => now()->toDateString(),
            'materi' => 'Persamaan kuadrat',
            'kegiatan' => 'Diskusi kelompok dan latihan soal',
            'catatan' => null,
            'guru_id' => $jadwal->guru_id,
        ]);

        foreach ($jadwal->kelas->siswa as $i => $siswa) {
            Presensi::create([
                'jurnal_id' => $jurnal->id,
                'siswa_id' => $siswa->id,
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
            'admin users' => ['/admin/users'],
            'admin users create' => ['/admin/users/create'],
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
        $siswa = User::where('role', 'siswa')->firstOrFail();

        $urls = [
            "/kelas/{$kelas->id}",
            "/kelas/{$kelas->id}/edit",
            "/mata-pelajaran/{$mapel->id}",
            "/mata-pelajaran/{$mapel->id}/edit",
            "/jadwal/{$jadwal->id}",
            "/jadwal/{$jadwal->id}/edit",
            "/jurnal/{$jurnal->id}",
            "/jurnal/{$jurnal->id}/edit",
            "/presensi/{$jurnal->id}",
            "/presensi/create/{$jurnal->id}",
            "/admin/users/{$siswa->id}",
            "/admin/users/{$siswa->id}/edit",
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk("GET {$url} did not render");
        }
    }

    public function test_kelas_can_be_created_from_the_form_fields(): void
    {
        $guru = User::where('role', 'guru')->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/kelas', [
                'nama_kelas' => 'XII RPL 3',
                'tingkat' => 'XII',
                'jurusan' => 'RPL',
                'ruang' => 'Lab RPL 2',
                'kapasitas' => 34,
                'tahun_ajaran' => '2025/2026',
                'wali_kelas_id' => $guru->id,
            ])
            ->assertRedirect(route('kelas.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kelas', [
            'nama_kelas' => 'XII RPL 3',
            'wali_kelas_id' => $guru->id,
        ]);
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
        $mapel = MataPelajaran::first();
        $guru = User::where('role', 'guru')->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/jadwal', [
                'kelas_id' => $kelas->id,
                'mata_pelajaran_id' => $mapel->id,
                'guru_id' => $guru->id,
                'hari' => 'Jumat',
                'jam_ke_mulai' => 7,
                'jam_ke_selesai' => 8,
                'jam_mulai' => '13:00',
                'jam_selesai' => '14:30',
                'ruang' => 'R-204',
            ])
            ->assertRedirect(route('jadwal.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('jadwal', ['hari' => 'Jumat', 'kelas_id' => $kelas->id]);
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
        $jurnal = Jurnal::first();
        $siswa = $jurnal->jadwal->kelas->siswa;

        $payload = ['jurnal_id' => $jurnal->id, 'presensi' => []];

        foreach ($siswa as $i => $s) {
            $payload['presensi'][$i] = [
                'siswa_id' => $s->id,
                'status' => 'hadir',
                'keterangan' => null,
            ];
        }

        $this->actingAs($this->admin)
            ->post('/presensi', $payload)
            ->assertRedirect(route('presensi.show', $jurnal->id))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $siswa->count(),
            Presensi::where('jurnal_id', $jurnal->id)->where('status', 'hadir')->count(),
        );
    }

    public function test_presensi_form_lists_the_students_and_a_submit_button(): void
    {
        $jurnal = Jurnal::first();
        $siswa = $jurnal->jadwal->kelas->siswa->first();

        $this->actingAs($this->admin)
            ->get("/presensi/create/{$jurnal->id}")
            ->assertOk()
            ->assertSee($siswa->name)
            ->assertSee($siswa->nis)
            ->assertSee('Simpan Presensi')
            ->assertSee('presensi[0][siswa_id]', false);
    }
}
