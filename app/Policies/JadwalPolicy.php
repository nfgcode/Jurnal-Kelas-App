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
     * Admin sees every slot; a guru sees a slot in a class they teach (their own
     * lesson or another subject in that class's timetable); a student sees only
     * their own class's timetable.
     */
    public function view(User $user, Jadwal $jadwal): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSiswa()) {
            return $user->kelas_id !== null && $jadwal->kelas_id === $user->kelas_id;
        }

        return $user->isGuru()
            && $jadwal->kelas?->jadwals()->where('guru_id', $user->id)->exists();
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
