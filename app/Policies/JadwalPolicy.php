<?php

namespace App\Policies;

use App\Models\Jadwal;
use App\Models\User;

/**
 * Who may see a timetable slot. Writes are admin-only and already gated by the
 * `role:admin` route middleware; only the read side needs scoping here.
 */
class JadwalPolicy
{
    /**
     * Who may open the timetable at all. Oct 2026: a guru only works with the
     * journal and the roster, and a ketua kelas only with today's journal, so
     * the timetable screens are for admin and ordinary students.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->isSiswa() && ! $user->isKetuaKelas());
    }

    /**
     * Admin sees every slot; an ordinary student only their own class's.
     */
    public function view(User $user, Jadwal $jadwal): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $user->isAdmin()
            || ($user->kelas_id !== null && $jadwal->kelas_id === $user->kelas_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Jadwal $jadwal): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Jadwal $jadwal): bool
    {
        return $user->isAdmin();
    }
}
