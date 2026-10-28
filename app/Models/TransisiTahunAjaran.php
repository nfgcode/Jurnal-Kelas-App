<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransisiTahunAjaran extends Model
{
    use HasFactory;

    protected $table = 'transisi_tahun_ajaran';

    public const STATUS_PERSIAPAN = 'persiapan';
    public const STATUS_DIPROSES = 'diproses';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_GAGAL = 'gagal';

    protected $fillable = [
        'tahun_asal',
        'tahun_tujuan',
        'status',
        'jumlah_siswa_dipromosikan',
        'jumlah_siswa_lulus',
        'jumlah_siswa_tinggal',
        'catatan',
        'diproses_oleh_id',
        'diproses_pada',
    ];

    protected function casts(): array
    {
        return [
            'diproses_pada' => 'datetime',
            'jumlah_siswa_dipromosikan' => 'integer',
            'jumlah_siswa_lulus' => 'integer',
            'jumlah_siswa_tinggal' => 'integer',
        ];
    }

    public function tahunAsal(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_asal', 'kode');
    }

    public function tahunTujuan(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_tujuan', 'kode');
    }

    public function diprosesOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh_id');
    }
}
