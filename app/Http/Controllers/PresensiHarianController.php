<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Presensi;
use App\Models\PresensiHarian;
use App\Models\PresensiHarianLog;
use App\Support\Ringkasan;
use App\Support\SimpanPresensiJurnal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * A class's attendance seen as a whole school day — read-only.
 *
 * Nothing writes this screen's record directly. Attendance is marked lesson by
 * lesson by the teacher who taught it ({@see PresensiJurnalController}), and the
 * day shown here is derived from those rosters by
 * {@see SimpanPresensiJurnal}: one row per student, carrying the
 * most consequential mark the day produced. Somebody asking "was this student in
 * school on the 3rd?" gets one answer; somebody asking "were they in Fisika?"
 * opens the lesson.
 */
class PresensiHarianController extends Controller
{
    /**
     * One class's day, read-only — who ended up marked what, and the trail of
     * every lesson-save that shaped it.
     */
    public function show(Request $request, Kelas $kelas)
    {
        Gate::authorize('lihatPresensiHarian', $kelas);

        $tanggal = $this->tanggal($request);

        $baris = PresensiHarian::with('siswa')
            ->where('kelas_id', $kelas->id)
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->sortBy(fn ($p) => $p->siswa?->nama ?? '')
            ->values();

        return view('presensi-harian.show', [
            'kelas' => $kelas,
            'tanggal' => Carbon::parse($tanggal),
            'baris' => $baris,
            'rekap' => Ringkasan::presensi(
                PresensiHarian::where('kelas_id', $kelas->id)->whereDate('tanggal', $tanggal)
            ),
            'pengisi' => $baris->first()?->diisiOleh,
            'riwayat' => PresensiHarianLog::with('dieditOleh')
                ->where('kelas_id', $kelas->id)
                ->whereDate('tanggal', $tanggal)
                ->orderByDesc('created_at')
                ->get(),
            // The lessons this day is built from, so a reader can go from the
            // rollup to the subject that produced a mark.
            'pertemuan' => $this->pertemuanHari($kelas, $tanggal),
        ]);
    }

    /**
     * The class's meetings on that date, each with how many students its teacher
     * marked — the per-subject detail behind the day's single row per student.
     *
     * @return Collection<int, Jurnal>
     */
    private function pertemuanHari(Kelas $kelas, string $tanggal)
    {
        $jurnals = Jurnal::with(['jadwal.mataPelajaran', 'jadwal.guru'])
            ->whereHas('jadwal', fn ($q) => $q->where('kelas_id', $kelas->id))
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->sortBy(fn ($j) => $j->jadwal?->jam_ke_mulai ?? 0)
            ->values();

        $jumlah = Presensi::jumlahPerJurnal($jurnals->pluck('id')->all());

        return $jurnals->each(fn ($j) => $j->setAttribute('jumlah_presensi', $jumlah[$j->id] ?? 0));
    }

    /**
     * The date the screen is about, defaulting to today.
     */
    private function tanggal(Request $request): string
    {
        $request->validate([
            'tanggal' => ['nullable', 'date'],
        ]);

        return $request->filled('tanggal')
            ? Carbon::parse($request->input('tanggal'))->toDateString()
            : today()->toDateString();
    }
}
