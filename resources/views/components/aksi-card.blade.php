@props([
    'href',
    'judul',
    'deskripsi' => null,
    'ikon' => 'journal-text',
    // hijau | oranye | khaki | sage — the Figma card gradients.
    'warna' => 'hijau',
    // A verb label ("Isi Jurnal") shown beside the title on wide screens. On a
    // phone the whole card is the tap target, so only the chevron remains.
    'cta' => null,
    // Compact: badge and chevron on top, title underneath — the four-up admin row.
    'ringkas' => false,
])

{{-- The whole card is the link: no button inside it, because the card already
     is the way in (the user's call on the Figma review). --}}
<a href="{{ $href }}" {{ $attributes->class(['aksi-card', 'aksi-card--'.$warna, 'aksi-card--ringkas' => $ringkas]) }}>
    <span class="aksi-card__top">
        <span class="aksi-card__badge"><x-ikon :nama="$ikon" /></span>
        @unless ($ringkas)
            <span class="aksi-card__judul">{{ $judul }}</span>
        @endunless
        @if ($cta)
            <span class="aksi-card__cta">{{ $cta }} <x-ikon nama="chevron-right" /></span>
        @endif
        <span class="aksi-card__chev {{ $cta ? 'aksi-card__chev--sempit' : '' }}"><x-ikon nama="chevron-right" /></span>
    </span>

    @if ($ringkas)
        <span class="aksi-card__judul">{{ $judul }}</span>
    @endif

    @if ($deskripsi)
        <span class="aksi-card__desk">{{ $deskripsi }}</span>
    @endif
</a>
