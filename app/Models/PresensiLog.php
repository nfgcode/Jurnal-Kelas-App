<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One audit row per attendance-roster save: which meeting, who saved it, when,
 * and how many students. Read only by admin (see Admin\PresensiLogController).
 */
class PresensiLog extends Model
{
    protected $table = 'presensi_log';

    /** Only created_at is meaningful; a save is never edited afterwards. */
    public $timestamps = false;

    protected $fillable = [
        'jurnal_id',
        'diedit_oleh_id',
        'jumlah_siswa',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(Jurnal::class);
    }

    public function dieditOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diedit_oleh_id');
    }
}
