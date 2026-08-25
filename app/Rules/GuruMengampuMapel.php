<?php

namespace App\Rules;

use App\Models\Guru;
use App\Models\MataPelajaran;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A timetable slot may only pair a teacher with a subject the school has
 * recorded them as teaching.
 *
 * Before `guru_mata_pelajaran` existed there was nothing to check against, so
 * the schedule form would cheerfully put the PE teacher down for Kimia and the
 * mistake only surfaced when someone read the printed timetable. The pairing is
 * a fact the school already knows; this makes the form ask.
 */
class GuruMengampuMapel implements ValidationRule
{
    public function __construct(private mixed $mataPelajaranId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value || ! $this->mataPelajaranId) {
            return;
        }

        $guru = Guru::with('mataPelajaran')->find($value);

        if (! $guru) {
            return; // The `exists` rule alongside this one reports that.
        }

        if ($guru->mataPelajaran->contains('id', (int) $this->mataPelajaranId)) {
            return;
        }

        $mapel = MataPelajaran::find($this->mataPelajaranId);
        $diampu = $guru->mataPelajaran->pluck('nama')->join(', ');

        $fail(sprintf(
            '%s tidak tercatat mengampu %s. Mata pelajaran yang diampu: %s. Ubah dulu di halaman Data Guru bila memang mengajar mapel ini.',
            $guru->nama,
            $mapel?->nama ?? 'mata pelajaran itu',
            $diampu !== '' ? $diampu : 'belum ada',
        ));
    }
}
