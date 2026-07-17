<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
use App\Models\Presensi;
use App\Models\User;
use Illuminate\Http\Request;

class PresensiController extends Controller
{
    /**
     * Display a listing of presensi records with optional filters.
     */
    public function index(Request $request)
    {
        $query = Presensi::with(['jurnal.jadwal.kelas', 'jurnal.jadwal.mataPelajaran', 'siswa']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date (via jurnal)
        if ($request->filled('tanggal')) {
            $query->whereHas('jurnal', function ($q) use ($request) {
                $q->whereDate('tanggal', $request->tanggal);
            });
        }

        // Filter by kelas (via jurnal -> jadwal)
        if ($request->filled('kelas_id')) {
            $query->whereHas('jurnal.jadwal', function ($q) use ($request) {
                $q->where('kelas_id', $request->kelas_id);
            });
        }

        $presensis = $query->latest()->paginate(20);

        return view('presensi.index', compact('presensis'));
    }

    /**
     * Show the form for creating presensi for a specific jurnal.
     * Loads all siswa from the jurnal's kelas.
     */
    public function create(int $jurnal_id)
    {
        $jurnal = Jurnal::with(['jadwal.kelas.siswa', 'jadwal.mataPelajaran'])->findOrFail($jurnal_id);
        $siswaList = $jurnal->jadwal->kelas->siswa()->orderBy('name')->get();

        return view('presensi.create', compact('jurnal', 'siswaList'));
    }

    /**
     * Bulk store attendance records for all siswa in a jurnal.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'jurnal_id' => 'required|exists:jurnal,id',
            'presensi' => 'required|array',
            'presensi.*.siswa_id' => 'required|exists:users,id',
            'presensi.*.status' => 'required|in:hadir,sakit,izin,alpa',
            'presensi.*.keterangan' => 'nullable|string',
        ]);

        // Delete existing presensi for this jurnal to allow re-submission
        Presensi::where('jurnal_id', $validated['jurnal_id'])->delete();

        foreach ($validated['presensi'] as $data) {
            Presensi::create([
                'jurnal_id' => $validated['jurnal_id'],
                'siswa_id' => $data['siswa_id'],
                'status' => $data['status'],
                'keterangan' => $data['keterangan'] ?? null,
            ]);
        }

        return redirect()->route('presensi.show', $validated['jurnal_id'])
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

        return view('presensi.show', compact('jurnal'));
    }
}
