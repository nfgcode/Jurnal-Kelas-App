<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical room, keyed by the code painted on its door.
 *
 * Replaces the free-text `ruang` string that used to sit on both `kelas` and
 * `jadwal`, where nothing connected two mentions of the same room and nobody
 * could ask how many seats it has.
 */
class Ruangan extends Model
{
    use HasFactory;

    protected $table = 'ruangan';

    protected $primaryKey = 'kode';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kode',
        'nama',
        'jenis',
        'kapasitas',
        'gedung',
        'lantai',
        'keterangan',
        'status',
    ];

    /** The labels the room-type filter and the detail page render. */
    public const JENIS = [
        'kelas' => 'Ruang Kelas',
        'laboratorium' => 'Laboratorium',
        'bengkel' => 'Bengkel',
        'aula' => 'Aula',
        'perpustakaan' => 'Perpustakaan',
        'olahraga' => 'Olahraga',
        'lainnya' => 'Lainnya',
    ];

    public function jenisLabel(): string
    {
        return self::JENIS[$this->jenis] ?? ucfirst((string) $this->jenis);
    }

    /**
     * How the room reads in a dropdown: the code people say out loud, plus the
     * name that tells them which room that is.
     */
    public function label(): string
    {
        return "{$this->kode} — {$this->nama}";
    }

    public function scopeCari($query, string $q)
    {
        return $query->where(fn ($inner) => $inner
            ->where('kode', 'like', "%{$q}%")
            ->orWhere('nama', 'like', "%{$q}%")
            ->orWhere('gedung', 'like', "%{$q}%"));
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    /** Classes whose home base this room is. */
    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'ruangan_kode', 'kode');
    }

    /** Timetable slots that meet here. */
    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'ruangan_kode', 'kode');
    }
}
