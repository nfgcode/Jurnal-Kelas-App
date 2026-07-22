<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\User;
use Illuminate\Http\Request;

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

        $kelasList = Kelas::orderBy('nama_kelas')->get();

        // A student only ever has one timetable; default everyone else to the
        // first class so the matrix is never empty on arrival.
        $kelasAktif = $request->user()->isSiswa() && $request->user()->kelas_id
            ? $kelasList->firstWhere('id', $request->user()->kelas_id)
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
            'guruList' => User::where('role', 'guru')->orderBy('name')->get(),
            'filters' => $filters,
            'statistik' => [
                'totalJadwal' => Jadwal::count(),
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
     * @param  \Illuminate\Support\Collection<int, Jadwal>  $jadwals
     */
    private function hitungBentrok($jadwals): int
    {
        $terpakai = [];
        $bentrok = 0;

        foreach ($jadwals as $jadwal) {
            for ($jp = $jadwal->jam_ke_mulai; $jp <= $jadwal->jam_ke_selesai; $jp++) {
                $kunci = $jadwal->hari . '-' . $jp;

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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kelas_id' => 'required|exists:kelas,id',
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'guru_id' => 'required|exists:users,id',
            'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'jam_ke_mulai' => 'required|integer|min:1|max:12',
            'jam_ke_selesai' => 'required|integer|min:1|max:12|gte:jam_ke_mulai',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'ruang' => 'nullable|string|max:50',
        ]);

        Jadwal::create($validated);

        return redirect()->route('jadwal.index')
            ->with('success', 'Jadwal berhasil ditambahkan.');
    }

    /**
     * Display the specified jadwal.
     */
    public function show(Jadwal $jadwal)
    {
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
    public function update(Request $request, Jadwal $jadwal)
    {
        $validated = $request->validate([
            'kelas_id' => 'required|exists:kelas,id',
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'guru_id' => 'required|exists:users,id',
            'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'jam_ke_mulai' => 'required|integer|min:1|max:12',
            'jam_ke_selesai' => 'required|integer|min:1|max:12|gte:jam_ke_mulai',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'ruang' => 'nullable|string|max:50',
        ]);

        $jadwal->update($validated);

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
