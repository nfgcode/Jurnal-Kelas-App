<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\PresensiHarian;
use App\Models\PresensiHarianLog;
use App\Support\Ringkasan;
use App\Support\SimpanPresensiHarian;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Student attendance, taken once a day for a whole class.
 *
 * The ketua kelas is the only person who files it, and only for their own class
 * and only for today — they are the one who can actually see who is in the room,
 * and pinning the fill to the current day is what keeps the record a roll call
 * rather than a reconstruction. Admin can correct any class on any date.
 *
 * A guru never lands here to write: they read the recap and export it from
 * {@see PresensiController}.
 */
class PresensiHarianController extends Controller
{
    /**
     * One class's roster for one day, read-only — who was marked what, by whom.
     */
    public function show(Request $request, Kelas $kelas)
    {
        Gate::authorize('lihatPresensiHarian', $kelas);

        $tanggal = $this->tanggal($request);

        $baris = PresensiHarian::with('siswa')
            ->where('kelas_id', $kelas->id)
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->sortBy(fn ($p) => $p->siswa?->name ?? '')
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
            'bolehIsi' => Gate::allows('isiPresensiHarian', $kelas) && $this->tanggalTerbuka($request, $tanggal),
        ]);
    }

    /**
     * The roll-call form: every student of the class, with today's roster
     * pre-selected when it has already been filed.
     */
    public function edit(Request $request, Kelas $kelas)
    {
        Gate::authorize('isiPresensiHarian', $kelas);

        $tanggal = $this->tanggal($request);

        // A ketua files today's roll call, not last week's. Sending them to the
        // read-only view rather than erroring keeps the "what happened on the
        // 3rd?" click working — they just cannot rewrite it.
        if (! $this->tanggalTerbuka($request, $tanggal)) {
            return redirect()
                ->route('presensi-harian.show', [$kelas, 'tanggal' => $tanggal])
                ->with('error', 'Presensi hanya dapat diisi untuk hari ini. Hubungi admin untuk mengoreksi tanggal lain.');
        }

        $siswaList = $kelas->siswa()->orderBy('name')->get();

        $tersimpan = PresensiHarian::where('kelas_id', $kelas->id)
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('siswa_id');

        return view('presensi-harian.isi', [
            'kelas' => $kelas,
            'tanggal' => Carbon::parse($tanggal),
            'siswaList' => $siswaList,
            'tersimpan' => $tersimpan,
            // Distinguishes "isi presensi" from "perbarui presensi" in the UI, and
            // warns that the day already has a record — the once-a-day rule made
            // visible rather than only enforced by the unique index.
            'sudahDiisi' => $tersimpan->isNotEmpty(),
        ]);
    }

    /**
     * Replace the class's whole roster for the day. Idempotent: re-submitting
     * the form overwrites the day rather than adding a second roll call, which
     * is the "once a day" rule the unique index also enforces underneath.
     */
    public function store(Request $request, Kelas $kelas)
    {
        Gate::authorize('isiPresensiHarian', $kelas);

        $tanggal = $this->tanggal($request);

        if (! $this->tanggalTerbuka($request, $tanggal)) {
            return back()->with('error', 'Presensi hanya dapat diisi untuk hari ini.');
        }

        // Attendance may only be recorded for students actually in this class, so
        // a crafted siswa_id (another class's student, or a teacher) is rejected.
        $roster = $kelas->siswa()->pluck('id')->all();

        $validated = $request->validate([
            'presensi' => ['required', 'array', 'min:1'],
            'presensi.*.siswa_id' => ['required', Rule::in($roster)],
            'presensi.*.status' => ['required', Rule::in(PresensiHarian::STATUS)],
            'presensi.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        SimpanPresensiHarian::simpan($kelas, $tanggal, $validated['presensi'], $request->user());

        return redirect()
            ->route('presensi-harian.show', [$kelas, 'tanggal' => $tanggal])
            ->with('success', 'Presensi '.$kelas->nama_kelas.' tanggal '
                .Carbon::parse($tanggal)->translatedFormat('j F Y').' tersimpan.');
    }

    /**
     * The date the screen is about, defaulting to today.
     *
     * input(), not query(): the read screens pass the date in the URL but the
     * form posts it in the body, and reading only the query string made a save
     * for any other date land silently on today instead.
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

    /**
     * Whether $tanggal may still be written. A ketua kelas gets today only —
     * one roll call, on the day it describes. Admin is correcting the record
     * after the fact by definition, so no date is closed to them.
     */
    private function tanggalTerbuka(Request $request, string $tanggal): bool
    {
        return $request->user()->isAdmin() || $tanggal === today()->toDateString();
    }
}
