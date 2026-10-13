<?php

namespace App\Policies;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\User;

/**
 * Who may read and write a journal entry.
 *
 * October 2026 revision:
 *  - a **guru** does full CRUD on the journals of the lessons they teach, and
 *    still marks each lesson's roster ({@see isiPresensi()});
 *  - a **ketua kelas** only files today's journal for the class and reads
 *    today's — no editing, no deleting, no history. Filing may land in the
 *    placeholder a guru opened by taking the roll first ({@see lengkapi()});
 *  - an **admin** may do anything.
 *
 * Both a guru and the ketua may file for the same meeting: the unique index is
 * per (meeting, side), so each side keeps its own entry.
 *
 * Auto-discovered by Laravel (App\Models\Jurnal -> App\Policies\JurnalPolicy),
 * so no manual registration.
 */
class JurnalPolicy
{
    /**
     * Admin sees every journal; a guru their own lessons' (and, as wali, their
     * homeroom's); a ketua kelas only today's of their class; any other student
     * their class's.
     */
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
            // taught it — these journals are already listed on the wali screens.
            $kelasId = $jurnal->jadwal?->kelas_id;

            return $kelasId !== null
                && Kelas::whereKey($kelasId)->where('wali_kelas_nip', $user->nip)->exists();
        }

        $kelasSendiri = $user->kelas_id !== null
            && $jurnal->jadwal?->kelas_id === $user->kelas_id;

        // The ketua's reach is the day they are filing for, nothing older.
        if ($user->isKetuaKelas()) {
            return $kelasSendiri && $jurnal->tanggal?->isToday();
        }

        return $kelasSendiri;
    }

    /**
     * A guru files for their own lessons, a ketua kelas for their class (today
     * only — enforced in the controller), an admin for any meeting.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isGuru() || $user->isKetuaKelas();
    }

    /**
     * A guru edits every journal on the lessons they teach — their own entries,
     * the class's entry filed by the ketua, and the nightly placeholders (which
     * they adopt). A ketua kelas edits nothing.
     */
    public function update(User $user, Jurnal $jurnal): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isGuru()
            && $jurnal->jadwal?->guru_nip === $user->nip;
    }

    /** Deleting follows editing: admin, and the guru on their own entries. */
    public function delete(User $user, Jurnal $jurnal): bool
    {
        return $this->update($user, $jurnal);
    }

    /**
     * Whether a ketua kelas may write the class's journal INTO a system
     * placeholder rather than beside it. That placeholder is today's record the
     * teacher opened by taking the roll before the class wrote anything
     * ({@see \App\Http\Controllers\PresensiJurnalController::mulai()}), so it
     * is the class's journal still waiting for its content — not something to
     * edit. Today only and own class only, the same reach as filing a journal.
     */
    public function lengkapi(User $user, Jurnal $jurnal): bool
    {
        return $user->isKetuaKelas()
            && $jurnal->dibuatSistem()
            && (bool) $jurnal->tanggal?->isToday()
            && $user->kelas_id !== null
            && $jurnal->jadwal?->kelas_id === $user->kelas_id;
    }

    /**
     * Who may MARK the meeting's roster: the guru timetabled to teach it, and
     * admin. Attendance is the teacher's statement about who was in the room,
     * per lesson.
     */
    public function isiPresensi(User $user, Jurnal $jurnal): bool
    {
        return $user->isAdmin()
            || ($user->isGuru() && $jurnal->jadwal?->guru_nip === $user->nip);
    }

    /**
     * Who may read the attendance shown alongside a journal: anyone who can see
     * the journal, plus any guru who teaches that class or is its wali.
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

        return $user->isGuru()
            && (Jadwal::where('guru_nip', $user->nip)->where('kelas_id', $kelasId)->exists()
                || Kelas::whereKey($kelasId)->where('wali_kelas_nip', $user->nip)->exists());
    }
}
