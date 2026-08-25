<?php

namespace App\Http\Controllers;

use App\Http\Requests\RuanganRequest;
use App\Models\Ruangan;
use App\Support\Halaman;
use App\Support\Urutan;
use Illuminate\Http\Request;

/**
 * The room register.
 *
 * Rooms used to be a string typed twice — once on the class, once on the
 * timetable slot — which meant the school had no list of its own rooms, no way
 * to see which were double-booked, and no seat count to check a class against.
 */
class RuanganController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'jenis' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'in:aktif,perbaikan,nonaktif'],
            'gedung' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $ruangan = Ruangan::query()
            ->withCount(['kelas', 'jadwals'])
            ->when($filters['jenis'] ?? null, fn ($q, $jenis) => $q->where('jenis', $jenis))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['gedung'] ?? null, fn ($q, $gedung) => $q->where('gedung', $gedung))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari));

        Urutan::terapkan($ruangan, $request, [
            'kode' => fn ($q, $dir) => $q->orderBy('kode', $dir),
            'nama' => fn ($q, $dir) => $q->orderBy('nama', $dir),
            'jenis' => fn ($q, $dir) => $q->orderBy('jenis', $dir),
            'kapasitas' => fn ($q, $dir) => $q->orderBy('kapasitas', $dir),
            'gedung' => fn ($q, $dir) => $q->orderBy('gedung', $dir)->orderBy('lantai', $dir),
            'jadwal' => fn ($q, $dir) => $q->orderBy('jadwals_count', $dir),
        ], fn ($q) => $q->orderBy('kode'));

        $semua = Ruangan::query();

        return view('ruangan.index', [
            'ruangan' => $ruangan->paginate(Halaman::perHalaman())->withQueryString(),
            'filters' => $filters,
            'gedungList' => Ruangan::whereNotNull('gedung')->distinct()->orderBy('gedung')->pluck('gedung'),
            'statistik' => [
                'total' => (clone $semua)->count(),
                'aktif' => (clone $semua)->where('status', 'aktif')->count(),
                'perbaikan' => (clone $semua)->where('status', 'perbaikan')->count(),
                'kapasitas' => (clone $semua)->where('status', 'aktif')->sum('kapasitas'),
                // A room nobody has timetabled is either genuinely spare or a
                // leftover from an old layout; either way the admin wants it
                // surfaced rather than buried on page three.
                'menganggur' => (clone $semua)->where('status', 'aktif')
                    ->whereDoesntHave('jadwals')->whereDoesntHave('kelas')
                    ->orderBy('kode')->pluck('kode'),
            ],
        ]);
    }

    public function create()
    {
        return view('ruangan.create');
    }

    public function store(RuanganRequest $request)
    {
        Ruangan::create($request->validated());

        return redirect()->route('ruangan.index')
            ->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function show(Ruangan $ruangan)
    {
        $ruangan->load([
            'kelas' => fn ($q) => $q->orderBy('nama_kelas'),
            'jadwals.kelas',
            'jadwals.mataPelajaran',
            'jadwals.guru',
        ]);

        return view('ruangan.show', compact('ruangan'));
    }

    public function edit(Ruangan $ruangan)
    {
        return view('ruangan.edit', compact('ruangan'));
    }

    public function update(RuanganRequest $request, Ruangan $ruangan)
    {
        $ruangan->update($request->validated());

        return redirect()->route('ruangan.index')
            ->with('success', 'Ruangan berhasil diperbarui.');
    }

    /**
     * Rooms in use are not deleted.
     *
     * The foreign keys would happily null out every class and timetable slot
     * pointing here, silently leaving lessons with nowhere to meet. A room that
     * is out of service has a status for that; deletion is for rows entered by
     * mistake.
     */
    public function destroy(Ruangan $ruangan)
    {
        $dipakai = $ruangan->kelas()->count() + $ruangan->jadwals()->count();

        if ($dipakai > 0) {
            return redirect()->route('ruangan.index')->with('error', sprintf(
                'Ruangan %s masih dipakai %d kelas/jadwal. Ubah statusnya jadi "Nonaktif" bila sudah tidak dipakai.',
                $ruangan->kode,
                $dipakai,
            ));
        }

        $ruangan->delete();

        return redirect()->route('ruangan.index')
            ->with('success', 'Ruangan berhasil dihapus.');
    }
}
