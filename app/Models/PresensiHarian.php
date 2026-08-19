<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's attendance for one school day, in one class.
 *
 * This is the school's attendance record. It is taken once a day by the class's
 * ketua kelas — not per lesson — so a class-day has exactly one roster and every
 * figure derived from it counts each student at most once. The unique index
 * (kelas_id, tanggal, siswa_id) is what guarantees that.
 */
class PresensiHarian extends Model
{
    use HasFactory;

    protected $table = 'presensi_harian';

    /** The four statuses a roster may record, in the order every screen lists them. */
    public const STATUS = ['hadir', 'sakit', 'izin', 'alpa'];

    protected $fillable = [
        'kelas_id',
        'tanggal',
        'siswa_id',
        'status',
        'keterangan',
        'diisi_oleh_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    /**
     * Rows inside a period. Unlike the old per-meeting table this needs no join
     * — the date is on the row itself, which is most of the point of the table.
     */
    public function scopeDalamPeriode($query, string $mulai, string $selesai)
    {
        return $query->whereBetween('presensi_harian.tanggal', [$mulai, $selesai]);
    }

    /** Rows for one class. */
    public function scopeUntukKelas($query, int $kelasId)
    {
        return $query->where('presensi_harian.kelas_id', $kelasId);
    }

    /**
     * Whether a class's roster for a date has been filed at all — the question
     * the ketua's dashboard card and the "sudah/belum" chips ask.
     */
    public static function sudahDiisi(int $kelasId, string $tanggal): bool
    {
        return static::query()
            ->where('kelas_id', $kelasId)
            ->whereDate('tanggal', $tanggal)
            ->exists();
    }

    /**
     * The class-days that actually have a roster inside a window, as a set of
     * "kelasId|Y-m-d" keys — one query behind a whole month of "sudah diisi?"
     * chips instead of one query per cell.
     *
     * @param  array<int>  $kelasIds
     * @return array<string, int> keyed "kelasId|Y-m-d" => student count
     */
    public static function petaHariTerisi(array $kelasIds, string $mulai, string $selesai): array
    {
        if ($kelasIds === []) {
            return [];
        }

        return static::query()
            ->selectRaw('kelas_id, tanggal, COUNT(*) as total')
            ->whereIn('kelas_id', $kelasIds)
            ->dalamPeriode($mulai, $selesai)
            ->groupBy('kelas_id', 'tanggal')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->kelas_id.'|'.substr((string) $row->tanggal, 0, 10) => (int) $row->total,
            ])
            ->all();
    }

    /**
     * The sort map the daily-recap tables share, mirroring Jurnal::petaUrutan()
     * so the two families of screens can never drift into ordering "kelas" two
     * different ways. Aggregate keys (hadir…persen) assume the caller selected
     * those aliases.
     *
     * @return array<string, callable>
     */
    public static function petaUrutan(): array
    {
        return [
            'tanggal' => fn (Builder|\Illuminate\Database\Query\Builder $q, $dir) => $q
                ->orderBy('tanggal', $dir),
            'kelas' => fn ($q, $dir) => $q->orderBy('nama_kelas', $dir),
            'siswa' => fn ($q, $dir) => $q->orderBy('total_siswa', $dir),
            'hadir' => fn ($q, $dir) => $q->orderBy('hadir', $dir),
            'sakit' => fn ($q, $dir) => $q->orderBy('sakit', $dir),
            'izin' => fn ($q, $dir) => $q->orderBy('izin', $dir),
            'alpa' => fn ($q, $dir) => $q->orderBy('alpa', $dir),
            'persen' => fn ($q, $dir) => $q->orderByRaw("hadir / NULLIF(total_siswa, 0) {$dir}"),
        ];
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'siswa_id');
    }

    /** The ketua kelas (or admin) who filed this roster. */
    public function diisiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diisi_oleh_id');
    }
}
