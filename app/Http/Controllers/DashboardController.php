<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Presensi;
use App\Models\User;
use App\Support\Ringkasan;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Route each role to the dashboard written for it. The three are different
     * screens, not one screen with panels hidden.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return $user->isGuru()
            ? $this->dashboardGuru($user)
            : $this->dashboardSiswa($user);
    }

    /**
     * A teacher's own load: today's timetable, how much of it is journalled,
     * and how their own attendance is tracking.
     */
    private function dashboardGuru(User $user)
    {
        $jadwalHariIni = Jadwal::with(['kelas', 'mataPelajaran'])
            ->where('guru_id', $user->id)
            ->where('hari', Ringkasan::hariIni())
            ->orderBy('jam_ke_mulai')
            ->get();

        // Today's journals, indexed by schedule so each row knows its status.
        $jurnalHariIni = Jurnal::withCount([
            'presensis as total_siswa',
            'presensis as hadir_count' => fn ($query) => $query->where('status', 'hadir'),
        ])
            ->where('guru_id', $user->id)
            ->whereDate('tanggal', today())
            ->get()
            ->keyBy('jadwal_id');

        $kelasDiampu = Kelas::whereIn('id', Jadwal::where('guru_id', $user->id)->select('kelas_id'))
            ->orderBy('nama_kelas')
            ->get();

        $presensiSaya = Ringkasan::presensi(
            Presensi::whereHas('jurnal', fn ($query) => $query->where('guru_id', $user->id))
        );
        $totalPresensi = array_sum($presensiSaya) ?: 1;

        // Attendance per class taught, so a struggling class stands out.
        $kehadiranPerKelas = Presensi::query()
            ->join('jurnal', 'presensi.jurnal_id', '=', 'jurnal.id')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->selectRaw('jadwal.kelas_id, presensi.status, COUNT(*) as total')
            ->where('jurnal.guru_id', $user->id)
            ->groupBy('jadwal.kelas_id', 'presensi.status')
            ->get()
            ->groupBy('kelas_id')
            ->map(fn ($rows) => $rows->pluck('total', 'status'));

        return view('dashboard.guru', [
            'jadwalHariIni' => $jadwalHariIni,
            'jurnalHariIni' => $jurnalHariIni,
            'kelasDiampu' => $kelasDiampu,
            'kehadiranPerKelas' => $kehadiranPerKelas,
            'aktivitas' => Ringkasan::harian(Jurnal::where('guru_id', $user->id)),
            'kehadiranGuru' => Ringkasan::kehadiranGuru(Jurnal::where('guru_id', $user->id)),
            'presensiSaya' => $presensiSaya,
            'kpi' => [
                'jadwalHariIni' => $jadwalHariIni->count(),
                'jurnalTerisi' => $jurnalHariIni->count(),
                'belumDiisi' => max(0, $jadwalHariIni->count() - $jurnalHariIni->count()),
                'kelasDiampu' => $kelasDiampu->count(),
                'siswaDiampu' => User::where('role', 'siswa')->whereIn('kelas_id', $kelasDiampu->pluck('id'))->count(),
                'rataKehadiran' => round($presensiSaya['hadir'] / $totalPresensi * 100),
            ],
            'jurnalTerakhir' => Jurnal::with(['jadwal.kelas', 'jadwal.mataPelajaran'])
                ->where('guru_id', $user->id)
                ->latest('tanggal')
                ->latest('id')
                ->take(5)
                ->get(),
            'heatmap' => Ringkasan::heatmapJurnal($kelasDiampu),
        ]);
    }

    /**
     * A student's own class: today's lessons, whether each was journalled, and
     * their personal attendance record.
     */
    private function dashboardSiswa(User $user)
    {
        $kelas = $user->kelas;

        $jadwalHariIni = Jadwal::with(['mataPelajaran', 'guru'])
            ->when($kelas, fn ($query) => $query->where('kelas_id', $kelas->id))
            ->where('hari', Ringkasan::hariIni())
            ->orderBy('jam_ke_mulai')
            ->get();

        $jurnalHariIni = Jurnal::whereIn('jadwal_id', $jadwalHariIni->pluck('id'))
            ->whereDate('tanggal', today())
            ->get()
            ->keyBy('jadwal_id');

        $presensiSaya = Ringkasan::presensi(Presensi::where('siswa_id', $user->id));
        $totalPresensi = array_sum($presensiSaya) ?: 1;

        $jurnalKelas = Jurnal::with(['jadwal.mataPelajaran', 'guru'])
            ->when($kelas, fn ($query) => $query->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $kelas->id)))
            ->latest('tanggal')
            ->latest('id')
            ->get();

        $tepatWaktu = $jurnalKelas->filter(fn ($j) => $j->statusPengisian()['label'] === 'Terisi')->count();

        // Attendance broken down per subject, busiest subjects first.
        $kehadiranPerMapel = Presensi::query()
            ->join('jurnal', 'presensi.jurnal_id', '=', 'jurnal.id')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->join('mata_pelajaran', 'jadwal.mata_pelajaran_id', '=', 'mata_pelajaran.id')
            ->selectRaw('mata_pelajaran.nama as mapel, presensi.status, COUNT(*) as total')
            ->where('presensi.siswa_id', $user->id)
            ->groupBy('mata_pelajaran.nama', 'presensi.status')
            ->get()
            ->groupBy('mapel')
            ->map(fn ($rows) => $rows->pluck('total', 'status'))
            ->sortByDesc(fn ($rows) => $rows->sum())
            ->take(5);

        return view('dashboard.siswa', [
            'kelas' => $kelas,
            'jadwalHariIni' => $jadwalHariIni,
            'jurnalHariIni' => $jurnalHariIni,
            'presensiSaya' => $presensiSaya,
            'kehadiranPerMapel' => $kehadiranPerMapel,
            'kpi' => [
                'jadwalHariIni' => $jadwalHariIni->count(),
                'jurnalTerisi' => $jurnalHariIni->count(),
                'belumDiisi' => max(0, $jadwalHariIni->count() - $jurnalHariIni->count()),
                'kehadiranSaya' => round($presensiSaya['hadir'] / $totalPresensi * 100),
                'hadir' => $presensiSaya['hadir'],
                'alpa' => $presensiSaya['alpa'],
            ],
            'jurnalStatus' => [
                'kelengkapan' => $kelas ? (Ringkasan::kelengkapan('kelas_id')[$kelas->id] ?? 0) : 0,
                'tepatWaktu' => $tepatWaktu,
                'terlambat' => $jurnalKelas->count() - $tepatWaktu,
                'total' => $jurnalKelas->count(),
            ],
            'riwayatJurnal' => $jurnalKelas->take(5),
            'heatmap' => $this->heatmapKehadiran($user),
        ]);
    }

    /**
     * The student's own attendance: one row per subject, one cell per school
     * day, shaded by the status recorded that day.
     *
     * @return array<string, array<string, string|int>>
     */
    private function heatmapKehadiran(User $user, int $days = 20): array
    {
        $tanggalList = Ringkasan::hariSekolah($days);

        $catatan = Presensi::query()
            ->join('jurnal', 'presensi.jurnal_id', '=', 'jurnal.id')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->join('mata_pelajaran', 'jadwal.mata_pelajaran_id', '=', 'mata_pelajaran.id')
            ->selectRaw('mata_pelajaran.nama as mapel, jurnal.tanggal, presensi.status')
            ->where('presensi.siswa_id', $user->id)
            ->whereIn('jurnal.tanggal', array_map(fn ($t) => $t->toDateString(), $tanggalList))
            ->get()
            ->groupBy('mapel');

        $rows = [];

        foreach ($catatan->take(5) as $mapel => $entries) {
            $perTanggal = $entries->keyBy(fn ($e) => Carbon::parse($e->tanggal)->toDateString());
            $cells = [];

            foreach ($tanggalList as $tanggal) {
                // No lesson that day reads as an empty cell, not as an absence.
                $cells[$tanggal->format('j')] = $perTanggal[$tanggal->toDateString()]->status ?? 0;
            }

            $rows[$mapel] = $cells;
        }

        return $rows;
    }
}
