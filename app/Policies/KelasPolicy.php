<?php

namespace App\Policies;

use App\Models\Kelas;
use App\Models\User;
use App\Support\SimpanPresensiJurnal;

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
            && $kelas->jadwals()->where('guru_nip', $user->nip)->exists();
    }

    /**
     * Who may READ a class's daily attendance: admin, its ketua, any guru who
     * teaches the class or is its wali, and the students in it (their own
     * class's roll).
     *
     * There is no matching write ability. The daily record is derived from the
     * per-lesson rosters the teachers mark — see
     * {@see SimpanPresensiJurnal} — so it is never filled in
     * directly by anyone, and the ability to write attendance is asked of the
     * meeting instead: {@see JurnalPolicy::isiPresensi()}.
     */
    public function lihatPresensiHarian(User $user, Kelas $kelas): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isGuru()) {
            return $kelas->jadwals()->where('guru_nip', $user->nip)->exists()
                || $kelas->wali_kelas_nip === $user->nip;
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
