@props([
    // Accepts "search" or the older "bi-search"; stored values still carry the prefix.
    'nama',
    // Extra classes for the <svg> itself, when one needs sizing or spacing.
    'kelas' => '',
])

{{-- Inline SVG rather than an icon font: see App\Support\Ikon for why. --}}
{!! \App\Support\Ikon::svg($nama, $kelas) !!}
