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

    /**
     * Who may FILE the class's daily attendance: its ketua kelas, and admin.
     *
     * Deliberately narrow. Attendance is now one roll call a day taken by the
     * student who is in the room all day; a guru only teaches a slice of it, so
     * letting them mark it too would put two authorities on one record and
     * reopen the disagreement the daily roster exists to end. A guru reads and
     * exports it instead — see {@see lihatPresensiHarian()}.
     */
    public function isiPresensiHarian(User $user, Kelas $kelas): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isKetuaKelas() && $user->kelas_id === $kelas->id;
    }

    /**
     * Who may READ a class's daily attendance: admin, its ketua, any guru who
     * teaches the class or is its wali, and the students in it (their own
     * class's roll).
     */
    public function lihatPresensiHarian(User $user, Kelas $kelas): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isGuru()) {
            return $kelas->jadwals()->where('guru_id', $user->id)->exists()
                || $kelas->wali_kelas_id === $user->id;
        }

        return $user->kelas_id === $kelas->id;
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
