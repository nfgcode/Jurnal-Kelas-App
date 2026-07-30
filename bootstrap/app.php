<?php

use App\Http\Middleware\CheckRole;
use App\Support\PesanError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        /*
         * Guru, siswa, and guests must never see a Laravel stack trace: it is
         * meaningless to them and exposes file paths, code, and query contents.
         * They get a friendly page with a reference code and the option to report
         * the problem. An admin is the one who debugs, so they still get the full
         * Laravel error page — returning null falls through to default handling.
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->user()?->isAdmin()) {
                return null;
            }

            /*
             * Not every exception is a fault to apologise for. These three are
             * ordinary flow that Laravel turns into its own response, and none of
             * them is an HttpExceptionInterface — so without this guard they would
             * all be treated as a 500 and swallowed by the friendly page:
             *   - ValidationException  → redirect back with field errors
             *   - AuthenticationException → redirect to the login page
             *   - HttpResponseException  → already carries its own response
             * Intercepting the first one broke every form for guru and siswa.
             */
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof HttpResponseException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            $ref = Str::upper(Str::random(8));

            // The technical summary rides in the session, not the form, so the
            // report cannot be forged from the browser and the page stays clean.
            //
            // A URL matching no route never passes through the `web` group, so it
            // has no session at all — stashing must be conditional or a plain 404
            // would itself blow up into a 500.
            if ($request->hasSession()) {
                $request->session()->put('sistem.error_terakhir', [
                    'ref' => $ref,
                    'status' => $status,
                    'pesan' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'url' => $request->fullUrl(),
                    'pada' => now()->toDateTimeString(),
                ]);
            }

            return response()->view('errors.ramah', [
                'status' => $status,
                'ref' => $ref,
                'teks' => PesanError::untuk($status),
                // Passed explicitly: inside a view rendered from the exception
                // handler the Auth facade resolves to null, even though the
                // request's own user resolver still works.
                'pengguna' => $request->user(),
            ], $status);
        });
    })->create();
