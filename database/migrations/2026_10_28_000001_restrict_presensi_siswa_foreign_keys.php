<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change siswa_nis foreign keys in presensi_harian and presensi from
     * cascadeOnDelete to restrictOnDelete so that deleting a student cannot
     * quietly purge years of historical attendance records.
     */
    public function up(): void
    {
        Schema::table('presensi_harian', function (Blueprint $table) {
            $table->dropForeign(['siswa_nis']);
            $table->foreign('siswa_nis')->references('nis')->on('siswa')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->dropForeign(['siswa_nis']);
            $table->foreign('siswa_nis')->references('nis')->on('siswa')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropForeign(['siswa_nis']);
            $table->foreign('siswa_nis')->references('nis')->on('siswa')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::table('presensi_harian', function (Blueprint $table) {
            $table->dropForeign(['siswa_nis']);
            $table->foreign('siswa_nis')->references('nis')->on('siswa')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }
};
