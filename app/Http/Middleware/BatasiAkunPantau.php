<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun pantau (users.hanya_baca): an admin who may look but not touch.
 *
 * Every write is refused here, before any controller or policy runs, so no
 * screen can forget the rule. The form screens that only exist to write are
 * refused too, and so are the admin areas that are about running the system
 * rather than watching the school (accounts, imports, backups, error logs).
 * {@see \App\Providers\AppServiceProvider} denies the same users every
 * non-read policy ability, which hides the buttons that @can already guards.
 */
class BatasiAkunPantau
{
    /** Writes an akun pantau still makes: reporting an error page. */
    private const BOLEH_MENULIS = ['laporan-error.store'];

    /** Screens closed to an akun pantau even for reading. */
    private const TERTUTUP = [
        '*.create',
        '*.edit',
        'admin.akun.*',
        'admin.impor.*',
        'admin.cadangan.*',
        'admin.sistem.*',
        'api.users.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isHanyaBaca()) {
            return $next($request);
        }

        $menulis = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        abort_if(
            ($menulis && ! $request->routeIs(...self::BOLEH_MENULIS)) || $request->routeIs(...self::TERTUTUP),
            403,
            'Akun pantau hanya dapat melihat data. Hubungi admin sekolah untuk melakukan perubahan.',
        );

        return $next($request);
    }
}
