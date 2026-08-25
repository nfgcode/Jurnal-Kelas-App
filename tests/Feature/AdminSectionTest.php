<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin section, now that people and accounts are managed apart.
 *
 * /admin/guru and /admin/siswa hold the school's records; /admin/akun holds
 * only what someone signs in with. The split is the thing under test as much
 * as any individual screen: creating a teacher must produce both rows, deleting
 * an account must leave the person, and neither page may quietly do the other's
 * job.
 */
class AdminSectionTest extends TestCase
{
    use RefreshDatabase;

    /** Memoized so repeated calls within one test reuse the same record. */
    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@jurnalkelas.app'],
            [
                'username' => 'admin',
                'nama' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
        );
    }

    /** A teacher and their login, as a pair. Returns the account. */
    private function guru(): User
    {
        $existing = User::where('email', 'budi@jurnalkelas.app')->first();

        return $existing ?? $this->buatGuru(
            ['nip' => '198501012010011001', 'nama' => 'Budi Santoso'],
            ['username' => 'budi.santoso', 'email' => 'budi@jurnalkelas.app'],
        );
    }

    private function kelas(): Kelas
    {
        return Kelas::where('nama_kelas', 'X IPA 1')->first()
            ?? $this->buatKelas(['nama_kelas' => 'X IPA 1', 'tingkat' => 'X', 'jurusan_kode' => 'IPA']);
    }

    private function mapel(): MataPelajaran
    {
        return MataPelajaran::firstOrCreate(
            ['kode' => 'MTK'],
            ['nama' => 'Matematika', 'kelompok' => 'wajib', 'jp_per_minggu' => 4],
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

        foreach (['/admin', '/admin/akun', '/admin/guru', '/admin/siswa', '/admin/laporan/jurnal'] as $url) {
            $this->actingAs($guru)->get($url)->assertForbidden("GET {$url} should be admin-only");
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    // ---- Data Guru --------------------------------------------------------

    public function test_creating_a_guru_creates_the_person_and_the_account_together(): void
    {
        $mapel = $this->mapel();

        $this->actingAs($this->admin())
            ->post('/admin/guru', [
                'nip' => '198802022012011002',
                'nama' => 'Siti Nurhaliza',
                'jenis_kelamin' => 'P',
                'status' => 'aktif',
                'username' => 'siti.n',
                'email' => 'siti@jurnalkelas.app',
                'password' => 'rahasia123',
                'mata_pelajaran_id' => [$mapel->id],
                'mapel_utama' => $mapel->id,
            ])
            ->assertRedirect(route('admin.guru.index'))
            ->assertSessionHasNoErrors();

        $guru = Guru::find('198802022012011002');
        $this->assertNotNull($guru, 'the person row must exist');
        $this->assertSame('Siti Nurhaliza', $guru->nama);
        $this->assertTrue($guru->mataPelajaran->contains('id', $mapel->id));

        $akun = $guru->akun;
        $this->assertNotNull($akun, 'the login must be created alongside the person');
        $this->assertSame('siti.n', $akun->username);
        // The accessor resolves the name through the person row; the column
        // itself stays empty, which is what "one source of truth" means here.
        $this->assertNull($akun->getRawOriginal('nama'), 'the name lives on the person row, not on the account');
        $this->assertSame('Siti Nurhaliza', $akun->nama);
        $this->assertTrue(Hash::check('rahasia123', $akun->password));
    }

    public function test_a_duplicate_nip_is_refused_and_leaves_no_orphan_account(): void
    {
        $this->guru();
        $this->admin(); // created first, so it is not mistaken for an orphan below
        $sebelum = User::count();

        $this->actingAs($this->admin())
            ->post('/admin/guru', [
                'nip' => '198501012010011001', // already taken
                'nama' => 'Guru Lain',
                'status' => 'aktif',
                'username' => 'guru.lain',
                'email' => 'lain@jurnalkelas.app',
                'password' => 'rahasia123',
            ])
            ->assertSessionHasErrors('nip');

        $this->assertSame($sebelum, User::count(), 'a refused registration must not leave a login behind');
    }

    public function test_a_duplicate_guru_name_is_refused_unless_explicitly_allowed(): void
    {
        $this->guru();

        $kirim = fn (array $extra = []) => $this->actingAs($this->admin())->post('/admin/guru', array_merge([
            'nip' => '199003032015011003',
            'nama' => 'Budi Santoso', // same name as the existing teacher
            'status' => 'aktif',
            'username' => 'budi.dua',
            'email' => 'budi2@jurnalkelas.app',
            'password' => 'rahasia123',
        ], $extra));

        $kirim()->assertSessionHasErrors('nama');
        $this->assertNull(Guru::find('199003032015011003'));

        $kirim(['izinkan_nama_sama' => 1])->assertSessionHasNoErrors();
        $this->assertNotNull(Guru::find('199003032015011003'));
    }

    public function test_admin_can_list_and_filter_guru(): void
    {
        $this->guru();

        $this->actingAs($this->admin())
            ->get('/admin/guru')
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('198501012010011001');

        $this->actingAs($this->admin())
            ->get('/admin/guru?q=198501012010011001')
            ->assertOk()
            ->assertSee('Budi Santoso');
    }

    public function test_admin_can_update_a_guru_without_changing_the_password(): void
    {
        $akun = $this->guru();
        $sandiLama = $akun->password;

        $this->actingAs($this->admin())
            ->put("/admin/guru/{$akun->nip}", [
                'nama' => 'Budi Santoso, S.Pd.',
                'status' => 'aktif',
                'username' => $akun->username,
                'email' => $akun->email,
                'password' => '',
            ])
            ->assertRedirect(route('admin.guru.show', $akun->nip))
            ->assertSessionHasNoErrors();

        $this->assertSame('Budi Santoso, S.Pd.', Guru::find($akun->nip)->nama);
        $this->assertSame($sandiLama, $akun->refresh()->password, 'a blank password box must leave the password alone');
    }

    public function test_admin_can_assign_and_release_wali_from_the_guru_form(): void
    {
        $akun = $this->guru();
        $kelas = $this->kelas();

        $payload = fn (array $extra = []): array => array_merge([
            'nama' => 'Budi Santoso',
            'status' => 'aktif',
            'username' => $akun->username,
            'email' => $akun->email,
            'password' => '',
        ], $extra);

        $this->actingAs($this->admin())
            ->put("/admin/guru/{$akun->nip}", $payload(['kelas_wali' => [$kelas->id]]))
            ->assertSessionHasNoErrors();

        $this->assertSame($akun->nip, $kelas->refresh()->wali_kelas_nip);

        // Submitting with none selected releases the assignment.
        $this->actingAs($this->admin())->put("/admin/guru/{$akun->nip}", $payload());

        $this->assertNull($kelas->refresh()->wali_kelas_nip);
    }

    public function test_a_guru_with_history_cannot_be_deleted(): void
    {
        $akun = $this->guru();
        $kelas = $this->kelas();

        $akun->guru->jadwals()->create([
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $this->mapel()->id,
            'hari' => 'Senin',
            'jam_ke_mulai' => 1,
            'jam_ke_selesai' => 2,
        ]);

        $this->actingAs($this->admin())
            ->delete("/admin/guru/{$akun->nip}")
            ->assertSessionHas('error');

        $this->assertNotNull(Guru::find($akun->nip), 'deleting would have cascaded away their teaching record');
    }

    // ---- Data Siswa -------------------------------------------------------

    public function test_creating_a_siswa_creates_the_person_and_the_account_together(): void
    {
        $kelas = $this->kelas();

        $this->actingAs($this->admin())
            ->post('/admin/siswa', [
                'nis' => '20240001',
                'nama' => 'Ahmad Fauzi',
                'kelas_id' => $kelas->id,
                'status' => 'aktif',
                'username' => 'ahmad.fauzi',
                'email' => 'ahmad@siswa.app',
                'password' => 'rahasia123',
            ])
            ->assertRedirect(route('admin.siswa.index'))
            ->assertSessionHasNoErrors();

        $siswa = Siswa::find('20240001');
        $this->assertNotNull($siswa);
        $this->assertSame($kelas->id, $siswa->kelas_id);
        $this->assertSame('siswa', $siswa->akun->role);
        $this->assertNull($siswa->akun->nip, 'a student account carries no NIP');
    }

    public function test_creating_a_siswa_requires_a_nis(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/siswa', [
                'nama' => 'Tanpa NIS',
                'status' => 'aktif',
                'username' => 'tanpa.nis',
                'email' => 'tanpa@siswa.app',
                'password' => 'rahasia123',
            ])
            ->assertSessionHasErrors('nis');
    }

    public function test_promoting_a_ketua_kelas_demotes_the_previous_one(): void
    {
        $kelas = $this->kelas();

        $lama = Siswa::factory()->create(['kelas_id' => $kelas->id]);
        $kelas->update(['ketua_nis' => $lama->nis]);
        $baru = Siswa::factory()->create(['kelas_id' => $kelas->id]);
        $akun = User::factory()->siswa($baru)->create();

        $this->actingAs($this->admin())
            ->put("/admin/siswa/{$baru->nis}", [
                'nama' => $baru->nama,
                'kelas_id' => $kelas->id,
                'is_ketua_kelas' => 1,
                'status' => 'aktif',
                'username' => $akun->username,
                'email' => $akun->email,
                'password' => '',
            ])
            ->assertSessionHasNoErrors();

        // One column holds the chair, so promoting one student demotes the
        // other by construction rather than by a model hook tidying up after.
        $this->assertSame($baru->nis, $kelas->refresh()->ketua_nis);
        $this->assertFalse($lama->refresh()->is_ketua_kelas, 'a class may have only one ketua');
    }

    public function test_admin_can_filter_siswa_by_class(): void
    {
        $kelas = $this->kelas();
        $lain = $this->buatKelas(['nama_kelas' => 'X IPS 1', 'tingkat' => 'X', 'jurusan_kode' => 'IPS']);

        Siswa::factory()->create(['kelas_id' => $kelas->id, 'nama' => 'Siswa Satu']);
        Siswa::factory()->create(['kelas_id' => $lain->id, 'nama' => 'Siswa Dua']);

        $this->actingAs($this->admin())
            ->get("/admin/siswa?kelas_id={$kelas->id}")
            ->assertOk()
            ->assertSee('Siswa Satu')
            ->assertDontSee('Siswa Dua');
    }

    // ---- Akun -------------------------------------------------------------

    public function test_admin_can_list_and_filter_accounts(): void
    {
        $this->guru();
        $this->buatSiswa(['nama' => 'Ahmad Fauzi'], ['username' => 'ahmad.f', 'email' => 'ahmad@siswa.app']);

        $this->actingAs($this->admin())
            ->get('/admin/akun')
            ->assertOk()
            ->assertSee('budi.santoso')
            ->assertSee('ahmad.f');

        $this->actingAs($this->admin())
            ->get('/admin/akun?role=guru')
            ->assertOk()
            ->assertSee('budi.santoso')
            ->assertDontSee('ahmad.f');
    }

    public function test_the_account_page_only_creates_admins(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/akun', [
                'username' => 'guru.baru',
                'nama' => 'Guru Baru',
                'email' => 'guru.baru@jurnalkelas.app',
                'password' => 'rahasia123',
                'role' => 'guru',
                'status' => 'aktif',
            ])
            ->assertSessionHasErrors('role');

        $this->assertNull(User::where('username', 'guru.baru')->first());
    }

    public function test_admin_can_create_another_admin_account(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/akun', [
                'username' => 'admin.dua',
                'nama' => 'Admin Dua',
                'email' => 'admin2@jurnalkelas.app',
                'password' => 'rahasia123',
                'role' => 'admin',
                'status' => 'aktif',
            ])
            ->assertRedirect(route('admin.akun.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Admin Dua', User::where('username', 'admin.dua')->firstOrFail()->nama);
    }

    public function test_an_accounts_role_cannot_be_changed_away_from_its_person(): void
    {
        $akun = $this->guru();

        $this->actingAs($this->admin())
            ->put("/admin/akun/{$akun->id}", [
                'username' => $akun->username,
                'email' => $akun->email,
                'password' => '',
                'role' => 'admin',
                'status' => 'aktif',
                'nama' => 'Bukan Guru Lagi',
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame('guru', $akun->refresh()->role);
    }

    public function test_admin_cannot_demote_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/admin/akun/{$admin->id}", [
                'username' => $admin->username,
                'nama' => $admin->nama,
                'email' => $admin->email,
                'password' => '',
                'role' => 'guru',
                'status' => 'aktif',
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame('admin', $admin->refresh()->role);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/admin/akun/{$admin->id}");

        $this->assertModelExists($admin);
    }

    public function test_deleting_an_account_leaves_the_person_on_the_register(): void
    {
        $akun = $this->guru();

        $this->actingAs($this->admin())
            ->delete("/admin/akun/{$akun->id}")
            ->assertRedirect(route('admin.akun.index'));

        $this->assertModelMissing($akun);
        $this->assertNotNull(
            Guru::find('198501012010011001'),
            'revoking a login must not erase the teacher from the school records',
        );
    }

    public function test_admin_can_view_the_guru_detail_page(): void
    {
        $akun = $this->guru();

        $this->actingAs($this->admin())
            ->get("/admin/guru/{$akun->nip}")
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
            ->get("/admin/laporan/jurnal?kelas_id={$kelas->id}&guru_nip={$guru->nip}")
            ->assertOk()
            ->assertSee('Rekap Jurnal Mengajar');

        $this->actingAs($admin)
            ->get("/admin/laporan/presensi?kelas_id={$kelas->id}")
            ->assertOk()
            ->assertSee('Rekap Presensi per Pertemuan');
    }
}
