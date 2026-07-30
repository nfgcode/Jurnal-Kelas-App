@props([
    'paginator',
    // Set false on tables where changing the page size makes no sense.
    'ukuran' => true,
])

@php
    // Everything except the page and size rides along, so changing either keeps
    // the active filters, search, and sort.
    $bawaan = collect(request()->except(['page', 'per']))
        ->filter(fn ($v) => is_scalar($v));
@endphp

<div class="pager-bar">
    @if ($ukuran)
        <form method="GET" class="pager-bar__ukuran">
            @foreach ($bawaan as $nama => $nilai)
                <input type="hidden" name="{{ $nama }}" value="{{ $nilai }}">
            @endforeach

            <label>
                Baris
                <select class="select-hifi select-hifi--sm" name="per" onchange="this.form.submit()">
                    @foreach (\App\Support\Halaman::PILIHAN as $pilihan)
                        <option value="{{ $pilihan }}" @selected($paginator->perPage() === $pilihan)>{{ $pilihan }}</option>
                    @endforeach
                </select>
            </label>
        </form>
    @endif

    @if ($paginator->hasPages())
        <nav class="pager">
            @if ($paginator->onFirstPage())
                <span class="is-disabled">‹</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a>
            @endif

            @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 1), min($paginator->lastPage(), $paginator->currentPage() + 1)) as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="is-current">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">›</a>
            @else
                <span class="is-disabled">›</span>
            @endif
        </nav>

        <form method="GET" class="pager-bar__lompat">
            @foreach ($bawaan as $nama => $nilai)
                <input type="hidden" name="{{ $nama }}" value="{{ $nilai }}">
            @endforeach
            <input type="hidden" name="per" value="{{ $paginator->perPage() }}">

            <label>
                Ke hal.
                {{-- max stops a jump landing on an empty page past the end. --}}
                <input class="input-hifi input-hifi--sm" type="number" name="page"
                       min="1" max="{{ $paginator->lastPage() }}"
                       value="{{ $paginator->currentPage() }}"
                       aria-label="Lompat ke halaman">
            </label>
            <span class="pager-bar__dari">dari {{ $paginator->lastPage() }}</span>
        </form>
    @endif
</div>
