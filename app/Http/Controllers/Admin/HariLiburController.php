<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HariLibur;
use App\Models\Jurnal;
use App\Support\Halaman;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Hari Libur: the dates the school does not meet. Without them the nightly
 * backfill records every teacher as absent on a national holiday, and every
 * completeness figure counts lessons that were never going to happen.
 */
class HariLiburController extends Controller
{
    /** Longest range one entry may cover — a semester break, not a year. */
    private const MAKS_HARI = 62;

    public function index(Request $request)
    {
        $tampil = $request->query('tampil') === 'semua' ? 'semua' : 'mendatang';

        $libur = HariLibur::query()
            ->when($tampil === 'mendatang', fn ($q) => $q->whereDate('tanggal', '>=', today()))
            ->orderBy('tanggal', $tampil === 'mendatang' ? 'asc' : 'desc')
            ->paginate(Halaman::perHalaman())
            ->withQueryString();

        return view('admin.hari-libur.index', [
            'libur' => $libur,
            'tampil' => $tampil,
        ]);
    }

    /**
     * One date or a range (ujian, PKL, libur semester). Saving an existing date
     * only renames it. Then the backfill's placeholders already sitting on those
     * dates are removed — they claim a teacher skipped a lesson that never took
     * place. Placeholders that carry a roster are kept: someone taught that day.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['required', 'string', 'max:150'],
        ], [
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        $mulai = Carbon::parse($data['tanggal_mulai'])->startOfDay();
        $selesai = Carbon::parse($data['tanggal_selesai'] ?? $data['tanggal_mulai'])->startOfDay();

        if ($mulai->diffInDays($selesai) + 1 > self::MAKS_HARI) {
            return back()->withInput()->withErrors([
                'tanggal_selesai' => 'Rentang paling panjang '.self::MAKS_HARI.' hari. Pisahkan menjadi beberapa entri.',
            ]);
        }

        $jumlah = 0;
        for ($t = $mulai->copy(); $t->lte($selesai); $t->addDay()) {
            $jumlah++;
            // Looked up by date, not by an exact string: SQLite keeps a date cast
            // with a time part, so updateOrCreate's equality would miss the row.
            $ada = HariLibur::whereDate('tanggal', $t->toDateString())->first();
            $ada
                ? $ada->update(['keterangan' => $data['keterangan']])
                : HariLibur::create(['tanggal' => $t->toDateString(), 'keterangan' => $data['keterangan']]);
        }

        // Model deletes, not a bulk query, so Jurnal::deleted keeps each class's
        // daily rollup in step (a placeholder without a roster changes nothing
        // there, but the hook is the one place that rule lives).
        $dihapus = Jurnal::where('diisi_oleh_peran', Jurnal::PERAN_SISTEM)
            ->whereDate('tanggal', '>=', $mulai->toDateString())
            ->whereDate('tanggal', '<=', $selesai->toDateString())
            ->whereDoesntHave('presensis')
            ->get()
            ->each->delete()
            ->count();

        $pesan = $jumlah.' tanggal ditandai libur: '.$data['keterangan'].'.';
        if ($dihapus > 0) {
            $pesan .= " {$dihapus} jurnal otomatis pada tanggal tersebut dihapus.";
        }

        return redirect()->route('admin.hari-libur.index')->with('success', $pesan);
    }

    public function destroy(HariLibur $hariLibur)
    {
        $hariLibur->delete();

        return redirect()->route('admin.hari-libur.index')
            ->with('success', 'Tanggal '.$hariLibur->tanggal->translatedFormat('j F Y').' kembali menjadi hari sekolah.');
    }
}
