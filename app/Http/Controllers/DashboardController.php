<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Presensi;
use App\Models\PresensiHarian;
use App\Models\Siswa;
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
            ->where('guru_nip', $user->nip)
            ->where('hari', Ringkasan::hariIni())
            ->orderBy('jam_ke_mulai')
            ->get();

        // Today's journals, indexed by schedule so each row knows its status.
        $jurnalHariIni = Jurnal::denganPresensi()
            ->diampu($user->nip)
            ->whereDate('tanggal', today())
            ->get()
            ->keyBy('jadwal_id');

        $kelasDiampu = Kelas::whereIn('id', Jadwal::where('guru_nip', $user->nip)->select('kelas_id'))
            ->orderBy('nama_kelas')
            ->get();

        // How many students this teacher has actually marked on each of today's
        // meetings — the "belum ditandai" count their dashboard leads with.
        $ditandaiHariIni = Presensi::jumlahPerJurnal($jurnalHariIni->pluck('id')->all());

        $belumDitandai = $jadwalHariIni
            ->reject(function ($jadwal) use ($jurnalHariIni, $ditandaiHariIni) {
                $jurnal = $jurnalHariIni->get($jadwal->id);

                return $jurnal && ($ditandaiHariIni[$jurnal->id] ?? 0) > 0;
            })
            ->count();

        // Attendance across the classes this teacher takes, read from the daily
        // rollup so a class counts once per school day however many lessons it
        // held. It is oversight; the marking itself happens per meeting.
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
            'ditandaiHariIni' => $ditandaiHariIni,
            'kelasDiampu' => $kelasDiampu,
            'kehadiranPerKelas' => $kehadiranPerKelas,
            // Journals this teacher wrote — not the ones the nightly backfill
            // filed under their name, which would draw a full activity chart for
            // a fortnight they actually skipped.
            'aktivitas' => Ringkasan::harian(Jurnal::manusia()->diampu($user->nip)),
            'kehadiranGuru' => Ringkasan::kehadiranGuru(Jurnal::diampu($user->nip)),
            'presensiSaya' => $presensiSaya,
            'kpi' => [
                'jadwalHariIni' => $jadwalHariIni->count(),
                'jurnalTerisi' => $jurnalHariIni->count(),
                'belumDitandai' => $belumDitandai,
                'kelasDiampu' => $kelasDiampu->count(),
                'siswaDiampu' => Siswa::whereIn('kelas_id', $kelasDiampu->pluck('id'))->count(),
                'rataKehadiran' => round($presensiSaya['hadir'] / $totalPresensi * 100),
            ],
            'jurnalTerakhir' => Jurnal::with(['jadwal.kelas', 'jadwal.mataPelajaran'])
                ->diampu($user->nip)
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
            : Ringkasan::presensi(PresensiHarian::where('siswa_nis', $user->nis));
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

        // Attendance broken down per month, most recent first — read from the
        // day-level record, so a month of school days is what the student sees
        // rather than a count that grows with how many lessons a day held.
        $kehadiranPerBulan = $this->kehadiranPerBulan($user);

        return view('dashboard.siswa', [
            'kelas' => $kelas,
            'jadwalHariIni' => $jadwalHariIni,
            'jurnalHariIni' => $jurnalHariIni,
            'isKetua' => $isKetua,
            'kehadiran' => $kehadiran,
            'kehadiranLabel' => $kehadiranLabel,
            'kehadiranPerBulan' => $kehadiranPerBulan,
            // The one action a ketua kelas owes the school each day: the class's
            // own journal for every lesson it had. Counted from journals written
            // from the class's side — a teacher's or the nightly placeholder does
            // not discharge it.
            'belumDitulis' => $isKetua
                ? max(0, $jadwalHariIni->count() - $jurnalHariIni->where('diisi_oleh_peran', 'siswa')->count())
                : 0,
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
            ->where('siswa_nis', $user->nis)
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
            ->where('siswa_nis', $user->nis)
            ->where('tanggal', '>=', $awal->toDateString())
            ->get()
            ->groupBy(fn ($p) => $p->tanggal->translatedFormat('F Y'))
            ->map(fn ($rows) => $rows->groupBy('status')->map->count())
            ->reverse();
    }
}
