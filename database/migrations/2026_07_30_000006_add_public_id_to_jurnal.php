<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * An opaque public identifier for a journal, so web URLs stop exposing the
 * sequential primary key (/presensi/3810). Authorization already blocks reading
 * someone else's journal, but a running counter still reveals how many journals
 * exist and invites URL tampering — this removes both.
 *
 * Same pattern as kelas.qr_token. ULID rather than UUID: shorter in a URL and
 * still time-ordered, so it does not fragment the index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->string('public_id', 26)->nullable()->after('id');
        });

        // Backfill row by row: every existing journal needs its own value before
        // the unique index can exist.
        foreach (DB::table('jurnal')->whereNull('public_id')->pluck('id') as $id) {
            DB::table('jurnal')->where('id', $id)->update(['public_id' => (string) Str::ulid()]);
        }

        Schema::table('jurnal', function (Blueprint $table) {
            $table->unique('public_id', 'jurnal_public_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->dropUnique('jurnal_public_id_unique');
            $table->dropColumn('public_id');
        });
    }
};
