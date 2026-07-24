<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Which database flavour the app is talking to. The advanced objects (views,
 * stored functions, the presensi procedure, full-text indexes) exist only on
 * MySQL; callers branch on this and fall back to portable SQL — the path the
 * SQLite test suite exercises.
 *
 * Deliberately NOT named `Db`: PHP class names are case-insensitive, so a
 * class spelled `Db` is the same name as the `DB` facade alias and the two
 * shadow each other depending on each file's imports.
 */
class DbDriver
{
    public static function mysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }
}
