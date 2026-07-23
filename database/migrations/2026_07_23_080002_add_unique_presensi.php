<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A student can have exactly one attendance record per journal. Without this a
 * double-submitted roster (or a race between two concurrent submits) silently
 * created duplicate rows. Existing duplicates are collapsed to the earliest row
 * before the unique index is added; on the empty test database this is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Keep the lowest id per (jurnal_id, siswa_id). MySQL needs the self-join
        // form; SQLite (and the test DB) takes the portable NOT IN form.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('DELETE p1 FROM presensi p1 JOIN presensi p2
                ON p1.jurnal_id = p2.jurnal_id AND p1.siswa_id = p2.siswa_id AND p1.id > p2.id');
        } else {
            DB::statement('DELETE FROM presensi WHERE id NOT IN (
                SELECT MIN(id) FROM presensi GROUP BY jurnal_id, siswa_id)');
        }

        Schema::table('presensi', function (Blueprint $table) {
            $table->unique(['jurnal_id', 'siswa_id'], 'presensi_jurnal_siswa_unique');
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropUnique('presensi_jurnal_siswa_unique');
        });
    }
};
