<?php

namespace App\Support;

use App\Services\AktivasiTahunAjaran;
use Illuminate\Support\Facades\DB;

/** Acquire before any dependency read in a supported academic writer transaction. */
final class AcademicOperation
{
    public static function run(callable $write): mixed
    {
        return DB::transaction(function () use ($write) {
            AktivasiTahunAjaran::kunci();

            return $write();
        });
    }
}
