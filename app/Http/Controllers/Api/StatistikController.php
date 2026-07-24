<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Ringkasan;
use Illuminate\Http\Request;

class StatistikController extends Controller
{
    /**
     * Attendance percentages (stored functions on MySQL, aggregate fallback
     * elsewhere — see Ringkasan). A student may only see their own.
     */
    public function kehadiran(Request $request)
    {
        $user = $request->user();

        if ($user->isSiswa()) {
            $siswaId = $user->id;
            $kelasId = $user->kelas_id;
        } else {
            $filters = $request->validate([
                'siswa_id' => ['nullable', 'exists:users,id'],
                'kelas_id' => ['nullable', 'exists:kelas,id'],
            ]);
            $siswaId = $filters['siswa_id'] ?? null;
            $kelasId = $filters['kelas_id'] ?? null;
        }

        return response()->json([
            'siswa_id' => $siswaId,
            'persentase_kehadiran_siswa' => $siswaId === null ? null
                : Ringkasan::persentaseKehadiranSiswa($siswaId),
            'kelas_id' => $kelasId,
            'persentase_kehadiran_kelas' => $kelasId === null ? null
                : Ringkasan::persentaseKehadiranKelas($kelasId),
        ]);
    }
}
