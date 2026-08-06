<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a journal that was edited on a day after the lesson it records — kept
 * distinct from "Telat" (filled late), so the recap can single out records
 * changed after the fact, which is where an auto "absent" being flipped to
 * "present" would show up. Set by the update paths, never reset once true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->boolean('diedit_setelah_hari')->default(false)->after('diisi_oleh_peran');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->dropColumn('diedit_setelah_hari');
        });
    }
};
