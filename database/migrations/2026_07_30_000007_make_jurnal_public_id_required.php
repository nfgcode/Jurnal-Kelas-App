<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * public_id is the route key for every web link to a journal, so a NULL there is
 * not a missing detail — it makes the row unlinkable and breaks the whole list
 * page that tries to build its URL. The previous migration only backfilled the
 * rows that existed then; anything inserted in bulk afterwards skips the model
 * event that generates one. Making the column required turns that silent hole
 * into a failed insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A late arrival (bulk insert between the two migrations) would abort the
        // ALTER, so close the gap first.
        DB::table('jurnal')->whereNull('public_id')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('jurnal')->where('id', $row->id)->update(['public_id' => (string) Str::ulid()]);
            }
        });

        Schema::table('jurnal', function (Blueprint $table) {
            $table->string('public_id', 26)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->string('public_id', 26)->nullable()->change();
        });
    }
};
