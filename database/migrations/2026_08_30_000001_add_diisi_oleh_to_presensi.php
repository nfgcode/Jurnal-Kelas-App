<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-meeting attendance is written again — by the guru who taught the lesson —
 * so the row has to say who marked it, exactly as the daily table already does.
 *
 * Nullable + nullOnDelete: the rows written before this change have no author to
 * name, and removing an account must not take the attendance record with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->foreignId('diisi_oleh_id')->nullable()->after('keterangan')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diisi_oleh_id');
        });
    }
};
