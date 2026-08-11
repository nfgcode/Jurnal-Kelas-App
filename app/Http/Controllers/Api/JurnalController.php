<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\JurnalRequest;
use App\Http\Resources\JurnalResource;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\JurnalAudit;
use Illuminate\Database\QueryException;
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
        $jadwal = Jadwal::findOrFail($data['jadwal_id']);

        // Same ownership rule as the web form: a guru writes on their own slot,
        // a ketua kelas on their own class's; only an admin writes anywhere.
        abort_if($user->isGuru() && $jadwal->guru_id !== $user->id, 403);
        abort_if($user->isSiswa() && $jadwal->kelas_id !== $user->kelas_id, 403);

        $peran = Jurnal::peranPengisi($user);

        // One journal per side per meeting (see the unique index). Reported as a
        // 422 with the existing id so a client can navigate to it.
        if ($lama = Jurnal::sudahAda($jadwal->id, $data['tanggal'], $peran)) {
            return $this->responsGanda($lama, $peran);
        }

        // A meeting the nightly backfill already placeholdered is corrected by
        // updating that row (which adopts it), never by filing a second journal
        // beside it — the unique index would allow the pair, since their
        // diisi_oleh_peran differ ('sistem' vs guru/siswa).
        if ($sistem = Jurnal::sudahAda($jadwal->id, $data['tanggal'], Jurnal::PERAN_SISTEM)) {
            return response()->json([
                'message' => 'Jurnal pertemuan ini sudah dibuat otomatis oleh sistem. Perbarui jurnal tersebut, jangan membuat yang baru.',
                'jurnal_id' => $sistem->id,
                // These routes bind on the journal's route key, so the pointer a
                // client needs to PUT to is the public id, not the numeric one.
                'jurnal_public_id' => $sistem->public_id,
                'errors' => ['jadwal_id' => ['Jurnal pertemuan ini sudah dibuat otomatis oleh sistem. Perbarui jurnal tersebut.']],
            ], 422);
        }

        // A guru writes their own; otherwise the slot's teacher owns the entry.
        $data['guru_id'] = $user->isGuru() ? $user->id : $jadwal->guru_id;
        $data['diisi_oleh_id'] = $user->id;
        $data['diisi_oleh_peran'] = $peran;
        $data['kehadiran_guru_status'] ??= 'hadir';

        try {
            $jurnal = Jurnal::create($data);
        } catch (QueryException $e) {
            if (! Jurnal::pelanggaranGanda($e)) {
                throw $e;
            }

            return $this->responsGanda(Jurnal::sudahAda($jadwal->id, $data['tanggal'], $peran), $peran);
        }

        return (new JurnalResource($jurnal->load(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])))
            ->response()->setStatusCode(201);
    }

    public function update(JurnalRequest $request, Jurnal $jurnal)
    {
        Gate::authorize('update', $jurnal);

        $data = $request->validated();
        $user = $request->user();
        $dariSistem = $jurnal->dibuatSistem();

        // The attestation is a gate, not a stored column (see JurnalRequest).
        unset($data['pernyataan']);

        // jadwal_id is editable, so the *destination* slot must be re-checked:
        // the policy only vetted the journal as it stands. Without this a guru
        // could move their entry onto another teacher's meeting (it would land
        // in that class's history and completeness while still crediting the
        // mover). Mirrors pastikanJadwalMilik() on the web path.
        $jadwalBaru = Jadwal::findOrFail($data['jadwal_id']);
        abort_if($user->isGuru() && $jadwalBaru->guru_id !== $user->id, 403);
        abort_if($user->isSiswa() && $jadwalBaru->kelas_id !== $user->kelas_id, 403);

        // Correcting a system placeholder "adopts" it as the caller's own entry,
        // so both the collision check and the saved row take the editor's side —
        // the same handover the web form performs.
        $peran = $dariSistem
            ? Jurnal::peranPengisi($user)
            : ($jurnal->diisi_oleh_peran ?? Jurnal::peranPengisi($user));

        // Re-pointing a journal must not land on a meeting already covered from
        // the same side.
        $lama = Jurnal::sudahAda((int) $data['jadwal_id'], $data['tanggal'], $peran, $jurnal->id);

        if ($lama) {
            return $this->responsGanda($lama, $lama->diisi_oleh_peran);
        }

        if ($dariSistem) {
            $data['diisi_oleh_peran'] = $peran;
            $data['diisi_oleh_id'] = $user->id;
        }

        $jurnal->update($data);

        return new JurnalResource($jurnal->load(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru']));
    }

    /** The 422 a duplicate journal gets, pointing at the row already on file. */
    private function responsGanda(?Jurnal $lama, string $peran)
    {
        $sisi = $peran === 'siswa' ? 'perwakilan kelas' : 'guru pengajar';

        return response()->json([
            'message' => "Jurnal pertemuan ini sudah diisi dari sisi {$sisi}.",
            'jurnal_id' => $lama?->id,
            'errors' => ['jadwal_id' => ["Jurnal pertemuan ini sudah diisi dari sisi {$sisi}."]],
        ], 422);
    }

    public function destroy(Jurnal $jurnal)
    {
        Gate::authorize('delete', $jurnal);

        $jurnal->delete();

        return response()->json(['message' => 'Jurnal berhasil dihapus.']);
    }
}
