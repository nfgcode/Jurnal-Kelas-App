@props(['title', 'sub' => null])

<div class="page-head">
    <div>
        <h2 class="page-head__title">{{ $title }}</h2>
        @if ($sub)
            <p class="page-head__sub">{{ $sub }}</p>
        @endif
    </div>

    @if (! $slot->isEmpty())
        <div class="page-head__actions">{{ $slot }}</div>
    @endif
</div>
