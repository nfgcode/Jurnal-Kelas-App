<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AksesWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_student_cannot_login_but_bad_password_does_not_reveal_access(): void
    {
        $user = $this->buatSiswa();
        $this->post('/login', ['user' => $user->nis, 'password' => 'salah'])
            ->assertSessionHasErrors(['user' => 'NIP/NIS atau password salah.']);
        $this->post('/login', ['user' => $user->nis, 'password' => 'password'])
            ->assertSessionHasErrors(['user' => 'Akses web siswa hanya tersedia untuk ketua kelas. Hubungi admin sekolah.']);
        $this->assertGuest();
    }

    public function test_old_session_and_direct_urls_are_denied_but_logout_is_available(): void
    {
        $user = $this->buatSiswa();
        foreach (['/dashboard', '/jurnal', '/jurnal/create', '/presensi', '/jadwal', '/admin/jurusan'] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
        $this->post('/jurnal', [])->assertForbidden();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_revoking_chairmanship_denies_even_a_user_with_cached_relations(): void
    {
        $kelas = $this->buatKelas();
        $user = $this->buatSiswa(['kelas_id' => $kelas->id]);
        $kelas->update(['ketua_nis' => $user->nis]);
        $this->assertTrue($user->isKetuaKelas());
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $kelas->update(['ketua_nis' => null]);
        $this->get('/dashboard')->assertForbidden();
    }

    public function test_admin_and_teacher_remain_allowed_and_api_student_access_is_unchanged(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertOk();
        $this->actingAs($this->buatGuru())->get('/dashboard')->assertOk();
        $this->actingAs($this->buatSiswa())->getJson('/api/jurnal')->assertOk();
    }
}
