<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One audit row per daily-roster save: which class-day, who saved it, when, how
 * many students, and whether it was the first filing or a later correction.
 * Read only by admin (see Admin\PresensiLogController).
 */
class PresensiHarianLog extends Model
{
    protected $table = 'presensi_harian_log';

    /** Only created_at is meaningful; a save is never edited afterwards. */
    public $timestamps = false;

    protected $fillable = [
        'kelas_id',
        'tanggal',
        'diedit_oleh_id',
        'jumlah_siswa',
        'koreksi',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'koreksi' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function dieditOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diedit_oleh_id');
    }
}
