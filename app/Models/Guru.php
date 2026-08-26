<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A teacher as the school records them, keyed by NIP.
 *
 * Separate from {@see User} on purpose: this row is the person, that row is
 * their login. A teacher on unpaid leave keeps their timetable history with the
 * account switched off, and a newly hired teacher can be entered into the
 * school's records before IT gets around to issuing credentials.
 */
class Guru extends Model
{
    use HasFactory;

    protected $table = 'guru';

    /**
     * NIP is the key. It is not an internal detail — it is printed on letters
     * and quoted between staff — so every row that names a teacher names them
     * by the identifier the school already uses.
     */
    protected $primaryKey = 'nip';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nip',
        'nama',
        'jenis_kelamin',
        'no_hp',
        'alamat',
        'status',
    ];

    /**
     * Initial used by the circular avatar on every screen.
     */
    public function inisial(): string
    {
        return mb_strtoupper(mb_substr($this->nama ?? '?', 0, 1));
    }

    /**
     * Free-text search across the teacher list: name, NIP, or a subject they
     * are certified to teach.
     */
    public function scopeCari($query, string $q)
    {
        return $query->where(fn ($inner) => $inner
            ->where('nama', 'like', "%{$q}%")
            ->orWhere('nip', 'like', "%{$q}%")
            ->orWhereHas('mataPelajaran', fn ($m) => $m->where('nama', 'like', "%{$q}%")));
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    /**
     * The login issued to this teacher, if one has been. Nullable by design —
     * see the class docblock.
     */
    public function akun(): HasOne
    {
        return $this->hasOne(User::class, 'nip', 'nip');
    }

    /**
     * Subjects this teacher is certified to teach. Most hold one, some two or
     * three; the pivot marks which is their principal subject.
     */
    public function mataPelajaran(): BelongsToMany
    {
        return $this->belongsToMany(MataPelajaran::class, 'guru_mata_pelajaran', 'guru_nip', 'mata_pelajaran_id')
            ->withPivot('utama')
            ->withTimestamps();
    }

    /**
     * Classes where this teacher is the homeroom teacher (wali kelas).
     */
    public function kelasWali(): HasMany
    {
        return $this->hasMany(Kelas::class, 'wali_kelas_nip', 'nip');
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'guru_nip', 'nip');
    }

    /**
     * Journals of the lessons this teacher is timetabled for. Through `jadwal`,
     * because that is where the pairing lives now.
     */
    public function jurnals(): HasManyThrough
    {
        return $this->hasManyThrough(
            Jurnal::class, Jadwal::class,
            'guru_nip', 'jadwal_id', 'nip', 'id',
        );
    }
}
