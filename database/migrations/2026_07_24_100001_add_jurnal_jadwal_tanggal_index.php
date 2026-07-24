<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Several hot queries filter journals by schedule AND date — the student
 * dashboard's "today's journals" (whereIn jadwal_id + whereDate), the journal
 * form's next-empty-slot picker, and the admin "belum diisi" drill-down
 * (whereIn jadwal_id + whereBetween tanggal). Only the single-column FK index
 * existed; this composite serves the pair. Portable across MySQL and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->index(['jadwal_id', 'tanggal'], 'jurnal_jadwal_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->dropIndex('jurnal_jadwal_tanggal_index');
        });
    }
};
