<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The classroom QR entry point: a guru scans the QR posted in a room, lands on a
 * confirmation for that class, and continues into the normal journal→presensi
 * flow. The QR is guru-only, and the class is addressed by an opaque token.
 */
class QrAksesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    /** A class that at least one guru actually teaches, plus that guru. */
    private function kelasDenganPengajar(): array
    {
        $jadwal = Jadwal::with('kelas')->firstOrFail();

        return [$jadwal->kelas, User::findOrFail($jadwal->guru_id), $jadwal];
    }

    public function test_every_class_has_a_unique_qr_token(): void
    {
        $tokens = Kelas::pluck('qr_token');

        $this->assertGreaterThan(0, $tokens->count());
        $this->assertFalse($tokens->contains(null), 'Setiap kelas harus punya qr_token.');
        $this->assertSame($tokens->count(), $tokens->unique()->count(), 'qr_token harus unik.');
    }

    public function test_a_new_class_gets_a_token_automatically(): void
    {
        $kelas = Kelas::create([
            'nama_kelas' => 'X QR 1',
            'tingkat' => 'X',
            'kapasitas' => 30,
            'tahun_ajaran' => '2025/2026',
        ]);

        $this->assertNotNull($kelas->qr_token);
        $this->assertStringContainsString($kelas->qr_token, $kelas->qrUrl());
    }

    public function test_a_guru_teaching_the_class_sees_the_confirmation(): void
    {
        [$kelas, $guru] = $this->kelasDenganPengajar();

        $this->actingAs($guru)
            ->get("/qr/{$kelas->qr_token}")
            ->assertOk()
            ->assertSee($kelas->nama_kelas)
            ->assertSee('Isi Jurnal', false);
    }

    public function test_the_qr_page_is_guru_only(): void
    {
        [$kelas] = $this->kelasDenganPengajar();

        $siswa = User::where('role', 'siswa')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($siswa)->get("/qr/{$kelas->qr_token}")->assertForbidden();
        $this->actingAs($admin)->get("/qr/{$kelas->qr_token}")->assertForbidden();
    }

    public function test_scanning_while_logged_out_redirects_to_login(): void
    {
        [$kelas] = $this->kelasDenganPengajar();

        $this->get("/qr/{$kelas->qr_token}")->assertRedirect('/login');
    }

    public function test_an_unknown_token_is_not_found(): void
    {
        [, $guru] = $this->kelasDenganPengajar();

        $this->actingAs($guru)->get('/qr/token-yang-tidak-ada')->assertNotFound();
    }

    public function test_a_guru_who_does_not_teach_the_class_gets_an_empty_state(): void
    {
        [$kelas] = $this->kelasDenganPengajar();

        // A fresh guru with no timetable teaches nothing anywhere.
        $luar = User::create([
            'name' => 'Guru Tanpa Jadwal',
            'email' => 'guru.tanpa.jadwal@test.app',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'nip' => '99881122',
        ]);

        // Scanning the wrong room is an ordinary mistake, not an attack: the page
        // still renders, it simply offers nothing to fill.
        $this->actingAs($luar)
            ->get("/qr/{$kelas->qr_token}")
            ->assertOk()
            ->assertSee('tidak mengajar', false);
    }

    public function test_the_confirmation_only_offers_the_scanning_gurus_own_subjects(): void
    {
        [$kelas, $guru] = $this->kelasDenganPengajar();

        // A subject taught in this class by a DIFFERENT teacher must not appear
        // as an option — the handoff would fail pastikanJadwalMilik anyway.
        $lain = Jadwal::where('kelas_id', $kelas->id)
            ->where('guru_id', '!=', $guru->id)
            ->with('mataPelajaran')
            ->first();

        $response = $this->actingAs($guru)->get("/qr/{$kelas->qr_token}")->assertOk();

        foreach (Jadwal::where('kelas_id', $kelas->id)->where('guru_id', $guru->id)->get() as $milik) {
            $response->assertSee('value="'.$milik->id.'"', false);
        }

        if ($lain) {
            $response->assertDontSee('value="'.$lain->id.'"', false);
        }
    }

    public function test_the_printable_qr_sheet_is_admin_only(): void
    {
        [, $guru] = $this->kelasDenganPengajar();

        $admin = User::where('role', 'admin')->firstOrFail();
        $siswa = User::where('role', 'siswa')->firstOrFail();

        $this->actingAs($admin)->get('/admin/kelas-qr')->assertOk()->assertSee('<svg', false);
        $this->actingAs($guru)->get('/admin/kelas-qr')->assertForbidden();
        $this->actingAs($siswa)->get('/admin/kelas-qr')->assertForbidden();
    }
}
