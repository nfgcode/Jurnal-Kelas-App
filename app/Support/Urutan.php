<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Column sorting driven by `?sort=` / `?dir=`.
 *
 * Both values come from the URL and can never reach SQL directly: the column is
 * looked up in a per-screen whitelist map and the direction is narrowed to
 * asc|desc. An unknown column falls back to the screen's default ordering rather
 * than erroring, so a stale or hand-edited link still renders.
 */
class Urutan
{
    /**
     * Apply the requested sort, or the caller's default when none/unknown.
     *
     * @param  Builder  $query
     * @param  array<string, callable>  $peta  UI column name => fn ($query, string $dir)
     * @param  callable  $bawaan  fn ($query) applied when no valid sort was asked for
     */
    public static function terapkan($query, Request $request, array $peta, callable $bawaan)
    {
        $sort = (string) $request->query('sort');
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        if ($sort !== '' && isset($peta[$sort])) {
            return $peta[$sort]($query, $dir);
        }

        return $bawaan($query);
    }
}
