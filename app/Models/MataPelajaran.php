<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataPelajaran extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mata_pelajaran';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nama',
        'kode',
        'kelompok',
        'jp_per_minggu',
        'deskripsi',
    ];

    /**
     * Human label for the curriculum group, as shown on the chip column.
     */
    public function kelompokLabel(): string
    {
        return match ($this->kelompok) {
            'peminatan' => 'Peminatan',
            'muatan_lokal' => 'Muatan Lokal',
            'kejuruan' => 'Kejuruan',
            default => 'Wajib',
        };
    }

    /**
     * Get the jadwal (schedules) for this subject.
     */
    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }
}
