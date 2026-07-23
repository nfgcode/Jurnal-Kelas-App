<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Presensi;
use App\Support\Ringkasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The homeroom-teacher view, reached from the "Mode Wali Kelas" toggle. Shows
 * the class a guru is wali kelas of: its roster with per-student attendance,
 * the class attendance rollup, journal completeness, and recent journals.
 */
class WaliKelasController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $kelasWali = Kelas::where('wali_kelas_id', $user->id)
            ->orderBy('nama_kelas')
            ->get();

        abort_if($kelasWali->isEmpty(), 403, 'Anda bukan wali kelas mana pun.');

        // Which homeroom class to show when a teacher chairs more than one.
        $kelas = $kelasWali->firstWhere('id', $request->integer('kelas_id')) ?? $kelasWali->first();

        $siswa = $kelas->siswa()->orderBy('name')->get();
        $persentase = $this->persentaseKehadiran($siswa->pluck('id')->all());

        // Class-wide attendance and journal completeness over the standard window.
        $presensiKelas = Ringkasan::presensi(
            Presensi::whereHas('jurnal.jadwal', fn ($q) => $q->where('kelas_id', $kelas->id))
        );
        $kelengkapan = Ringkasan::kelengkapan('kelas_id')[$kelas->id] ?? 0;
        $totalPresensi = array_sum($presensiKelas) ?: 1;

        return view('wali-kelas.index', [
            'kelasWali' => $kelasWali,
            'kelas' => $kelas,
            'siswa' => $siswa,
            'persentase' => $persentase,
            'presensiKelas' => $presensiKelas,
            'kelengkapan' => $kelengkapan,
            'kpi' => [
                'jumlah_siswa' => $siswa->count(),
                'rata_kehadiran' => round($presensiKelas['hadir'] / $totalPresensi * 100),
                'kelengkapan' => $kelengkapan,
                'alpa' => $presensiKelas['alpa'],
            ],
            'jurnalTerakhir' => Jurnal::with(['jadwal.mataPelajaran', 'guru'])
                ->whereHas('jadwal', fn ($q) => $q->where('kelas_id', $kelas->id))
                ->latest('tanggal')
                ->latest('id')
                ->take(5)
                ->get(),
        ]);
    }

    /**
     * Per-student attendance %, keyed by student id — computed by the stored
     * function on MySQL (one call per row in a single query), an equivalent
     * aggregate elsewhere.
     *
     * @param  array<int>  $siswaIds
     * @return array<int, float>
     */
    private function persentaseKehadiran(array $siswaIds): array
    {
        if ($siswaIds === []) {
            return [];
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            return DB::table('users')
                ->whereIn('id', $siswaIds)
                ->selectRaw('id, fn_persentase_kehadiran_siswa(id) AS p')
                ->pluck('p', 'id')
                ->map(fn ($v) => (float) $v)
                ->all();
        }

        return Presensi::whereIn('siswa_id', $siswaIds)
            ->selectRaw("siswa_id, ROUND(SUM(status = 'hadir') / COUNT(*) * 100, 2) AS p")
            ->groupBy('siswa_id')
            ->pluck('p', 'siswa_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
