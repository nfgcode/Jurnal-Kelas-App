<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alumni extends Model
{
    use HasFactory;

    protected $table = 'alumni';

    protected $fillable = [
        'siswa_nis',
        'tahun_lulus',
        'tanggal_lulus',
        'kelas_terakhir_id',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lulus' => 'date',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_nis', 'nis');
    }

    public function kelasTerakhir(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_terakhir_id');
    }

    public function tahunLulus(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_lulus', 'kode');
    }
}
