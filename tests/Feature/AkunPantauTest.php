<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\User;
use App\Support\Ringkasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Akun pantau (users.hanya_baca): the kepala sekolah / waka kurikulum account
 * that sees everything an admin sees and changes nothing.
 */
class AkunPantauTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@jurnalkelas.app'],
            ['username' => 'admin', 'nama' => 'Administrator', 'password' => Hash::make('password'), 'role' => 'admin'],
        );
    }

    private function pantau(): User
    {
        return User::create([
            'username' => 'kepsek',
            'nama' => 'Kepala Sekolah',
            'email' => 'kepsek@jurnalkelas.app',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'hanya_baca' => true,
            'status' => 'aktif',
        ]);
    }

    /** @return array{0: Kelas, 1: Jurnal} */
    private function pertemuan(): array
    {
        $guru = $this->buatGuru(['nip' => '198700000001']);
        $kelas = $this->buatKelas(['nama_kelas' => 'X PANTAU 1', 'tingkat' => 'X']);
        $mapel = new MataPelajaran;
        $mapel->forceFill(['nama' => 'Matematika', 'kode' => 'MTK-P', 'kelompok' => 'wajib', 'jp_per_minggu' => 2])->save();
        $jadwal = Jadwal::create([
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'guru_nip' => $guru->nip,
            'hari' => Ringkasan::HARI[today()->dayOfWeekIso - 1] ?? 'Senin',
            'jam_ke_mulai' => 1,
            'jam_ke_selesai' => 2,
        ]);
        $jurnal = Jurnal::create([
            'jadwal_id' => $jadwal->id,
            'tanggal' => today()->toDateString(),
            'materi' => 'Persamaan kuadrat',
            'kehadiran_guru_status' => 'hadir',
            'diisi_oleh_id' => $guru->id,
            'diisi_oleh_peran' => 'guru',
        ]);

        return [$kelas, $jurnal];
    }

    public function test_the_akun_pantau_reads_every_monitoring_screen(): void
    {
        [$kelas, $jurnal] = $this->pertemuan();
        $pantau = $this->pantau();

        foreach ([
            route('admin.dashboard'),
            route('admin.laporan.jurnal'),
            route('admin.laporan.presensi'),
            route('admin.presensi.log'),
            route('admin.guru.index'),
            route('admin.siswa.index'),
            route('admin.hari-libur.index'),
            route('jurnal.index'),
            route('jurnal.show', $jurnal),
            route('presensi.index'),
            route('kelas.index'),
            route('jadwal.index'),
        ] as $url) {
            $this->actingAs($pantau)->get($url)->assertOk();
        }
    }

    public function test_the_akun_pantau_is_refused_every_write(): void
    {
        [$kelas, $jurnal] = $this->pertemuan();
        $pantau = $this->pantau();

        $this->actingAs($pantau)->post(route('jurnal.store'), [
            'jadwal_id' => $jurnal->jadwal_id, 'tanggal' => today()->toDateString(),
            'materi' => 'X', 'kehadiran_guru' => 'hadir',
        ])->assertForbidden();
        $this->actingAs($pantau)->put(route('jurnal.update', $jurnal), ['materi' => 'Diubah'])->assertForbidden();
        $this->actingAs($pantau)->delete(route('jurnal.destroy', $jurnal))->assertForbidden();
        $this->actingAs($pantau)->post(route('presensi-jurnal.mulai'), ['jadwal_id' => $jurnal->jadwal_id])->assertForbidden();
        $this->actingAs($pantau)->delete(route('kelas.destroy', $kelas))->assertForbidden();
        $this->actingAs($pantau)->post(route('admin.hari-libur.store'), [
            'tanggal_mulai' => today()->addDay()->toDateString(), 'keterangan' => 'X',
        ])->assertForbidden();

        $this->assertModelExists($jurnal);
        $this->assertModelExists($kelas);
        $this->assertSame('Persamaan kuadrat', $jurnal->fresh()->materi);
    }

    public function test_write_forms_and_system_areas_stay_closed(): void
    {
        [$kelas, $jurnal] = $this->pertemuan();
        $pantau = $this->pantau();

        foreach ([
            route('kelas.create'),
            route('jurnal.create'),
            route('jurnal.edit', $jurnal),
            route('presensi-jurnal.edit', $jurnal),
            route('admin.akun.index'),
            route('admin.impor.index'),
            route('admin.cadangan.index'),
            route('admin.sistem.index'),
        ] as $url) {
            $this->actingAs($pantau)->get($url)->assertForbidden();
        }
    }

    public function test_no_write_ability_is_granted(): void
    {
        [, $jurnal] = $this->pertemuan();
        $pantau = $this->pantau();

        $this->assertTrue($pantau->can('view', $jurnal));
        $this->assertFalse($pantau->can('update', $jurnal));
        $this->assertFalse($pantau->can('delete', $jurnal));
        $this->assertFalse($pantau->can('isiPresensi', $jurnal));
        $this->assertFalse($pantau->can('create', Jurnal::class));
    }

    public function test_the_screen_marks_the_account_and_hides_system_menus(): void
    {
        $this->actingAs($this->pantau())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('mode-pantau', false)
            ->assertSee('Akun Pantau')
            ->assertDontSee(route('admin.akun.index'))
            ->assertDontSee(route('admin.cadangan.index'));

        // An ordinary admin keeps everything.
        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('mode-pantau', false)
            ->assertSee(route('admin.akun.index'));
    }

    public function test_the_api_is_read_only_for_the_akun_pantau(): void
    {
        [, $jurnal] = $this->pertemuan();
        Sanctum::actingAs($this->pantau());

        $this->getJson('/api/jurnal')->assertOk();
        $this->postJson('/api/jurnal', [
            'jadwal_id' => $jurnal->jadwal_id, 'tanggal' => today()->toDateString(), 'materi' => 'X',
        ])->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_the_admin_creates_an_akun_pantau(): void
    {
        $this->actingAs($this->admin())->post(route('admin.akun.store'), [
            'username' => 'waka.kurikulum',
            'email' => 'waka@jurnalkelas.app',
            'nama' => 'Waka Kurikulum',
            'password' => 'rahasia123',
            'status' => 'aktif',
            'role' => 'admin',
            'hanya_baca' => '1',
        ])->assertRedirect(route('admin.akun.index'));

        $akun = User::where('username', 'waka.kurikulum')->sole();
        $this->assertTrue($akun->isHanyaBaca());
    }

    public function test_an_admin_cannot_make_their_own_account_read_only(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.akun.update', $admin), [
            'username' => $admin->username,
            'email' => $admin->email,
            'nama' => $admin->nama,
            'status' => 'aktif',
            'role' => 'admin',
            'hanya_baca' => '1',
        ])->assertSessionHasErrors('hanya_baca');

        $this->assertFalse($admin->fresh()->isHanyaBaca());
    }

    public function test_only_an_admin_account_can_be_read_only(): void
    {
        $guru = $this->buatGuru(['nip' => '198700000009']);
        $guru->forceFill(['hanya_baca' => true])->save();

        $this->assertFalse($guru->fresh()->isHanyaBaca());
    }
}
