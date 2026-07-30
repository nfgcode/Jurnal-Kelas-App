<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A stable, opaque per-class token that the room's printed QR code encodes, so
 * the QR URL (route qr.show) never exposes a sequential class id. Existing
 * classes are backfilled with a UUID; new ones get theirs from Kelas::booted().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->string('qr_token', 36)->nullable();
        });

        foreach (DB::table('kelas')->whereNull('qr_token')->pluck('id') as $id) {
            DB::table('kelas')->where('id', $id)->update(['qr_token' => (string) Str::uuid()]);
        }

        Schema::table('kelas', function (Blueprint $table) {
            $table->unique('qr_token', 'kelas_qr_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropUnique('kelas_qr_token_unique');
            $table->dropColumn('qr_token');
        });
    }
};
