<?php

namespace App\Http\Controllers;

use App\Http\Requests\KelasRequest;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Ruangan;
use App\Models\Siswa;
use App\Support\Halaman;
use App\Support\Ringkasan;
use App\Support\Urutan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KelasController extends Controller
{
    /**
     * Display a listing of all kelas, with roll counts and how completely each
     * class's journals have been filled.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'tingkat' => ['nullable', 'in:X,XI,XII'],
            'jurusan' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $kelas = Kelas::query()
            ->with(['waliKelas', 'ruangan'])
            ->withCount(['siswa', 'jadwals'])
            // A guru only sees classes they actually teach in; admin sees all.
            ->when($user->isGuru(), fn ($query) => $query->whereIn('id',
                Jadwal::where('guru_nip', $user->nip)->select('kelas_id')))
            ->when($filters['tingkat'] ?? null, fn ($query, $tingkat) => $query->where('tingkat', $tingkat))
            ->when($filters['jurusan'] ?? null, fn ($query, $jurusan) => $query->where('jurusan', $jurusan))
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->cari($q));

        // Only columns the database can order. "Kelengkapan"/"Status" are worked
        // out in PHP from a separate aggregate after paginating, so sorting on
        // them would only reorder the page in hand — worse than not offering it.
        $peta = [
            'nama' => fn ($q, $dir) => $q->orderBy('nama_kelas', $dir),
            'wali' => fn ($q, $dir) => $q->orderBy(
                Guru::select('nama')->whereColumn('guru.nip', 'kelas.wali_kelas_nip')->limit(1), $dir
            ),
            'ruang' => fn ($q, $dir) => $q->orderBy('ruangan_kode', $dir),
            'siswa' => fn ($q, $dir) => $q->orderBy('siswa_count', $dir),
            'jadwal' => fn ($q, $dir) => $q->orderBy('jadwals_count', $dir),
        ];

        // CASE rather than MySQL's FIELD(): the test suite runs on SQLite.
        Urutan::terapkan($kelas, $request, $peta, fn ($q) => $q
            ->orderByRaw("CASE tingkat WHEN 'X' THEN 1 WHEN 'XI' THEN 2 ELSE 3 END")
            ->orderBy('jurusan')
            ->orderBy('nama_kelas'));

        $kelas = $kelas->paginate(Halaman::perHalaman())->withQueryString();

        $kelengkapan = Ringkasan::kelengkapan('kelas_id');
        $totalSiswa = Siswa::count();
        $totalKelas = Kelas::count();
        $tanpaWali = Kelas::whereNull('wali_kelas_nip')->pluck('nama_kelas');

        return view('kelas.index', [
            'kelas' => $kelas,
            'kelengkapan' => $kelengkapan,
            'jurusanList' => Kelas::whereNotNull('jurusan')->distinct()->orderBy('jurusan')->pluck('jurusan'),
            'filters' => $filters,
            'statistik' => [
                'totalKelas' => $totalKelas,
                'rataSiswa' => $totalKelas > 0 ? round($totalSiswa / $totalKelas) : 0,
                'waliTerisi' => $totalKelas - $tanpaWali->count(),
                'tanpaWali' => $tanpaWali,
                'rataKelengkapan' => $kelengkapan ? round(array_sum($kelengkapan) / count($kelengkapan)) : 0,
            ],
        ]);
    }

    /**
     * Show the form for creating a new kelas.
     */
    public function create()
    {
        return view('kelas.create', $this->opsiForm());
    }

    /**
     * Pick lists shared by the create and edit forms, so the two cannot offer
     * different sets of teachers or rooms.
     *
     * @return array<string, mixed>
     */
    private function opsiForm(): array
    {
        return [
            'gurus' => Guru::aktif()->orderBy('nama')->get(),
            'ruanganList' => Ruangan::aktif()->orderBy('kode')->get(),
        ];
    }

    /**
     * Store a newly created kelas in storage.
     */
    public function store(KelasRequest $request)
    {
        Kelas::create($request->validated());

        return redirect()->route('kelas.index')
            ->with('success', 'Kelas berhasil ditambahkan.');
    }

    /**
     * Display the specified kelas.
     */
    public function show(Kelas $kela)
    {
        Gate::authorize('view', $kela);

        $kela->load(['waliKelas', 'ruangan', 'siswa', 'jadwals.mataPelajaran', 'jadwals.guru', 'jadwals.ruangan']);

        return view('kelas.show', ['kelas' => $kela]);
    }

    /**
     * Show the form for editing the specified kelas.
     */
    public function edit(Kelas $kela)
    {
        return view('kelas.edit', ['kelas' => $kela] + $this->opsiForm());
    }

    /**
     * Update the specified kelas in storage.
     */
    public function update(KelasRequest $request, Kelas $kela)
    {
        $kela->update($request->validated());

        return redirect()->route('kelas.index')
            ->with('success', 'Kelas berhasil diperbarui.');
    }

    /**
     * Remove the specified kelas from storage.
     */
    public function destroy(Kelas $kela)
    {
        $kela->delete();

        return redirect()->route('kelas.index')
            ->with('success', 'Kelas berhasil dihapus.');
    }
}
