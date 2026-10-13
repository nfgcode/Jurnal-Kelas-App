<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A date the school does not meet, set by the admin (Hari Libur).
 *
 * @property \Illuminate\Support\Carbon $tanggal
 * @property string $keterangan
 */
class HariLibur extends Model
{
    protected $table = 'hari_libur';

    protected $fillable = ['tanggal', 'keterangan'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    /** The holiday on that date, if it is one. */
    public static function pada(CarbonInterface $tanggal): ?self
    {
        return static::whereDate('tanggal', $tanggal->toDateString())->first();
    }

    /**
     * Holiday dates between two dates inclusive, as a lookup set keyed Y-m-d —
     * one query however many days the caller then walks.
     *
     * @return array<string, true>
     */
    public static function tanggalAntara(CarbonInterface $mulai, CarbonInterface $selesai): array
    {
        // whereDate on both ends: SQLite keeps a date cast as "Y-m-d 00:00:00",
        // which a plain BETWEEN on "Y-m-d" strings would drop on the last day.
        return static::whereDate('tanggal', '>=', $mulai->toDateString())
            ->whereDate('tanggal', '<=', $selesai->toDateString())
            ->pluck('tanggal')
            ->mapWithKeys(fn ($t) => [$t->toDateString() => true])
            ->all();
    }
}
