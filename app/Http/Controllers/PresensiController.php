<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Presensi;
use App\Models\User;
use App\Support\Ringkasan;
use App\Support\SimpanPresensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PresensiController extends Controller
{
    /**
     * A student sees their own attendance record; a teacher sees the meetings
     * they are responsible for marking.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        return $user->isSiswa()
            ? $this->rekapSiswa($request, $user)
            : $this->daftarPertemuan($request, $user);
    }

    /**
     * The student's own attendance, broken down per subject with a predicate.
     */
    private function rekapSiswa(Request $request, User $user)
    {
        $filters = $request->validate([
            'mata_pelajaran_id' => ['nullable', 'exists:mata_pelajaran,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $perMapel = Presensi::query()
            ->join('jurnal', 'presensi.jurnal_id', '=', 'jurnal.id')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->join('mata_pelajaran', 'jadwal.mata_pelajaran_id', '=', 'mata_pelajaran.id')
            ->join('users as guru', 'jadwal.guru_id', '=', 'guru.id')
            ->selectRaw('mata_pelajaran.id as mapel_id, mata_pelajaran.nama as mapel, guru.name as guru, presensi.status, COUNT(*) as total')
            ->where('presensi.siswa_id', $user->id)
            ->when($filters['mata_pelajaran_id'] ?? null, fn ($q, $id) => $q->where('mata_pelajaran.id', $id))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->where(fn ($inner) => $inner
                ->where('mata_pelajaran.nama', 'like', "%{$cari}%")
                ->orWhere('guru.name', 'like', "%{$cari}%")))
            ->groupBy('mata_pelajaran.id', 'mata_pelajaran.nama', 'guru.name', 'presensi.status')
            ->get()
            ->groupBy('mapel')
            ->map(fn ($rows) => [
                'guru' => $rows->first()->guru,
                'status' => $rows->pluck('total', 'status'),
                'total' => $rows->sum('total'),
            ])
            ->sortByDesc('total');

        return view('presensi.rekap-saya', [
            'perMapel' => $perMapel,
            'rekap' => Ringkasan::presensi(Presensi::where('siswa_id', $user->id)),
            'mapelList' => MataPelajaran::orderBy('nama')->get(),
            'filters' => $filters,
        ]);
    }

    /**
     * Meetings whose roster the signed-in teacher can mark.
     */
    private function daftarPertemuan(Request $request, User $user)
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $pertemuan = Jurnal::query()
            ->with(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])
            ->withCount([
                'presensis as total_siswa',
                'presensis as hadir_count' => fn ($q) => $q->where('status', 'hadir'),
                'presensis as sakit_count' => fn ($q) => $q->where('status', 'sakit'),
                'presensis as izin_count' => fn ($q) => $q->where('status', 'izin'),
                'presensis as alpa_count' => fn ($q) => $q->where('status', 'alpa'),
            ])
            ->when($user->isGuru(), fn ($q) => $q->where('guru_id', $user->id))
            ->when($filters['kelas_id'] ?? null, fn ($q, $id) => $q->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $id)))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari))
            ->latest('tanggal')
            ->latest('id')
            ->paginate(18)
            ->withQueryString();

        $milik = Jurnal::when($user->isGuru(), fn ($q) => $q->where('guru_id', $user->id));

        return view('presensi.index', [
            'pertemuan' => $pertemuan,
            // A guru filters only among the classes they teach; admin all.
            'kelasList' => Kelas::query()
                ->when($user->isGuru(), fn ($q) => $q->whereIn('id', Jadwal::where('guru_id', $user->id)->select('kelas_id')))
                ->orderBy('nama_kelas')->get(),
            'filters' => $filters,
            'rekap' => Ringkasan::presensi(
                Presensi::whereHas('jurnal', fn ($q) => $q->when($user->isGuru(), fn ($i) => $i->where('guru_id', $user->id)))
            ),
            'totalPertemuan' => (clone $milik)->count(),
        ]);
    }

    /**
     * The roster marking screen for one meeting.
     */
    public function create(int $jurnal_id)
    {
        $jurnal = Jurnal::with(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])->findOrFail($jurnal_id);

        // Marking a roster is a write on the journal — only its guru (or admin) may.
        Gate::authorize('update', $jurnal);

        $siswaList = $jurnal->jadwal->kelas->siswa()->orderBy('name')->get();

        // Re-opening the form should show what was already recorded, since
        // store() replaces the whole set for this jurnal.
        $presensiTersimpan = $jurnal->presensis()->get()->keyBy('siswa_id');

        return view('presensi.create', [
            'jurnal' => $jurnal,
            'siswaList' => $siswaList,
            'presensiTersimpan' => $presensiTersimpan,
            'rekap' => Ringkasan::presensi(Presensi::where('jurnal_id', $jurnal->id)),
        ]);
    }

    /**
     * Bulk store attendance records for all siswa in a jurnal.
     */
    public function store(Request $request)
    {
        $jurnal = Jurnal::with('jadwal.kelas')->findOrFail($request->integer('jurnal_id'));

        // Marking a roster is a write on the journal — only its guru (or admin) may.
        Gate::authorize('update', $jurnal);

        // Attendance may only be recorded for students actually in this class, so
        // a crafted siswa_id (a teacher, or another class's student) is rejected.
        $roster = $jurnal->jadwal->kelas->siswa()->pluck('id')->all();

        $validated = $request->validate([
            'jurnal_id' => 'required|exists:jurnal,id',
            'presensi' => 'required|array',
            'presensi.*.siswa_id' => ['required', Rule::in($roster)],
            'presensi.*.status' => 'required|in:hadir,sakit,izin,alpa',
            'presensi.*.keterangan' => 'nullable|string',
        ]);

        SimpanPresensi::simpan($jurnal, $validated['presensi']);

        return redirect()->route('presensi.show', $jurnal->id)
            ->with('success', 'Presensi berhasil disimpan.');
    }

    /**
     * Show attendance records for a specific jurnal.
     */
    public function show(int $jurnal_id)
    {
        $jurnal = Jurnal::with([
            'jadwal.kelas',
            'jadwal.mataPelajaran',
            'guru',
            'presensis.siswa',
        ])->findOrFail($jurnal_id);

        Gate::authorize('view', $jurnal);

        return view('presensi.show', [
            'jurnal' => $jurnal,
            'rekap' => Ringkasan::presensi(Presensi::where('jurnal_id', $jurnal->id)),
        ]);
    }
}
