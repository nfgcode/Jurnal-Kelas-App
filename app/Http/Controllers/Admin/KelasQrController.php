<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Printable per-room QR codes — one per class — for an admin to cut out and post
 * in each classroom. Each QR encodes that class's guru landing (Kelas::qrUrl()).
 * Admin-only via the route group.
 *
 * The sheet defaults to every class, but a school rarely reprints all of them at
 * once: a room changes, one code gets torn down, a new rombel starts. So the
 * selection is part of the address — the same `kelas_id[]` drives both the screen
 * and the PDF, and a chosen set survives being bookmarked or shared.
 */
class KelasQrController extends Controller
{
    public function index(Request $request)
    {
        [$dipilih, $daftar] = $this->pilihan($request);

        return view('admin.kelas-qr.index', [
            'daftar' => $daftar->map(fn (Kelas $k) => [
                'kelas' => $k,
                'url' => $k->qrUrl(),
                'svg' => $this->qrSvg($k->qrUrl()),
            ]),
            'semuaKelas' => $this->urutKelas()->get(),
            'dipilih' => $dipilih,
        ]);
    }

    /**
     * The same sheet as a downloadable PDF, so it can be filed or sent to whoever
     * does the printing instead of relying on the browser's print dialog.
     */
    public function pdf(Request $request)
    {
        [$dipilih, $daftar] = $this->pilihan($request);

        // PNG, not SVG: Dompdf's SVG support is partial and renders QR modules
        // unreliably, and a QR that scans is the entire point of the sheet.
        $html = view('admin.kelas-qr.pdf', [
            'daftar' => $daftar->map(fn (Kelas $k) => [
                'kelas' => $k,
                'url' => $k->qrUrl(),
                'png' => $this->qrPngDataUri($k->qrUrl()),
            ]),
            'dicetak' => now(),
        ])->render();

        $opsi = new Options;
        // Everything is embedded as a data: URI, so the renderer never needs to
        // reach the network — this app is expected to run on an offline LAN.
        $opsi->set('isRemoteEnabled', false);
        $opsi->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($opsi);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $nama = $dipilih === []
            ? 'qr-kelas-semua.pdf'
            : 'qr-kelas-'.$daftar->count().'-kelas.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
        ]);
    }

    /**
     * Which classes the sheet covers: the ones asked for, or all of them.
     *
     * An id that does not exist is simply dropped rather than erroring — a stale
     * bookmark should still print the classes that are left.
     *
     * @return array{0: array<int>, 1: Collection<int, Kelas>}
     */
    private function pilihan(Request $request): array
    {
        $request->validate([
            'kelas_id' => ['nullable', 'array'],
            'kelas_id.*' => ['integer'],
        ]);

        $diminta = array_filter((array) $request->input('kelas_id', []), 'is_numeric');
        $diminta = array_map('intval', $diminta);

        $daftar = $this->urutKelas()
            ->when($diminta !== [], fn ($q) => $q->whereIn('id', $diminta))
            ->get();

        return [$diminta, $daftar];
    }

    /** Tingkat, then jurusan, then name — the order a printed stack is filed in. */
    private function urutKelas()
    {
        // CASE rather than MySQL's FIELD(): the test suite runs on SQLite.
        return Kelas::orderByRaw("CASE tingkat WHEN 'X' THEN 1 WHEN 'XI' THEN 2 ELSE 3 END")
            ->orderBy('jurusan')
            ->orderBy('nama_kelas');
    }

    /**
     * The QR for a URL as an inline SVG (crisp at any print size), with the XML
     * prolog stripped so it embeds directly in the page.
     */
    private function qrSvg(string $url): string
    {
        $svg = (new Builder(writer: new SvgWriter, data: $url, size: 220, margin: 2))
            ->build()
            ->getString();

        // Drop the leading XML declaration; keep from "<svg" onward for inlining.
        $pos = strpos($svg, '<svg');

        return $pos === false ? $svg : substr($svg, $pos);
    }

    /** The same QR as a base64 PNG data URI, for the PDF renderer. */
    private function qrPngDataUri(string $url): string
    {
        $png = (new Builder(writer: new PngWriter, data: $url, size: 320, margin: 2))->build();

        return 'data:image/png;base64,'.base64_encode($png->getString());
    }
}
