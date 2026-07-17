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
     * Display a listing of all jadwal.
     */
    public function index()
    {
        $jadwals = Jadwal::with(['kelas', 'mataPelajaran', 'guru'])
            ->latest()
            ->paginate(15);

        return view('jadwal.index', compact('jadwals'));
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
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
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
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
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
