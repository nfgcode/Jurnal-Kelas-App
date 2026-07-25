<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSectionTest extends TestCase
{
    use RefreshDatabase;

    /** Memoized so repeated calls within one test reuse the same record. */
    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@jurnalkelas.app'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
        );
    }

    private function guru(): User
    {
        return User::firstOrCreate(
            ['email' => 'budi@jurnalkelas.app'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password'),
                'role' => 'guru',
                'nip' => '198501012010011001',
            ],
        );
    }

    private function kelas(): Kelas
    {
        return Kelas::firstOrCreate(
            ['nama_kelas' => 'X IPA 1'],
            [
                'tingkat' => 'X',
                'jurusan' => 'IPA',
                'tahun_ajaran' => '2024/2025',
            ],
        );
    }

    public function test_landing_page_is_publicly_accessible(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Jurnal Kelas')
            ->assertSee('Catat jurnal mengajar')
            ->assertSee('Masuk ke akun Anda');
    }

    public function test_admin_can_view_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Dashboard Admin')
            ->assertSee('Ringkasan seluruh data sekolah');
    }

    public function test_generic_dashboard_redirects_admin_to_admin_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_guru_cannot_access_admin_section(): void
    {
        $guru = $this->guru();

        $this->actingAs($guru)->get('/admin')->assertForbidden();
        $this->actingAs($guru)->get('/admin/users')->assertForbidden();
        $this->actingAs($guru)->get('/admin/laporan/jurnal')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_admin_can_list_and_filter_users(): void
    {
        $this->guru();

        User::create([
            'name' => 'Ahmad Fauzi',
            'email' => 'ahmad@siswa.app',
            'password' => Hash::make('password'),
            'role' => 'siswa',
            'nis' => '20240001',
            'kelas_id' => $this->kelas()->id,
        ]);

        // Unfiltered: both users are listed.
        $this->actingAs($this->admin())
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Ahmad Fauzi');

        // Filtered by role: only the guru survives.
        $this->actingAs($this->admin())
            ->get('/admin/users?role=guru')
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertDontSee('Ahmad Fauzi');

        // Filtered by search term.
        $this->actingAs($this->admin())
            ->get('/admin/users?q=20240001')
            ->assertOk()
            ->assertSee('Ahmad Fauzi')
            ->assertDontSee('Budi Santoso');
    }

    public function test_admin_can_create_a_siswa(): void
    {
        $kelas = $this->kelas();

        $this->actingAs($this->admin())
            ->post('/admin/users', [
                'name' => 'Ahmad Fauzi',
                'email' => 'ahmad@siswa.app',
                'password' => 'rahasia123',
                'role' => 'siswa',
                'status' => 'aktif',
                'nis' => '20240001',
                'kelas_id' => $kelas->id,
                'nip' => '999',            // must be discarded for a siswa
            ])
            ->assertRedirect(route('admin.users.index'));

        $siswa = User::where('email', 'ahmad@siswa.app')->first();

        $this->assertNotNull($siswa);
        $this->assertSame('siswa', $siswa->role);
        $this->assertSame('20240001', $siswa->nis);
        $this->assertSame($kelas->id, $siswa->kelas_id);
        $this->assertNull($siswa->nip, 'nip should be cleared for a siswa');
        $this->assertTrue(Hash::check('rahasia123', $siswa->password));
    }

    public function test_creating_a_siswa_requires_nis_and_kelas(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', [
                'name' => 'Tanpa Kelas',
                'email' => 'tanpa@siswa.app',
                'password' => 'rahasia123',
                'role' => 'siswa',
            ])
            ->assertSessionHasErrors(['nis', 'kelas_id']);
    }

    public function test_admin_can_update_user_without_changing_password(): void
    {
        $guru = $this->guru();
        $originalPassword = $guru->password;

        $this->actingAs($this->admin())
            ->put("/admin/users/{$guru->id}", [
                'name' => 'Budi Santoso, S.Pd.',
                'email' => $guru->email,
                'password' => '',
                'role' => 'guru',
                'status' => 'aktif',
                'nip' => '198501012010011001',
            ])
            ->assertRedirect(route('admin.users.index'));

        $guru->refresh();

        $this->assertSame('Budi Santoso, S.Pd.', $guru->name);
        $this->assertSame($originalPassword, $guru->password, 'password should be untouched when left blank');
    }

    public function test_admin_cannot_demote_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/admin/users/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'password' => '',
                'role' => 'guru',
                'status' => 'aktif',
                'nip' => '123',
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame('admin', $admin->refresh()->role);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}");

        $this->assertModelExists($admin);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $guru = $this->guru();

        $this->actingAs($this->admin())
            ->delete("/admin/users/{$guru->id}")
            ->assertRedirect(route('admin.users.index'));

        $this->assertModelMissing($guru);
    }

    public function test_admin_can_view_user_detail(): void
    {
        $guru = $this->guru();

        $this->actingAs($this->admin())
            ->get("/admin/users/{$guru->id}")
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Jadwal Mengajar');
    }

    public function test_admin_can_open_reports(): void
    {
        $kelas = $this->kelas();
        $guru = $this->guru();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get("/admin/laporan/jurnal?kelas_id={$kelas->id}&guru_id={$guru->id}")
            ->assertOk()
            ->assertSee('Rekap Jurnal Mengajar');

        $this->actingAs($admin)
            ->get("/admin/laporan/presensi?kelas_id={$kelas->id}")
            ->assertOk()
            ->assertSee('Rekap Presensi per Pertemuan');
    }

    public function test_admin_can_assign_and_release_wali_from_the_user_form(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();

        $payload = fn (array $extra = []): array => array_merge([
            'name' => $guru->name,
            'email' => $guru->email,
            'password' => '',
            'role' => 'guru',
            'status' => 'aktif',
            'nip' => $guru->nip,
        ], $extra);

        // Checking the class on the user form makes this guru its wali.
        $this->actingAs($this->admin())
            ->put("/admin/users/{$guru->id}", $payload(['kelas_wali' => [$kelas->id]]))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame($guru->id, $kelas->refresh()->wali_kelas_id);

        // Submitting with none selected releases the assignment.
        $this->actingAs($this->admin())
            ->put("/admin/users/{$guru->id}", $payload());

        $this->assertNull($kelas->refresh()->wali_kelas_id);
    }

    public function test_changing_a_wali_guru_to_another_role_releases_the_class(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $kelas->update(['wali_kelas_id' => $guru->id]);

        $this->actingAs($this->admin())
            ->put("/admin/users/{$guru->id}", [
                'name' => $guru->name,
                'email' => $guru->email,
                'password' => '',
                'role' => 'siswa',
                'status' => 'aktif',
                'nis' => '20990001',
                'kelas_id' => $kelas->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('siswa', $guru->refresh()->role);
        $this->assertNull($kelas->refresh()->wali_kelas_id, 'a non-guru must not remain a class wali');
    }
}
