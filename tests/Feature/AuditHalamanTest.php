<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\User;
use App\Support\CadanganData;
use App\Support\Ringkasan;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regressions found by the October 2026 page-by-page audit (a crawl of every
 * GET route as five kinds of account against the MySQL dev database, plus the
 * Figma "High Fidelity 2" comparison). Several only failed on MySQL, which this
 * SQLite suite cannot reproduce, so they are pinned by what the code builds.
 */
class AuditHalamanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A fixed Monday, so "today's lessons" exist whatever day the suite runs.
        Carbon::setTestNow('2026-08-24 09:00:00');
        $this->seed(DemoSeeder::class);
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

    public function test_the_qr_print_page_survives_a_class_seeded_without_a_token(): void
    {
        // DatabaseSeeder runs WithoutModelEvents, so seeded classes used to have
        // no token and route('qr.show', null) took the whole page down.
        $kelas = Kelas::firstOrFail();
        DB::table('kelas')->where('id', $kelas->id)->update(['qr_token' => null]);

        $this->actingAs($this->admin())->get('/admin/kelas-qr')->assertOk();

        $this->assertNotNull($kelas->fresh()->qr_token);
    }

    public function test_backups_order_each_table_by_its_own_primary_key(): void
    {
        // guru and siswa have no `id`; orderBy('id') was a MySQL 500 that
        // SQLite silently accepts as a string literal.
        $urut = new ReflectionMethod(CadanganData::class, 'urut');
        $sql = $urut->invoke(app(CadanganData::class), DB::table('guru'), 'guru')->toSql();

        $this->assertStringContainsString('order by "nip"', $sql);
        $this->assertStringNotContainsString('"id"', $sql);
    }

    public function test_the_class_page_names_its_jurusan_instead_of_dumping_json(): void
    {
        $kelas = Kelas::whereNotNull('jurusan_kode')->with('jurusan')->firstOrFail();

        $this->actingAs($this->admin())
            ->get("/kelas/{$kelas->id}")
            ->assertOk()
            ->assertSee($kelas->jurusan->nama)
            ->assertDontSee('{&quot;kode', false)
            ->assertDontSee('"kode":', false);
    }

    public function test_the_admin_journal_form_names_the_lessons_teacher(): void
    {
        $jadwal = Jadwal::with('guru')->where('hari', Ringkasan::hariIni())->firstOrFail();

        $this->actingAs($this->admin())
            ->get('/jurnal/create?tanggal=2026-08-24&jadwal_id='.$jadwal->id)
            ->assertOk()
            ->assertSee($jadwal->guru->nama)
            ->assertSee('Kehadiran Guru Bulan Ini')
            ->assertDontSee('Kehadiran Mengajar Saya')
            // A real local draft, not a label claiming one.
            ->assertSee('data-draf="jurnal:baru:2026-08-24:'.$jadwal->id.'"', false)
            ->assertSee('Simpan Draf');
    }

    public function test_an_admin_can_record_why_the_teacher_was_absent(): void
    {
        $jadwal = Jadwal::where('hari', Ringkasan::hariIni())->firstOrFail();
        Jurnal::where('jadwal_id', $jadwal->id)->whereDate('tanggal', '2026-08-24')->delete();

        $this->actingAs($this->admin())->post('/jurnal', [
            'jadwal_id' => $jadwal->id,
            'tanggal' => '2026-08-24',
            'materi' => 'Latihan soal',
            'kehadiran_guru' => 'ada_tugas',
            'kehadiran_guru_keterangan' => 'Dinas luar',
        ])->assertRedirect();

        $this->assertSame('Dinas luar', Jurnal::where('jadwal_id', $jadwal->id)
            ->whereDate('tanggal', '2026-08-24')->firstOrFail()->kehadiran_guru_keterangan);
    }

    public function test_a_guru_sees_todays_lessons_as_action_cards(): void
    {
        $jadwal = Jadwal::with(['kelas', 'mataPelajaran'])->where('hari', Ringkasan::hariIni())->firstOrFail();
        $guru = $this->akunGuru($jadwal->guru_nip);

        $this->actingAs($guru)
            ->get('/presensi')
            ->assertOk()
            ->assertSee('Pertemuan Anda Hari Ini')
            ->assertSee('class="pertemuan-grid"', false)
            ->assertSee('presensi '.$jadwal->mataPelajaran->nama.' '.$jadwal->kelas->nama_kelas, false);
    }

    public function test_a_student_sees_attendance_per_subject(): void
    {
        // A plain student: a ketua kelas reads the class recap instead.
        $presensi = DB::table('presensi')
            ->whereNotIn('siswa_nis', Kelas::whereNotNull('ketua_nis')->pluck('ketua_nis'))
            ->first();
        $siswa = $this->akunSiswa($presensi->siswa_nis);

        $this->actingAs($siswa)
            ->get('/presensi?preset=30_hari')
            ->assertOk()
            ->assertSee('Kehadiran per Mata Pelajaran')
            ->assertSee('Predikat')
            ->assertDontSee('Belum ada presensi mata pelajaran pada periode ini.');
    }

    public function test_the_roster_shows_live_attendance_tallies(): void
    {
        $jurnal = Jurnal::with('jadwal')->firstOrFail();
        $guru = $this->akunGuru($jurnal->jadwal->guru_nip);

        $this->actingAs($guru)
            ->get(route('presensi-jurnal.edit', $jurnal))
            ->assertOk()
            ->assertSee('Total Siswa')
            ->assertSee('data-hitung="hadir"', false)
            ->assertSee('Belum Tersimpan');
    }

    public function test_the_login_page_explains_how_to_reset_a_password(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('id="lupaSandi"', false)
            ->assertSee('Minta admin sekolah mengatur ulang kata sandi');
    }

    public function test_the_journal_report_no_longer_credits_the_guru_as_author(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/laporan/jurnal?preset=30_hari')
            ->assertOk()
            ->assertSee('Jurnal Terisi')
            ->assertDontSee('Diisi Guru')
            ->assertDontSee('ditulis guru');
    }
}
