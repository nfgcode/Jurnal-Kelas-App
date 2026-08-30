<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Presensi;
use App\Support\SimpanPresensiJurnal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Student attendance, marked per meeting by the guru who taught it.
 *
 * One roster per lesson, so the same student can be present for Matematika at
 * JP 1 and absent for Fisika at JP 5 and the record says both. The class writes
 * the journal around it ({@see JurnalController}); the teacher writes only this.
 *
 * The class's day-level record is derived from these rosters and never typed in
 * — see {@see SimpanPresensiJurnal}.
 */
class PresensiJurnalController extends Controller
{
    /**
     * The roster form for one meeting: every student of the class, with what
     * was marked last time pre-selected.
     */
    public function edit(Request $request, Jurnal $jurnal)
    {
        Gate::authorize('isiPresensi', $jurnal);

        $jurnal->load(['jadwal.kelas', 'jadwal.mataPelajaran', 'jadwal.guru']);
        $kelas = $jurnal->jadwal?->kelas;

        abort_if($kelas === null, 404, 'Pertemuan ini tidak terhubung ke kelas mana pun.');

        $tersimpan = Presensi::where('jurnal_id', $jurnal->id)->get()->keyBy('siswa_nis');

        return view('presensi.isi-jurnal', [
            'jurnal' => $jurnal,
            'kelas' => $kelas,
            'siswaList' => $kelas->siswa()->orderBy('nama')->get(),
            'tersimpan' => $tersimpan,
            // Distinguishes "isi presensi" from "perbarui presensi" in the UI.
            'sudahDiisi' => $tersimpan->isNotEmpty(),
            // The other lessons this class had that day, so a guru can see at a
            // glance which subjects are still unmarked — this is the screen where
            // "presensi per mata pelajaran" becomes visible rather than implied.
            'pertemuanLain' => $this->pertemuanSehari($jurnal),
        ]);
    }

    /**
     * Replace this meeting's whole roster. Idempotent: re-submitting the form
     * overwrites the lesson rather than adding a second roster beside it.
     */
    public function store(Request $request, Jurnal $jurnal)
    {
        Gate::authorize('isiPresensi', $jurnal);

        $kelas = $jurnal->jadwal?->kelas;

        abort_if($kelas === null, 404, 'Pertemuan ini tidak terhubung ke kelas mana pun.');

        // Attendance may only be recorded for students actually in this class, so
        // a crafted siswa_nis (another class's student, or a teacher) is rejected.
        $roster = $kelas->siswa()->pluck('nis')->all();

        $validated = $request->validate([
            'presensi' => ['required', 'array', 'min:1'],
            'presensi.*.siswa_nis' => ['required', Rule::in($roster)],
            'presensi.*.status' => ['required', Rule::in(Presensi::STATUS)],
            'presensi.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        SimpanPresensiJurnal::simpan($jurnal, $validated['presensi'], $request->user());

        return redirect()
            ->route('jurnal.show', $jurnal)
            ->with('success', 'Presensi '.$kelas->nama_kelas.' — '
                .($jurnal->jadwal?->mataPelajaran?->nama ?? 'pertemuan ini').' tersimpan.');
    }

    /**
     * Open the roster for a timetable slot rather than for a journal.
     *
     * A guru marks attendance during the lesson, which is usually before the
     * ketua kelas has written its journal. Rather than making the teacher wait
     * for someone else, the meeting's record is opened here if it does not exist
     * yet — an empty placeholder for the class to complete, exactly like the one
     * the nightly backfill leaves, so it never counts as a journal anybody wrote.
     */
    public function mulai(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'jadwal_id' => ['required', 'exists:jadwal,id'],
            'tanggal' => ['nullable', 'date'],
        ]);

        $jadwal = Jadwal::findOrFail($validated['jadwal_id']);
        $tanggal = Carbon::parse($validated['tanggal'] ?? today())->toDateString();

        // A guru may only open their own slot; admin any. Checked here because
        // there is no journal yet to ask JurnalPolicy::isiPresensi about.
        abort_if(! $user->isAdmin() && $jadwal->guru_nip !== $user->nip, 403,
            'Jadwal tersebut bukan jadwal mengajar Anda.');

        $jurnal = Jurnal::where('jadwal_id', $jadwal->id)
            ->whereDate('tanggal', $tanggal)
            // A meeting may hold the class's journal and a system placeholder;
            // the class's own comes first so the roster attaches to the real one.
            ->orderByRaw("CASE WHEN diisi_oleh_peran = '".Jurnal::PERAN_SISTEM."' THEN 1 ELSE 0 END")
            ->first()
            ?? Jurnal::create([
                'jadwal_id' => $jadwal->id,
                'tanggal' => $tanggal,
                'materi' => Jurnal::MATERI_PLACEHOLDER,
                // The guru is demonstrably in the room — they are taking the roll
                // — so the placeholder must not record them as absent the way the
                // nightly backfill's does. The class corrects the rest.
                'kehadiran_guru_status' => 'hadir',
                'kehadiran_guru_ada_tugas' => null,
                'diisi_oleh_id' => null,
                'diisi_oleh_peran' => Jurnal::PERAN_SISTEM,
            ]);

        return redirect()->route('presensi-jurnal.edit', $jurnal);
    }

    /**
     * The class's other lessons on the same date, each with how many students
     * are already marked — the per-subject picture the guru is working inside.
     *
     * @return Collection<int, Jurnal>
     */
    private function pertemuanSehari(Jurnal $jurnal)
    {
        $lain = Jurnal::with(['jadwal.mataPelajaran', 'jadwal.guru'])
            ->whereHas('jadwal', fn ($q) => $q->where('kelas_id', $jurnal->jadwal->kelas_id))
            ->whereDate('tanggal', $jurnal->tanggal->toDateString())
            ->get()
            ->sortBy(fn ($j) => $j->jadwal?->jam_ke_mulai ?? 0)
            ->values();

        $jumlah = Presensi::jumlahPerJurnal($lain->pluck('id')->all());

        return $lain->each(fn ($j) => $j->setAttribute('jumlah_presensi', $jumlah[$j->id] ?? 0));
    }
}
