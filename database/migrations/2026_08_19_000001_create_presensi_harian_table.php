<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student attendance as the school actually takes it: once per class per day,
 * filed by that class's ketua kelas.
 *
 * The older `presensi` table hangs off a journal, so a class with six lessons
 * carried six rosters a day — six chances to disagree about whether a student
 * was in school. Attendance is a property of the day, not of the lesson, so it
 * lives here keyed by (kelas, tanggal, siswa) and the unique index makes a
 * second roster for the same day impossible rather than merely discouraged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->date('tanggal');
            $table->foreignId('siswa_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['hadir', 'sakit', 'izin', 'alpa']);
            $table->text('keterangan')->nullable();
            // Who filed it — the ketua kelas normally, an admin when correcting.
            // Nullable + nullOnDelete so removing an account keeps the record.
            $table->foreignId('diisi_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The rule the whole feature rests on: one status per student per
            // class per day, enforced by the database and not just by the form.
            $table->unique(['kelas_id', 'tanggal', 'siswa_id'], 'presensi_harian_unik');

            // The class-day lookup every recap screen opens with.
            $table->index(['kelas_id', 'tanggal'], 'presensi_harian_kelas_tanggal_index');
            // A student's own history, and the school-wide period filter.
            $table->index(['siswa_id', 'tanggal'], 'presensi_harian_siswa_tanggal_index');
            $table->index(['tanggal', 'status'], 'presensi_harian_tanggal_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi_harian');
    }
};
