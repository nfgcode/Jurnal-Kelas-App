<?php

namespace App\Models;

use App\Services\AktivasiTahunAjaran;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school year, keyed by the string everyone already writes: "2026/2027".
 *
 * Having it as a row rather than a repeated column gives the year somewhere to
 * carry its own facts — when it starts and ends, and which one is current —
 * instead of that being implied by whatever the newest class says.
 */
class TahunAjaran extends Model
{
    use HasFactory;
    use \App\Models\Concerns\SerializesAcademicMaster;

    protected $table = 'tahun_ajaran';

    protected $primaryKey = 'kode';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kode',
        'mulai',
        'selesai',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'mulai' => 'date',
            'selesai' => 'date',
            'aktif' => 'boolean',
        ];
    }

    /**
     * Model events.
     */
    protected static function booted(): void
    {
        // The database unique active_slot enforces the invariant, including raw SQL.
        // Activation readiness belongs to the explicit activation service, never saved().
    }

    /**
     * The current school year, or the newest one on record if nobody has marked
     * one active yet.
     */
    public static function berjalan(): ?self
    {
        return static::where('aktif', true)->first()
            ?? static::orderByDesc('kode')->first();
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'tahun_ajaran_kode', 'kode');
    }

    public function transisiAsal(): HasMany
    {
        return $this->hasMany(TransisiTahunAjaran::class, 'tahun_asal', 'kode');
    }

    public function transisiTujuan(): HasMany
    {
        return $this->hasMany(TransisiTahunAjaran::class, 'tahun_tujuan', 'kode');
    }

    /**
     * Aktifkan tahun ajaran ini dan nonaktifkan tahun ajaran lainnya.
     */
    public function aktifkan(): void
    {
        app(AktivasiTahunAjaran::class)->aktifkan($this->kode);
        $this->refresh();
    }

    /**
     * Validasi format kode tahun ajaran: YYYY/YYYY (contoh: 2026/2027).
     */
    public static function formatValid(string $kode): bool
    {
        if (! preg_match('/^(\d{4})\/(\d{4})$/', $kode, $m)) {
            return false;
        }

        return ((int) $m[2]) === ((int) $m[1] + 1);
    }
}
