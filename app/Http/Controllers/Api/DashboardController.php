<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\PresensiHarian;
use App\Models\User;
use App\Support\Ringkasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Six separate COUNT(*) round trips for six numbers became two: one
        // grouped pass over users (the same idiom Admin\UserController uses), and
        // one statement whose scalar subqueries each resolve from an index.
        $peran = User::selectRaw('role, COUNT(*) as total')
            ->whereIn('role', ['guru', 'siswa'])
            ->groupBy('role')
            ->pluck('total', 'role');

        $baris = DB::selectOne(
            'select (select count(*) from kelas) as kelas,'
            .' (select count(*) from mata_pelajaran) as mata_pelajaran,'
            .' (select count(*) from jadwal) as jadwal,'
            // People-written journals only, matching the web dashboard's KPI —
            // otherwise the same named figure differs between web and API. The
            // NULL arm keeps rows whose peran predates the column.
            ." (select count(*) from jurnal where diisi_oleh_peran is null or diisi_oleh_peran <> 'sistem') as jurnal"
        );

        return [
            'role' => 'admin',
            'totals' => [
                'guru' => (int) ($peran['guru'] ?? 0),
                'siswa' => (int) ($peran['siswa'] ?? 0),
                'kelas' => (int) $baris->kelas,
                'mata_pelajaran' => (int) $baris->mata_pelajaran,
                'jadwal' => (int) $baris->jadwal,
                'jurnal' => (int) $baris->jurnal,
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
        // The classes this teacher takes, not a roster they own: attendance is
        // one daily roll call per class, filed by its ketua kelas.
        $presensiSaya = Ringkasan::presensi(
            PresensiHarian::whereIn('kelas_id', Jadwal::where('guru_id', $user->id)->select('kelas_id'))
        );
        $total = array_sum($presensiSaya) ?: 1;

        $jadwalHariIni = Jadwal::where('guru_id', $user->id)
            ->where('hari', Ringkasan::hariIni())->count();
        // What this teacher filled in themselves — a backfill placeholder means
        // the opposite, so counting it would shrink "belum_diisi" wrongly.
        $jurnalHariIni = Jurnal::manusia()->where('guru_id', $user->id)
            ->whereDate('tanggal', today())->count();

        return [
            'role' => 'guru',
            'kpi' => [
                'jadwal_hari_ini' => $jadwalHariIni,
                'jurnal_terisi' => $jurnalHariIni,
                'belum_diisi' => max(0, $jadwalHariIni - $jurnalHariIni),
                'total_jurnal' => Jurnal::manusia()->where('guru_id', $user->id)->count(),
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
        $presensiSaya = Ringkasan::presensi(PresensiHarian::where('siswa_id', $user->id));
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
