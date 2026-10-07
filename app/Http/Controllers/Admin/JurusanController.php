<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\JurusanRequest;
use App\Models\Jurusan;
use App\Support\Halaman;
use App\Support\PencatatanAkademik;
use App\Support\Urutan;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JurusanController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'bidang' => ['nullable', 'string', 'max:255'],
            'aktif' => ['nullable', 'boolean'],
        ]);
        $query = Jurusan::withCount(['kelas', 'siswa'])
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari))
            ->when($filters['bidang'] ?? null, fn ($q, $bidang) => $q->where('bidang', $bidang));

        if (isset($filters['aktif'])) {
            $query->where('aktif', $filters['aktif']);
        }

        Urutan::terapkan($query, $request, [
            'kode' => fn ($q, $dir) => $q->orderBy('kode', $dir),
            'nama' => fn ($q, $dir) => $q->orderBy('nama', $dir),
            'bidang' => fn ($q, $dir) => $q->orderBy('bidang', $dir),
            'aktif' => fn ($q, $dir) => $q->orderBy('aktif', $dir),
            'kelas' => fn ($q, $dir) => $q->orderBy('kelas_count', $dir),
            'siswa' => fn ($q, $dir) => $q->orderBy('siswa_count', $dir),
        ], fn ($q) => $q->orderBy('kode'));

        return view('admin.jurusan.index', [
            'jurusan' => $query->paginate(Halaman::perHalaman())->withQueryString(),
            'filters' => $filters,
            'bidangList' => Jurusan::whereNotNull('bidang')->distinct()->orderBy('bidang')->pluck('bidang'),
        ]);
    }

    public function create()
    {
        return view('admin.jurusan.create');
    }

    public function store(JurusanRequest $request, PencatatanAkademik $pencatatan)
    {
        $pencatatan->jurusan($request->validated());

        return redirect()->route('admin.jurusan.index')->with('success', 'Jurusan berhasil ditambahkan.');
    }

    public function edit(Jurusan $jurusan)
    {
        return view('admin.jurusan.edit', compact('jurusan'));
    }

    public function update(JurusanRequest $request, Jurusan $jurusan)
    {
        try {
            DB::transaction(fn () => $jurusan->update($request->validated()));
        } catch (QueryException $e) {
            if ($e instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['kode' => 'Kode jurusan ini sudah dipakai.']);
            }

            throw $e;
        }

        return redirect()->route('admin.jurusan.index')->with('success', 'Jurusan berhasil diperbarui.');
    }

    public function destroy(Jurusan $jurusan)
    {
        $ditolak = 'Jurusan masih dipakai kelas atau siswa. Nonaktifkan jurusan jika tidak dipakai untuk pendaftaran baru.';

        if ($jurusan->kelas()->exists() || $jurusan->siswa()->exists()) {
            return redirect()->route('admin.jurusan.index')->with('error', $ditolak);
        }

        try {
            $jurusan->delete();
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1451 || str_contains($e->getMessage(), 'FOREIGN KEY constraint failed')) {
                return redirect()->route('admin.jurusan.index')->with('error', $ditolak);
            }

            throw $e;
        }

        return redirect()->route('admin.jurusan.index')->with('success', 'Jurusan berhasil dihapus.');
    }
}
