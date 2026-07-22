<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Administrator',
            'email' => 'admin@jurnalkelas.app',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
    }

    private function guru(): User
    {
        return User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@jurnalkelas.app',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'nip' => '198501012010011001',
        ]);
    }

    private function siswa(): User
    {
        return User::create([
            'name' => 'Ahmad Fauzi',
            'email' => 'ahmad@siswa.app',
            'password' => Hash::make('password'),
            'role' => 'siswa',
            'nis' => '20240001',
        ]);
    }

    public function test_login_form_asks_for_nip_or_nis(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Gunakan NIP untuk guru dan admin, atau NIS untuk siswa.')
            ->assertSee('name="user"', false)
            ->assertDontSee('name="email"', false);
    }

    public function test_guru_signs_in_with_nip(): void
    {
        $guru = $this->guru();

        $this->post('/login', [
            'user' => '198501012010011001',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($guru);
    }

    public function test_siswa_signs_in_with_nis(): void
    {
        $siswa = $this->siswa();

        $this->post('/login', [
            'user' => '20240001',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($siswa);
    }

    public function test_admin_falls_back_to_email(): void
    {
        $admin = $this->admin();

        $this->post('/login', [
            'user' => 'admin@jurnalkelas.app',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->guru();

        $this->post('/login', [
            'user' => '198501012010011001',
            'password' => 'salah',
        ])->assertSessionHasErrors('user');

        $this->assertGuest();
    }

    public function test_unknown_identifier_is_rejected(): void
    {
        $this->guru();

        $this->post('/login', [
            'user' => '000000000000000000',
            'password' => 'password',
        ])->assertSessionHasErrors('user');

        $this->assertGuest();
    }

    /**
     * A guru has a NULL nis; submitting an empty identifier must not match it.
     */
    public function test_blank_identifier_never_matches_a_null_column(): void
    {
        $this->guru();

        $this->post('/login', [
            'user' => '',
            'password' => 'password',
        ])->assertSessionHasErrors('user');

        $this->assertGuest();
    }

    public function test_a_siswa_nis_cannot_be_used_to_log_in_as_a_guru(): void
    {
        $this->guru();
        $siswa = $this->siswa();

        $this->post('/login', [
            'user' => '20240001',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($siswa);
        $this->assertSame('siswa', Auth::user()->role);
    }

    public function test_nip_must_be_unique_across_users(): void
    {
        $this->guru();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Guru Kembar',
                'email' => 'kembar@jurnalkelas.app',
                'password' => 'rahasia123',
                'role' => 'guru',
                'status' => 'aktif',
                'nip' => '198501012010011001', // already taken
            ])
            ->assertSessionHasErrors('nip');
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs($this->guru())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
