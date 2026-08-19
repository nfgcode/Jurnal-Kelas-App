<?php

use App\Http\Controllers\Admin\CadanganController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ImporController;
use App\Http\Controllers\Admin\KelasQrController;
use App\Http\Controllers\Admin\LaporanController;
use App\Http\Controllers\Admin\PresensiLogController;
use App\Http\Controllers\Admin\SistemController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\JurnalController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LaporanErrorController;
use App\Http\Controllers\MataPelajaranController;
use App\Http\Controllers\PresensiController;
use App\Http\Controllers\PresensiHarianController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\WaliKelasController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
|
*/

// Public landing page (preview figures computed live from the DB)
Route::get('/', [LandingController::class, 'index'])->name('landing');

// Authentication routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Protected routes (require authentication)
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
    |----------------------------------------------------------------------
    | "Mode Wali Kelas" — the guru screens narrowed to the homeroom class,
    | reached from the topbar toggle. Access is checked per action.
    |----------------------------------------------------------------------
    */
    Route::prefix('wali-kelas')->name('wali-kelas.')->group(function () {
        Route::get('/', [WaliKelasController::class, 'index'])->name('dashboard');
        Route::get('/siswa', [WaliKelasController::class, 'siswa'])->name('siswa');
        Route::get('/jadwal', [WaliKelasController::class, 'jadwal'])->name('jadwal');
        Route::get('/jurnal', [WaliKelasController::class, 'jurnal'])->name('jurnal');
        Route::get('/presensi', [WaliKelasController::class, 'presensi'])->name('presensi');
    });

    /*
    |----------------------------------------------------------------------
    | Master data. Kelas & Mata Pelajaran are read by admin+guru only (a guru
    | sees only what they teach, enforced in the controller); students never
    | reach them. Jadwal is readable by everyone — a student needs their own
    | class timetable. All writes are admin-only.
    |
    | The admin write routes are registered BEFORE the read routes so the
    | literal `/{resource}/create` path is matched before the `/{resource}/{id}`
    | show route would otherwise swallow "create" as an id.
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {
        Route::resource('kelas', KelasController::class)->except(['index', 'show']);
        Route::resource('mata-pelajaran', MataPelajaranController::class)->except(['index', 'show']);
        Route::resource('jadwal', JadwalController::class)->except(['index', 'show']);
    });

    Route::middleware('role:admin,guru')->group(function () {
        Route::resource('kelas', KelasController::class)->only(['index', 'show']);
        Route::resource('mata-pelajaran', MataPelajaranController::class)->only(['index', 'show']);
    });

    Route::resource('jadwal', JadwalController::class)->only(['index', 'show']);

    // Jurnal management — index/show scoped per role in the controller; writes
    // are gated per-record by JurnalPolicy inside the controller actions.
    Route::resource('jurnal', JurnalController::class);

    /*
    |----------------------------------------------------------------------
    | Presensi. Reading and writing are two different screens now, because
    | they are two different jobs: a roster is filed once a day for a whole
    | class by its ketua kelas, and everyone else reads the recap.
    |----------------------------------------------------------------------
    */

    // Read side — scoped per role in the controller. The export is guru/admin
    // only; a student has nothing to export but their own visible record.
    Route::get('/presensi', [PresensiController::class, 'index'])->name('presensi.index');
    Route::get('/presensi/ekspor', [PresensiController::class, 'ekspor'])
        ->middleware('role:guru,admin')->name('presensi.ekspor');

    // Write side — one roster per class per day, gated by
    // KelasPolicy::isiPresensiHarian (ketua kelas of that class, or admin).
    // `isi` is registered before the bare show route so it is never read as a date.
    Route::prefix('presensi-harian/{kelas}')->name('presensi-harian.')->group(function () {
        Route::get('/isi', [PresensiHarianController::class, 'edit'])->name('edit');
        Route::post('/', [PresensiHarianController::class, 'store'])->name('store');
        Route::get('/', [PresensiHarianController::class, 'show'])->name('show');
    });

    // Error report a guru/siswa submits from the friendly error page. Throttled
    // and deduped in the controller; technical details come from the session.
    Route::post('/laporan-error', [LaporanErrorController::class, 'store'])->name('laporan-error.store');

    // QR entry point: a room's QR encodes this URL for its class. A guru scans
    // it, lands on a confirmation of the class + their subjects there, then
    // continues into the normal journal→presensi flow. Guru-only; the class is
    // resolved by its opaque qr_token, not its id.
    Route::get('/qr/{kelas:qr_token}', [QrController::class, 'show'])
        ->middleware('role:guru')->name('qr.show');

    /*
    |----------------------------------------------------------------------
    | Admin-only routes
    |----------------------------------------------------------------------
    */
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        // Admin dashboard
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // JSON drill-down behind the dashboard's clickable figures.
        Route::get('/dashboard/detail', [AdminDashboardController::class, 'detail'])->name('dashboard.detail');

        // User management (admin, guru, siswa accounts)
        Route::resource('users', UserController::class);

        // Bulk account creation from a spreadsheet: template out, filled file
        // in, preview, then commit.
        Route::get('/impor', [ImporController::class, 'index'])->name('impor.index');
        Route::get('/impor/template', [ImporController::class, 'template'])->name('impor.template');
        Route::post('/impor/pratinjau', [ImporController::class, 'pratinjau'])->name('impor.pratinjau');
        Route::post('/impor', [ImporController::class, 'simpan'])->name('impor.simpan');

        // Read-only reports
        Route::get('/laporan/jurnal', [LaporanController::class, 'jurnal'])->name('laporan.jurnal');
        Route::get('/laporan/presensi', [LaporanController::class, 'presensi'])->name('laporan.presensi');

        // Audit trail of who edited attendance rosters — admin-only.
        Route::get('/presensi-log', [PresensiLogController::class, 'index'])->name('presensi.log');

        // Printable per-room QR codes (one per class) for teachers to scan.
        Route::get('/kelas-qr', [KelasQrController::class, 'index'])->name('kelas-qr.index');
        Route::get('/kelas-qr/pdf', [KelasQrController::class, 'pdf'])->name('kelas-qr.pdf');

        // Backup & restore of the whole dataset — JSON snapshot (also the restore
        // format) plus a readable XLSX workbook. For server moves / recovery.
        Route::get('/cadangan', [CadanganController::class, 'index'])->name('cadangan.index');
        Route::get('/cadangan/json', [CadanganController::class, 'unduhJson'])->name('cadangan.json');
        Route::get('/cadangan/xlsx', [CadanganController::class, 'unduhXlsx'])->name('cadangan.xlsx');
        Route::post('/cadangan/pulihkan', [CadanganController::class, 'pulihkan'])->name('cadangan.pulihkan');

        /*
        |------------------------------------------------------------------
        | Sistem & Log — health of the app, the error log, the inbox of
        | reports from guru/siswa, and the banners shown to them.
        |------------------------------------------------------------------
        */
        Route::get('/sistem', [SistemController::class, 'index'])->name('sistem.index');
        Route::post('/sistem/laporan/{laporan}', [SistemController::class, 'ubahStatusLaporan'])->name('sistem.laporan.status');
        Route::post('/sistem/pengumuman', [SistemController::class, 'simpanPengumuman'])->name('sistem.pengumuman.simpan');
        Route::post('/sistem/pengumuman/{pengumuman}/alih', [SistemController::class, 'alihkanPengumuman'])->name('sistem.pengumuman.alih');
        Route::post('/sistem/log/bersihkan', [SistemController::class, 'bersihkanLog'])->name('sistem.log.bersihkan');
    });
});
