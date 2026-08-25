<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The keys a timetable has always had, finally written down.
     *
     * `jadwal` had no unique constraint at all, so the same class could be
     * booked twice in one period, a teacher could be scheduled into two rooms
     * at once, and a room could hold two lessons — none of which is possible in
     * a building. The seeded dev data had 353 teacher clashes precisely because
     * nothing refused them.
     *
     * Three separate keys rather than one composite: each expresses a different
     * physical impossibility, and each names the one it caught when it fires.
     */
    public function up(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            // A class is in one place at a time.
            $table->unique(['kelas_id', 'hari', 'jam_ke_mulai'], 'jadwal_kelas_slot_unique');
            // So is a teacher.
            $table->unique(['guru_nip', 'hari', 'jam_ke_mulai'], 'jadwal_guru_slot_unique');
            // A room holds one lesson at a time. Nullable, and MySQL allows any
            // number of NULLs in a unique index, so slots with no room assigned
            // are unaffected.
            $table->unique(['ruangan_kode', 'hari', 'jam_ke_mulai'], 'jadwal_ruangan_slot_unique');
        });

        // (kelas_id, hari) and (guru_nip, hari) are leftmost prefixes of the
        // unique keys above, so every lookup they served is already served —
        // they were pure write overhead from here on.
        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropIndex('jadwal_kelas_hari_index');
            $table->dropIndex('jadwal_guru_hari_index');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->index(['kelas_id', 'hari'], 'jadwal_kelas_hari_index');
            $table->index(['guru_nip', 'hari'], 'jadwal_guru_hari_index');
            $table->dropUnique('jadwal_kelas_slot_unique');
            $table->dropUnique('jadwal_guru_slot_unique');
            $table->dropUnique('jadwal_ruangan_slot_unique');
        });
    }
};
