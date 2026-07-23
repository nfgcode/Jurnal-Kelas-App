@props(['guru' => null, 'avatar' => true])

@php
    // Only an admin may open the user detail page, so only they get a link;
    // everyone else sees the same name as plain text.
    $bisaTaut = ($guru?->id) && (auth()->user()?->isAdmin() ?? false);
@endphp

@if (! $guru)
    <span class="is-muted">—</span>
@elseif ($bisaTaut)
    <a class="name-cell text-reset" href="{{ route('admin.users.show', $guru) }}">
        @if ($avatar)<span class="avatar avatar--xs">{{ $guru->inisial() }}</span>@endif
        <span>{{ $guru->name }}</span>
    </a>
@else
    <span class="name-cell">
        @if ($avatar)<span class="avatar avatar--xs">{{ $guru->inisial() }}</span>@endif
        <span>{{ $guru->name }}</span>
    </span>
@endif
