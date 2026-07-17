<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    /**
     * Display a listing of all kelas.
     */
    public function index()
    {
        $kelas = Kelas::with('waliKelas')
            ->withCount('siswa')
            ->latest()
            ->paginate(15);

        return view('kelas.index', compact('kelas'));
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_kelas' => 'required|string|max:255',
            'tingkat' => 'required|in:X,XI,XII',
            'jurusan' => 'nullable|string|max:255',
            'tahun_ajaran' => 'required|string|max:9',
            'wali_kelas_id' => 'nullable|exists:users,id',
        ]);

        Kelas::create($validated);

        return redirect()->route('kelas.index')
            ->with('success', 'Kelas berhasil ditambahkan.');
    }

    /**
     * Display the specified kelas.
     */
    public function show(Kelas $kela)
    {
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
    public function update(Request $request, Kelas $kela)
    {
        $validated = $request->validate([
            'nama_kelas' => 'required|string|max:255',
            'tingkat' => 'required|in:X,XI,XII',
            'jurusan' => 'nullable|string|max:255',
            'tahun_ajaran' => 'required|string|max:9',
            'wali_kelas_id' => 'nullable|exists:users,id',
        ]);

        $kela->update($validated);

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
