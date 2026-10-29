<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\LaporanError;
use App\Models\Pengumuman;
use App\Support\Halaman;
use App\Support\PembacaLog;
use App\Support\PengingatPemeliharaan;
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
            'tab' => ['nullable', 'string', 'in:log-sistem,aktivitas-admin'],
            'audit_q' => ['nullable', 'string', 'max:255'],
            'audit_user' => ['nullable', 'string', 'max:255'],
            'audit_aksi' => ['nullable', 'string', 'max:50'],
            'audit_status' => ['nullable', 'string', 'in:berhasil,gagal,diproses'],
            'audit_dari' => ['nullable', 'date'],
            'audit_sampai' => ['nullable', 'date'],
        ]);

        // 1. Pengingat pemeliharaan data tahunan
        $pengingat = PengingatPemeliharaan::evaluasiPengingat();

        // 2. Log teknis aplikasi (storage/logs/laravel.log)
        $log = PembacaLog::ringkasan($filter['level'] ?? null);

        // 3. Laporan error dari pengguna
        $laporan = LaporanError::with('pelapor')
            ->when($filter['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByRaw("CASE status WHEN 'baru' THEN 1 WHEN 'diproses' THEN 2 ELSE 3 END")
            ->latest('updated_at')
            ->paginate(Halaman::perHalaman())
            ->withQueryString();

        // 4. Log audit aktivitas admin (tabel admin_audit_log)
        $auditQuery = AdminAuditLog::query()
            ->with('user')
            ->when($filter['audit_q'] ?? null, function ($q, $cari) {
                $q->where(function ($sub) use ($cari) {
                    $sub->where('deskripsi', 'like', "%{$cari}%")
                        ->orWhere('entitas_id', 'like', "%{$cari}%")
                        ->orWhere('nama_user', 'like', "%{$cari}%");
                });
            })
            ->when($filter['audit_user'] ?? null, function ($q, $user) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('nama_user', 'like', "%{$user}%")
                        ->orWhere('user_id', $user);
                });
            })
            ->when($filter['audit_aksi'] ?? null, fn ($q, $aksi) => $q->where('aksi', $aksi))
            ->when($filter['audit_status'] ?? null, fn ($q, $st) => $q->where('status', $st))
            ->when($filter['audit_dari'] ?? null, fn ($q, $dari) => $q->whereDate('created_at', '>=', $dari))
            ->when($filter['audit_sampai'] ?? null, fn ($q, $sampai) => $q->whereDate('created_at', '<=', $sampai))
            ->latest('id');

        $auditLogs = $auditQuery->paginate(20, ['*'], 'audit_page')->withQueryString();
        $aksiAuditTersedia = AdminAuditLog::distinct('aksi')->orderBy('aksi')->pluck('aksi');
        $pelaksanaAuditTersedia = AdminAuditLog::whereNotNull('nama_user')->distinct('nama_user')->orderBy('nama_user')->pluck('nama_user');

        $tabAktif = $filter['tab'] ?? ($request->hasAny(['audit_page', 'audit_q', 'audit_user', 'audit_aksi', 'audit_status', 'audit_dari', 'audit_sampai']) ? 'aktivitas-admin' : 'log-sistem');

        return view('admin.sistem.index', [
            'cek' => SistemStatus::lengkap(),
            'pengingat' => $pengingat,
            'log' => $log,
            'levelTersedia' => $log['level'],
            'laporan' => $laporan,
            'jumlahBaru' => LaporanError::where('status', 'baru')->count(),
            'pengumuman' => Pengumuman::with('dibuatOleh')->latest('id')->get(),
            'filter' => $filter,
            'tabAktif' => $tabAktif,
            'auditLogs' => $auditLogs,
            'aksiAuditTersedia' => $aksiAuditTersedia,
            'pelaksanaAuditTersedia' => $pelaksanaAuditTersedia,
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
