<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with summary statistics.
     */
    public function index()
    {
        $totalSiswa = User::where('role', 'siswa')->count();
        $totalGuru = User::where('role', 'guru')->count();
        $totalKelas = Kelas::count();
        $totalJurnalHariIni = Jurnal::whereDate('tanggal', Carbon::today())->count();

        return view('dashboard', compact(
            'totalSiswa',
            'totalGuru',
            'totalKelas',
            'totalJurnalHariIni',
        ));
    }
}
