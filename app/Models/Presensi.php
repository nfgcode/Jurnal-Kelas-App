<?php

namespace App\Models;

use App\Support\SimpanPresensiJurnal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's attendance for one *meeting* — a single lesson of a single
 * subject, identified by the journal that records it.
 *
 * This is the school's attendance record. It is marked by the guru who taught
 * the lesson, which is why it is keyed to the journal rather than to the day: a
 * class that had six lessons can legitimately report a student present in five
 * of them and absent in the sixth, and only a per-lesson row can say so.
 *
 * The day-level view of the same facts still exists, derived from these rows by
 * {@see SimpanPresensiJurnal} into {@see PresensiHarian}, so every
 * recap, export and dashboard that thinks in school days keeps working.
 */
class Presensi extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'presensi';

    /** The four statuses a roster may record, in the order every screen lists them. */
    public const STATUS = ['hadir', 'sakit', 'izin', 'alpa'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'jurnal_id',
        'siswa_nis',
        'status',
        'keterangan',
        'diisi_oleh_id',
    ];

    /**
     * Whether a meeting's roster has been marked at all — the question every
     * "sudah/belum" chip on the journal screens asks.
     */
    public static function sudahDiisi(int $jurnalId): bool
    {
        return static::where('jurnal_id', $jurnalId)->exists();
    }

    /**
     * How many students are marked on each of a set of meetings, keyed by
     * journal id — one query behind a whole list of chips instead of one per row.
     *
     * @param  array<int>  $jurnalIds
     * @return array<int, int>
     */
    public static function jumlahPerJurnal(array $jurnalIds): array
    {
        if ($jurnalIds === []) {
            return [];
        }

        return static::query()
            ->selectRaw('jurnal_id, COUNT(*) as total')
            ->whereIn('jurnal_id', $jurnalIds)
            ->groupBy('jurnal_id')
            ->pluck('total', 'jurnal_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Get the jurnal (journal) this attendance record belongs to.
     */
    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(Jurnal::class);
    }

    /**
     * Get the siswa (student) this attendance record belongs to.
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_nis', 'nis');
    }

    /** The guru (or admin) who marked this row. */
    public function diisiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diisi_oleh_id');
    }
}
