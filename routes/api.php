<?php

use App\Http\Controllers\Api\Admin\LaporanController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\JadwalController;
use App\Http\Controllers\Api\JurnalController;
use App\Http\Controllers\Api\KelasController;
use App\Http\Controllers\Api\MataPelajaranController;
use App\Http\Controllers\Api\PresensiController;
use App\Http\Controllers\Api\StatistikController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Token-authenticated JSON API (Laravel Sanctum). Every route below is
| prefixed with /api. Errors render as JSON (see bootstrap/app.php's
| shouldRenderJsonWhen('api/*')).
|
*/

// Public: exchange credentials for a bearer token.
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Session
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    // Role-aware KPI summary
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Master data — reads open to any authenticated user
    Route::apiResource('kelas', KelasController::class)
        ->only(['index', 'show'])->parameters(['kelas' => 'kelas']);
    Route::apiResource('mata-pelajaran', MataPelajaranController::class)
        ->only(['index', 'show'])->parameters(['mata-pelajaran' => 'mataPelajaran']);
    Route::apiResource('jadwal', JadwalController::class)->only(['index', 'show']);

    // Jurnal — index/show role-scoped in the controller; writes authorized by policy
    Route::get('jurnal/{jurnal}/audit', [JurnalController::class, 'audit']);
    Route::apiResource('jurnal', JurnalController::class);

    // Attendance percentages (stored functions)
    Route::get('statistik/kehadiran', [StatistikController::class, 'kehadiran']);

    // Presensi — attendance is addressed by its jurnal
    Route::get('presensi', [PresensiController::class, 'index']);
    Route::post('presensi', [PresensiController::class, 'store']);
    Route::get('presensi/{jurnal}', [PresensiController::class, 'show']);

    // Admin-only management
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('kelas', KelasController::class)
            ->except(['index', 'show'])->parameters(['kelas' => 'kelas']);
        Route::apiResource('mata-pelajaran', MataPelajaranController::class)
            ->except(['index', 'show'])->parameters(['mata-pelajaran' => 'mataPelajaran']);
        Route::apiResource('jadwal', JadwalController::class)->except(['index', 'show']);
        Route::apiResource('users', UserController::class);

        Route::get('laporan/jurnal', [LaporanController::class, 'jurnal']);
        Route::get('laporan/presensi', [LaporanController::class, 'presensi']);
        Route::get('laporan/rekap-kelas', [LaporanController::class, 'rekapKelas']);
    });
});
