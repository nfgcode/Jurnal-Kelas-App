<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
     * Model events.
     */
    protected static function booted(): void
    {
        // Every class gets a stable, opaque QR token the moment it's created, so
        // the printed room QR (route qr.show) never exposes a sequential id.
        static::creating(function (Kelas $kelas) {
            $kelas->qr_token ??= (string) Str::uuid();
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nama_kelas',
        'tingkat',
        'jurusan',
        'ruangan_kode',
        'kapasitas',
        'tahun_ajaran',
        'wali_kelas_nip',
    ];

    /**
     * The absolute URL the room's QR code encodes — the guru confirmation
     * landing for this class. Uses APP_URL, which on the school deployment must
     * be the LAN address (not localhost) for phones to reach it.
     */
    public function qrUrl(): string
    {
        return route('qr.show', $this->qr_token);
    }

    /**
     * Free-text search across the class list: class name or homeroom teacher.
     */
    public function scopeCari($query, string $q)
    {
        return $query->where(fn ($inner) => $inner
            ->where('nama_kelas', 'like', "%{$q}%")
            ->orWhereHas('waliKelas', fn ($w) => $w->where('nama', 'like', "%{$q}%")));
    }

    /**
     * Get the wali kelas (homeroom teacher) for this class.
     */
    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'wali_kelas_nip', 'nip');
    }

    /**
     * The room this class calls home. Nullable: a class can exist before it has
     * been given one.
     */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_kode', 'kode');
    }

    /**
     * The room code, for the many screens that just want to print where a
     * lesson meets. Rooms are rows now ({@see ruangan()}); this keeps the read
     * side reading as plainly as it did when the column was free text.
     *
     * Eager-load `ruangan` wherever this is rendered in a list, or it costs a
     * query per row.
     */
    protected function ruang(): Attribute
    {
        return Attribute::make(get: fn () => $this->ruangan?->kode);
    }

    /**
     * Get the siswa (students) enrolled in this class.
     *
     * These are person rows, not accounts — a student enrolled but not yet
     * issued a login still belongs to the class and still gets marked present.
     */
    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class, 'kelas_id');
    }

    /**
     * Get the jadwal (schedules) for this class.
     */
    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }
}
