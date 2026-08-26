<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Shared plumbing for the writes that go through a stored procedure.
 *
 * Every one of them has the same shape: MySQL hands the work to a procedure
 * that holds the checks and the transaction inside the database, and everywhere
 * else — the SQLite test database — the identical sequence runs here in PHP.
 * Both paths raise the same short codes ('NIS_SUDAH_ADA', 'KELAS_BENTROK', …)
 * and both are turned into field-level validation errors by one translator, so
 * the two implementations cannot drift apart in what the user is told.
 *
 * The point of the procedure is not that a single INSERT needs a transaction —
 * it does not. It is that the rule lives where every writer meets it: the form,
 * the API, a bulk import, or an admin typing SQL into phpMyAdmin.
 */
abstract class ProsedurTersimpan
{
    /**
     * Which form field each failure code belongs against, and what to say.
     *
     * A duplicate NIS is not a generic error — it is a problem with the NIS box,
     * and that is where the user needs to see it.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    abstract protected function pesan(): array;

    protected function mysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    /**
     * Pick the implementation, and translate whatever it raises.
     *
     * The MySQL branch surfaces SIGNAL text inside a QueryException message;
     * the portable branch throws the bare code. Matching on substring covers
     * both without either having to know how the other reports.
     */
    protected function jalankan(callable $mysql, callable $portabel): void
    {
        try {
            $this->mysql() ? $mysql() : $portabel();
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException|Throwable $e) {
            foreach ($this->pesan() as $kode => [$kolom, $pesan]) {
                if (str_contains($e->getMessage(), $kode)) {
                    throw ValidationException::withMessages([$kolom => $pesan]);
                }
            }

            throw $e;
        }
    }

    protected function tolakBila(bool $salah, string $kode): void
    {
        if ($salah) {
            [$kolom, $pesan] = $this->pesan()[$kode];

            throw ValidationException::withMessages([$kolom => $pesan]);
        }
    }
}
