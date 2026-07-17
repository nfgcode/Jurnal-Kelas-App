<?php

namespace App\Http\Controllers;

use App\Models\MataPelajaran;
use Illuminate\Http\Request;

class MataPelajaranController extends Controller
{
    /**
     * Display a listing of all mata pelajaran.
     */
    public function index()
    {
        $mataPelajaran = MataPelajaran::latest()->paginate(15);

        return view('mata-pelajaran.index', compact('mataPelajaran'));
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kode' => 'required|string|max:20|unique:mata_pelajaran,kode',
            'deskripsi' => 'nullable|string',
        ]);

        MataPelajaran::create($validated);

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
    public function update(Request $request, MataPelajaran $mataPelajaran)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kode' => 'required|string|max:20|unique:mata_pelajaran,kode,' . $mataPelajaran->id,
            'deskripsi' => 'nullable|string',
        ]);

        $mataPelajaran->update($validated);

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
