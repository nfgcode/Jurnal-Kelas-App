@props(['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpa' => 0])

@php
    $segments = ['hadir' => $hadir, 'sakit' => $sakit, 'izin' => $izin, 'alpa' => $alpa];
    $total = array_sum($segments) ?: 1;
@endphp

<span class="stack">
    @foreach ($segments as $key => $value)
        @if ($value > 0)
            <span class="stack__seg--{{ $key }}" style="width: {{ $value / $total * 100 }}%"></span>
        @endif
    @endforeach
</span>
