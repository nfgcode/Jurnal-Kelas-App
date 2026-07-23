<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Presensi;
use App\Models\User;
use App\Support\Ringkasan;
use Illuminate\Http\Request;

/**
 * A compact, role-aware KPI summary — the same figures the web dashboards
 * compute (via {@see Ringkasan}), shaped as JSON.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json(match ($user->role) {
            'admin' => $this->admin(),
            'guru' => $this->guru($user),
            default => $this->siswa($user),
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function admin(): array
    {
        $kelengkapan = Ringkasan::kelengkapan('kelas_id');

        return [
            'role' => 'admin',
            'totals' => [
                'guru' => User::where('role', 'guru')->count(),
                'siswa' => User::where('role', 'siswa')->count(),
                'kelas' => Kelas::count(),
                'mata_pelajaran' => MataPelajaran::count(),
                'jadwal' => Jadwal::count(),
                'jurnal' => Jurnal::count(),
            ],
            'presensi' => Ringkasan::presensi(),
            'kehadiran_guru' => Ringkasan::kehadiranGuru(),
            'kelengkapan_rata' => $kelengkapan === []
                ? 0
                : round(array_sum($kelengkapan) / count($kelengkapan)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function guru(User $user): array
    {
        $presensiSaya = Ringkasan::presensi(
            Presensi::whereHas('jurnal', fn ($q) => $q->where('guru_id', $user->id))
        );
        $total = array_sum($presensiSaya) ?: 1;

        $jadwalHariIni = Jadwal::where('guru_id', $user->id)
            ->where('hari', Ringkasan::hariIni())->count();
        $jurnalHariIni = Jurnal::where('guru_id', $user->id)
            ->whereDate('tanggal', today())->count();

        return [
            'role' => 'guru',
            'kpi' => [
                'jadwal_hari_ini' => $jadwalHariIni,
                'jurnal_terisi' => $jurnalHariIni,
                'belum_diisi' => max(0, $jadwalHariIni - $jurnalHariIni),
                'total_jurnal' => Jurnal::where('guru_id', $user->id)->count(),
                'rata_kehadiran' => round($presensiSaya['hadir'] / $total * 100),
            ],
            'presensi' => $presensiSaya,
            'kehadiran_guru' => Ringkasan::kehadiranGuru(Jurnal::where('guru_id', $user->id)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function siswa(User $user): array
    {
        $presensiSaya = Ringkasan::presensi(Presensi::where('siswa_id', $user->id));
        $total = array_sum($presensiSaya) ?: 1;

        $jadwalHariIni = Jadwal::when($user->kelas_id, fn ($q) => $q->where('kelas_id', $user->kelas_id))
            ->where('hari', Ringkasan::hariIni())->count();

        return [
            'role' => 'siswa',
            'kelas_id' => $user->kelas_id,
            'kpi' => [
                'jadwal_hari_ini' => $jadwalHariIni,
                'kehadiran_saya' => round($presensiSaya['hadir'] / $total * 100),
                'hadir' => $presensiSaya['hadir'],
                'alpa' => $presensiSaya['alpa'],
            ],
            'presensi' => $presensiSaya,
        ];
    }
}
