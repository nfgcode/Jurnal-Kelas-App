<?php

namespace App\Http\Controllers;

use App\Http\Requests\MataPelajaranRequest;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\MataPelajaran;
use App\Support\Halaman;
use App\Support\PencatatanAkademik;
use App\Support\Ringkasan;
use App\Support\Urutan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MataPelajaranController extends Controller
{
    /**
     * Display a listing of all mata pelajaran, ordered by teaching load, with
     * the teacher who covers each one and how complete its journals are.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'kelompok' => ['nullable', 'in:wajib,peminatan,muatan_lokal,kejuruan'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $mataPelajaran = MataPelajaran::query()
            // A guru only sees subjects they are timetabled to teach; admin all.
            ->when($user->isGuru(), fn ($query) => $query->whereHas('jadwals', fn ($j) => $j->where('guru_nip', $user->nip)))
            ->when($filters['kelompok'] ?? null, fn ($query, $kelompok) => $query->where('kelompok', $kelompok))
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->cari($q));

        // "Guru Pengampu", "Diajarkan Di", "Kelengkapan" and "Status" come from
        // aggregates resolved after this query, so they cannot be ordered here
        // without reordering only the page already fetched.
        $peta = [
            'nama' => fn ($q, $dir) => $q->orderBy('nama', $dir),
            'kelompok' => fn ($q, $dir) => $q->orderBy('kelompok', $dir),
            'jp' => fn ($q, $dir) => $q->orderBy('jp_per_minggu', $dir),
        ];

        Urutan::terapkan($mataPelajaran, $request, $peta, fn ($q) => $q
            ->orderByDesc('jp_per_minggu')->orderBy('nama'));

        $mataPelajaran = $mataPelajaran->paginate(Halaman::perHalaman())->withQueryString();

        // One teacher name and the distinct class count per subject, aggregated
        // in a single query rather than hydrating every jadwal + guru + kelas.
        // For a guru the summary covers only their own timetable, matching the
        // scoped list — not other teachers' assignments.
        $ringkasan = Jadwal::query()
            ->join('guru', 'jadwal.guru_nip', '=', 'guru.nip')
            ->whereIn('jadwal.mata_pelajaran_id', $mataPelajaran->pluck('id'))
            ->when($user->isGuru(), fn ($q) => $q->where('jadwal.guru_nip', $user->nip))
            ->selectRaw('jadwal.mata_pelajaran_id, COUNT(DISTINCT jadwal.kelas_id) as kelas_count, MIN(guru.nama) as guru_nama')
            ->groupBy('jadwal.mata_pelajaran_id')
            ->get()
            ->keyBy('mata_pelajaran_id');

        // A subject with no scheduled meeting has nobody teaching it.
        $tanpaGuru = MataPelajaran::doesntHave('jadwals')->pluck('nama');

        return view('mata-pelajaran.index', [
            'mataPelajaran' => $mataPelajaran,
            'ringkasan' => $ringkasan,
            'kelengkapan' => Ringkasan::kelengkapan('mata_pelajaran_id'),
            'filters' => $filters,
            'statistik' => [
                'total' => MataPelajaran::count(),
                'totalJadwal' => Jadwal::count(),
                'totalJp' => (int) Jadwal::query()
                    ->join('mata_pelajaran', 'jadwal.mata_pelajaran_id', '=', 'mata_pelajaran.id')
                    ->sum('mata_pelajaran.jp_per_minggu'),
                'guruPengampu' => Jadwal::distinct()->count('guru_nip'),
                'totalGuru' => Guru::count(),
                'tanpaGuru' => $tanpaGuru,
            ],
        ]);
    }

    /**
     * Show the form for creating a new mata pelajaran.
     */
    public function create()
    {
        return view('mata-pelajaran.create', [
            'guruList' => Guru::aktif()->orderBy('nama')->get(),
        ]);
    }

    /**
     * Store a newly created mata pelajaran in storage.
     */
    public function store(MataPelajaranRequest $request, PencatatanAkademik $pencatatan)
    {
        $data = $request->validated();

        // The subject and the teachers certified for it are written together:
        // a subject nobody can teach is one the schedule form refuses everyone for.
        $pencatatan->mataPelajaran($data, $request->input('guru_nip', []));

        return redirect()->route('mata-pelajaran.index')
            ->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    /**
     * Display the specified mata pelajaran.
     */
    public function show(MataPelajaran $mataPelajaran)
    {
        Gate::authorize('view', $mataPelajaran);

        $mataPelajaran->load([
            'jadwals.kelas',
            'jadwals.guru',
            'jadwals.ruangan',
            'guru' => fn ($q) => $q->orderBy('nama'),
        ]);

        return view('mata-pelajaran.show', [
            'mataPelajaran' => $mataPelajaran,
            // Every active teacher, so the assignment box on this page can offer
            // the ones not yet holding the subject.
            'guruList' => Guru::aktif()->orderBy('nama')->get(),
        ]);
    }

    /**
     * Replace the set of teachers certified for this one subject.
     *
     * Edited from the subject's own page rather than a central matrix, because
     * that is the question an admin actually arrives with: "who teaches Kimia?"
     * The teacher's page edits the same pivot from the other side.
     */
    public function simpanGuru(Request $request, MataPelajaran $mataPelajaran)
    {
        $data = $request->validate([
            'guru_nip' => ['array'],
            'guru_nip.*' => ['string', 'exists:guru,nip'],
            'utama' => ['nullable', 'string', 'exists:guru,nip'],
        ]);

        $nips = array_values(array_unique($data['guru_nip'] ?? []));
        $utama = in_array($data['utama'] ?? null, $nips, true) ? $data['utama'] : null;

        // Detaching a teacher who is still timetabled for this subject would
        // leave the schedule asserting something the school no longer records.
        $terjadwal = $mataPelajaran->jadwals()
            ->whereNotIn('guru_nip', $nips ?: [''])
            ->distinct()
            ->pluck('guru_nip');

        if ($terjadwal->isNotEmpty()) {
            $nama = Guru::whereIn('nip', $terjadwal)->orderBy('nama')->pluck('nama')->join(', ');

            return back()->with('error', sprintf(
                'Tidak bisa melepas %s: masih ada jadwal %s yang diampu. Hapus atau pindahkan jadwalnya dulu.',
                $nama,
                $mataPelajaran->nama,
            ));
        }

        $mataPelajaran->guru()->sync(
            collect($nips)->mapWithKeys(fn ($nip) => [$nip => ['utama' => $nip === $utama]])->all()
        );

        return back()->with('success', 'Guru pengampu '.$mataPelajaran->nama.' berhasil diperbarui.');
    }

    /**
     * Show the form for editing the specified mata pelajaran.
     */
    public function edit(MataPelajaran $mataPelajaran)
    {
        return view('mata-pelajaran.edit', compact('mataPelajaran'));
    }

    /**
     * Update the specified mata pelajaran in storage.
     */
    public function update(MataPelajaranRequest $request, MataPelajaran $mataPelajaran)
    {
        $mataPelajaran->update($request->validated());

        return redirect()->route('mata-pelajaran.index')
            ->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    /**
     * Remove the specified mata pelajaran from storage.
     */
    public function destroy(MataPelajaran $mataPelajaran)
    {
        $mataPelajaran->delete();

        return redirect()->route('mata-pelajaran.index')
            ->with('success', 'Mata pelajaran berhasil dihapus.');
    }
}
