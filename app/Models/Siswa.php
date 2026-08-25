<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A student as the school records them, keyed by NIS.
 *
 * Attendance and journals reference this row, not the login — a graduated
 * student's account is closed while their attendance record has to stay
 * readable for years afterwards.
 */
class Siswa extends Model
{
    use HasFactory;

    protected $table = 'siswa';

    protected $primaryKey = 'nis';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nis',
        'nisn',
        'nama',
        'jenis_kelamin',
        'kelas_id',
        'is_ketua_kelas',
        'no_hp',
        'alamat',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_ketua_kelas' => 'boolean',
        ];
    }

    /**
     * Model events.
     */
    protected static function booted(): void
    {
        // A class has exactly one ketua kelas. Promoting a student demotes
        // whoever held it, so the journal-on-behalf-of permission can never be
        // claimed by two students at once.
        static::saved(function (Siswa $siswa) {
            if ($siswa->is_ketua_kelas && $siswa->kelas_id) {
                static::where('kelas_id', $siswa->kelas_id)
                    ->where('nis', '!=', $siswa->nis)
                    ->where('is_ketua_kelas', true)
                    ->update(['is_ketua_kelas' => false]);
            }
        });
    }

    /**
     * Initial used by the circular avatar on every screen.
     */
    public function inisial(): string
    {
        return mb_strtoupper(mb_substr($this->nama ?? '?', 0, 1));
    }

    /**
     * Free-text search across the student list: name, NIS/NISN, or class name.
     */
    public function scopeCari($query, string $q)
    {
        return $query->where(fn ($inner) => $inner
            ->where('nama', 'like', "%{$q}%")
            ->orWhere('nis', 'like', "%{$q}%")
            ->orWhere('nisn', 'like', "%{$q}%")
            ->orWhereHas('kelas', fn ($k) => $k->where('nama_kelas', 'like', "%{$q}%")));
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    /**
     * The login issued to this student, if one has been.
     */
    public function akun(): HasOne
    {
        return $this->hasOne(User::class, 'nis', 'nis');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * This student's daily attendance — the live record.
     */
    public function presensiHarian(): HasMany
    {
        return $this->hasMany(PresensiHarian::class, 'siswa_nis', 'nis');
    }

    /**
     * The archived per-meeting attendance, kept for history only.
     */
    public function presensis(): HasMany
    {
        return $this->hasMany(Presensi::class, 'siswa_nis', 'nis');
    }
}
