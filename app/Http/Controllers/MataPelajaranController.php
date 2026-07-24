<?php

namespace App\Http\Controllers;

use App\Http\Requests\MataPelajaranRequest;
use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Models\User;
use App\Support\Ringkasan;
use Illuminate\Http\Request;

class MataPelajaranController extends Controller
{
    /**
     * Display a listing of all mata pelajaran, ordered by teaching load, with
     * the teacher who covers each one and how complete its journals are.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'kelompok' => ['nullable', 'in:wajib,peminatan,muatan_lokal,kejuruan'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $mataPelajaran = MataPelajaran::query()
            ->withCount('jadwals')
            ->with(['jadwals.guru', 'jadwals.kelas'])
            ->when($filters['kelompok'] ?? null, fn ($query, $kelompok) => $query->where('kelompok', $kelompok))
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->cari($q))
            ->orderByDesc('jp_per_minggu')
            ->orderBy('nama')
            ->paginate(18)
            ->withQueryString();

        // A subject with no scheduled meeting has nobody teaching it.
        $tanpaGuru = MataPelajaran::doesntHave('jadwals')->pluck('nama');

        return view('mata-pelajaran.index', [
            'mataPelajaran' => $mataPelajaran,
            'kelengkapan' => Ringkasan::kelengkapan('mata_pelajaran_id'),
            'filters' => $filters,
            'statistik' => [
                'total' => MataPelajaran::count(),
                'totalJadwal' => Jadwal::count(),
                'totalJp' => (int) Jadwal::query()
                    ->join('mata_pelajaran', 'jadwal.mata_pelajaran_id', '=', 'mata_pelajaran.id')
                    ->sum('mata_pelajaran.jp_per_minggu'),
                'guruPengampu' => Jadwal::distinct()->count('guru_id'),
                'totalGuru' => User::where('role', 'guru')->count(),
                'tanpaGuru' => $tanpaGuru,
            ],
        ]);
    }

    /**
     * Show the form for creating a new mata pelajaran.
     */
    public function create()
    {
        return view('mata-pelajaran.create');
    }

    /**
     * Store a newly created mata pelajaran in storage.
     */
    public function store(MataPelajaranRequest $request)
    {
        MataPelajaran::create($request->validated());

        return redirect()->route('mata-pelajaran.index')
            ->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    /**
     * Display the specified mata pelajaran.
     */
    public function show(MataPelajaran $mataPelajaran)
    {
        $mataPelajaran->load('jadwals.kelas', 'jadwals.guru');

        return view('mata-pelajaran.show', compact('mataPelajaran'));
    }

    /**
     * Show the form for editing the specified mata pelajaran.
     */
    public function edit(MataPelajaran $mataPelajaran)
    {
        return view('mata-pelajaran.edit', compact('mataPelajaran'));
    }

    /**
     * Update the specified mata pelajaran in storage.
     */
    public function update(MataPelajaranRequest $request, MataPelajaran $mataPelajaran)
    {
        $mataPelajaran->update($request->validated());

        return redirect()->route('mata-pelajaran.index')
            ->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    /**
     * Remove the specified mata pelajaran from storage.
     */
    public function destroy(MataPelajaran $mataPelajaran)
    {
        $mataPelajaran->delete();

        return redirect()->route('mata-pelajaran.index')
            ->with('success', 'Mata pelajaran berhasil dihapus.');
    }
}
