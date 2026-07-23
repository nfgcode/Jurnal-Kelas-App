<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read model over the jurnal_audit table, whose rows are written by the
 * AFTER UPDATE/DELETE triggers on `jurnal` (MySQL). Has its own diubah_pada
 * timestamp rather than Eloquent's created_at/updated_at.
 */
class JurnalAudit extends Model
{
    protected $table = 'jurnal_audit';

    public $timestamps = false;

    protected $fillable = [
        'jurnal_id',
        'aksi',
        'materi_lama',
        'materi_baru',
        'diubah_pada',
    ];

    protected function casts(): array
    {
        return [
            'diubah_pada' => 'datetime',
        ];
    }
}
