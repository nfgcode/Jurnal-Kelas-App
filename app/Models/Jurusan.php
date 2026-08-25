<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A competency area (jurusan), keyed by the code the school says out loud.
 *
 * Was free text on `kelas`: the same long name written out on every parallel
 * class, where one typo produced an eleventh jurusan that then appeared as its
 * own entry in every filter.
 */
class Jurusan extends Model
{
    use HasFactory;

    protected $table = 'jurusan';

    protected $primaryKey = 'kode';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kode',
        'nama',
        'bidang',
        'deskripsi',
        'aktif',
    ];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    /**
     * How the jurusan reads in a dropdown: the code people use, plus the full
     * name that says which one it is.
     */
    public function label(): string
    {
        return "{$this->kode} — {$this->nama}";
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }

    public function scopeCari($query, string $q)
    {
        return $query->where(fn ($inner) => $inner
            ->where('kode', 'like', "%{$q}%")
            ->orWhere('nama', 'like', "%{$q}%")
            ->orWhere('bidang', 'like', "%{$q}%"));
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'jurusan_kode', 'kode');
    }
}
