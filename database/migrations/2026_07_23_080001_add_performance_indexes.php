<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the hot aggregate queries. Laravel's constrained() FKs
 * already index the single foreign-key columns; these cover the (column, filter)
 * shapes the dashboard/report queries actually group and filter on — e.g.
 * attendance rolled up per student by status over ~114k rows. Portable, so they
 * apply on both MySQL and the SQLite test database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->index('tanggal', 'jurnal_tanggal_index');
            $table->index(['guru_id', 'tanggal'], 'jurnal_guru_tanggal_index');
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->index(['siswa_id', 'status'], 'presensi_siswa_status_index');
            $table->index(['jurnal_id', 'status'], 'presensi_jurnal_status_index');
        });

        Schema::table('jadwal', function (Blueprint $table) {
            $table->index(['kelas_id', 'hari'], 'jadwal_kelas_hari_index');
            $table->index(['guru_id', 'hari'], 'jadwal_guru_hari_index');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->dropIndex('jurnal_tanggal_index');
            $table->dropIndex('jurnal_guru_tanggal_index');
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->dropIndex('presensi_siswa_status_index');
            $table->dropIndex('presensi_jurnal_status_index');
        });

        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropIndex('jadwal_kelas_hari_index');
            $table->dropIndex('jadwal_guru_hari_index');
        });
    }
};
