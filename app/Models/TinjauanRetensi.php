<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TinjauanRetensi extends Model
{
    use HasFactory;

    protected $table = 'tinjauan_retensi';

    public const STATUS_PERLU_TINJAUAN = 'perlu_tinjauan';
    public const STATUS_DITINJAU = 'ditinjau';
    public const STATUS_DITUNDA = 'ditunda';
    public const STATUS_DISETUJUI_ARSIP = 'disetujui_arsip';

    protected $fillable = [
        'siswa_nis',
        'status_tinjauan',
        'catatan',
        'ditinjau_oleh_id',
        'ditinjau_pada',
    ];

    protected function casts(): array
    {
        return [
            'ditinjau_pada' => 'datetime',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_nis', 'nis');
    }

    public function ditinjauOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditinjau_oleh_id');
    }
}
