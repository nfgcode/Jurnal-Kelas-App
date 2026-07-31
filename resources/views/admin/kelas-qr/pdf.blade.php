<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>QR Kelas</title>
    {{--
        Rendered by Dompdf, not a browser: no external stylesheet, no flexbox and
        no CSS grid (Dompdf supports neither), so the sheet is laid out with a
        table — three cards per row, which is what fits an A4 page.
    --}}
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #131313; margin: 0; }
        h1 { font-size: 14pt; margin: 0 0 2mm; }
        .info { font-size: 8pt; color: #626262; margin: 0 0 5mm; }
        table.kartu { width: 100%; border-collapse: separate; border-spacing: 3mm; }
        td.sel {
            width: 33.33%;
            border: 0.4mm solid #cbcbcb;
            border-radius: 2mm;
            padding: 3mm;
            text-align: center;
            vertical-align: top;
        }
        td.kosong { border: 0; }
        .nama { font-size: 11pt; font-weight: bold; margin: 0 0 0.5mm; }
        .ruang { font-size: 7.5pt; color: #626262; margin: 0 0 2mm; }
        .qr { width: 38mm; height: 38mm; }
        .petunjuk { font-size: 7pt; color: #474747; margin: 2mm 0 0; }
        .alamat { font-size: 5.5pt; color: #7c7c7c; margin: 1mm 0 0; word-wrap: break-word; }
    </style>
</head>
<body>
    <h1>QR Kelas — Jurnal Kelas</h1>
    <p class="info">
        {{ $daftar->count() }} kelas · dicetak {{ $dicetak->translatedFormat('l, j F Y H:i') }} ·
        tempel di tiap ruang rombel. QR hanya bisa dibuka oleh guru yang login.
    </p>

    <table class="kartu">
        @foreach ($daftar->chunk(3) as $baris)
            <tr>
                @foreach ($baris as $item)
                    <td class="sel">
                        <p class="nama">{{ $item['kelas']->nama_kelas }}</p>
                        <p class="ruang">Ruang {{ $item['kelas']->ruang ?? '—' }}</p>
                        <img class="qr" src="{{ $item['png'] }}" alt="QR {{ $item['kelas']->nama_kelas }}">
                        <p class="petunjuk">Scan untuk isi jurnal &amp; presensi (khusus guru)</p>
                        <p class="alamat">{{ $item['url'] }}</p>
                    </td>
                @endforeach

                {{-- Keep the last row's cards the same width as every other row. --}}
                @for ($i = $baris->count(); $i < 3; $i++)
                    <td class="kosong"></td>
                @endfor
            </tr>
        @endforeach
    </table>

    @if ($daftar->isEmpty())
        <p class="info">Tidak ada kelas yang dipilih.</p>
    @endif
</body>
</html>
