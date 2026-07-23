<?php

namespace App\Policies;

use App\Models\Jurnal;
use App\Models\User;

/**
 * Who may read and write a journal entry. Auto-discovered by Laravel
 * (App\Models\Jurnal -> App\Policies\JurnalPolicy), so no manual registration.
 */
class JurnalPolicy
{
    /** Admin sees every journal; a guru sees their own; a student their class's. */
    public function view(User $user, Jurnal $jurnal): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isGuru()) {
            return $jurnal->guru_id === $user->id;
        }

        return $user->kelas_id !== null
            && $jurnal->jadwal?->kelas_id === $user->kelas_id;
    }

    /** Only teachers (and admins) author journals. */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isGuru();
    }

    /** A guru may edit only their own journal; an admin, any. */
    public function update(User $user, Jurnal $jurnal): bool
    {
        return $user->isAdmin()
            || ($user->isGuru() && $jurnal->guru_id === $user->id);
    }

    public function delete(User $user, Jurnal $jurnal): bool
    {
        return $this->update($user, $jurnal);
    }
}
