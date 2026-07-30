<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\User;
use App\Support\Halaman;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The row-count control is driven by `?per=`, which means the page size is user
 * input on every table in the app. It must be whitelisted: an arbitrary
 * `?per=100000` would ask the database for the whole table on every request.
 */
class PaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->guru = Jadwal::firstOrFail()->guru;
    }

    public function test_the_offered_sizes_change_the_page_size(): void
    {
        foreach (Halaman::PILIHAN as $per) {
            $this->actingAs($this->guru)
                ->get("/jurnal?per={$per}")
                ->assertOk()
                ->assertViewHas('jurnals', fn ($jurnals) => $jurnals->perPage() === $per);
        }
    }

    public function test_a_size_outside_the_whitelist_falls_back_to_the_default(): void
    {
        $bawaan = Halaman::PILIHAN[0];

        foreach (['99999', '37', 'abc', '-10', '0'] as $nakal) {
            $this->actingAs($this->guru)
                ->get("/jurnal?per={$nakal}")
                ->assertOk()
                ->assertViewHas('jurnals', fn ($jurnals) => $jurnals->perPage() === $bawaan);
        }
    }

    public function test_the_default_applies_when_no_size_is_asked_for(): void
    {
        $this->actingAs($this->guru)
            ->get('/jurnal')
            ->assertOk()
            ->assertViewHas('jurnals', fn ($jurnals) => $jurnals->perPage() === Halaman::PILIHAN[0]);
    }

    /**
     * The size must not become a way around role scoping: a guru asking for the
     * largest page still only gets their own journals.
     */
    public function test_a_bigger_page_does_not_widen_what_a_guru_can_see(): void
    {
        $this->actingAs($this->guru)
            ->get('/jurnal?per='.max(Halaman::PILIHAN))
            ->assertOk()
            ->assertViewHas('jurnals', fn ($jurnals) => $jurnals
                ->every(fn ($jurnal) => $jurnal->guru_id === $this->guru->id));
    }

    public function test_the_admin_tables_take_the_size_too(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/users?per=50')
            ->assertOk()
            ->assertViewHas('users', fn ($users) => $users->perPage() === 50);
    }
}
