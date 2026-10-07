@props(['user'])

{{-- Online / Offline, read from the activity stamp (User::isOnline()). Offline
     says how long ago, so "logged out a minute ago" and "never" differ. --}}
@if ($user->isOnline())
    <span class="sesi sesi--online"><span class="sesi__titik" aria-hidden="true"></span>Online</span>
@else
    <span class="sesi sesi--offline">
        <span class="sesi__titik" aria-hidden="true"></span>Offline
        <span class="sesi__kapan">· {{ $user->last_active_at ? $user->last_active_at->locale('id')->diffForHumans() : 'belum pernah masuk' }}</span>
    </span>
@endif
