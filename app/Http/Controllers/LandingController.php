<?php

namespace App\Http\Controllers;

use App\Support\Ringkasan;

/**
 * The public landing page. Its preview figures are computed live from the
 * database (school-wide journal completeness and the teacher-attendance
 * rollup) rather than hardcoded, falling back to zero on an empty install.
 */
class LandingController extends Controller
{
    public function index()
    {
        $kelengkapan = Ringkasan::kelengkapan('kelas_id');

        return view('welcome', [
            'kelengkapan' => $kelengkapan === []
                ? 0
                : (int) round(array_sum($kelengkapan) / count($kelengkapan)),
            'kehadiranGuru' => Ringkasan::kehadiranGuru(),
        ]);
    }
}
