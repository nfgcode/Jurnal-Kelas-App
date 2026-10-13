<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\User;
use App\Support\Ringkasan;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Half the screens are chosen by role inside the controller: /jurnal renders
 * histori for a guru and riwayat for a siswa, /presensi renders the class recap
 * or the student's own record, and /dashboard forks three ways. The rest of the
 * suite only ever acts as an admin, so none of those branches were being
 * rendered. This covers them, plus the shared journal vocabulary that folds onto
 * the kehadiran_guru columns.
 */
class RolePagesTest extends TestCase
{
    use RefreshDatabase;

    private Jadwal $jadwal;

    private User $guru;

    private User $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);

        $this->jadwal = Jadwal::with('kelas')->firstOrFail();
        $this->guru = $this->akunGuru($this->jadwal->guru_nip);
        // The ketua kelas: journal filling for a siswa is a ketua-only ability,
        // so the role screens/posts below must act as one.
        $this->siswa = $this->akunSiswaKelas($this->jadwal->kelas_id, true);
    }

    /** A journal belonging to the class this guru and siswa share. */
    private function jurnal(): Jurnal
    {
        return Jurnal::where('jadwal_id', $this->jadwal->id)->firstOrFail();
    }

    public function test_guru_screens_render(): void
    {
        $jurnal = $this->jurnal();

        $expectations = [
            '/dashboard' => 'Dashboard Guru',
            '/jurnal' => 'Histori Jurnal',
            // A guru's own write screen is the roster, not the journal.
            route('presensi-jurnal.edit', $jurnal) => 'Simpan Presensi',
            '/presensi' => 'Rekap Presensi Siswa',
            route('presensi-harian.show', [$this->jadwal->kelas_id, 'tanggal' => $jurnal->tanggal->toDateString()]) => 'Presensi Harian',
            "/jurnal/{$jurnal->public_id}" => 'Kehadiran Siswa',
        ];

        foreach ($expectations as $url => $penanda) {
            $this->actingAs($this->guru)
                ->get($url)
                ->assertOk("GET {$url} did not render for a guru")
                ->assertSee($penanda);
        }
    }

    /**
     * Oct 2026: the ketua kelas files today's journal and reads today's. Every
     * other screen they used to reach - editing, the timetable, attendance -
     * is closed to them.
     */
    public function test_ketua_screens_render(): void
    {
        // Today's journal, written by the class, so there is one to read.
        Jurnal::where('jadwal_id', $this->jadwal->id)->whereDate('tanggal', today())->delete();
        $jurnal = Jurnal::create([
            'jadwal_id' => $this->jadwal->id,
            'tanggal' => today()->toDateString(),
            'materi' => 'Materi hari ini',
            'kehadiran_guru_status' => 'hadir',
            'diisi_oleh_id' => $this->siswa->id,
            'diisi_oleh_peran' => 'siswa',
        ]);

        $expectations = [
            '/dashboard' => 'Dashboard Siswa',
            '/jurnal' => 'Jurnal Kelas Hari Ini',
            '/jurnal/create' => 'Mengisi Jurnal Kelas',
            "/jurnal/{$jurnal->public_id}" => 'Kehadiran Siswa',
        ];

        foreach ($expectations as $url => $penanda) {
            $this->actingAs($this->siswa)
                ->get($url)
                ->assertOk("GET {$url} did not render for a ketua")
                ->assertSee($penanda);
        }

        foreach (["/jurnal/{$jurnal->public_id}/edit", '/jadwal', '/presensi',
            route('presensi-harian.show', $this->jadwal->kelas_id)] as $url) {
            $this->actingAs($this->siswa)->get($url)->assertForbidden("GET {$url} should be closed to a ketua");
        }

        // The sidebar offers only what they may open.
        $this->actingAs($this->siswa)->get('/dashboard')
            ->assertSee('Jurnal Hari Ini')
            ->assertDontSee('href="'.route('jadwal.index').'"', false)
            ->assertDontSee('href="'.route('presensi.index').'"', false);
    }

    public function test_regular_siswa_web_screens_are_denied(): void
    {
        $siswa = $this->akunSiswaKelas($this->jadwal->kelas_id, false);
        $jurnal = $this->jurnal();

        foreach (['/dashboard', '/jadwal', '/jurnal', '/presensi', "/jurnal/{$jurnal->public_id}"] as $url) {
            $this->actingAs($siswa)
                ->get($url)
                ->assertForbidden();
        }

        foreach (['/jurnal/create', "/jurnal/{$jurnal->public_id}/edit"] as $url) {
            $this->actingAs($siswa)->get($url)->assertForbidden("GET {$url} should be closed to a siswa");
        }
    }

    /**
     * "Mode Wali Kelas" is a whole second set of screens no other test renders,
     * and its attendance page reads the daily roster rather than the journals.
     */
    public function test_wali_kelas_screens_render(): void
    {
        $kelas = $this->jadwal->kelas;
        $wali = $this->buatGuru();
        $kelas->update(['wali_kelas_nip' => $wali->nip]);

        $expectations = [
            '/wali-kelas' => $kelas->nama_kelas,
            '/wali-kelas/siswa' => $kelas->nama_kelas,
            '/wali-kelas/jadwal' => $kelas->nama_kelas,
            '/wali-kelas/jurnal' => $kelas->nama_kelas,
            '/wali-kelas/presensi' => 'Histori Presensi Harian',
        ];

        foreach ($expectations as $url => $penanda) {
            $this->actingAs($wali)
                ->get($url)
                ->assertOk("GET {$url} did not render for a wali kelas")
                ->assertSee($penanda);
        }
    }

    /**
     * A wali kelas oversees attendance but marks none of it — that belongs to
     * each meeting's own teacher — so their screen must not offer a way in.
     */
    public function test_the_wali_kelas_attendance_screen_offers_no_way_to_fill_it(): void
    {
        $kelas = $this->jadwal->kelas;
        $wali = $this->buatGuru();
        $kelas->update(['wali_kelas_nip' => $wali->nip]);

        $this->actingAs($wali)
            ->get('/wali-kelas/presensi')
            ->assertOk()
            ->assertDontSee(route('presensi-jurnal.mulai'), false);
    }

    public function test_the_admin_import_screen_renders(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.impor.index'))
            ->assertOk()
            ->assertSee('Impor Data Siswa')
            ->assertSee('Unduh Template Siswa')
            ->assertSee('Unduh Template Guru');
    }

    public function test_siswa_cannot_access_admin_section(): void
    {
        $this->actingAs($this->siswa)->get('/admin')->assertForbidden();
        $this->actingAs($this->siswa)->get('/admin/akun')->assertForbidden();
    }

    /**
     * Oct 2026: the guru has full CRUD over the journals of the lessons they
     * teach - and only those. Another teacher's lesson is refused outright.
     */
    public function test_a_guru_authors_journals_on_their_own_lessons_only(): void
    {
        // A day a month out on this lesson's weekday: nothing written yet.
        $hari = array_search($this->jadwal->hari, Ringkasan::HARI, true);
        $tanggal = today()->addMonth();
        while ($tanggal->dayOfWeekIso - 1 !== $hari) {
            $tanggal->addDay();
        }
        $this->travelTo($tanggal->copy()->setTime(9, 0));

        // The teacher's form is named for the teacher's journal, not the class's.
        $this->actingAs($this->guru)->get('/jurnal/create')
            ->assertOk()
            ->assertSee('Isi Jurnal Mengajar')
            ->assertDontSee('Isi Jurnal Kelas')
            ->assertViewHas('jadwalList', fn ($list) => $list->isNotEmpty()
                && $list->every(fn ($j) => $j->guru_nip === $this->guru->nip));

        $isian = [
            'jadwal_id' => $this->jadwal->id,
            'tanggal' => $tanggal->toDateString(),
            'materi' => 'Turunan fungsi aljabar',
            'kehadiran_guru' => 'hadir',
        ];

        $lain = User::where('role', 'guru')->where('nip', '!=', $this->jadwal->guru_nip)->firstOrFail();
        $this->actingAs($lain)->post('/jurnal', $isian)->assertForbidden();
        $this->assertDatabaseMissing('jurnal', ['materi' => 'Turunan fungsi aljabar']);

        $this->actingAs($this->guru)->post('/jurnal', $isian)->assertRedirect();
        $jurnal = Jurnal::where('materi', 'Turunan fungsi aljabar')->firstOrFail();
        $this->assertSame('guru', $jurnal->diisi_oleh_peran);

        // ...and it is theirs to edit.
        $this->actingAs($this->guru)->get("/jurnal/{$jurnal->public_id}/edit")
            ->assertOk()
            ->assertSee('Ubah Jurnal Mengajar');
        $this->actingAs($lain)->get("/jurnal/{$jurnal->public_id}/edit")->assertForbidden();
    }

    /**
     * A siswa reports the same three outcomes, may add a note about the absence,
     * and the journal still belongs to the guru on the timetable rather than to
     * the student who typed it.
     */
    public function test_siswa_reports_the_guru_attendance(): void
    {
        $this->actingAs($this->siswa)
            ->post('/jurnal', [
                'jadwal_id' => $this->jadwal->id,
                'tanggal' => now()->toDateString(),
                'materi' => 'Belajar mandiri',
                'kehadiran_guru' => 'ada_tugas',
                'kehadiran_guru_keterangan' => 'Diwakili guru piket',
            ])
            ->assertSessionHasNoErrors();

        $jurnal = Jurnal::latest('id')->firstOrFail();

        $this->assertSame('tidak_hadir', $jurnal->kehadiran_guru_status);
        $this->assertTrue((bool) $jurnal->kehadiran_guru_ada_tugas);
        $this->assertNull($jurnal->kehadiran_guru_alasan);
        $this->assertSame('Diwakili guru piket', $jurnal->kehadiran_guru_keterangan);
        $this->assertSame($this->jadwal->guru_nip, $jurnal->jadwal->guru_nip);
        $this->assertSame($this->siswa->id, $jurnal->diisi_oleh_id);
    }

    /**
     * Both authors — the class's ketua and admin — share one vocabulary:
     * hadir / ada_tugas / tanpa_tugas. The retired reason options are rejected
     * for either of them.
     */
    public function test_both_authors_share_one_attendance_vocabulary(): void
    {
        $payload = [
            'jadwal_id' => $this->jadwal->id,
            'tanggal' => now()->toDateString(),
            'materi' => 'Materi apa saja',
        ];

        $admin = User::where('role', 'admin')->firstOrFail();

        foreach ([$this->siswa, $admin] as $pengguna) {
            // The vocabulary both roles now share.
            $this->actingAs($pengguna)
                ->post('/jurnal', $payload + ['kehadiran_guru' => 'tanpa_tugas'])
                ->assertSessionHasNoErrors();

            // The reason vocabulary the ketua kelas used to submit is gone.
            foreach (['sakit', 'izin', 'alpa'] as $usang) {
                $this->actingAs($pengguna)
                    ->post('/jurnal', $payload + ['kehadiran_guru' => $usang])
                    ->assertSessionHasErrors('kehadiran_guru');
            }
        }
    }
}
