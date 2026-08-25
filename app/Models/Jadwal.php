<?php

namespace App\Models;

use App\Support\JamPelajaran;
use App\Support\Ringkasan;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Jadwal extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'jadwal';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'kelas_id',
        'mata_pelajaran_id',
        'guru_nip',
        'hari',
        'jam_ke_mulai',
        'jam_ke_selesai',
        'ruangan_kode',
    ];

    /**
     * The timetable rows a user may write a journal against: a guru their own
     * lessons (a subject taught by several teachers therefore never leaks
     * between them), a student their class's. Admin sees everything.
     *
     * One definition, because the journal form picks a default slot and builds
     * the dropdown from it — two copies of this condition would let the two
     * disagree about what a user is allowed to file against.
     */
    public function scopeUntukPengguna($query, User $user)
    {
        return $query
            ->when($user->isGuru(), fn ($q) => $q->where('guru_nip', $user->nip))
            ->when($user->isSiswa(), fn ($q) => $q->where('kelas_id', $user->kelas_id ?? 0));
    }

    /**
     * Rows taught on the weekday of the given date. `hari` stores the Indonesian
     * day name, so the date is mapped through the same list the rest of the app
     * uses; a Sunday matches nothing, which is correct — there are no lessons.
     */
    public function scopePadaHariDari($query, Carbon $tanggal)
    {
        return $query->where('hari', Ringkasan::HARI[$tanggal->dayOfWeekIso - 1] ?? '—');
    }

    /**
     * Lesson-period range as the screens render it, e.g. "1 - 2".
     */
    public function jpLabel(): string
    {
        return $this->jam_ke_mulai === $this->jam_ke_selesai
            ? (string) $this->jam_ke_mulai
            : "{$this->jam_ke_mulai} - {$this->jam_ke_selesai}";
    }

    /**
     * Wall-clock span of this slot, e.g. "07:00 - 08:30". Read from the bell
     * schedule rather than the stored columns so a config change shows up on
     * screen immediately, without a re-save of every row.
     */
    public function waktuLabel(): string
    {
        return JamPelajaran::label((int) $this->jam_ke_mulai, (int) $this->jam_ke_selesai);
    }

    /**
     * Get the kelas (class) for this schedule.
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * Get the mata pelajaran (subject) for this schedule.
     */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    /**
     * Get the guru (teacher) for this schedule.
     */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'guru_nip', 'nip');
    }

    /**
     * The room this slot meets in. Falls back to the class's home room when the
     * slot has none of its own — most lessons happen where the class lives.
     */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_kode', 'kode');
    }

    /**
     * The room code, for the many screens that just want to print where a
     * lesson meets. Rooms are rows now ({@see ruangan()}); this keeps the read
     * side reading as plainly as it did when the column was free text.
     *
     * Eager-load `ruangan` wherever this is rendered in a list, or it costs a
     * query per row.
     */
    protected function ruang(): Attribute
    {
        return Attribute::make(get: fn () => $this->ruangan?->kode);
    }

    /**
     * Get the jurnal (journal) entries for this schedule.
     */
    public function jurnals(): HasMany
    {
        return $this->hasMany(Jurnal::class);
    }
}
