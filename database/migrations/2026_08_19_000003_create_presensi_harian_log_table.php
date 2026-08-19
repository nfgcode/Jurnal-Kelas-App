<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit of who filed or corrected a class's daily attendance. One row per save
 * action, not per student — the same shape as the older presensi_log, but keyed
 * to a class-day instead of a journal, because that is what a roster now is.
 *
 * presensi_log is left in place untouched: it is the record of how attendance
 * used to be edited per meeting, and deleting it would erase an audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi_harian_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->date('tanggal');
            // The editor. Nullable + nullOnDelete so removing a user keeps the
            // audit trail rather than cascading it away.
            $table->foreignId('diedit_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('jumlah_siswa');
            // Distinguishes the first filing of the day from a later correction,
            // which is the question an admin actually opens this screen to ask.
            $table->boolean('koreksi')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['kelas_id', 'tanggal'], 'presensi_harian_log_kelas_tanggal_index');
            $table->index('created_at', 'presensi_harian_log_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi_harian_log');
    }
};
