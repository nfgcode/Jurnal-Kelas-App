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

    /**
     * Teachers and admins author journals — and so does a ketua kelas, who
     * fills the class journal on an absent teacher's behalf. Which schedule a
     * ketua may write against is enforced in the controller (own class only).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isGuru() || $user->isKetuaKelas();
    }

    /**
     * A guru may edit only their own journal; an admin, any; a ketua kelas the
     * journals of their own class — that same ability is what lets them mark
     * the roster right after saving (presensi create/store gates on `update`).
     */
    public function update(User $user, Jurnal $jurnal): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isGuru()) {
            return $jurnal->guru_id === $user->id;
        }

        return $user->isKetuaKelas()
            && $user->kelas_id !== null
            && $jurnal->jadwal?->kelas_id === $user->kelas_id;
    }

    /** Deleting stays with admin and the owning guru — never the ketua. */
    public function delete(User $user, Jurnal $jurnal): bool
    {
        return $user->isAdmin()
            || ($user->isGuru() && $jurnal->guru_id === $user->id);
    }
}
