<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CadanganData;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Backup & restore of the whole dataset — for moving the app to another server or
 * recovering after one fails. Admin-only (the route group enforces role:admin).
 *
 * Export comes in two shapes: a JSON snapshot (the restore format) and a readable
 * XLSX workbook. Restore accepts the JSON back, in a mode the admin picks:
 * "gabung" (merge) or "ganti" (replace) — the destructive one asks to confirm.
 */
class CadanganController extends Controller
{
    public function index(CadanganData $cadangan)
    {
        return view('admin.cadangan.index', [
            'ringkasan' => $cadangan->ringkasan(),
        ]);
    }

    /**
     * The JSON snapshot (optionally a chosen subset of tables) — the restore file.
     * Written through gzip: ~20x smaller to store and transfer. Restore reads both
     * the compressed .json.gz and a plain .json back.
     */
    public function unduhJson(Request $request, CadanganData $cadangan): BinaryFileResponse
    {
        $only = $this->tabelDipilih($request);

        // Streamed + gzipped to a temp file so the presensi table never sits in
        // memory whole and the download stays small.
        $tmp = tempnam(sys_get_temp_dir(), 'cadangan');
        $cadangan->tulisJson($tmp, $only, gzip: true);

        $nama = 'cadangan-jurnal-kelas-'.now()->format('Ymd-His').'.json.gz';

        return response()
            ->download($tmp, $nama, ['Content-Type' => 'application/gzip'])
            ->deleteFileAfterSend(true);
    }

    /** The readable workbook (chosen master tables, one per sheet). */
    public function unduhXlsx(Request $request, CadanganData $cadangan): BinaryFileResponse
    {
        return $cadangan->unduhXlsx('data-jurnal-kelas-'.now()->format('Ymd-His').'.xlsx', $this->tabelDipilih($request));
    }

    /**
     * The tables the admin ticked, kept only if they are real backup-able tables.
     * An empty pick means "everything" — a blank sheet is never the intent.
     */
    private function tabelDipilih(Request $request): ?array
    {
        $pilih = array_values(array_intersect(
            (array) $request->input('tabel', []),
            CadanganData::semuaTabel(),
        ));

        return $pilih === [] ? null : $pilih;
    }

    public function pulihkan(Request $request, CadanganData $cadangan)
    {
        $request->validate([
            // 100 MB; the server's PHP upload_max_filesize / nginx client_max_body_size
            // must allow at least this for a large school's backup.
            'berkas' => ['required', 'file', 'max:102400'],
            'mode' => ['required', 'in:gabung,ganti'],
            'konfirmasi' => ['accepted'],
        ], [
            'konfirmasi.accepted' => 'Centang konfirmasi dulu — pemulihan menimpa data yang ada.',
        ]);

        // A rare admin action over a potentially large file; give it room.
        @ini_set('memory_limit', '512M');

        $isi = file_get_contents($request->file('berkas')->getRealPath());

        // Accept both the gzipped download (.json.gz) and a plain .json — detect
        // gzip by its magic bytes rather than trusting the extension.
        if (str_starts_with($isi, "\x1f\x8b")) {
            $isi = @gzdecode($isi);

            if ($isi === false) {
                return back()->with('error', 'Berkas .gz gagal dibuka. Pastikan file cadangan tidak rusak.');
            }
        }

        $data = json_decode($isi, true);

        if (! is_array($data) || ! isset($data['tabel']) || ! is_array($data['tabel'])) {
            return back()->with('error', 'Berkas tidak dikenali sebagai cadangan JSON Jurnal Kelas.');
        }

        try {
            $hasil = $cadangan->pulihkan($data, $request->input('mode'));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Pemulihan gagal dan tidak ada perubahan yang disimpan: '.$e->getMessage());
        }

        $mode = $request->input('mode') === 'ganti' ? 'Ganti total' : 'Gabung';
        $baris = array_sum($hasil);

        return back()->with('success', sprintf(
            '%s selesai — %s baris dipulihkan ke %d tabel.',
            $mode,
            number_format($baris, 0, ',', '.'),
            count($hasil),
        ));
    }
}
