@props([
    // The UI column name, matching a key of the screen's sort map.
    'kolom',
    'label',
    // Set when this column is the screen's default ordering, so it reads as
    // active before the user has clicked anything.
    'bawaan' => false,
])

@php
    $aktifSekarang = (string) request()->query('sort');
    $arah = request()->query('dir') === 'desc' ? 'desc' : 'asc';

    // Active when explicitly sorted on, or when nothing is sorted and this is the
    // screen's natural order.
    $aktif = $aktifSekarang === $kolom || ($aktifSekarang === '' && $bawaan);

    // Clicking the active column flips direction; any other column starts ascending.
    // `page` is reset because row 1 of the new order is what the user wants to see.
    $url = request()->fullUrlWithQuery([
        'sort' => $kolom,
        'dir' => $aktifSekarang === $kolom && $arah === 'asc' ? 'desc' : 'asc',
        'page' => null,
    ]);

    $panah = $aktif ? ($arah === 'desc' ? '↓' : '↑') : '↕';
@endphp

<a class="tbl__sort {{ $aktif ? 'is-active' : '' }}" href="{{ $url }}"
   title="Urutkan menurut {{ Str::lower($label) }}">
    {{ $label }}<span class="tbl__sort-arrow">{{ $panah }}</span>
</a>
