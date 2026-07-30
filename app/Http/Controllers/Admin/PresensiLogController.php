<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\PresensiLog;
use App\Models\User;
use App\Support\Halaman;
use Illuminate\Http\Request;

class PresensiLogController extends Controller
{
    /**
     * The attendance-edit audit trail, newest first. Behind role:admin, so only
     * an admin can see who saved which class's roster and when.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'diedit_oleh_id' => ['nullable', 'exists:users,id'],
        ]);

        $log = PresensiLog::query()
            ->with([
                'dieditOleh',
                'jurnal.jadwal.kelas',
                'jurnal.jadwal.mataPelajaran',
                'jurnal.guru',
            ])
            ->when($filters['kelas_id'] ?? null, fn ($q, $id) => $q->whereHas('jurnal.jadwal', fn ($j) => $j->where('kelas_id', $id)))
            ->when($filters['diedit_oleh_id'] ?? null, fn ($q, $id) => $q->where('diedit_oleh_id', $id))
            ->latest('created_at')
            ->latest('id')
            ->paginate(Halaman::perHalaman())
            ->withQueryString();

        return view('admin.presensi-log.index', [
            'log' => $log,
            'kelasList' => Kelas::orderBy('nama_kelas')->get(),
            'editorList' => User::whereIn('id', PresensiLog::select('diedit_oleh_id')->distinct())
                ->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }
}
