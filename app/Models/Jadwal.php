<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
