<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps users.last_active_at current, which the Akun Pengguna "Online /
 * Offline" status reads. Before this, the column only moved at sign-in, so a
 * teacher working all morning looked offline five minutes after logging in.
 *
 * Throttled: written at most once a minute per user, and through the query
 * builder so it neither fires model events nor bumps updated_at.
 */
class CatatAktivitas
{
    /** Minimum seconds between two writes for the same user. */
    private const JEDA_DETIK = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->last_active_at || $user->last_active_at->lt(now()->subSeconds(self::JEDA_DETIK)))) {
            $sekarang = now();
            DB::table('users')->where('id', $user->getKey())->update(['last_active_at' => $sekarang]);
            $user->setAttribute('last_active_at', $sekarang);
        }

        return $next($request);
    }
}
