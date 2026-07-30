<?php

namespace App\Models;

use App\Support\Ringkasan;
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
        'guru_id',
        'hari',
        'jam_ke_mulai',
        'jam_ke_selesai',
        'jam_mulai',
        'jam_selesai',
        'ruang',
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
            ->when($user->isGuru(), fn ($q) => $q->where('guru_id', $user->id))
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
        return $this->belongsTo(User::class, 'guru_id');
    }

    /**
     * Get the jurnal (journal) entries for this schedule.
     */
    public function jurnals(): HasMany
    {
        return $this->hasMany(Jurnal::class);
    }
}
