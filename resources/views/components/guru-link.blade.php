@props(['guru' => null, 'avatar' => true])

@php
    // Only an admin may open the teacher's page, so only they get a link;
    // everyone else sees the same name as plain text. getKey() rather than ->id:
    // a Guru is keyed by NIP, and asking for ->id silently yielded null, which
    // quietly turned every admin link back into plain text.
    $bisaTaut = ($guru?->getKey()) && (auth()->user()?->isAdmin() ?? false);
@endphp

@if (! $guru)
    <span class="is-muted">—</span>
@elseif ($bisaTaut)
    <a class="name-cell text-reset" href="{{ route('admin.guru.show', $guru) }}">
        @if ($avatar)<span class="avatar avatar--xs">{{ $guru->inisial() }}</span>@endif
        <span>{{ $guru->nama }}</span>
    </a>
@else
    <span class="name-cell">
        @if ($avatar)<span class="avatar avatar--xs">{{ $guru->inisial() }}</span>@endif
        <span>{{ $guru->nama }}</span>
    </span>
@endif
