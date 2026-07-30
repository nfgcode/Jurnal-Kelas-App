<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Support\Ringkasan;
use Illuminate\Http\Request;

/**
 * The landing a guru reaches by scanning a classroom's QR code. The class is
 * fixed by the QR (resolved from its opaque qr_token); this confirms it and
 * offers the guru's own subjects in that class, then hands off to the normal
 * journal→presensi flow. Guru-only (enforced by the route's role:guru).
 */
class QrController extends Controller
{
    public function show(Request $request, Kelas $kelas)
    {
        $user = $request->user();

        // Only the scanning guru's own meetings in this class — never another
        // teacher's — so the handoff to jurnal.create passes ownership cleanly.
        // CASE rather than MySQL's FIELD(): the test suite runs on SQLite.
        $urutHari = "CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 "
            ."WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 ELSE 6 END";

        $jadwals = Jadwal::where('kelas_id', $kelas->id)
            ->where('guru_id', $user->id)
            ->with('mataPelajaran')
            ->orderByRaw($urutHari)
            ->orderBy('jam_ke_mulai')
            ->get();

        // Which of today's meetings already have a journal, so the page can
        // steer the guru to the one still missing — same rule as jadwalTerpilih.
        $hariIni = Ringkasan::hariIni();
        $jadwalHariIni = $jadwals->where('hari', $hariIni);
        $sudahDiisi = Jurnal::whereIn('jadwal_id', $jadwalHariIni->pluck('id'))
            ->whereDate('tanggal', today())
            ->pluck('jadwal_id')
            ->all();

        // Default the picker to today's next unfilled meeting, else today's
        // first, else the guru's first meeting in this class at all.
        $rekomendasi = $jadwalHariIni->firstWhere(fn ($j) => ! in_array($j->id, $sudahDiisi, true))
            ?? $jadwalHariIni->first()
            ?? $jadwals->first();

        return view('qr.konfirmasi', [
            'kelas' => $kelas,
            'jadwals' => $jadwals,
            'hariIni' => $hariIni,
            'sudahDiisi' => $sudahDiisi,
            'rekomendasi' => $rekomendasi,
        ]);
    }
}
