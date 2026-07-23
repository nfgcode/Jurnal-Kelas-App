<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FULLTEXT index powering natural-language search over journal content
 * (materi + kegiatan). MySQL-only — SQLite has no FULLTEXT, so the search
 * endpoint falls back to LIKE there (and in the test suite).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE jurnal ADD FULLTEXT ft_jurnal (materi, kegiatan)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE jurnal DROP INDEX ft_jurnal');
    }
};
