<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Jurnal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JurnalController extends Controller
{
    /**
     * Display a listing of all jurnal entries.
     */
    public function index()
    {
        $jurnals = Jurnal::with(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])
            ->latest('tanggal')
            ->paginate(15);

        return view('jurnal.index', compact('jurnals'));
    }

    /**
     * Show the form for creating a new jurnal entry.
     */
    public function create()
    {
        $jadwals = Jadwal::with(['kelas', 'mataPelajaran'])
            ->orderBy('hari')
            ->get();

        return view('jurnal.create', compact('jadwals'));
    }

    /**
     * Store a newly created jurnal entry in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'jadwal_id' => 'required|exists:jadwal,id',
            'tanggal' => 'required|date',
            'materi' => 'required|string',
            'kegiatan' => 'required|string',
            'catatan' => 'nullable|string',
        ]);

        $validated['guru_id'] = Auth::id();

        Jurnal::create($validated);

        return redirect()->route('jurnal.index')
            ->with('success', 'Jurnal berhasil ditambahkan.');
    }

    /**
     * Display the specified jurnal entry.
     */
    public function show(Jurnal $jurnal)
    {
        $jurnal->load(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru', 'presensis.siswa']);

        return view('jurnal.show', compact('jurnal'));
    }

    /**
     * Show the form for editing the specified jurnal entry.
     */
    public function edit(Jurnal $jurnal)
    {
        $jadwals = Jadwal::with(['kelas', 'mataPelajaran'])
            ->orderBy('hari')
            ->get();

        return view('jurnal.edit', compact('jurnal', 'jadwals'));
    }

    /**
     * Update the specified jurnal entry in storage.
     */
    public function update(Request $request, Jurnal $jurnal)
    {
        $validated = $request->validate([
            'jadwal_id' => 'required|exists:jadwal,id',
            'tanggal' => 'required|date',
            'materi' => 'required|string',
            'kegiatan' => 'required|string',
            'catatan' => 'nullable|string',
        ]);

        $jurnal->update($validated);

        return redirect()->route('jurnal.index')
            ->with('success', 'Jurnal berhasil diperbarui.');
    }

    /**
     * Remove the specified jurnal entry from storage.
     */
    public function destroy(Jurnal $jurnal)
    {
        $jurnal->delete();

        return redirect()->route('jurnal.index')
            ->with('success', 'Jurnal berhasil dihapus.');
    }
}
