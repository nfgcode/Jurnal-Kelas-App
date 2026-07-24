<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\JurnalRequest;
use App\Http\Resources\JurnalResource;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\JurnalAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class JurnalController extends Controller
{
    /**
     * Journals scoped to the caller: a guru sees their own, a student their
     * class's, an admin every entry.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:tanggal,id,materi'],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        $user = $request->user();
        $sort = $filters['sort'] ?? 'tanggal';
        $dir = $filters['dir'] ?? 'desc';

        $jurnals = Jurnal::query()
            ->with(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])
            ->withCount([
                'presensis as total_siswa',
                'presensis as hadir_count' => fn ($q) => $q->where('status', 'hadir'),
            ])
            ->when($user->isGuru(), fn ($q) => $q->where('guru_id', $user->id))
            ->when($user->isSiswa(), fn ($q) => $q->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $user->kelas_id)))
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->cariTeks($q))
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        return JurnalResource::collection($jurnals);
    }

    /**
     * Change history for a journal, written by the AFTER UPDATE/DELETE triggers.
     */
    public function audit(Jurnal $jurnal)
    {
        Gate::authorize('update', $jurnal);

        $audit = JurnalAudit::where('jurnal_id', $jurnal->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json(['data' => $audit]);
    }

    public function show(Jurnal $jurnal)
    {
        Gate::authorize('view', $jurnal);

        $jurnal->load(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru', 'diisiOleh', 'presensis.siswa']);

        return new JurnalResource($jurnal);
    }

    public function store(JurnalRequest $request)
    {
        Gate::authorize('create', Jurnal::class);

        $data = $request->validated();
        $user = $request->user();

        // A guru writes their own; an admin writes on behalf of the slot's teacher.
        $data['guru_id'] = $user->isGuru()
            ? $user->id
            : Jadwal::find($data['jadwal_id'])?->guru_id;
        $data['diisi_oleh_id'] = $user->id;
        $data['kehadiran_guru_status'] ??= 'hadir';

        $jurnal = Jurnal::create($data);

        return (new JurnalResource($jurnal->load(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])))
            ->response()->setStatusCode(201);
    }

    public function update(JurnalRequest $request, Jurnal $jurnal)
    {
        Gate::authorize('update', $jurnal);

        $jurnal->update($request->validated());

        return new JurnalResource($jurnal->load(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru']));
    }

    public function destroy(Jurnal $jurnal)
    {
        Gate::authorize('delete', $jurnal);

        $jurnal->delete();

        return response()->json(['message' => 'Jurnal berhasil dihapus.']);
    }
}
