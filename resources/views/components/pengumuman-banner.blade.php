@props(['untukPeran' => true])

{{--
    Banners for guru/siswa: the announcements an admin posted, plus a generic
    "sedang ada gangguan" notice when the cached health summary is unhealthy.
    Deliberately generic for users — the specifics live on the admin Sistem page.
    Admin does not see these; they see the real status page instead.
--}}
@if ($untukPeran)
    @php
        // Both are cached: this renders on every guru/siswa page view.
        $tayang = \App\Models\Pengumuman::untukBanner();
        $kesehatan = \App\Support\SistemStatus::ringkas();
    @endphp

    @foreach ($tayang as $item)
        <div class="banner banner--{{ $item['warna'] }}">
            <i class="bi {{ $item['ikon'] }}"></i>
            <span>{{ $item['pesan'] }}</span>
        </div>
    @endforeach

    @unless ($kesehatan['sehat'])
        <div class="banner banner--peringatan">
            <i class="bi bi-exclamation-triangle"></i>
            <span>
                Sedang ada gangguan teknis pada sistem. Admin sudah diberi tahu —
                bila ada menu yang gagal dibuka, coba beberapa saat lagi.
            </span>
        </div>
    @endunless
@endif
