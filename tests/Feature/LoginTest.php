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
            'username' => 'admin',
            'nama' => 'Administrator',
            'email' => 'admin@jurnalkelas.app',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
    }

    /**
     * A teacher is two rows: the person, then the login that points at them.
     * Written out here rather than via the helper so the NIP stays the literal
     * value the sign-in tests below type in.
     */
    private function guru(): User
    {
        return $this->buatGuru(
            ['nip' => '198501012010011001', 'nama' => 'Budi Santoso'],
            ['username' => 'budi.santoso', 'email' => 'budi@jurnalkelas.app', 'password' => Hash::make('password')],
        );
    }

    private function siswa(): User
    {
        return $this->buatSiswa(
            ['nis' => '20240001', 'nama' => 'Ahmad Fauzi'],
            ['username' => 'ahmad.fauzi', 'email' => 'ahmad@siswa.app', 'password' => Hash::make('password')],
        );
    }

    public function test_login_form_asks_for_nip_or_nis(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk dengan username, email, NIP (guru), atau NIS (siswa).')
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

    public function test_login_form_has_no_role_picker(): void
    {
        // The Figma login has one identifier field; the server works out the role.
        $this->get('/login')
            ->assertOk()
            ->assertSee('NIP / NIS / Username')
            ->assertDontSee('name="role"', false)
            // Nor does the public page announce the school's head counts.
            ->assertDontSee('Siswa aktif')
            ->assertDontSee('Jurnal tercatat');
    }

    /**
     * Without a role picker the same digits can match two accounts. Each person
     * still reaches their own account, chosen by the password they typed.
     */
    public function test_a_shared_identifier_signs_in_whoever_the_password_belongs_to(): void
    {
        $guru = $this->buatGuru(
            ['nip' => '20240001', 'nama' => 'Guru Kembar'],
            ['username' => 'guru.kembar', 'email' => 'kembar@jurnalkelas.app', 'password' => Hash::make('sandi-guru')],
        );
        $siswa = $this->siswa();   // NIS 20240001, password "password"

        $this->post('/login', ['user' => '20240001', 'password' => 'sandi-guru'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($guru);

        Auth::logout();

        $this->post('/login', ['user' => '20240001', 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($siswa);
    }

    public function test_an_inactive_account_is_only_named_to_whoever_knows_its_password(): void
    {
        $guru = $this->guru();
        $guru->update(['status' => 'nonaktif']);

        $this->post('/login', ['user' => '198501012010011001', 'password' => 'salah'])
            ->assertSessionHasErrors(['user' => 'NIP/NIS atau password salah.']);

        $this->post('/login', ['user' => '198501012010011001', 'password' => 'password'])
            ->assertSessionHasErrors(['user' => 'Akun ini nonaktif. Hubungi admin sekolah.']);

        $this->assertGuest();
    }

    public function test_nip_must_be_unique_across_guru(): void
    {
        $this->guru();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/guru', [
                'nip' => '198501012010011001', // already taken
                'nama' => 'Guru Kembar',
                'status' => 'aktif',
                'username' => 'guru.kembar',
                'email' => 'kembar@jurnalkelas.app',
                'password' => 'rahasia123',
            ])
            ->assertSessionHasErrors('nip');
    }

    public function test_username_is_also_a_login_identifier(): void
    {
        $guru = $this->guru();

        $this->post('/login', [
            'user' => 'budi.santoso',
            'password' => 'password',
            'role' => 'guru',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($guru);
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs($this->guru())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
