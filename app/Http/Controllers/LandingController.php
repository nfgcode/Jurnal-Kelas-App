<?php

namespace App\Http\Controllers;

use App\Support\Ringkasan;
use Illuminate\Support\Facades\Cache;

/**
 * The public landing page. Its preview figures are computed live from the
 * database (school-wide journal completeness and the teacher-attendance
 * rollup) rather than hardcoded, falling back to zero on an empty install.
 */
class LandingController extends Controller
{
    public function index()
    {
        // Public page: cache the two school-wide aggregates so anonymous hits
        // don't recompute them. Five minutes of staleness is invisible here.
        $kpi = Cache::remember('landing.kpi', 300, function () {
            $kelengkapan = Ringkasan::kelengkapan('kelas_id');

            return [
                'kelengkapan' => $kelengkapan === []
                    ? 0
                    : (int) round(array_sum($kelengkapan) / count($kelengkapan)),
                'kehadiranGuru' => Ringkasan::kehadiranGuru(),
            ];
        });

        return view('welcome', $kpi);
    }
}
