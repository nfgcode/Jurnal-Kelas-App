<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PresensiResource;
use App\Models\Jurnal;
use App\Models\Presensi;
use App\Support\SimpanPresensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PresensiController extends Controller
{
    /**
     * Attendance records scoped to the caller: a student sees their own, a guru
     * the rows on their journals, an admin all. Optionally filter by jurnal_id.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $presensi = Presensi::query()
            ->with(['siswa', 'jurnal'])
            ->when($user->isSiswa(), fn ($q) => $q->where('siswa_id', $user->id))
            ->when($user->isGuru(), fn ($q) => $q->whereHas('jurnal', fn ($j) => $j->where('guru_id', $user->id)))
            ->when($request->integer('jurnal_id'), fn ($q, $id) => $q->where('jurnal_id', $id))
            ->latest('id')
            ->paginate(30);

        return PresensiResource::collection($presensi);
    }

    /**
     * The attendance roster recorded for one journal.
     */
    public function show(Jurnal $jurnal)
    {
        Gate::authorize('view', $jurnal);

        return PresensiResource::collection(
            $jurnal->presensis()->with('siswa')->get()
        );
    }

    /**
     * Replace the whole attendance set for a journal (idempotent), mirroring the
     * web roster form. Marking attendance is a write on the journal, so it takes
     * the journal's update policy.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'jurnal_id' => 'required|exists:jurnal,id',
            'presensi' => 'required|array|min:1',
            'presensi.*.siswa_id' => 'required|exists:users,id',
            'presensi.*.status' => 'required|in:hadir,sakit,izin,alpa',
            'presensi.*.keterangan' => 'nullable|string',
        ]);

        $jurnal = Jurnal::findOrFail($validated['jurnal_id']);
        Gate::authorize('update', $jurnal);

        SimpanPresensi::simpan($jurnal, $validated['presensi']);

        return PresensiResource::collection(
            $jurnal->presensis()->with('siswa')->get()
        );
    }
}
