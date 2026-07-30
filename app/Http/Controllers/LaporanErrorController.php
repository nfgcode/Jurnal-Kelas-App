<?php

namespace App\Http\Controllers;

use App\Models\LaporanError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Receives an error report a guru/siswa submits from the friendly error page.
 *
 * The technical details are taken from the session (stashed by the exception
 * renderer in bootstrap/app.php), never from the form, so a reporter cannot forge
 * them. Three anti-spam layers apply, in order of cheapness:
 *   1. throttle — one report per JEDA_DETIK per account;
 *   2. daily cap — at most MAKS_HARIAN new reports per account per day;
 *   3. dedupe — the same fault reported again within 24h increments `jumlah`
 *      on the open report instead of creating a new row (and costs no quota).
 */
class LaporanErrorController extends Controller
{
    /** One report per account per 10 minutes. */
    private const JEDA_DETIK = 600;

    /** At most 5 genuinely new reports per account per day. */
    private const MAKS_HARIAN = 5;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pesan' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $error = $request->session()->get('sistem.error_terakhir', []);

        $tandaTangan = md5(implode('|', [
            $error['pesan'] ?? 'tidak diketahui',
            $error['file'] ?? '',
            $error['line'] ?? '',
        ]));

        // 3. Dedupe first: a recurrence is always accepted (it is useful signal)
        // and must not be blocked by the throttle or burn daily quota.
        $lama = LaporanError::belumSelesai()
            ->where('user_id', $user->id)
            ->where('tanda_tangan', $tandaTangan)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if ($lama) {
            $lama->increment('jumlah');

            // Keep the reporter's newest description if they wrote one.
            if (filled($validated['pesan'] ?? null)) {
                $lama->update(['pesan' => $validated['pesan']]);
            }

            return back()->with('lapor_sukses',
                'Terima kasih. Kesalahan ini sudah pernah Anda laporkan, jadi kami tambahkan sebagai kejadian berulang agar admin tahu ini sering terjadi.');
        }

        // 1. Throttle.
        $kunci = 'laporan-error:'.$user->id;

        if (RateLimiter::tooManyAttempts($kunci, 1)) {
            $menit = max(1, (int) ceil(RateLimiter::availableIn($kunci) / 60));

            return back()->with('lapor_gagal',
                "Anda baru saja mengirim laporan. Silakan coba lagi dalam sekitar {$menit} menit.");
        }

        // 2. Daily cap.
        $hariIni = LaporanError::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        if ($hariIni >= self::MAKS_HARIAN) {
            return back()->with('lapor_gagal',
                'Anda sudah mencapai batas '.self::MAKS_HARIAN.' laporan hari ini. Bila masalah mendesak, hubungi admin sekolah langsung.');
        }

        RateLimiter::hit($kunci, self::JEDA_DETIK);

        LaporanError::create([
            'user_id' => $user->id,
            'ref' => $error['ref'] ?? '-',
            'tanda_tangan' => $tandaTangan,
            'pesan' => $validated['pesan'] ?? null,
            'url' => $error['url'] ?? $request->headers->get('referer'),
            'http_status' => $error['status'] ?? null,
            'exception_pesan' => $error['pesan'] ?? null,
            'exception_file' => $error['file'] ?? null,
            'exception_line' => $error['line'] ?? null,
        ]);

        return back()->with('lapor_sukses',
            'Terima kasih, laporan Anda sudah dikirim ke admin beserta kode referensinya.');
    }
}
