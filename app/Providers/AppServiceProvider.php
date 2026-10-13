<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // An akun pantau reads everything an admin reads and is granted nothing
        // else, so every @can around an edit, delete or roster button hides it.
        // The writes themselves are refused by the BatasiAkunPantau middleware.
        Gate::before(function (User $user, string $ability) {
            if ($user->isHanyaBaca() && ! in_array($ability, ['view', 'viewAny', 'viewRoster'], true)) {
                return false;
            }

            return null;
        });
    }
}
