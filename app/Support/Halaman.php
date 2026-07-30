<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * How many rows a paginated table shows. The size comes from the URL (`?per=`),
 * so it must be whitelisted: an arbitrary `?per=100000` would let any visitor ask
 * the database for the whole table on every request.
 */
class Halaman
{
    /** The sizes the pager offers. */
    public const PILIHAN = [25, 50, 75, 100];

    private const BAWAAN = 25;

    /**
     * The page size for this request. The Request is optional so screens that
     * paginate from a helper without one in scope can still call this.
     */
    public static function perHalaman(?Request $request = null): int
    {
        $per = (int) ($request ?? request())->query('per');

        return in_array($per, self::PILIHAN, true) ? $per : self::BAWAAN;
    }
}
