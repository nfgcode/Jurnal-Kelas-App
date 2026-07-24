<?php

namespace App\Http\Controllers;

use App\Http\Requests\KelasRequest;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\User;
use App\Support\Ringkasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KelasController extends Controller
{
    /**
     * Display a listing of all kelas, with roll counts and how completely each
     * class's journals have been filled.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'tingkat' => ['nullable', 'in:X,XI,XII'],
            'jurusan' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $kelas = Kelas::query()
            ->with('waliKelas')
            ->withCount(['siswa', 'jadwals'])
            // A guru only sees classes they actually teach in; admin sees all.
            ->when($user->isGuru(), fn ($query) => $query->whereIn('id',
                Jadwal::where('guru_id', $user->id)->select('kelas_id')))
            ->when($filters['tingkat'] ?? null, fn ($query, $tingkat) => $query->where('tingkat', $tingkat))
            ->when($filters['jurusan'] ?? null, fn ($query, $jurusan) => $query->where('jurusan', $jurusan))
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->cari($q))
            // CASE rather than MySQL's FIELD(): the test suite runs on SQLite.
            ->orderByRaw("CASE tingkat WHEN 'X' THEN 1 WHEN 'XI' THEN 2 ELSE 3 END")
            ->orderBy('jurusan')
            ->orderBy('nama_kelas')
            ->paginate(18)
            ->withQueryString();

        $kelengkapan = Ringkasan::kelengkapan('kelas_id');
        $totalSiswa = User::where('role', 'siswa')->count();
        $totalKelas = Kelas::count();
        $tanpaWali = Kelas::whereNull('wali_kelas_id')->pluck('nama_kelas');

        return view('kelas.index', [
            'kelas' => $kelas,
            'kelengkapan' => $kelengkapan,
            'jurusanList' => Kelas::whereNotNull('jurusan')->distinct()->orderBy('jurusan')->pluck('jurusan'),
            'filters' => $filters,
            'statistik' => [
                'totalKelas' => $totalKelas,
                'rataSiswa' => $totalKelas > 0 ? round($totalSiswa / $totalKelas) : 0,
                'waliTerisi' => $totalKelas - $tanpaWali->count(),
                'tanpaWali' => $tanpaWali,
                'rataKelengkapan' => $kelengkapan ? round(array_sum($kelengkapan) / count($kelengkapan)) : 0,
            ],
        ]);
    }

    /**
     * Show the form for creating a new kelas.
     */
    public function create()
    {
        $gurus = User::where('role', 'guru')->orderBy('name')->get();

        return view('kelas.create', compact('gurus'));
    }

    /**
     * Store a newly created kelas in storage.
     */
    public function store(KelasRequest $request)
    {
        Kelas::create($request->validated());

        return redirect()->route('kelas.index')
            ->with('success', 'Kelas berhasil ditambahkan.');
    }

    /**
     * Display the specified kelas.
     */
    public function show(Kelas $kela)
    {
        Gate::authorize('view', $kela);

        $kela->load(['waliKelas', 'siswa', 'jadwals.mataPelajaran', 'jadwals.guru']);

        return view('kelas.show', ['kelas' => $kela]);
    }

    /**
     * Show the form for editing the specified kelas.
     */
    public function edit(Kelas $kela)
    {
        $gurus = User::where('role', 'guru')->orderBy('name')->get();

        return view('kelas.edit', ['kelas' => $kela, 'gurus' => $gurus]);
    }

    /**
     * Update the specified kelas in storage.
     */
    public function update(KelasRequest $request, Kelas $kela)
    {
        $kela->update($request->validated());

        return redirect()->route('kelas.index')
            ->with('success', 'Kelas berhasil diperbarui.');
    }

    /**
     * Remove the specified kelas from storage.
     */
    public function destroy(Kelas $kela)
    {
        $kela->delete();

        return redirect()->route('kelas.index')
            ->with('success', 'Kelas berhasil dihapus.');
    }
}
