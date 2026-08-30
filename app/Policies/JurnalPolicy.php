<?php

namespace App\Policies;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\User;

/**
 * Who may read and write a journal entry.
 *
 * The two halves of a meeting's record belong to two different people now. The
 * class writes the journal — what was taught, and whether the teacher turned up
 * — through its ketua kelas, because they are the ones sitting in the lesson.
 * The teacher marks who was in front of them, per lesson, through
 * {@see isiPresensi()}. Neither side writes the other's half.
 *
 * Auto-discovered by Laravel (App\Models\Jurnal -> App\Policies\JurnalPolicy),
 * so no manual registration.
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
            if ($jurnal->jadwal?->guru_nip === $user->nip) {
                return true;
            }

            // A wali kelas reads every meeting of their homeroom class, whoever
            // taught it — the same reach viewRoster() grants, and these
            // journals are already listed on the wali screens. Read-only:
            // update()/delete() below stay with the class that wrote it.
            $kelasId = $jurnal->jadwal?->kelas_id;

            return $kelasId !== null
                && Kelas::whereKey($kelasId)->where('wali_kelas_nip', $user->nip)->exists();
        }

        return $user->kelas_id !== null
            && $jurnal->jadwal?->kelas_id === $user->kelas_id;
    }

    /**
     * The class journal is authored by the class: its ketua kelas, and admin.
     *
     * A guru no longer writes journals at all — they report attendance for the
     * lessons they taught instead. Which schedule a ketua may write against is
     * enforced in the controller (own class only).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isKetuaKelas();
    }

    /**
     * A ketua kelas may edit their own class's journals; an admin, any. A guru
     * may not — including the one who taught the lesson, whose own record of it
     * is the roster, not the journal.
     */
    public function update(User $user, Jurnal $jurnal): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isKetuaKelas()
            && $user->kelas_id !== null
            && $jurnal->jadwal?->kelas_id === $user->kelas_id;
    }

    /** Deleting follows authorship: admin, and the class's own ketua. */
    public function delete(User $user, Jurnal $jurnal): bool
    {
        return $this->update($user, $jurnal);
    }

    /**
     * Who may MARK the meeting's roster: the guru timetabled to teach it, and
     * admin.
     *
     * Deliberately narrow, and deliberately the teacher rather than the class.
     * Attendance is a statement about who was in the room, and the teacher is
     * the one making it — per lesson, so the same student can be present for
     * Matematika and absent for Fisika on the same afternoon and the record can
     * say exactly that. The class reads it; it never writes it.
     */
    public function isiPresensi(User $user, Jurnal $jurnal): bool
    {
        return $user->isAdmin()
            || ($user->isGuru() && $jurnal->jadwal?->guru_nip === $user->nip);
    }

    /**
     * Who may read the attendance shown alongside a journal.
     *
     * Wider than writing: anyone who can see the journal, plus the class's ketua
     * and any guru who teaches that class or is its wali.
     */
    public function viewRoster(User $user, Jurnal $jurnal): bool
    {
        if ($this->view($user, $jurnal)) {
            return true;
        }

        $kelasId = $jurnal->jadwal?->kelas_id;

        if ($kelasId === null) {
            return false;
        }

        if ($user->isKetuaKelas()) {
            return $user->kelas_id === $kelasId;
        }

        return $user->isGuru()
            && (Jadwal::where('guru_nip', $user->nip)->where('kelas_id', $kelasId)->exists()
                || Kelas::whereKey($kelasId)->where('wali_kelas_nip', $user->nip)->exists());
    }
}
