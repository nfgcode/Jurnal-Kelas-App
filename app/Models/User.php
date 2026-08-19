<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'nip',
        'nis',
        'kelas_id',
        'last_active_at',
        'is_ketua_kelas',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'is_ketua_kelas' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Model events.
     */
    protected static function booted(): void
    {
        // A homeroom assignment (kelas.wali_kelas_id) is only meaningful for a
        // guru. When an account stops being a guru, release any class it was
        // wali of, so the class list never shows a non-guru as its wali.
        // Deletion is already covered by the FK's nullOnDelete.
        static::updated(function (User $user) {
            if ($user->wasChanged('role') && $user->role !== 'guru') {
                Kelas::where('wali_kelas_id', $user->id)->update(['wali_kelas_id' => null]);
            }
        });
    }

    /**
     * The student allowed to fill the class journal on the teacher's behalf.
     */
    public function isKetuaKelas(): bool
    {
        return $this->role === 'siswa' && $this->is_ketua_kelas;
    }

    /**
     * Initial used by the circular avatar on every screen.
     */
    public function inisial(): string
    {
        return mb_strtoupper(mb_substr($this->name ?? '?', 0, 1));
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is guru (teacher).
     */
    public function isGuru(): bool
    {
        return $this->role === 'guru';
    }

    /**
     * Check if user is siswa (student).
     */
    public function isSiswa(): bool
    {
        return $this->role === 'siswa';
    }

    /**
     * Free-text search across the people list: name, email, NIP/NIS, and class
     * name — a student's own class or a class a teacher is timetabled in.
     */
    public function scopeCari($query, string $q)
    {
        return $query->where(function ($inner) use ($q) {
            $inner->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('nip', 'like', "%{$q}%")
                ->orWhere('nis', 'like', "%{$q}%")
                ->orWhereHas('kelas', fn ($k) => $k->where('nama_kelas', 'like', "%{$q}%"))
                ->orWhereHas('jadwals.kelas', fn ($k) => $k->where('nama_kelas', 'like', "%{$q}%"));
        });
    }

    /**
     * Get the kelas (class) that this student belongs to.
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * Classes where this teacher is the homeroom teacher (wali kelas).
     */
    public function kelasWali(): HasMany
    {
        return $this->hasMany(Kelas::class, 'wali_kelas_id');
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
     * Get the jadwal (schedules) where this user teaches.
     */
    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'guru_id');
    }

    /**
     * Get the jurnal (journals) created by this teacher.
     */
    public function jurnals(): HasMany
    {
        return $this->hasMany(Jurnal::class, 'guru_id');
    }

    /**
     * Get the presensi (attendance) records for this student.
     */
    public function presensis(): HasMany
    {
        return $this->hasMany(Presensi::class, 'siswa_id');
    }

    /**
     * This student's daily attendance — the live record. {@see presensis()}
     * above is the archived per-meeting one, kept for history only.
     */
    public function presensiHarian(): HasMany
    {
        return $this->hasMany(PresensiHarian::class, 'siswa_id');
    }
}
