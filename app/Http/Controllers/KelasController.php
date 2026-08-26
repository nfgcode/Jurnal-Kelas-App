<?php

namespace App\Http\Controllers;

use App\Http\Requests\KelasRequest;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Ruangan;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Support\Halaman;
use App\Support\PencatatanAkademik;
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
            'jurusan' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $kelas = Kelas::query()
            ->with(['waliKelas', 'ruangan', 'jurusan'])
            ->withCount(['siswa', 'jadwals'])
            // A guru only sees classes they actually teach in; admin sees all.
            ->when($user->isGuru(), fn ($query) => $query->whereIn('id',
                Jadwal::where('guru_nip', $user->nip)->select('kelas_id')))
            ->when($filters['tingkat'] ?? null, fn ($query, $tingkat) => $query->where('tingkat', $tingkat))
            ->when($filters['jurusan'] ?? null, fn ($query, $jurusan) => $query->where('jurusan_kode', $jurusan))
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
            ->orderBy('jurusan_kode')
            ->orderBy('nama_kelas'));

        $kelas = $kelas->paginate(Halaman::perHalaman())->withQueryString();

        $kelengkapan = Ringkasan::kelengkapan('kelas_id');
        $totalSiswa = Siswa::count();
        $totalKelas = Kelas::count();
        $tanpaWali = Kelas::whereNull('wali_kelas_nip')->pluck('nama_kelas');

        return view('kelas.index', [
            'kelas' => $kelas,
            'kelengkapan' => $kelengkapan,
            'jurusanList' => Jurusan::aktif()->orderBy('nama')->get(),
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
    private function opsiForm(?Kelas $kelas = null): array
    {
        return [
            'gurus' => Guru::aktif()->orderBy('nama')->get(),
            'ruanganList' => Ruangan::aktif()->orderBy('kode')->get(),
            'jurusanList' => Jurusan::aktif()->orderBy('nama')->get(),
            'tahunAjaranList' => TahunAjaran::orderByDesc('kode')->get(),
            'tahunBerjalan' => TahunAjaran::berjalan()?->kode,
            // Only this class's own students can chair it, so the dropdown is
            // empty on the create form — there is nobody enrolled yet.
            'siswaKelas' => $kelas
                ? Siswa::where('kelas_id', $kelas->id)->orderBy('nama')->get()
                : collect(),
        ];
    }

    /**
     * Store a newly created kelas in storage.
     */
    public function store(KelasRequest $request, PencatatanAkademik $pencatatan)
    {
        $data = $request->validated();
        // A class with no students cannot have a chair yet.
        unset($data['ketua_nis']);

        // Through sp_tambah_kelas, so the duplicate-rombel check and the insert
        // are one step — two admins filing "XII TKJ 2" at once cannot both pass.
        $pencatatan->kelas($data);

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
        return view('kelas.edit', ['kelas' => $kela] + $this->opsiForm($kela));
    }

    /**
     * Update the specified kelas in storage.
     */
    public function update(KelasRequest $request, Kelas $kela)
    {
        $data = $request->validated();

        // The chair must be a student of this class. Checked here rather than in
        // the request because the rule needs the class being edited.
        if (! empty($data['ketua_nis'])
            && ! Siswa::where('kelas_id', $kela->id)->whereKey($data['ketua_nis'])->exists()) {
            return back()->withInput()->withErrors([
                'ketua_nis' => 'Ketua kelas harus siswa dari kelas ini.',
            ]);
        }

        $kela->update($data);

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
