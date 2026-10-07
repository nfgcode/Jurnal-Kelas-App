<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Data Jurusan screens (Oct 2026) were built frontend-first: the
 * controller is still to be written (docs/backend-jurusan.md). These tests pin
 * the data contract the views expect by serving them from stub routes that
 * hand over exactly the documented variables.
 *
 * Once the real `admin.jurusan.*` routes exist they skip themselves — replace
 * them then with tests against the real controller.
 */
class JurusanTampilanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (Route::has('admin.jurusan.index')) {
            $this->markTestSkipped('Route jurusan sudah dibuat backend — ganti dengan test controller asli.');
        }

        $this->seed(DemoSeeder::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    /** The documented contract, served from closures in place of the controller. */
    private function pasangRouteTiruan(): void
    {
        $bidangList = fn () => Jurusan::whereNotNull('bidang')->where('bidang', '!=', '')
            ->distinct()->orderBy('bidang')->pluck('bidang');

        Route::middleware(['web', 'auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () use ($bidangList) {
            Route::get('/jurusan', function () use ($bidangList) {
                $filters = request()->only(['q', 'bidang', 'status']);

                return view('admin.jurusan.index', [
                    'jurusan' => Jurusan::withCount(['kelas', 'siswa'])
                        ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari))
                        ->orderBy('kode')->paginate(15)->withQueryString(),
                    'filters' => $filters,
                    'bidangList' => $bidangList(),
                    'statistik' => [
                        'total' => Jurusan::count(),
                        'aktif' => Jurusan::aktif()->count(),
                        'bidang' => $bidangList()->count(),
                        'kelas' => \App\Models\Kelas::whereNotNull('jurusan_kode')->count(),
                        'siswa' => Siswa::whereNotNull('jurusan_kode')->count(),
                    ],
                ]);
            })->name('jurusan.index');

            Route::get('/jurusan/create', fn () => view('admin.jurusan.create', ['bidangList' => $bidangList()]))
                ->name('jurusan.create');

            Route::get('/jurusan/{jurusan}/edit', fn (Jurusan $jurusan) => view('admin.jurusan.edit', [
                'jurusan' => $jurusan->loadCount(['kelas', 'siswa']),
                'bidangList' => $bidangList(),
            ]))->name('jurusan.edit');

            Route::post('/jurusan', fn () => abort(501))->name('jurusan.store');
            Route::put('/jurusan/{jurusan}', fn () => abort(501))->name('jurusan.update');
            Route::delete('/jurusan/{jurusan}', fn () => abort(501))->name('jurusan.destroy');
        });

        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();
    }

    public function test_the_sidebar_hides_jurusan_until_the_routes_exist(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('/admin/jurusan', false);

        $this->pasangRouteTiruan();

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.jurusan.index'), false);
    }

    public function test_the_index_lists_each_jurusan_with_its_usage(): void
    {
        $this->pasangRouteTiruan();
        $tkj = Jurusan::withCount(['kelas', 'siswa'])->findOrFail('TKJ');

        $this->actingAs($this->admin)->get(route('admin.jurusan.index'))
            ->assertOk()
            ->assertSee('Data Jurusan')
            ->assertSee('+ Tambah Jurusan')
            ->assertSee($tkj->nama)
            ->assertSee($tkj->kelas_count.' kelas')
            ->assertSee($tkj->siswa_count.' siswa')
            ->assertSee(route('admin.jurusan.edit', $tkj), false);

        $this->actingAs($this->admin)->get(route('admin.jurusan.index', ['q' => 'tidak-ada-jurusan-ini']))
            ->assertOk()
            ->assertSee('Tidak ada jurusan yang cocok dengan filter.');
    }

    public function test_the_create_form_carries_every_column(): void
    {
        $this->pasangRouteTiruan();

        $this->actingAs($this->admin)->get(route('admin.jurusan.create'))
            ->assertOk()
            ->assertSee('action="'.route('admin.jurusan.store').'"', false)
            ->assertSee('name="kode"', false)
            ->assertSee('name="nama"', false)
            ->assertSee('name="bidang"', false)
            ->assertSee('name="deskripsi"', false)
            // A hidden 0 before the checkbox, so an unticked box still posts.
            ->assertSee('<input type="hidden" name="aktif" value="0">', false)
            ->assertSee('<option value="Teknologi Informasi">', false);
    }

    public function test_a_jurusan_in_use_cannot_be_deleted_from_the_form(): void
    {
        $this->pasangRouteTiruan();

        $this->actingAs($this->admin)->get(route('admin.jurusan.edit', 'TKJ'))
            ->assertOk()
            ->assertSee('Ubah Jurusan TKJ')
            ->assertSee('value="TKJ"', false)
            ->assertSee('Masih dipakai')
            ->assertDontSee('id="hapusJurusan"', false);
    }

    public function test_an_unused_jurusan_offers_a_confirmed_delete(): void
    {
        $this->pasangRouteTiruan();
        Jurusan::create(['kode' => 'DKV', 'nama' => 'Desain Komunikasi Visual', 'bidang' => 'Seni dan Industri Kreatif', 'aktif' => false]);

        $this->actingAs($this->admin)->get(route('admin.jurusan.edit', 'DKV'))
            ->assertOk()
            ->assertSee('id="hapusJurusan"', false)
            ->assertSee('data-konfirmasi="Hapus jurusan DKV', false)
            ->assertSee('action="'.route('admin.jurusan.destroy', 'DKV').'"', false)
            ->assertDontSee('Masih dipakai');
    }

    /** The new siswa.jurusan_kode column: filled from the class, shown on Data Siswa. */
    public function test_students_carry_their_jurusan(): void
    {
        $this->assertSame(0, Siswa::whereNull('jurusan_kode')->whereHas('kelas', fn ($k) => $k->whereNotNull('jurusan_kode'))->count());

        $siswa = Siswa::with('kelas')->whereNotNull('jurusan_kode')->firstOrFail();
        $this->assertSame($siswa->kelas->jurusan_kode, $siswa->jurusan_kode);

        $this->actingAs($this->admin)->get(route('admin.siswa.index'))
            ->assertOk()
            ->assertSee('<th>Jurusan</th>', false);

        $this->actingAs($this->admin)->get(route('admin.siswa.show', $siswa))
            ->assertOk()
            ->assertSee($siswa->jurusan->label());
    }
}
