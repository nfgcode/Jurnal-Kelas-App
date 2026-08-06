<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>QR Kelas</title>
    {{--
        Rendered by Dompdf, not a browser: no flexbox and no CSS grid (Dompdf
        supports neither), so each A4 sheet is a fixed-layout table of 2 x 2 A6
        cells (105 x 148 mm). Four QR per sheet with dashed cut guides — the same
        result as the on-screen "Cetak" button — and a page break after every 4.
    --}}
    <style>
        @page { margin: 0; }
        body { font-family: "DejaVu Sans", sans-serif; color: #131313; margin: 0; }

        table.lembar { width: 210mm; border-collapse: collapse; table-layout: fixed; }
        /* Dompdf adds padding to the declared height (content-box), so the cell
           is height + 20mm of padding. 128 + 20 = 148mm = A6 tall, and two rows
           (296mm) still clear the 297mm A4 page — four cards to a sheet. */
        table.lembar td.sel {
            width: 105mm;
            height: 128mm;
            border: 0.3mm dashed #9a9a9a;
            text-align: center;
            vertical-align: middle;
            padding: 10mm;
        }
        td.kosong { border: 0; }

        .nama { font-size: 18pt; font-weight: bold; margin: 0 0 1mm; }
        .ruang { font-size: 10pt; color: #626262; margin: 0 0 6mm; }
        .qr { width: 62mm; height: 62mm; }
        .petunjuk { font-size: 8pt; color: #474747; margin: 5mm 0 0; }
        .alamat { font-size: 6pt; color: #7c7c7c; margin: 2mm 0 0; word-wrap: break-word; }

        .pemisah { page-break-after: always; }
        .kosong-info { font-family: "DejaVu Sans", sans-serif; padding: 14mm; }
    </style>
</head>
<body>
    @forelse ($daftar->chunk(4) as $grup)
        <table class="lembar">
            @foreach ($grup->chunk(2) as $baris)
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

                    {{-- Pad the final row so its cells keep the A6 width. --}}
                    @for ($i = $baris->count(); $i < 2; $i++)
                        <td class="kosong"></td>
                    @endfor
                </tr>
            @endforeach
        </table>

        @if (! $loop->last)
            <div class="pemisah"></div>
        @endif
    @empty
        <p class="kosong-info">Tidak ada kelas yang dipilih.</p>
    @endforelse
</body>
</html>
