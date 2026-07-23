<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LaporanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\JurnalController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\MataPelajaranController;
use App\Http\Controllers\PresensiController;
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
Route::get('/', [\App\Http\Controllers\LandingController::class, 'index'])->name('landing');

// Authentication routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Protected routes (require authentication)
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Kelas management
    Route::resource('kelas', KelasController::class);

    // Mata Pelajaran management
    Route::resource('mata-pelajaran', MataPelajaranController::class);

    // Jadwal management
    Route::resource('jadwal', JadwalController::class);

    // Jurnal management
    Route::resource('jurnal', JurnalController::class);

    // Presensi management
    Route::get('/presensi', [PresensiController::class, 'index'])->name('presensi.index');
    Route::get('/presensi/create/{jurnal_id}', [PresensiController::class, 'create'])->name('presensi.create');
    Route::post('/presensi', [PresensiController::class, 'store'])->name('presensi.store');
    Route::get('/presensi/{jurnal_id}', [PresensiController::class, 'show'])->name('presensi.show');

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

        // Read-only reports
        Route::get('/laporan/jurnal', [LaporanController::class, 'jurnal'])->name('laporan.jurnal');
        Route::get('/laporan/presensi', [LaporanController::class, 'presensi'])->name('laporan.presensi');
    });
});
