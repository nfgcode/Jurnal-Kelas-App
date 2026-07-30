<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `jurnal_jadwal_tanggal_index (jadwal_id, tanggal)` became a leftmost prefix of
 * `jurnal_pertemuan_peran_unique (jadwal_id, tanggal, diisi_oleh_peran)`, which
 * the optimizer can serve every query the old index served. Keeping both only
 * costs write time and space on a table that grows with every lesson.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->dropIndex('jurnal_jadwal_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->index(['jadwal_id', 'tanggal'], 'jurnal_jadwal_tanggal_index');
        });
    }
};
