<?php

namespace App\Policies;

use App\Models\Kelas;
use App\Models\User;

/**
 * Who may see a class. Writes are admin-only and already gated by the
 * `role:admin` route middleware, so only the read side needs scoping here.
 * Auto-discovered by Laravel (App\Models\Kelas -> App\Policies\KelasPolicy).
 */
class KelasPolicy
{
    /** Admin sees every class; a guru only a class they actually teach in. */
    public function view(User $user, Kelas $kelas): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isGuru()
            && $kelas->jadwals()->where('guru_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Kelas $kelas): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Kelas $kelas): bool
    {
        return $user->isAdmin();
    }
}
