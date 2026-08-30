<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PresensiHarianResource;
use App\Http\Resources\PresensiResource;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Presensi;
use App\Models\PresensiHarian;
use App\Support\SimpanPresensiJurnal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Attendance over the API, in the same shape the web uses.
 *
 * Reading is addressed by class and date, because that is the question a recap
 * asks: one row per student per school day, derived from the day's lessons.
 * Writing is addressed by journal, because that is where a roster is actually
 * made — one per meeting, by the guru who taught it. See {@see Presensi}.
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
            ->when($user->isSiswa() && ! $user->isKetuaKelas(), fn ($q) => $q->where('siswa_nis', $user->nis))
            ->when($user->isKetuaKelas(), fn ($q) => $q->where('kelas_id', $user->kelas_id))
            ->when($user->isGuru(), fn ($q) => $q->whereIn(
                'kelas_id',
                Jadwal::where('guru_nip', $user->nip)->select('kelas_id')
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
     * Replace one meeting's whole roster (idempotent), mirroring the web form.
     *
     * Only the guru timetabled to teach the meeting may write it, and admin —
     * see JurnalPolicy::isiPresensi. Saving also re-derives the class's day-level
     * record from every lesson it holds.
     */
    public function store(Request $request, Jurnal $jurnal)
    {
        Gate::authorize('isiPresensi', $jurnal);

        $kelas = $jurnal->jadwal?->kelas;

        if ($kelas === null) {
            return response()->json([
                'message' => 'Pertemuan ini tidak terhubung ke kelas mana pun.',
            ], 422);
        }

        // Attendance may only be recorded for students actually in this class.
        $roster = $kelas->siswa()->pluck('nis')->all();

        $validated = $request->validate([
            'presensi' => ['required', 'array', 'min:1'],
            'presensi.*.siswa_nis' => ['required', Rule::in($roster)],
            'presensi.*.status' => ['required', Rule::in(Presensi::STATUS)],
            'presensi.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        SimpanPresensiJurnal::simpan($jurnal, $validated['presensi'], $request->user());

        return PresensiResource::collection(
            Presensi::with('siswa')->where('jurnal_id', $jurnal->id)->get()
        );
    }
}
