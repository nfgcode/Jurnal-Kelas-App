<?php

namespace Database\Seeders;

/**
 * Keeps track of who is already teaching when, so a generated timetable can
 * satisfy `jadwal_guru_slot_unique`.
 *
 * The seeders used to pick a teacher per subject and then hand that pairing to
 * every class that studies the subject — which put one person in six rooms at
 * once. The dev database carried 353 such clashes, and nothing complained,
 * because the timetable had no unique key on (guru, hari, jam_ke) to complain
 * with. This is the bookkeeping that makes the generated data satisfiable.
 *
 * Blocks are two periods long ([1,2], [3,4], …) and every class uses the same
 * four, so occupancy is tracked by the block's opening period — which is
 * exactly the column the unique key is built on.
 */
class PenjadwalTanpaBentrok
{
    /** @var array<string, array<int, array<string, true>>> hari => jam_ke => nip */
    private array $sibuk = [];

    /** @var array<string, int> nip => number of blocks already assigned */
    private array $beban = [];

    public function bebas(string $nip, string $hari, int $jamKe): bool
    {
        return ! isset($this->sibuk[$hari][$jamKe][$nip]);
    }

    /**
     * Pick someone free for this slot.
     *
     * `$utama` is the teacher this class already uses for the subject; keeping
     * them is what makes a class see one face per subject all week, so they win
     * whenever they are free. Otherwise the least-loaded free candidate takes
     * it, which spreads the week evenly instead of exhausting the first name in
     * the list and then failing.
     *
     * Returns null when everyone qualified is already busy — the caller should
     * try a different subject for this slot rather than book a clash.
     *
     * @param  array<int, string>  $kandidat  NIPs certified for the subject
     */
    public function pilih(array $kandidat, string $hari, int $jamKe, ?string $utama = null): ?string
    {
        if ($utama !== null && in_array($utama, $kandidat, true) && $this->bebas($utama, $hari, $jamKe)) {
            return $utama;
        }

        $bebas = array_values(array_filter($kandidat, fn ($nip) => $this->bebas($nip, $hari, $jamKe)));

        if ($bebas === []) {
            return null;
        }

        usort($bebas, fn ($a, $b) => ($this->beban[$a] ?? 0) <=> ($this->beban[$b] ?? 0));

        return $bebas[0];
    }

    public function tandai(string $nip, string $hari, int $jamKe): void
    {
        $this->sibuk[$hari][$jamKe][$nip] = true;
        $this->beban[$nip] = ($this->beban[$nip] ?? 0) + 1;
    }
}
