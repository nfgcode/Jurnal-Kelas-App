<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Akun Pengguna shows each account as Online or Offline (Oct 2026). Online
 * means a request in the last {@see User::MENIT_ONLINE} minutes and no sign-out
 * since; the CatatAktivitas middleware keeps `last_active_at` current, at most
 * once a minute.
 */
class StatusSesiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);

        // Start every account with no session at all.
        User::query()->update(['last_active_at' => null, 'last_logout_at' => null]);

        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    private function guru(int $urutan = 0): User
    {
        return User::where('role', 'guru')->orderBy('id')->skip($urutan)->firstOrFail();
    }

    public function test_a_request_records_activity(): void
    {
        $this->freezeTime();
        $guru = $this->guru();

        $this->assertFalse($guru->isOnline());

        $this->actingAs($guru)->get('/dashboard')->assertOk();

        $this->assertSame(now()->toDateTimeString(), $guru->fresh()->last_active_at->toDateTimeString());
        $this->assertTrue($guru->fresh()->isOnline());
    }

    public function test_activity_is_written_at_most_once_a_minute(): void
    {
        $this->freezeTime();
        $guru = $this->guru();

        $barusan = now()->subSeconds(30);
        $guru->forceFill(['last_active_at' => $barusan])->saveQuietly();
        $this->actingAs($guru)->get('/dashboard')->assertOk();
        $this->assertSame($barusan->toDateTimeString(), $guru->fresh()->last_active_at->toDateTimeString());

        $guru->forceFill(['last_active_at' => now()->subMinutes(2)])->saveQuietly();
        $this->actingAs($guru)->get('/dashboard')->assertOk();
        $this->assertSame(now()->toDateTimeString(), $guru->fresh()->last_active_at->toDateTimeString());
    }

    public function test_online_means_active_in_the_last_five_minutes_without_signing_out(): void
    {
        $this->freezeTime();

        $aktif = $this->guru(0);
        $aktif->forceFill(['last_active_at' => now()->subMinutes(2)])->saveQuietly();

        $lewat = $this->guru(1);
        $lewat->forceFill(['last_active_at' => now()->subMinutes(User::MENIT_ONLINE + 1)])->saveQuietly();

        $keluar = $this->guru(2);
        $keluar->forceFill([
            'last_active_at' => now()->subMinute(),
            'last_logout_at' => now()->subSeconds(30),
        ])->saveQuietly();

        $this->assertTrue($aktif->isOnline());
        $this->assertFalse($lewat->isOnline());
        $this->assertFalse($keluar->isOnline());

        // The scopes the filter and the counter use agree with the model.
        $this->assertSame([$aktif->id], User::online()->pluck('id')->all());
        $this->assertSame(User::count() - 1, User::offline()->count());
    }

    public function test_signing_out_turns_the_account_offline_until_the_next_visit(): void
    {
        $this->freezeTime();
        $guru = $this->guru();

        $this->actingAs($guru)->get('/dashboard')->assertOk();
        $this->assertTrue($guru->fresh()->isOnline());

        $this->actingAs($guru)->post('/logout')->assertRedirect();
        $this->assertNotNull($guru->fresh()->last_logout_at);
        $this->assertFalse($guru->fresh()->isOnline());

        // Back a few minutes later: online again.
        $this->travel(2)->minutes();
        $this->actingAs($guru->fresh())->get('/dashboard')->assertOk();
        $this->assertTrue($guru->fresh()->isOnline());
    }

    public function test_the_admin_sees_and_filters_by_session_status(): void
    {
        $this->freezeTime();

        $online = $this->guru(0);
        $online->forceFill(['last_active_at' => now()->subMinute()])->saveQuietly();

        $offline = $this->guru(1);
        $offline->forceFill(['last_active_at' => now()->subHour()])->saveQuietly();

        $this->actingAs($this->admin)->get('/admin/akun?sesi=online')
            ->assertOk()
            ->assertSee('Sedang Online')
            ->assertSee('sesi--online', false)
            ->assertSee($online->username)
            ->assertDontSee($offline->username);

        $this->actingAs($this->admin)->get('/admin/akun?sesi=offline')
            ->assertOk()
            ->assertSee('sesi--offline', false)
            ->assertSee($offline->username)
            ->assertDontSee($online->username);

        $this->actingAs($this->admin)->get("/admin/akun/{$online->id}")
            ->assertOk()
            ->assertSee('sesi--online', false);

        $this->actingAs($this->admin)->get('/admin/akun?sesi=entah')
            ->assertSessionHasErrors('sesi');
    }

    public function test_session_status_is_admin_only(): void
    {
        $this->actingAs($this->guru())->get('/admin/akun?sesi=online')->assertForbidden();
    }
}
