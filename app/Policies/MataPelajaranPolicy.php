<?php

namespace App\Policies;

use App\Models\MataPelajaran;
use App\Models\User;

/**
 * Who may see a subject. Writes are admin-only and already gated by the
 * `role:admin` route middleware; only the read side needs scoping here.
 */
class MataPelajaranPolicy
{
    /** Admin sees every subject; a guru only one they are timetabled to teach. */
    public function view(User $user, MataPelajaran $mataPelajaran): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isGuru()
            && $mataPelajaran->jadwals()->where('guru_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, MataPelajaran $mataPelajaran): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, MataPelajaran $mataPelajaran): bool
    {
        return $user->isAdmin();
    }
}
