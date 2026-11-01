<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AuditAdmin;
use App\Support\CadanganData;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        AuditAdmin::catat(
            aksi: 'cadangan.ekspor_json',
            tipeEntitas: 'cadangan',
            entitasId: $nama,
            deskripsi: 'Membuat dan mengunduh cadangan JSON (terkompresi) untuk '.($only ? count($only).' tabel' : 'seluruh tabel'),
            metadata: ['format' => 'json.gz', 'tabel' => $only ?? CadanganData::semuaTabel(), 'ukuran_bytes' => filesize($tmp)],
            status: 'berhasil',
            jumlah: $only ? count($only) : count(CadanganData::semuaTabel()),
        );

        $response = response()
            ->download($tmp, $nama, ['Content-Type' => 'application/gzip', 'X-Content-Type-Options' => 'nosniff'])
            ->deleteFileAfterSend(true);
        // BinaryFileResponse defaults to public; set privacy after construction.
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    /** The readable workbook (chosen master tables, one per sheet). */
    public function unduhXlsx(Request $request, CadanganData $cadangan): BinaryFileResponse
    {
        $only = $this->tabelDipilih($request);
        $nama = 'data-jurnal-kelas-'.now()->format('Ymd-His').'.xlsx';

        AuditAdmin::catat(
            aksi: 'cadangan.ekspor_xlsx',
            tipeEntitas: 'cadangan',
            entitasId: $nama,
            deskripsi: 'Membuat dan mengunduh data master Excel (XLSX) untuk '.($only ? count($only).' tabel' : 'tabel master'),
            metadata: ['format' => 'xlsx', 'tabel' => $only ?? 'master'],
            status: 'berhasil',
        );

        return $cadangan->unduhXlsx($nama, $only);
    }

    /**
     * The tables the admin ticked, kept only if they are real backup-able tables.
     * Empty/invalid selections are rejected; only explicit semua=1 means all.
     */
    private function tabelDipilih(Request $request): ?array
    {
        $data = $request->validate([
            'semua' => ['nullable', 'in:1', 'prohibits:tabel'],
            'tabel' => ['required_without:semua', 'array', 'min:1', 'max:64'],
            'tabel.*' => ['required', 'string', 'distinct', \Illuminate\Validation\Rule::in(CadanganData::semuaTabel())],
        ]);

        return ($data['semua'] ?? null) === '1' ? null : array_values($data['tabel']);
    }

    public function pulihkan(Request $request, CadanganData $cadangan)
    {
        abort(503, 'Pemulihan dinonaktifkan sampai pipeline staging Fase 7–8 tersedia.');
    }
}
