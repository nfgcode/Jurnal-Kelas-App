<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LaporanError;
use App\Models\Pengumuman;
use App\Support\Halaman;
use App\Support\PembacaLog;
use App\Support\SistemStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The admin "Sistem & Log" page: live health of the app and its dependencies, the
 * tail of the error log, the inbox of error reports guru/siswa submitted, and the
 * announcement banners shown to them. Admin-only via the route group.
 */
class SistemController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->validate([
            'level' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(LaporanError::STATUS)],
        ]);

        // One read/parse of the log tail yields both the entries and the levels
        // available for the filter.
        $log = PembacaLog::ringkasan($filter['level'] ?? null);

        $laporan = LaporanError::with('pelapor')
            ->when($filter['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            // Open reports first, then the most recent activity.
            ->orderByRaw("CASE status WHEN 'baru' THEN 1 WHEN 'diproses' THEN 2 ELSE 3 END")
            ->latest('updated_at')
            ->paginate(Halaman::perHalaman())
            ->withQueryString();

        return view('admin.sistem.index', [
            'cek' => SistemStatus::lengkap(),
            'log' => $log,
            'levelTersedia' => $log['level'],
            'laporan' => $laporan,
            'jumlahBaru' => LaporanError::where('status', 'baru')->count(),
            'pengumuman' => Pengumuman::with('dibuatOleh')->latest('id')->get(),
            'filter' => $filter,
        ]);
    }

    /** Move a report through triage (baru → diproses → selesai). */
    public function ubahStatusLaporan(Request $request, LaporanError $laporan)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(LaporanError::STATUS)],
        ]);

        $laporan->update($data);

        return back()->with('success', "Status laporan {$laporan->ref} diubah menjadi {$data['status']}.");
    }

    /** Post a banner for guru/siswa. */
    public function simpanPengumuman(Request $request)
    {
        $data = $request->validate([
            'pesan' => ['required', 'string', 'max:500'],
            'tipe' => ['required', Rule::in(Pengumuman::TIPE)],
            'selesai' => ['nullable', 'date', 'after:now'],
        ]);

        Pengumuman::create($data + [
            'aktif' => true,
            'mulai' => now(),
            'dibuat_oleh_id' => $request->user()->id,
        ]);

        // The banner set is cached; show this one immediately.
        Pengumuman::lupakanTayang();

        return back()->with('success', 'Pengumuman ditayangkan untuk guru dan siswa.');
    }

    /** Switch a banner on or off without deleting its history. */
    public function alihkanPengumuman(Pengumuman $pengumuman)
    {
        $pengumuman->update(['aktif' => ! $pengumuman->aktif]);

        Pengumuman::lupakanTayang();

        return back()->with('success', $pengumuman->aktif
            ? 'Pengumuman kembali ditayangkan.'
            : 'Pengumuman dihentikan.');
    }

    /**
     * Truncate the log file. Useful once a pile of errors has been dealt with —
     * the viewer only reads the tail, so a huge log is mostly noise.
     */
    public function bersihkanLog()
    {
        $berkas = storage_path('logs/laravel.log');

        if (is_file($berkas)) {
            file_put_contents($berkas, '');
        }

        // The health summary reports log size, so let it re-measure.
        SistemStatus::lupakanRingkas();

        return back()->with('success', 'Berkas log dibersihkan.');
    }
}
