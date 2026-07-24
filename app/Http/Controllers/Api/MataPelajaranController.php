<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MataPelajaranRequest;
use App\Http\Resources\MataPelajaranResource;
use App\Models\MataPelajaran;
use Illuminate\Http\Request;

class MataPelajaranController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'kelompok' => ['nullable', 'in:wajib,peminatan,muatan_lokal,kejuruan'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $mapel = MataPelajaran::query()
            ->withCount('jadwals')
            ->when($filters['kelompok'] ?? null, fn ($q, $kelompok) => $q->where('kelompok', $kelompok))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari))
            ->orderBy('nama')
            ->paginate(20);

        return MataPelajaranResource::collection($mapel);
    }

    public function show(MataPelajaran $mataPelajaran)
    {
        $mataPelajaran->loadCount('jadwals');

        return new MataPelajaranResource($mataPelajaran);
    }

    public function store(MataPelajaranRequest $request)
    {
        $mataPelajaran = MataPelajaran::create($request->validated());

        return (new MataPelajaranResource($mataPelajaran))->response()->setStatusCode(201);
    }

    public function update(MataPelajaranRequest $request, MataPelajaran $mataPelajaran)
    {
        $mataPelajaran->update($request->validated());

        return new MataPelajaranResource($mataPelajaran);
    }

    public function destroy(MataPelajaran $mataPelajaran)
    {
        $mataPelajaran->delete();

        return response()->json(['message' => 'Mata pelajaran berhasil dihapus.']);
    }
}
