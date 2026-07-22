@props([
    'title' => null,
    'meta' => null,
    // Flush cards let a table run edge to edge; padded cards wrap content.
    'flush' => false,
])

<div {{ $attributes->class(['card-hifi', 'card-hifi--table' => $flush]) }}>
    @if ($title || $meta || isset($actions))
        <div class="card-hifi__head">
            <h3 class="card-hifi__title">{{ $title }}</h3>
            @isset($actions)
                {{ $actions }}
            @else
                @if ($meta)<span class="card-hifi__meta">{{ $meta }}</span>@endif
            @endisset
        </div>
    @endif

    @if ($flush)
        {{ $slot }}
    @else
        <div class="card-hifi__body">{{ $slot }}</div>
    @endif

    @isset($foot)
        <div class="card-hifi__foot">{{ $foot }}</div>
    @endisset
</div>
