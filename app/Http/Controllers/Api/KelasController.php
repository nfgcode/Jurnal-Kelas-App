<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\KelasRequest;
use App\Http\Resources\KelasResource;
use App\Models\Kelas;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    /**
     * Paginated list of classes with roll counts, filterable by tingkat/jurusan/name.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'tingkat' => ['nullable', 'in:X,XI,XII'],
            'jurusan' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $kelas = Kelas::query()
            ->with('waliKelas')
            ->withCount(['siswa', 'jadwals'])
            ->when($filters['tingkat'] ?? null, fn ($q, $tingkat) => $q->where('tingkat', $tingkat))
            ->when($filters['jurusan'] ?? null, fn ($q, $jurusan) => $q->where('jurusan', $jurusan))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->where('nama_kelas', 'like', "%{$cari}%"))
            ->orderByRaw("CASE tingkat WHEN 'X' THEN 1 WHEN 'XI' THEN 2 ELSE 3 END")
            ->orderBy('jurusan')
            ->orderBy('nama_kelas')
            ->paginate(20);

        return KelasResource::collection($kelas);
    }

    public function show(Kelas $kelas)
    {
        $kelas->load('waliKelas')->loadCount(['siswa', 'jadwals']);

        return new KelasResource($kelas);
    }

    public function store(KelasRequest $request)
    {
        $kelas = Kelas::create($request->validated());

        return (new KelasResource($kelas))->response()->setStatusCode(201);
    }

    public function update(KelasRequest $request, Kelas $kelas)
    {
        $kelas->update($request->validated());

        return new KelasResource($kelas);
    }

    public function destroy(Kelas $kelas)
    {
        $kelas->delete();

        return response()->json(['message' => 'Kelas berhasil dihapus.']);
    }
}
