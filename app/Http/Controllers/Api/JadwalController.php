<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\JadwalRequest;
use App\Http\Resources\JadwalResource;
use App\Models\Jadwal;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'guru_id' => ['nullable', 'exists:users,id'],
            'hari' => ['nullable', 'in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $jadwal = Jadwal::query()
            ->with(['kelas', 'mataPelajaran', 'guru'])
            ->when($filters['kelas_id'] ?? null, fn ($q, $id) => $q->where('kelas_id', $id))
            ->when($filters['guru_id'] ?? null, fn ($q, $id) => $q->where('guru_id', $id))
            ->when($filters['hari'] ?? null, fn ($q, $hari) => $q->where('hari', $hari))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->whereHas('mataPelajaran', fn ($m) => $m->where('nama', 'like', "%{$cari}%")))
            ->orderBy('hari')
            ->orderBy('jam_ke_mulai')
            ->paginate(30);

        return JadwalResource::collection($jadwal);
    }

    public function show(Jadwal $jadwal)
    {
        $jadwal->load(['kelas', 'mataPelajaran', 'guru']);

        return new JadwalResource($jadwal);
    }

    public function store(JadwalRequest $request)
    {
        $jadwal = Jadwal::create($request->validated());

        return (new JadwalResource($jadwal->load(['kelas', 'mataPelajaran', 'guru'])))
            ->response()->setStatusCode(201);
    }

    public function update(JadwalRequest $request, Jadwal $jadwal)
    {
        $jadwal->update($request->validated());

        return new JadwalResource($jadwal->load(['kelas', 'mataPelajaran', 'guru']));
    }

    public function destroy(Jadwal $jadwal)
    {
        $jadwal->delete();

        return response()->json(['message' => 'Jadwal berhasil dihapus.']);
    }
}
