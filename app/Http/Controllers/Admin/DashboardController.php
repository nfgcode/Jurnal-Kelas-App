<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\User;
use App\Support\Ringkasan;

class DashboardController extends Controller
{
    /**
     * School-wide overview: headline counts, the 14-day fill trend, attendance
     * and teacher-attendance rollups, and the per-class completeness grid.
     */
    public function index()
    {
        $kelasList = Kelas::orderBy('tingkat')->orderBy('nama_kelas')->get();

        $kpi = [
            'siswa' => User::where('role', 'siswa')->count(),
            'guru' => User::where('role', 'guru')->count(),
            'kelas' => $kelasList->count(),
            'mapel' => MataPelajaran::count(),
            'jadwal' => Jadwal::count(),
            'jurnal' => Jurnal::count(),
        ];

        $pengisian = Ringkasan::harian(Jurnal::query(), 14);

        // Week-on-week movement in journal writing — the only headline figure
        // with a genuine time series behind it.
        $mingguIni = array_sum(array_slice($pengisian, -7));
        $mingguLalu = array_sum(array_slice($pengisian, 0, 7));
        $trenJurnal = $mingguLalu > 0
            ? round(($mingguIni - $mingguLalu) / $mingguLalu * 100, 1)
            : 0;

        // Teachers whose absence was not covered by an assignment are the ones
        // the "perlu perhatian" callout points at.
        $guruPerluPerhatian = Jurnal::query()
            ->where('kehadiran_guru_status', 'tidak_hadir')
            ->where(fn ($query) => $query->whereNull('kehadiran_guru_ada_tugas')->orWhere('kehadiran_guru_ada_tugas', false))
            ->distinct()
            ->count('guru_id');

        $jurnalTerbaru = Jurnal::with(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])
            ->withCount([
                'presensis as total_presensi',
                'presensis as hadir_count' => fn ($query) => $query->where('status', 'hadir'),
            ])
            ->latest('tanggal')
            ->latest('id')
            ->take(7)
            ->get();

        return view('admin.dashboard', [
            'kpi' => $kpi,
            'trenJurnal' => $trenJurnal,
            'pengisian' => $pengisian,
            'presensi' => Ringkasan::presensi(),
            'kehadiranGuru' => Ringkasan::kehadiranGuru(),
            'guruPerluPerhatian' => $guruPerluPerhatian,
            'guruTeraktif' => User::where('role', 'guru')
                ->withCount('jurnals')
                ->orderByDesc('jurnals_count')
                ->take(5)
                ->get(),
            'jurnalTerbaru' => $jurnalTerbaru,
            'heatmap' => Ringkasan::heatmapJurnal($kelasList, 20),
        ]);
    }
}
