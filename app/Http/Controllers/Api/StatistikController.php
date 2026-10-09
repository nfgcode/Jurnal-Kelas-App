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
            // The account's own NIS, not its id: attendance is keyed by
            // siswa.nis, and the field this endpoint answers with is siswa_nis.
            $nis = $user->nis;
            $kelasId = $user->kelas_id;
        } else {
            $filters = $request->validate([
                'siswa_nis' => ['nullable', 'exists:siswa,nis'],
                'kelas_id' => ['nullable', 'exists:kelas,id'],
            ]);
            $nis = $filters['siswa_nis'] ?? null;
            $kelasId = $filters['kelas_id'] ?? null;
        }

        return response()->json([
            'siswa_nis' => $nis,
            'persentase_kehadiran_siswa' => $nis === null ? null
                : Ringkasan::persentaseKehadiranSiswa((string) $nis),
            'kelas_id' => $kelasId,
            'persentase_kehadiran_kelas' => $kelasId === null ? null
                : Ringkasan::persentaseKehadiranKelas($kelasId),
        ]);
    }
}
