<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Presensi;
use App\Models\User;
use App\Support\Ringkasan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        $tanggal = $this->tanggalDipilih($request);

        return $user->isGuru()
            ? $this->dashboardGuru($user, $tanggal)
            : $this->dashboardSiswa($user, $tanggal);
    }

    /**
     * A teacher's day, trimmed to what MoSCoW kept: the one duty (marking each
     * lesson's roster) as an action card, the chosen day's timetable with one
     * button per row, and the week around it so another day is a click away.
     */
    private function dashboardGuru(User $user, Carbon $tanggal)
    {
        $jadwal = Jadwal::with(['kelas', 'mataPelajaran'])
            ->where('guru_nip', $user->nip)
            ->padaHariDari($tanggal)
            ->orderBy('jam_ke_mulai')
            ->get();

        // That day's journals, indexed by schedule so each row knows its status.
        $jurnal = Jurnal::diampu($user->nip)
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('jadwal_id');

        // How many students were marked on each of those meetings: a row whose
        // roster is still empty is the "Tandai Presensi" the screen leads with.
        $ditandai = Presensi::jumlahPerJurnal($jurnal->pluck('id')->all());

        $belumDitandai = $jadwal
            ->reject(fn ($j) => ($ditandai[$jurnal->get($j->id)?->id] ?? 0) > 0)
            ->count();

        // The week calendar: lessons per weekday and, for days already reached,
        // how many of them have a marked roster.
        [$senin, $batas] = $this->rentangMinggu($tanggal);
        $jurnalMinggu = Jurnal::diampu($user->nip)
            ->whereBetween('tanggal', [$senin->toDateString(), $batas->toDateString()])
            ->get(['id', 'jadwal_id', 'tanggal']);
        $ditandaiMinggu = Presensi::jumlahPerJurnal($jurnalMinggu->pluck('id')->all());
        $selesaiPerTanggal = $jurnalMinggu
            ->filter(fn ($j) => ($ditandaiMinggu[$j->id] ?? 0) > 0)
            ->groupBy(fn ($j) => $j->tanggal->toDateString())
            ->map(fn ($rows) => $rows->unique('jadwal_id')->count());

        return view('dashboard.guru', [
            'tanggal' => $tanggal,
            'jadwal' => $jadwal,
            'jurnal' => $jurnal,
            'ditandai' => $ditandai,
            'belumDitandai' => $belumDitandai,
            'minggu' => $this->mingguKalender(
                $tanggal,
                $this->jadwalPerHari(Jadwal::where('guru_nip', $user->nip)),
                $selesaiPerTanggal->all(),
            ),
        ]);
    }

    /**
     * A student's day: the ketua's one duty (the class journal) and the class's
     * attendance as action cards, the chosen day's lessons with one button per
     * row, and the week around it.
     */
    private function dashboardSiswa(User $user, Carbon $tanggal)
    {
        $kelas = $user->kelas;

        // A student with no class sees their own (empty) view, never every
        // class's schedule/journals. kelas_id 0 never matches a real row.
        $kelasId = $kelas?->id ?? 0;
        $isKetua = $user->isKetuaKelas();

        $jadwal = Jadwal::with(['mataPelajaran', 'guru'])
            ->where('kelas_id', $kelasId)
            ->padaHariDari($tanggal)
            ->orderBy('jam_ke_mulai')
            ->get();

        // The ketua's duty is discharged only by a journal written from the
        // class's side — a teacher's entry or the nightly placeholder does not
        // count. Everyone else just needs to know whether there is one to read.
        $jurnal = Jurnal::whereIn('jadwal_id', $jadwal->pluck('id'))
            ->whereDate('tanggal', $tanggal)
            ->when($isKetua, fn ($q) => $q->where('diisi_oleh_peran', 'siswa'))
            ->get()
            ->keyBy('jadwal_id');

        [$senin, $batas] = $this->rentangMinggu($tanggal);
        $selesaiPerTanggal = Jurnal::untukKelas($kelasId)
            ->whereBetween('tanggal', [$senin->toDateString(), $batas->toDateString()])
            ->when($isKetua, fn ($q) => $q->where('diisi_oleh_peran', 'siswa'))
            ->get(['jadwal_id', 'tanggal'])
            ->groupBy(fn ($j) => $j->tanggal->toDateString())
            ->map(fn ($rows) => $rows->unique('jadwal_id')->count());

        return view('dashboard.siswa', [
            'tanggal' => $tanggal,
            'kelas' => $kelas,
            'isKetua' => $isKetua,
            'jadwal' => $jadwal,
            'jurnal' => $jurnal,
            'belumDitulis' => max(0, $jadwal->count() - $jurnal->count()),
            'minggu' => $this->mingguKalender(
                $tanggal,
                $this->jadwalPerHari(Jadwal::where('kelas_id', $kelasId)),
                $selesaiPerTanggal->all(),
            ),
        ]);
    }

    /**
     * The day the dashboard shows: ?tanggal= from the date picker or the week
     * calendar, otherwise today.
     */
    private function tanggalDipilih(Request $request): Carbon
    {
        $request->validate(['tanggal' => ['nullable', 'date']]);

        return $request->filled('tanggal')
            ? Carbon::parse($request->query('tanggal'))->startOfDay()
            : today();
    }

    /**
     * Monday of the chosen week, and the last day of it whose work can already
     * be outstanding (today, when the week is the current one).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rentangMinggu(Carbon $tanggal): array
    {
        $senin = $tanggal->copy()->startOfWeek(Carbon::MONDAY);

        return [$senin, $senin->copy()->addDays(6)->min(today())];
    }

    /**
     * Timetabled lessons per weekday name.
     *
     * @return array<string, int>
     */
    private function jadwalPerHari($query): array
    {
        return $query->selectRaw('hari, COUNT(*) as jumlah')
            ->groupBy('hari')
            ->pluck('jumlah', 'hari')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Monday to Sunday around $tanggal for the week calendar: how many lessons
     * each day holds and, for days already reached, how many are still open.
     *
     * @param  array<string, int>  $jadwalPerHari  weekday name => lessons
     * @param  array<string, int>  $selesaiPerTanggal  Y-m-d => lessons done
     * @return array<int, array{tanggal: Carbon, jumlah: int, belum: ?int}>
     */
    private function mingguKalender(Carbon $tanggal, array $jadwalPerHari, array $selesaiPerTanggal): array
    {
        $senin = $tanggal->copy()->startOfWeek(Carbon::MONDAY);
        $hari = [];

        for ($i = 0; $i < 7; $i++) {
            $t = $senin->copy()->addDays($i);
            // Ringkasan::HARI stops at Sabtu — Minggu is never timetabled.
            $jumlah = $jadwalPerHari[Ringkasan::HARI[$i] ?? ''] ?? 0;

            $hari[] = [
                'tanggal' => $t,
                'jumlah' => $jumlah,
                'belum' => $t->lte(today())
                    ? max(0, $jumlah - ($selesaiPerTanggal[$t->toDateString()] ?? 0))
                    : null,
            ];
        }

        return $hari;
    }
}
