<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Presensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatistikController extends Controller
{
    /**
     * Attendance percentages via the stored functions on MySQL, with an
     * equivalent aggregate as the fallback. A student may only see their own.
     */
    public function kehadiran(Request $request)
    {
        $user = $request->user();
        $mysql = DB::connection()->getDriverName() === 'mysql';

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
                : ($mysql
                    ? (float) DB::selectOne('SELECT fn_persentase_kehadiran_siswa(?) AS p', [$siswaId])->p
                    : $this->pctSiswa($siswaId)),
            'kelas_id' => $kelasId,
            'persentase_kehadiran_kelas' => $kelasId === null ? null
                : ($mysql
                    ? (float) DB::selectOne('SELECT fn_persentase_kehadiran_kelas(?) AS p', [$kelasId])->p
                    : $this->pctKelas($kelasId)),
        ]);
    }

    private function pctSiswa(int $siswaId): float
    {
        $r = Presensi::where('siswa_id', $siswaId)
            ->selectRaw("COUNT(*) t, SUM(status = 'hadir') h")->first();

        return $r->t ? round($r->h / $r->t * 100, 2) : 0.0;
    }

    private function pctKelas(int $kelasId): float
    {
        $r = Presensi::query()
            ->join('jurnal', 'presensi.jurnal_id', '=', 'jurnal.id')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->where('jadwal.kelas_id', $kelasId)
            ->selectRaw("COUNT(*) t, SUM(presensi.status = 'hadir') h")->first();

        return $r->t ? round($r->h / $r->t * 100, 2) : 0.0;
    }
}
