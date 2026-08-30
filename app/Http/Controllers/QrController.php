<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Presensi;
use App\Support\Ringkasan;
use Illuminate\Http\Request;

/**
 * The landing a guru reaches by scanning a classroom's QR code. The class is
 * fixed by the QR (resolved from its opaque qr_token); this confirms it and
 * offers the guru's own subjects in that class, then hands off to the roster for
 * the one they pick. Guru-only (enforced by the route's role:guru).
 *
 * It leads to presensi, not to the journal: the journal is the class's to write
 * now, and marking the roll is what the teacher came to the room to do.
 */
class QrController extends Controller
{
    public function show(Request $request, Kelas $kelas)
    {
        $user = $request->user();

        // Only the scanning guru's own meetings in this class — never another
        // teacher's — so the handoff passes ownership cleanly.
        // CASE rather than MySQL's FIELD(): the test suite runs on SQLite.
        $urutHari = "CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 "
            ."WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 ELSE 6 END";

        $jadwals = Jadwal::where('kelas_id', $kelas->id)
            ->where('guru_nip', $user->nip)
            ->with('mataPelajaran')
            ->orderByRaw($urutHari)
            ->orderBy('jam_ke_mulai')
            ->get();

        // Which of today's meetings already have their roster marked, so the
        // page can steer the guru to the one still outstanding.
        $hariIni = Ringkasan::hariIni();
        $jadwalHariIni = $jadwals->where('hari', $hariIni);

        $jurnalHariIni = Jurnal::whereIn('jadwal_id', $jadwalHariIni->pluck('id'))
            ->whereDate('tanggal', today())
            ->get();

        $jumlah = Presensi::jumlahPerJurnal($jurnalHariIni->pluck('id')->all());

        $sudahDitandai = $jurnalHariIni
            ->filter(fn ($j) => ($jumlah[$j->id] ?? 0) > 0)
            ->pluck('jadwal_id')
            ->all();

        // Default the picker to today's next unmarked meeting, else today's
        // first, else the guru's first meeting in this class at all.
        $rekomendasi = $jadwalHariIni->firstWhere(fn ($j) => ! in_array($j->id, $sudahDitandai, true))
            ?? $jadwalHariIni->first()
            ?? $jadwals->first();

        return view('qr.konfirmasi', [
            'kelas' => $kelas,
            'jadwals' => $jadwals,
            'hariIni' => $hariIni,
            'sudahDitandai' => $sudahDitandai,
            'rekomendasi' => $rekomendasi,
        ]);
    }
}
