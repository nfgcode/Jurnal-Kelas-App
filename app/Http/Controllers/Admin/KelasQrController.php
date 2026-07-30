<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * A printable sheet of per-room QR codes — one per class — for an admin to cut
 * out and post in each classroom. Each QR encodes that class's guru landing
 * (Kelas::qrUrl()). Admin-only via the route group.
 */
class KelasQrController extends Controller
{
    public function index()
    {
        $kelas = Kelas::orderByRaw("CASE tingkat WHEN 'X' THEN 1 WHEN 'XI' THEN 2 ELSE 3 END")
            ->orderBy('jurusan')
            ->orderBy('nama_kelas')
            ->get()
            ->map(function (Kelas $k) {
                $url = $k->qrUrl();

                return [
                    'kelas' => $k,
                    'url' => $url,
                    'svg' => $this->qrSvg($url),
                ];
            });

        return view('admin.kelas-qr.index', ['daftar' => $kelas]);
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
}
