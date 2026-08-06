@props(['filters' => [], 'kelasList' => []])

@php
    // The jurusan options are exactly those present in the (already role-scoped)
    // class list, so the filter never offers a jurusan the reader cannot see —
    // and no extra query or controller variable is needed to build them.
    $jurusanList = collect($kelasList)->pluck('jurusan')->filter()->unique()->sort();
@endphp

<select class="select-hifi" name="tingkat" style="width: 140px" onchange="this.form.submit()">
    <option value="">Semua Tingkat</option>
    @foreach (['X', 'XI', 'XII'] as $t)
        <option value="{{ $t }}" @selected(($filters['tingkat'] ?? null) === $t)>Tingkat {{ $t }}</option>
    @endforeach
</select>

<select class="select-hifi" name="jurusan" style="width: 180px" data-searchable onchange="this.form.submit()">
    <option value="">Semua Jurusan</option>
    @foreach ($jurusanList as $j)
        <option value="{{ $j }}" @selected(($filters['jurusan'] ?? null) === $j)>{{ $j }}</option>
    @endforeach
</select>
