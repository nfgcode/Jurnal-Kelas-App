<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Presensi;
use App\Models\User;
use App\Support\Ringkasan;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Attacks, not features: stored XSS through the fields people type into, and
 * mass assignment through fields nobody should be able to send. Each test
 * sends the payload the way a hostile client would and checks it is either
 * escaped, ignored or refused.
 */
class KeamananTest extends TestCase
{
    use RefreshDatabase;

    private const XSS = '<script>alert(1)</script><img src=x onerror=alert(2)>';

    private Jadwal $jadwal;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 09:00:00');   // a Monday with lessons
        $this->seed(DemoSeeder::class);
        $this->jadwal = Jadwal::with(['kelas', 'guru'])->where('hari', Ringkasan::hariIni())->firstOrFail();
        // A clean slot to write into.
        Jurnal::where('jadwal_id', $this->jadwal->id)->whereDate('tanggal', '2026-08-24')->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function ketua(): User
    {
        return $this->akunSiswaKelas($this->jadwal->kelas_id, true);
    }

    /* ----------------------------------------------------------------- XSS */

    public function test_a_journal_carrying_script_is_shown_as_text_everywhere(): void
    {
        $this->actingAs($this->ketua())->post('/jurnal', [
            'jadwal_id' => $this->jadwal->id,
            'tanggal' => '2026-08-24',
            'materi' => self::XSS,
            'tugas' => self::XSS,
            'kehadiran_guru' => 'ada_tugas',
            'kehadiran_guru_keterangan' => self::XSS,
        ])->assertRedirect();

        $jurnal = Jurnal::where('jadwal_id', $this->jadwal->id)->whereDate('tanggal', '2026-08-24')->firstOrFail();
        $guru = $this->akunGuru($this->jadwal->guru_nip);

        foreach ([
            [$this->ketua(), "/jurnal/{$jurnal->public_id}"],
            [$this->ketua(), '/jurnal?preset=30_hari'],
            [$guru, "/jurnal/{$jurnal->public_id}"],
            [$guru, '/jurnal?preset=30_hari'],
            [$this->admin(), '/admin/laporan/jurnal?preset=30_hari'],
            [$this->admin(), '/admin?preset=30_hari'],
        ] as [$siapa, $url]) {
            $this->actingAs($siapa)->get($url)
                ->assertOk()
                ->assertDontSee('<script>alert(1)</script>', false)
                ->assertDontSee('<img src=x onerror', false);
        }

        $this->actingAs($this->ketua())->get("/jurnal/{$jurnal->public_id}")
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_a_name_cannot_break_out_of_a_delete_confirmation(): void
    {
        // Used to sit in onclick="return confirm('Hapus {{ $guru->nama }}…')",
        // where the browser decodes &#039; back to a quote before running it.
        $guru = Guru::firstOrFail();
        $guru->update(['nama' => "X'); alert(document.cookie);//"]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.guru.edit', $guru))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('confirm(', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringContainsString('data-konfirmasi="Hapus X&#039;); alert(document.cookie);//', $html);
    }

    /* --------------------------------------------------- Mass assignment */

    public function test_a_journal_ignores_the_authorship_fields_a_client_slips_in(): void
    {
        $ketua = $this->ketua();

        $this->actingAs($ketua)->post('/jurnal', [
            'jadwal_id' => $this->jadwal->id,
            'tanggal' => '2026-08-24',
            'materi' => 'Materi asli',
            'kehadiran_guru' => 'hadir',
            // Smuggled: none of these is the client's to choose.
            'diisi_oleh_peran' => Jurnal::PERAN_SISTEM,
            'diisi_oleh_id' => $this->admin()->id,
            'public_id' => 'DIPALSUKAN',
            'diedit_setelah_hari' => 1,
            'kehadiran_guru_status' => 'tidak_hadir',
        ])->assertRedirect();

        $jurnal = Jurnal::where('jadwal_id', $this->jadwal->id)->whereDate('tanggal', '2026-08-24')->firstOrFail();

        $this->assertSame('siswa', $jurnal->diisi_oleh_peran);
        $this->assertSame($ketua->id, $jurnal->diisi_oleh_id);
        $this->assertNotSame('DIPALSUKAN', $jurnal->public_id);
        $this->assertFalse((bool) $jurnal->diedit_setelah_hari);
        $this->assertSame('hadir', $jurnal->kehadiran_guru_status);
    }

    public function test_a_roster_ignores_smuggled_keys_and_outsiders(): void
    {
        $jurnal = Jurnal::with('jadwal.kelas')->firstOrFail();
        $guru = $this->akunGuru($jurnal->jadwal->guru_nip);
        $lain = Jurnal::whereHas('jadwal', fn ($q) => $q->where('kelas_id', '!=', $jurnal->jadwal->kelas_id))->firstOrFail();
        $nis = $jurnal->jadwal->kelas->siswa()->value('nis');

        $this->actingAs($guru)->post(route('presensi-jurnal.store', $jurnal), [
            'presensi' => [[
                'siswa_nis' => $nis,
                'status' => 'hadir',
                'jurnal_id' => $lain->id,              // write into someone else's meeting
                'diisi_oleh_id' => $this->admin()->id, // pose as the admin
            ]],
        ])->assertRedirect();

        $baris = Presensi::where('siswa_nis', $nis)->where('jurnal_id', $jurnal->id)->firstOrFail();
        $this->assertSame($guru->id, $baris->diisi_oleh_id);
        $this->assertFalse(Presensi::where('jurnal_id', $lain->id)->where('siswa_nis', $nis)->exists());

        // A student from another class cannot be marked on this roster.
        $orangLuar = $lain->jadwal->kelas->siswa()->value('nis');
        $this->actingAs($guru)->post(route('presensi-jurnal.store', $jurnal), [
            'presensi' => [['siswa_nis' => $orangLuar, 'status' => 'alpa']],
        ])->assertSessionHasErrors();
    }

    public function test_an_account_form_cannot_mint_a_guru_or_siswa_login(): void
    {
        $this->actingAs($this->admin())->post('/admin/akun', [
            'username' => 'penyusup',
            'email' => 'penyusup@contoh.test',
            'nama' => 'Penyusup',
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
            'role' => 'guru',
            'status' => 'aktif',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['username' => 'penyusup']);
    }

    public function test_an_error_report_cannot_set_its_own_status(): void
    {
        $siswa = $this->ketua();

        $this->actingAs($siswa)->post('/laporan-error', [
            'pesan' => 'Halaman macet',
            'status' => 'selesai',
            'user_id' => $this->admin()->id,
            'jumlah' => 999,
        ]);

        $laporan = \App\Models\LaporanError::latest('id')->firstOrFail();
        $this->assertSame($siswa->id, $laporan->user_id);
        $this->assertNotSame('selesai', $laporan->status);
        $this->assertNotSame(999, (int) $laporan->jumlah);
    }

    /* --------------------------------------------- Writing outside your lane */

    public function test_a_ketua_cannot_write_a_journal_for_another_class(): void
    {
        $lain = Jadwal::where('kelas_id', '!=', $this->jadwal->kelas_id)->where('hari', Ringkasan::hariIni())->firstOrFail();

        $this->actingAs($this->ketua())->post('/jurnal', [
            'jadwal_id' => $lain->id,
            'tanggal' => '2026-08-24',
            'materi' => 'Bukan kelas saya',
            'kehadiran_guru' => 'hadir',
        ])->assertForbidden();
    }

    public function test_a_plain_student_cannot_write_a_journal(): void
    {
        $siswa = $this->akunSiswaKelas($this->jadwal->kelas_id, false);

        $this->actingAs($siswa)->post('/jurnal', [
            'jadwal_id' => $this->jadwal->id,
            'tanggal' => '2026-08-24',
            'materi' => 'Bukan ketua',
            'kehadiran_guru' => 'hadir',
        ])->assertForbidden();
    }

    public function test_a_guru_cannot_mark_another_teachers_roster(): void
    {
        $jurnal = Jurnal::with('jadwal')->firstOrFail();
        $guruLain = User::where('role', 'guru')->where('nip', '!=', $jurnal->jadwal->guru_nip)->firstOrFail();

        $this->actingAs($guruLain)->post(route('presensi-jurnal.store', $jurnal), [
            'presensi' => [['siswa_nis' => $jurnal->jadwal->kelas->siswa()->value('nis'), 'status' => 'alpa']],
        ])->assertForbidden();
    }

    public function test_a_guru_cannot_reach_admin_writes(): void
    {
        $guru = $this->akunGuru($this->jadwal->guru_nip);

        $this->actingAs($guru)->post('/admin/akun', ['role' => 'admin'])->assertForbidden();
        $this->actingAs($guru)->post('/kelas', ['nama_kelas' => 'X'])->assertForbidden();
        $this->actingAs($guru)->delete(route('admin.siswa.destroy', \App\Models\Siswa::firstOrFail()))->assertForbidden();
    }
}
