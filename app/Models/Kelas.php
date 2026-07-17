<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelas extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'kelas';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nama_kelas',
        'tingkat',
        'jurusan',
        'tahun_ajaran',
        'wali_kelas_id',
    ];

    /**
     * Get the wali kelas (homeroom teacher) for this class.
     */
    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_kelas_id');
    }

    /**
     * Get the siswa (students) enrolled in this class.
     */
    public function siswa(): HasMany
    {
        return $this->hasMany(User::class, 'kelas_id')->where('role', 'siswa');
    }

    /**
     * Get the jadwal (schedules) for this class.
     */
    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }
}
