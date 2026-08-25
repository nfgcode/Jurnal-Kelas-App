<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'no_hp',
        'alamat',
        'status',
    ];

    /**
     * Whether this student chairs their class.
     *
     * No longer a column: the fact belongs to the class ("who chairs X TKJ 1?"),
     * not to the student, and living in `kelas.ketua_nis` makes a second ketua
     * unrepresentable instead of something a model hook had to keep undoing.
     *
     * Reads through the class, so eager-load `kelas` wherever a roster renders
     * this — or compare `$kelas->ketua_nis` directly when the class is already
     * in hand, which costs nothing.
     */
    protected function isKetuaKelas(): Attribute
    {
        return Attribute::make(get: fn () => $this->kelas?->ketua_nis === $this->nis);
    }

    /**
     * Students who do (or do not) chair a class.
     */
    public function scopeKetua($query, bool $ya = true)
    {
        $ketua = Kelas::whereNotNull('ketua_nis')->select('ketua_nis');

        return $ya ? $query->whereIn('nis', $ketua) : $query->whereNotIn('nis', $ketua);
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

    /** The class this student chairs, if any. */
    public function kelasDiketuai(): HasOne
    {
        return $this->hasOne(Kelas::class, 'ketua_nis', 'nis');
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
