<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\PresensiHarianLog;
use App\Models\User;
use App\Support\Halaman;
use Illuminate\Http\Request;

class PresensiLogController extends Controller
{
    /**
     * The attendance-edit audit trail, newest first. Behind role:admin, so only
     * an admin can see who changed which class's day, when, and whether it was
     * the first roster filed that day or a later correction.
     *
     * One entry per lesson saved: the day-level record is rebuilt from the
     * meeting rosters on every save, so this is the trail of teachers marking
     * their own lessons rather than of one person filing the whole day.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'diedit_oleh_id' => ['nullable', 'exists:users,id'],
            'koreksi' => ['nullable', 'in:1'],
        ]);

        $log = PresensiHarianLog::query()
            ->with(['dieditOleh', 'kelas'])
            ->when($filters['kelas_id'] ?? null, fn ($q, $id) => $q->where('kelas_id', $id))
            ->when($filters['diedit_oleh_id'] ?? null, fn ($q, $id) => $q->where('diedit_oleh_id', $id))
            ->when($filters['koreksi'] ?? null, fn ($q) => $q->where('koreksi', true))
            ->latest('created_at')
            ->latest('id')
            ->paginate(Halaman::perHalaman())
            ->withQueryString();

        return view('admin.presensi-log.index', [
            'log' => $log,
            'kelasList' => Kelas::orderBy('nama_kelas')->get(),
            'editorList' => User::whereIn('id', PresensiHarianLog::select('diedit_oleh_id')->distinct())
                ->orderBy('nama')->get(),
            'filters' => $filters,
        ]);
    }
}
