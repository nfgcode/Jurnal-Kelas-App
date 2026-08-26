<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A login, and only a login.
 *
 * Everything describing the *person* — their name, class, homeroom, contact
 * details — lives in {@see Guru} or {@see Siswa} and is reached through `nip` /
 * `nis`. An admin is the one account type with no person row behind it, so
 * theirs is the only role that carries `nama` on the account itself; a database
 * trigger keeps it that way.
 *
 * The relations below still hang off this model even after the split, because
 * `users` carries the NIP/NIS itself: `$user->jadwals` reaches the timetable in
 * one hop rather than through the teacher row.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'nama',
        'email',
        'password',
        'role',
        'status',
        'nip',
        'nis',
        'last_active_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The account holder's name, read from wherever it actually lives.
     *
     * For a guru or a siswa that is the person row; the column on `users` is
     * NULL for them, so there is exactly one place a name can be corrected.
     * Screens that list many accounts must eager-load `guru`/`siswa` — reading
     * this attribute on an unloaded relation costs a query per row.
     */
    protected function nama(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->guru?->nama ?? $this->siswa?->nama ?? $value,
        );
    }

    /**
     * The class this account's student belongs to, flattened back onto the user
     * so the many `$user->kelas_id` call sites keep reading naturally.
     */
    protected function kelasId(): Attribute
    {
        return Attribute::make(get: fn () => $this->siswa?->kelas_id);
    }

    /**
     * The student allowed to fill the class journal on the teacher's behalf.
     */
    public function isKetuaKelas(): bool
    {
        return $this->isKetuaKelas ??= $this->role === 'siswa'
            && $this->nis !== null
            && Kelas::where('ketua_nis', $this->nis)->exists();
    }

    /** Memo for {@see isKetuaKelas()}: it is checked on every journal render. */
    private ?bool $isKetuaKelas = null;

    /**
     * Initial used by the circular avatar on every screen.
     */
    public function inisial(): string
    {
        return mb_strtoupper(mb_substr($this->nama ?? '?', 0, 1));
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isGuru(): bool
    {
        return $this->role === 'guru';
    }

    public function isSiswa(): bool
    {
        return $this->role === 'siswa';
    }

    /**
     * Free-text search across the *account* list: the credentials themselves,
     * plus the name and identifier of the person behind them.
     */
    public function scopeCari($query, string $q)
    {
        return $query->where(function ($inner) use ($q) {
            $inner->where('username', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('nama', 'like', "%{$q}%")
                ->orWhere('nip', 'like', "%{$q}%")
                ->orWhere('nis', 'like', "%{$q}%")
                ->orWhereHas('guru', fn ($g) => $g->where('nama', 'like', "%{$q}%"))
                ->orWhereHas('siswa', fn ($s) => $s->where('nama', 'like', "%{$q}%"));
        });
    }

    /** The teacher this account belongs to, if it is a teacher's. */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'nip', 'nip');
    }

    /** The student this account belongs to, if it is a student's. */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'nis', 'nis');
    }

    /**
     * The student's class, two hops away: account -> siswa -> kelas.
     */
    public function kelas(): HasOneThrough
    {
        return $this->hasOneThrough(
            Kelas::class, Siswa::class,
            'nis', 'id', 'nis', 'kelas_id',
        );
    }

    /**
     * Classes where this teacher is the homeroom teacher (wali kelas).
     */
    public function kelasWali(): HasMany
    {
        return $this->hasMany(Kelas::class, 'wali_kelas_nip', 'nip');
    }

    /** Memo for {@see isWaliKelas()} so the layout doesn't re-query every render. */
    private ?bool $isWaliKelas = null;

    /**
     * Whether this teacher is the homeroom teacher of at least one class — the
     * gate for the "Mode Wali Kelas" toggle and its homeroom dashboard. Checked
     * on every authenticated page render, so the result is memoized per request.
     */
    public function isWaliKelas(): bool
    {
        return $this->isWaliKelas ??= $this->role === 'guru' && $this->kelasWali()->exists();
    }

    /**
     * The timetable rows this teacher owns.
     */
    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'guru_nip', 'nip');
    }

    /**
     * The journals of the lessons this teacher is timetabled for, through the
     * timetable — the journal itself no longer carries a NIP.
     */
    public function jurnals(): HasManyThrough
    {
        return $this->hasManyThrough(
            Jurnal::class, Jadwal::class,
            'guru_nip', 'jadwal_id', 'nip', 'id',
        );
    }

    /**
     * The archived per-meeting attendance for this student.
     */
    public function presensis(): HasMany
    {
        return $this->hasMany(Presensi::class, 'siswa_nis', 'nis');
    }

    /**
     * This student's daily attendance — the live record. {@see presensis()}
     * above is the archived per-meeting one, kept for history only.
     */
    public function presensiHarian(): HasMany
    {
        return $this->hasMany(PresensiHarian::class, 'siswa_nis', 'nis');
    }
}
