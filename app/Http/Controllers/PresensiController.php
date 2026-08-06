<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Presensi;
use App\Models\User;
use App\Support\Halaman;
use App\Support\Periode;
use App\Support\Ringkasan;
use App\Support\SimpanPresensi;
use App\Support\Urutan;
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

        // A regular student sees only their own record; a ketua kelas and every
        // teacher/admin get a markable meeting list (scoped in daftarPertemuan).
        if ($user->isSiswa() && ! $user->isKetuaKelas()) {
            return $this->rekapSiswa($request, $user);
        }

        return $this->daftarPertemuan($request, $user);
    }

    /**
     * The class ids whose rosters $user may mark, or null for "all" (admin).
     * A ketua chairs one class; a guru reaches the classes they teach plus any
     * they are wali of — matching JurnalPolicy::markRoster.
     *
     * @return array<int>|null
     */
    private function kelasTerjangkau(User $user): ?array
    {
        if ($user->isAdmin()) {
            return null;
        }

        if ($user->isKetuaKelas()) {
            return array_filter([$user->kelas_id]);
        }

        return Jadwal::where('guru_id', $user->id)->pluck('kelas_id')
            ->merge(Kelas::where('wali_kelas_id', $user->id)->pluck('id'))
            ->unique()->values()->all();
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

        $periode = Periode::dari($request);

        $perMapel = Presensi::query()
            ->join('jurnal', 'presensi.jurnal_id', '=', 'jurnal.id')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->join('mata_pelajaran', 'jadwal.mata_pelajaran_id', '=', 'mata_pelajaran.id')
            ->join('users as guru', 'jadwal.guru_id', '=', 'guru.id')
            ->selectRaw('mata_pelajaran.id as mapel_id, mata_pelajaran.nama as mapel, guru.name as guru, presensi.status, COUNT(*) as total')
            ->where('presensi.siswa_id', $user->id)
            ->whereBetween('jurnal.tanggal', [$periode->mulaiString(), $periode->selesaiString()])
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
            'periode' => $periode,
            'rekap' => Ringkasan::presensi(
                Presensi::where('siswa_id', $user->id)
                    ->whereHas('jurnal', fn ($q) => $q->whereBetween('tanggal', [$periode->mulaiString(), $periode->selesaiString()]))
            ),
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
            'tingkat' => ['nullable', 'in:X,XI,XII'],
            'jurusan' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $periode = Periode::dari($request);

        // Classes whose rosters this user may mark — null means every class (admin).
        $kelasIds = $this->kelasTerjangkau($user);
        $batasiKelas = fn ($query) => $query->whereHas('jadwal', fn ($j) => $j->whereIn('kelas_id', $kelasIds));
        $dalamPeriode = fn ($query) => $query->whereBetween('tanggal', [$periode->mulaiString(), $periode->selesaiString()]);

        $pertemuan = Jurnal::query()
            ->with(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])
            ->withCount([
                'presensis as total_siswa',
                'presensis as hadir_count' => fn ($q) => $q->where('status', 'hadir'),
                'presensis as sakit_count' => fn ($q) => $q->where('status', 'sakit'),
                'presensis as izin_count' => fn ($q) => $q->where('status', 'izin'),
                'presensis as alpa_count' => fn ($q) => $q->where('status', 'alpa'),
            ])
            ->when($kelasIds !== null, $batasiKelas)
            ->whereBetween('tanggal', [$periode->mulaiString(), $periode->selesaiString()])
            ->when($filters['kelas_id'] ?? null, fn ($q, $id) => $q->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $id)))
            ->when($filters['tingkat'] ?? null, fn ($q, $t) => $q->whereHas('jadwal.kelas', fn ($k) => $k->where('tingkat', $t)))
            ->when($filters['jurusan'] ?? null, fn ($q, $j) => $q->whereHas('jadwal.kelas', fn ($k) => $k->where('jurusan', $j)))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari));

        $peta = array_intersect_key(Jurnal::petaUrutan(), array_flip(['tanggal', 'jam', 'kelas', 'mapel', 'siswa', 'hadir', 'sakit', 'izin', 'alpa', 'persen']));
        Urutan::terapkan($pertemuan, $request, $peta, fn ($q) => $q->latest('tanggal')->latest('id'));

        $pertemuan = $pertemuan->paginate(Halaman::perHalaman())->withQueryString();

        // A guru/ketua filters only among classes they can mark; admin all.
        $kelasList = Kelas::query()
            ->when($kelasIds !== null, fn ($q) => $q->whereIn('id', $kelasIds))
            ->orderBy('nama_kelas')->get();

        return view('presensi.index', [
            'pertemuan' => $pertemuan,
            'periode' => $periode,
            'kelasList' => $kelasList,
            'filters' => $filters,
            // The recap follows the period too, so the H/S/I/A totals describe the
            // meetings actually listed rather than every meeting ever held.
            'rekap' => Ringkasan::presensi(
                Presensi::whereHas('jurnal', $dalamPeriode)
                    ->when($kelasIds !== null, fn ($q) => $q->whereHas('jurnal.jadwal', fn ($j) => $j->whereIn('kelas_id', $kelasIds)))
            ),
            // Meetings in this period the user may reach — the search/class filters
            // deliberately do not narrow it, so the card stays a stable reference.
            'totalPertemuan' => Jurnal::query()
                ->when($kelasIds !== null, $batasiKelas)
                ->whereBetween('tanggal', [$periode->mulaiString(), $periode->selesaiString()])
                ->count(),
        ]);
    }

    /**
     * The roster marking screen for one meeting.
     */
    public function create(Jurnal $jurnal)
    {
        $jurnal->load(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru']);

        // A meeting's roster may be marked by admin, its class's ketua, and any
        // guru who teaches or is wali of that class — see JurnalPolicy::markRoster.
        Gate::authorize('markRoster', $jurnal);

        // The lesson happened once, so its roster lives on one journal even when
        // the meeting has both a guru and a ketua version. Send the user to
        // whichever journal already holds it instead of starting a second set.
        if ($pemegang = $jurnal->pemegangPresensi()) {
            return redirect()->route('presensi.create', $pemegang)
                ->with('success', 'Presensi pertemuan ini sudah tercatat pada jurnal lain; perubahan dilakukan di sini.');
        }

        $siswaList = $jurnal->jadwal->kelas->siswa()->orderBy('name')->get();

        // Re-opening the form should show what was already recorded, since
        // store() replaces the whole set for this jurnal.
        $presensiTersimpan = $jurnal->presensis()->get()->keyBy('siswa_id');

        return view('presensi.create', [
            'jurnal' => $jurnal,
            'siswaList' => $siswaList,
            'presensiTersimpan' => $presensiTersimpan,
            // Nothing marked yet? Offer the class's earlier lesson today as a
            // starting point, so a teacher isn't retyping a roster the room
            // already reported an hour ago. Only a suggestion — nothing is saved
            // until they submit.
            'prefill' => $presensiTersimpan->isEmpty() ? $this->prefillDariPertemuanLain($jurnal) : null,
            'rekap' => Ringkasan::presensi(Presensi::where('jurnal_id', $jurnal->id)),
        ]);
    }

    /**
     * A starting roster copied from another meeting of the same class on the same
     * day that already has attendance — the nearest earlier lesson preferred, so
     * the suggestion reflects who was in the room closest to now. Returns null
     * when the class has no other marked meeting that day.
     *
     * @return array{map: array<int, string>, label: string}|null
     */
    private function prefillDariPertemuanLain(Jurnal $jurnal): ?array
    {
        $kelasId = $jurnal->jadwal?->kelas_id;

        if (! $kelasId) {
            return null;
        }

        $jamIni = (int) ($jurnal->jadwal->jam_ke_mulai ?? 0);

        $kandidat = Jurnal::query()
            ->where('jadwal_id', '!=', $jurnal->jadwal_id)
            ->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $kelasId))
            ->whereDate('tanggal', $jurnal->tanggal)
            ->whereHas('presensis')
            ->with(['jadwal.mataPelajaran', 'presensis:id,jurnal_id,siswa_id,status'])
            ->get();

        if ($kandidat->isEmpty()) {
            return null;
        }

        // Nearest earlier lesson first; failing that, the nearest later one.
        $sumber = $kandidat->sortBy(function ($j) use ($jamIni) {
            $jam = (int) ($j->jadwal->jam_ke_mulai ?? 0);

            return [$jam <= $jamIni ? 0 : 1, abs($jam - $jamIni)];
        })->first();

        return [
            'map' => $sumber->presensis->pluck('status', 'siswa_id')->all(),
            'label' => trim(($sumber->jadwal->mataPelajaran->nama ?? 'pertemuan lain').' · JP '.$sumber->jadwal->jpLabel()),
        ];
    }

    /**
     * Bulk store attendance records for all siswa in a jurnal.
     */
    public function store(Request $request)
    {
        // The form posts the opaque public id, so the numeric key never appears in
        // the page's HTML either.
        $jurnal = Jurnal::with('jadwal.kelas')
            ->where('public_id', $request->input('jurnal_id'))
            ->firstOrFail();

        // A meeting's roster may be marked by admin, its class's ketua, and any
        // guru who teaches or is wali of that class — see JurnalPolicy::markRoster.
        Gate::authorize('markRoster', $jurnal);

        // One roster per meeting, even when the meeting has two journals — see
        // Jurnal::pemegangPresensi(). Marking a second set would double every
        // attendance figure for this lesson.
        if ($pemegang = $jurnal->pemegangPresensi()) {
            return redirect()->route('presensi.create', $pemegang)
                ->with('error', 'Presensi pertemuan ini sudah tercatat pada jurnal lain. Perbarui di sini agar tidak terhitung dua kali.');
        }

        // Attendance may only be recorded for students actually in this class, so
        // a crafted siswa_id (a teacher, or another class's student) is rejected.
        $roster = $jurnal->jadwal->kelas->siswa()->pluck('id')->all();

        $validated = $request->validate([
            // The form posts the opaque id, so this must check that column — the
            // numeric key would never match and would reject every save.
            'jurnal_id' => 'required|exists:jurnal,public_id',
            'presensi' => 'required|array',
            'presensi.*.siswa_id' => ['required', Rule::in($roster)],
            'presensi.*.status' => 'required|in:hadir,sakit,izin,alpa',
            'presensi.*.keterangan' => 'nullable|string',
        ]);

        SimpanPresensi::simpan($jurnal, $validated['presensi'], $request->user());

        return redirect()->route('presensi.show', $jurnal)
            ->with('success', 'Presensi berhasil disimpan.');
    }

    /**
     * Show attendance records for a specific jurnal.
     */
    public function show(Jurnal $jurnal)
    {
        $jurnal->load([
            'jadwal.kelas',
            'jadwal.mataPelajaran',
            'guru',
            'presensis.siswa',
        ]);

        Gate::authorize('viewRoster', $jurnal);

        return view('presensi.show', [
            'jurnal' => $jurnal,
            'rekap' => Ringkasan::presensi(Presensi::where('jurnal_id', $jurnal->id)),
        ]);
    }
}
