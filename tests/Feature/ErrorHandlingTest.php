<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\LaporanError;
use App\Models\Pengumuman;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Errors must never show a guru or siswa a Laravel stack trace: they get a
 * friendly page, and may report the problem (rate limited). Admin keeps the full
 * debug output, and owns the Sistem & Log page where reports land.
 */
class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    private User $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->guru = User::where('role', 'guru')->firstOrFail();
        $this->siswa = User::where('role', 'siswa')->firstOrFail();
    }

    /** A route that always fails, to exercise the exception renderer. */
    private function rutePeledak(): string
    {
        Route::middleware('web')->get('/__meledak', function () {
            throw new RuntimeException('Ledakan uji coba');
        });

        return '/__meledak';
    }

    /** The technical payload the renderer would have stashed for a real error. */
    private function sesiError(array $ganti = []): array
    {
        return ['sistem.error_terakhir' => array_merge([
            'ref' => 'UJICOBA1',
            'status' => 500,
            'pesan' => 'Ledakan uji coba',
            'file' => '/var/www/html/app/Contoh.php',
            'line' => 42,
            'url' => 'http://localhost/contoh',
            'pada' => now()->toDateTimeString(),
        ], $ganti)];
    }

    public function test_a_guru_sees_a_friendly_page_instead_of_a_stack_trace(): void
    {
        $url = $this->rutePeledak();

        $response = $this->actingAs($this->guru)->get($url);

        $response->assertStatus(500)
            ->assertSee('Terjadi kesalahan')
            ->assertSee('Kode referensi')
            // The whole point: no exception class, no file path, no code.
            ->assertDontSee('RuntimeException')
            ->assertDontSee('Ledakan uji coba')
            ->assertDontSee('/var/www/html', false);
    }

    public function test_a_siswa_also_sees_the_friendly_page(): void
    {
        $url = $this->rutePeledak();

        $this->actingAs($this->siswa)->get($url)
            ->assertStatus(500)
            ->assertSee('Terjadi kesalahan')
            ->assertDontSee('RuntimeException');
    }

    public function test_an_admin_keeps_the_full_laravel_error_output(): void
    {
        $url = $this->rutePeledak();

        // Admin is the one debugging, so the friendly page must NOT intercept:
        // the real exception class is proof the default handler ran.
        $this->actingAs($this->admin)->get($url)
            ->assertStatus(500)
            ->assertSee('RuntimeException');
    }

    public function test_a_missing_page_is_a_friendly_404_not_a_500(): void
    {
        // A routeless URL never passes through the web group, so it has no
        // session — the renderer must cope rather than turn a 404 into a 500.
        $this->actingAs($this->guru)->get('/jalan-yang-tidak-ada')
            ->assertStatus(404)
            ->assertSee('Halaman tidak ditemukan');
    }

    /**
     * Regression: the renderer must not treat a validation failure as a fault.
     * It intercepted ValidationException once (it is not an HttpExceptionInterface,
     * so it computed 500), which turned every invalid form submission by a guru or
     * siswa into an apology page instead of returning to the form with errors.
     */
    public function test_a_validation_failure_still_returns_to_the_form_with_errors(): void
    {
        $jadwal = Jadwal::firstOrFail();
        $guru = User::findOrFail($jadwal->guru_id);

        $this->actingAs($guru)
            ->from('/jurnal/create')
            ->post('/jurnal', [
                'jadwal_id' => $jadwal->id,
                'tanggal' => now()->toDateString(),
                // materi is required, and kehadiran_guru is not a valid option.
                'kehadiran_guru' => 'bukan-pilihan',
            ])
            ->assertRedirect('/jurnal/create')
            ->assertSessionHasErrors(['materi', 'kehadiran_guru']);
    }

    /** Regression: an unauthenticated visitor belongs at the login page. */
    public function test_a_guest_is_redirected_to_login_not_shown_an_error(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_a_report_stores_the_technical_details_from_the_session(): void
    {
        $this->actingAs($this->guru)
            ->withSession($this->sesiError())
            ->post('/laporan-error', ['pesan' => 'Saya menekan simpan lalu gagal'])
            ->assertRedirect();

        $this->assertDatabaseHas('laporan_error', [
            'user_id' => $this->guru->id,
            'ref' => 'UJICOBA1',
            'pesan' => 'Saya menekan simpan lalu gagal',
            'exception_pesan' => 'Ledakan uji coba',
            'exception_line' => 42,
            'status' => 'baru',
            'jumlah' => 1,
        ]);
    }

    public function test_a_second_report_within_the_window_is_throttled(): void
    {
        $this->actingAs($this->guru)
            ->withSession($this->sesiError())
            ->post('/laporan-error', ['pesan' => 'Pertama']);

        // A different fault this time, so dedupe cannot absorb it — the throttle
        // is what must stop it.
        $this->actingAs($this->guru)
            ->withSession($this->sesiError(['pesan' => 'Ledakan lain', 'line' => 99, 'ref' => 'UJICOBA2']))
            ->post('/laporan-error', ['pesan' => 'Kedua'])
            ->assertSessionHas('lapor_gagal');

        $this->assertSame(1, LaporanError::count(), 'Laporan kedua seharusnya tertahan throttle.');
    }

    public function test_reporting_the_same_fault_again_increments_the_counter(): void
    {
        $sesi = $this->sesiError();

        $this->actingAs($this->guru)->withSession($sesi)->post('/laporan-error', ['pesan' => 'Pertama']);
        // Same signature → merged into the open report, and not blocked by the
        // throttle, because a recurrence is useful signal.
        $this->actingAs($this->guru)->withSession($sesi)->post('/laporan-error', ['pesan' => 'Terjadi lagi'])
            ->assertSessionHas('lapor_sukses');

        $this->assertSame(1, LaporanError::count());
        $this->assertSame(2, LaporanError::first()->jumlah);
    }

    public function test_the_sistem_page_is_admin_only(): void
    {
        $this->actingAs($this->admin)->get('/admin/sistem')
            ->assertOk()
            ->assertSee('Status Komponen')
            ->assertSee('Laporan Error dari Pengguna');

        $this->actingAs($this->guru)->get('/admin/sistem')->assertForbidden();
        $this->actingAs($this->siswa)->get('/admin/sistem')->assertForbidden();
    }

    public function test_an_admin_can_triage_a_report(): void
    {
        $laporan = LaporanError::create([
            'user_id' => $this->guru->id,
            'ref' => 'UJICOBA9',
            'tanda_tangan' => md5('a'),
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/sistem/laporan/{$laporan->id}", ['status' => 'selesai'])
            ->assertRedirect();

        $this->assertSame('selesai', $laporan->fresh()->status);
    }

    public function test_an_announcement_banner_shows_to_guru_and_siswa_but_not_admin(): void
    {
        Pengumuman::create([
            'pesan' => 'Server dimatikan pukul 17.00 untuk pemeliharaan.',
            'tipe' => 'maintenance',
            'aktif' => true,
            'mulai' => now()->subMinute(),
            'dibuat_oleh_id' => $this->admin->id,
        ]);

        $this->actingAs($this->guru)->get('/dashboard')->assertSee('dimatikan pukul 17.00');
        $this->actingAs($this->siswa)->get('/dashboard')->assertSee('dimatikan pukul 17.00');
        // Admin manages announcements; they don't need to be nagged by them.
        $this->actingAs($this->admin)->get('/admin')->assertDontSee('dimatikan pukul 17.00');
    }

    public function test_a_switched_off_announcement_does_not_show(): void
    {
        Pengumuman::create([
            'pesan' => 'Pengumuman lama yang sudah dimatikan.',
            'tipe' => 'info',
            'aktif' => false,
            'dibuat_oleh_id' => $this->admin->id,
        ]);

        $this->actingAs($this->guru)->get('/dashboard')->assertDontSee('Pengumuman lama');
    }
}
