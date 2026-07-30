<?php

namespace App\Http\Controllers;

use App\Http\Requests\JadwalRequest;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class JadwalController extends Controller
{
    /**
     * The timetable as a weekly matrix for one class — the view teachers and
     * students actually reason about, rather than a flat list of rows.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'guru_id' => ['nullable', 'exists:users,id'],
            'hari' => ['nullable', 'in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        // The class picker is scoped to the role: a guru chooses among classes
        // they teach, a student only ever sees their own, an admin all.
        $kelasList = Kelas::query()
            ->when($user->isGuru(), fn ($q) => $q->whereIn('id', Jadwal::where('guru_id', $user->id)->select('kelas_id')))
            ->when($user->isSiswa(), fn ($q) => $q->whereKey($user->kelas_id))
            ->orderBy('nama_kelas')
            ->get();

        // A student only ever has one timetable; default everyone else to the
        // first class in their scoped list so the matrix is never empty.
        $kelasAktif = $user->isSiswa() && $user->kelas_id
            ? $kelasList->firstWhere('id', $user->kelas_id)
            : $kelasList->firstWhere('id', $filters['kelas_id'] ?? null) ?? $kelasList->first();

        $jadwals = Jadwal::query()
            ->with(['mataPelajaran', 'guru'])
            ->when($kelasAktif, fn ($query) => $query->where('kelas_id', $kelasAktif->id))
            ->when($filters['guru_id'] ?? null, fn ($query, $id) => $query->where('guru_id', $id))
            ->when($filters['hari'] ?? null, fn ($query, $hari) => $query->where('hari', $hari))
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(
                fn ($inner) => $inner->whereHas('mataPelajaran', fn ($m) => $m->where('nama', 'like', "%{$q}%"))
                    ->orWhereHas('guru', fn ($g) => $g->where('name', 'like', "%{$q}%"))
                    ->orWhere('ruang', 'like', "%{$q}%")
            ))
            ->get();

        // Index by day and starting period so the matrix can place each block.
        $matriks = [];
        foreach ($jadwals as $jadwal) {
            $matriks[$jadwal->hari][$jadwal->jam_ke_mulai] = $jadwal;
        }

        return view('jadwal.index', [
            'matriks' => $matriks,
            'kelasList' => $kelasList,
            'kelasAktif' => $kelasAktif,
            // The teacher filter is an admin tool; a guru/siswa never picks
            // "another teacher", so the dropdown isn't built for them.
            'guruList' => $user->isAdmin()
                ? User::where('role', 'guru')->orderBy('name')->get()
                : collect(),
            'filters' => $filters,
            'statistik' => [
                'totalJadwal' => $user->isAdmin() ? Jadwal::count() : $jadwals->count(),
                'jpKelas' => $jadwals->sum(fn ($j) => $j->jam_ke_selesai - $j->jam_ke_mulai + 1),
                'guruTerlibat' => $jadwals->pluck('guru_id')->unique()->count(),
                'mapelTerlibat' => $jadwals->pluck('mata_pelajaran_id')->unique()->count(),
                'bentrok' => $this->hitungBentrok($jadwals),
            ],
        ]);
    }

    /**
     * Count slots where the same period is claimed twice for this class.
     *
     * @param  Collection<int, Jadwal>  $jadwals
     */
    private function hitungBentrok($jadwals): int
    {
        $terpakai = [];
        $bentrok = 0;

        foreach ($jadwals as $jadwal) {
            for ($jp = $jadwal->jam_ke_mulai; $jp <= $jadwal->jam_ke_selesai; $jp++) {
                $kunci = $jadwal->hari.'-'.$jp;

                if (isset($terpakai[$kunci])) {
                    $bentrok++;
                }

                $terpakai[$kunci] = true;
            }
        }

        return $bentrok;
    }

    /**
     * Show the form for creating a new jadwal.
     */
    public function create()
    {
        $kelasList = Kelas::orderBy('nama_kelas')->get();
        $mataPelajaranList = MataPelajaran::orderBy('nama')->get();
        $gurus = User::where('role', 'guru')->orderBy('name')->get();

        return view('jadwal.create', compact('kelasList', 'mataPelajaranList', 'gurus'));
    }

    /**
     * Store a newly created jadwal in storage.
     */
    public function store(JadwalRequest $request)
    {
        Jadwal::create($request->validated());

        return redirect()->route('jadwal.index')
            ->with('success', 'Jadwal berhasil ditambahkan.');
    }

    /**
     * Display the specified jadwal.
     */
    public function show(Jadwal $jadwal)
    {
        Gate::authorize('view', $jadwal);

        $jadwal->load(['kelas', 'mataPelajaran', 'guru', 'jurnals']);

        return view('jadwal.show', compact('jadwal'));
    }

    /**
     * Show the form for editing the specified jadwal.
     */
    public function edit(Jadwal $jadwal)
    {
        $kelasList = Kelas::orderBy('nama_kelas')->get();
        $mataPelajaranList = MataPelajaran::orderBy('nama')->get();
        $gurus = User::where('role', 'guru')->orderBy('name')->get();

        return view('jadwal.edit', compact('jadwal', 'kelasList', 'mataPelajaranList', 'gurus'));
    }

    /**
     * Update the specified jadwal in storage.
     */
    public function update(JadwalRequest $request, Jadwal $jadwal)
    {
        $jadwal->update($request->validated());

        return redirect()->route('jadwal.index')
            ->with('success', 'Jadwal berhasil diperbarui.');
    }

    /**
     * Remove the specified jadwal from storage.
     */
    public function destroy(Jadwal $jadwal)
    {
        $jadwal->delete();

        return redirect()->route('jadwal.index')
            ->with('success', 'Jadwal berhasil dihapus.');
    }
}
