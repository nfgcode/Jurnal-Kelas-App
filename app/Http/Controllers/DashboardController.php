<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\PresensiHarian;
use App\Models\User;
use App\Support\Ringkasan;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
        $jurnalHariIni = Jurnal::denganPresensiHarian()
            ->where('guru_id', $user->id)
            ->whereDate('tanggal', today())
            ->get()
            ->keyBy('jadwal_id');

        $kelasDiampu = Kelas::whereIn('id', Jadwal::where('guru_id', $user->id)->select('kelas_id'))
            ->orderBy('nama_kelas')
            ->get();

        // Attendance across the classes this teacher takes, not "their" roster:
        // the roll call belongs to the class's day, and several teachers share
        // the same one. It is oversight, which is all a guru needs of it now.
        $kelasIds = $kelasDiampu->pluck('id');

        $presensiSaya = Ringkasan::presensi(PresensiHarian::whereIn('kelas_id', $kelasIds));
        $totalPresensi = array_sum($presensiSaya) ?: 1;

        // Attendance per class taught, so a struggling class stands out.
        $kehadiranPerKelas = PresensiHarian::query()
            ->selectRaw('kelas_id, status, COUNT(*) as total')
            ->whereIn('kelas_id', $kelasIds)
            ->groupBy('kelas_id', 'status')
            ->get()
            ->groupBy('kelas_id')
            ->map(fn ($rows) => $rows->pluck('total', 'status'));

        return view('dashboard.guru', [
            'jadwalHariIni' => $jadwalHariIni,
            'jurnalHariIni' => $jurnalHariIni,
            'kelasDiampu' => $kelasDiampu,
            'kehadiranPerKelas' => $kehadiranPerKelas,
            // Journals this teacher wrote — not the ones the nightly backfill
            // filed under their name, which would draw a full activity chart for
            // a fortnight they actually skipped.
            'aktivitas' => Ringkasan::harian(Jurnal::manusia()->where('guru_id', $user->id)),
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

        // A student with no class sees their own (empty) view, never every
        // class's schedule/journals. kelas_id 0 never matches a real row.
        $kelasId = $kelas?->id ?? 0;

        $jadwalHariIni = Jadwal::with(['mataPelajaran', 'guru'])
            ->where('kelas_id', $kelasId)
            ->where('hari', Ringkasan::hariIni())
            ->orderBy('jam_ke_mulai')
            ->get();

        $jurnalHariIni = Jurnal::whereIn('jadwal_id', $jadwalHariIni->pluck('id'))
            ->whereDate('tanggal', today())
            ->get()
            ->keyBy('jadwal_id');

        // A ketua kelas represents the whole class, so their attendance summary
        // is the class's, not just their own; a regular siswa sees their own.
        $isKetua = $user->isKetuaKelas();
        $kehadiran = $isKetua
            ? Ringkasan::presensi(PresensiHarian::where('kelas_id', $kelasId))
            : Ringkasan::presensi(PresensiHarian::where('siswa_id', $user->id));
        $kehadiranLabel = $isKetua ? 'Kehadiran Kelas' : 'Kehadiran Saya';
        $totalKehadiran = array_sum($kehadiran) ?: 1;

        // One aggregate pass instead of hydrating every journal of the class:
        // "Terisi" = materi filled and not filed late — the same definition
        // statusPengisian() renders, expressed in SQL.
        $agregatJurnal = Jurnal::untukKelas($kelasId)
            ->selectRaw(
                "COUNT(*) as total, SUM(CASE WHEN materi IS NOT NULL AND materi <> '' "
                .'AND NOT ('.Jurnal::ekspresiTerlambat().') THEN 1 ELSE 0 END) as tepat'
            )
            ->first();

        $totalJurnal = (int) $agregatJurnal->total;
        $tepatWaktu = (int) $agregatJurnal->tepat;

        $riwayatJurnal = Jurnal::with(['jadwal.mataPelajaran', 'guru'])
            ->untukKelas($kelasId)
            ->latest('tanggal')
            ->latest('id')
            ->take(5)
            ->get();

        // Attendance broken down per month, most recent first. It used to be per
        // subject; a roll call is taken once for the whole day, so it belongs to
        // no subject in particular and splitting it by one would be a fiction.
        $kehadiranPerBulan = $this->kehadiranPerBulan($user);

        return view('dashboard.siswa', [
            'kelas' => $kelas,
            'jadwalHariIni' => $jadwalHariIni,
            'jurnalHariIni' => $jurnalHariIni,
            'isKetua' => $isKetua,
            'kehadiran' => $kehadiran,
            'kehadiranLabel' => $kehadiranLabel,
            'kehadiranPerBulan' => $kehadiranPerBulan,
            // The one action a ketua kelas owes the school each day.
            'sudahIsiHariIni' => $isKetua && $kelas
                ? PresensiHarian::sudahDiisi($kelas->id, today()->toDateString())
                : false,
            'kpi' => [
                'jadwalHariIni' => $jadwalHariIni->count(),
                'jurnalTerisi' => $jurnalHariIni->count(),
                'belumDiisi' => max(0, $jadwalHariIni->count() - $jurnalHariIni->count()),
                'kehadiran' => round($kehadiran['hadir'] / $totalKehadiran * 100),
                'hadir' => $kehadiran['hadir'],
                'alpa' => $kehadiran['alpa'],
            ],
            'jurnalStatus' => [
                'kelengkapan' => $kelas ? Ringkasan::kelengkapanKelas($kelas->id) : 0,
                'tepatWaktu' => $tepatWaktu,
                'terlambat' => $totalJurnal - $tepatWaktu,
                'total' => $totalJurnal,
            ],
            'riwayatJurnal' => $riwayatJurnal,
            'heatmap' => $this->heatmapKehadiran($user),
        ]);
    }

    /**
     * The student's own attendance as an attendance book: one row per month, one
     * cell per day of that month, shaded by the status recorded.
     *
     * A calendar is the natural shape now that the roll call is daily — the old
     * per-subject grid could only exist while every lesson took its own roster.
     *
     * @return array<string, array<string, string|int>>
     */
    private function heatmapKehadiran(User $user, int $bulan = 3): array
    {
        $awal = today()->copy()->startOfMonth()->subMonthsNoOverflow($bulan - 1);

        $catatan = PresensiHarian::query()
            ->where('siswa_id', $user->id)
            ->where('tanggal', '>=', $awal->toDateString())
            ->get()
            ->keyBy(fn ($p) => $p->tanggal->toDateString());

        $rows = [];

        for ($i = 0; $i < $bulan; $i++) {
            $kursor = $awal->copy()->addMonthsNoOverflow($i);
            $cells = [];

            // Every row spans 1-31 so the grid's columns stay aligned; days a
            // month does not have, and days with no record, read as empty.
            for ($hari = 1; $hari <= 31; $hari++) {
                $tanggal = $kursor->copy()->startOfMonth()->addDays($hari - 1);

                $cells[(string) $hari] = $tanggal->month === $kursor->month
                    ? ($catatan[$tanggal->toDateString()]->status ?? 0)
                    : 0;
            }

            $rows[$kursor->translatedFormat('F Y')] = $cells;
        }

        return $rows;
    }

    /**
     * The student's attendance rolled up per month, newest first.
     *
     * @return Collection<string, Collection<string, int>>
     */
    private function kehadiranPerBulan(User $user, int $bulan = 6)
    {
        $awal = today()->copy()->startOfMonth()->subMonthsNoOverflow($bulan - 1);

        return PresensiHarian::query()
            ->where('siswa_id', $user->id)
            ->where('tanggal', '>=', $awal->toDateString())
            ->get()
            ->groupBy(fn ($p) => $p->tanggal->translatedFormat('F Y'))
            ->map(fn ($rows) => $rows->groupBy('status')->map->count())
            ->reverse();
    }
}
