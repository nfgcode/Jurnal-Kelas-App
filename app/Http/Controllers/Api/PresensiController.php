<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PresensiHarianResource;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\PresensiHarian;
use App\Support\SimpanPresensiHarian;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Attendance over the API, in the same shape the web uses: one roll call per
 * class per day.
 *
 * The endpoints are addressed by class and date rather than by journal, because
 * that is what a roster is now — see {@see PresensiHarian}.
 */
class PresensiController extends Controller
{
    /**
     * Attendance scoped to the caller: a student sees their own, a guru the
     * classes they teach, an admin everything. Filterable by class and date.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'tanggal' => ['nullable', 'date'],
            'mulai' => ['nullable', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
        ]);

        $presensi = PresensiHarian::query()
            ->with(['siswa', 'kelas'])
            ->when($user->isSiswa() && ! $user->isKetuaKelas(), fn ($q) => $q->where('siswa_id', $user->id))
            ->when($user->isKetuaKelas(), fn ($q) => $q->where('kelas_id', $user->kelas_id))
            ->when($user->isGuru(), fn ($q) => $q->whereIn(
                'kelas_id',
                Jadwal::where('guru_id', $user->id)->select('kelas_id')
            ))
            ->when($filters['kelas_id'] ?? null, fn ($q, $id) => $q->where('kelas_id', $id))
            ->when($filters['tanggal'] ?? null, fn ($q, $t) => $q->whereDate('tanggal', Carbon::parse($t)->toDateString()))
            ->when($filters['mulai'] ?? null, fn ($q, $t) => $q->whereDate('tanggal', '>=', Carbon::parse($t)->toDateString()))
            ->when($filters['selesai'] ?? null, fn ($q, $t) => $q->whereDate('tanggal', '<=', Carbon::parse($t)->toDateString()))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(30);

        return PresensiHarianResource::collection($presensi);
    }

    /**
     * One class's roll call for one day. The date defaults to today.
     */
    public function show(Request $request, Kelas $kelas)
    {
        Gate::authorize('lihatPresensiHarian', $kelas);

        $request->validate(['tanggal' => ['nullable', 'date']]);

        $tanggal = $request->filled('tanggal')
            ? Carbon::parse($request->query('tanggal'))->toDateString()
            : today()->toDateString();

        return PresensiHarianResource::collection(
            PresensiHarian::with('siswa')
                ->where('kelas_id', $kelas->id)
                ->whereDate('tanggal', $tanggal)
                ->get()
        );
    }

    /**
     * Replace a class's whole roster for a day (idempotent), mirroring the web
     * form. Only the class's ketua kelas and admin may write it, and a ketua
     * only for today — see KelasPolicy::isiPresensiHarian.
     */
    public function store(Request $request, Kelas $kelas)
    {
        Gate::authorize('isiPresensiHarian', $kelas);

        $user = $request->user();

        $tanggal = $request->filled('tanggal')
            ? Carbon::parse($request->input('tanggal'))->toDateString()
            : today()->toDateString();

        if (! $user->isAdmin() && $tanggal !== today()->toDateString()) {
            return response()->json([
                'message' => 'Presensi hanya dapat diisi untuk hari ini.',
            ], 422);
        }

        // Attendance may only be recorded for students actually in this class.
        $roster = $kelas->siswa()->pluck('id')->all();

        $validated = $request->validate([
            'tanggal' => ['nullable', 'date'],
            'presensi' => ['required', 'array', 'min:1'],
            'presensi.*.siswa_id' => ['required', Rule::in($roster)],
            'presensi.*.status' => ['required', Rule::in(PresensiHarian::STATUS)],
            'presensi.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        SimpanPresensiHarian::simpan($kelas, $tanggal, $validated['presensi'], $user);

        return PresensiHarianResource::collection(
            PresensiHarian::with('siswa')
                ->where('kelas_id', $kelas->id)
                ->whereDate('tanggal', $tanggal)
                ->get()
        );
    }
}
