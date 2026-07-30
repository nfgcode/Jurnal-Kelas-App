<?php

namespace App\Policies;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
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
            if ($jurnal->guru_id === $user->id) {
                return true;
            }

            // A wali kelas reads every meeting of their homeroom class, whoever
            // taught it — the same reach markRoster() already grants, and these
            // journals are already listed on the wali screens. Read-only:
            // update()/delete() below stay with the teacher who wrote it.
            $kelasId = $jurnal->jadwal?->kelas_id;

            return $kelasId !== null
                && Kelas::whereKey($kelasId)->where('wali_kelas_id', $user->id)->exists();
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

    /**
     * Who may mark/edit a meeting's attendance roster. Broader than editing the
     * journal's own content (update): a guru may mark any meeting of a class
     * they teach, a wali kelas any meeting of their homeroom class (whoever
     * taught it), and a ketua kelas their own class — plus admin. Kept separate
     * from update() so this reach never grants editing of the journal's materi.
     */
    public function markRoster(User $user, Jurnal $jurnal): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $kelasId = $jurnal->jadwal?->kelas_id;

        if ($kelasId === null) {
            return false;
        }

        if ($user->isKetuaKelas()) {
            return $user->kelas_id === $kelasId;
        }

        if ($user->isGuru()) {
            return Jadwal::where('guru_id', $user->id)->where('kelas_id', $kelasId)->exists()
                || Kelas::whereKey($kelasId)->where('wali_kelas_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Who may view a roster: anyone who can mark it, plus a student reading
     * their own class's meeting (covered by view()).
     */
    public function viewRoster(User $user, Jurnal $jurnal): bool
    {
        return $this->markRoster($user, $jurnal) || $this->view($user, $jurnal);
    }
}
