<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which subjects a teacher is certified to teach — a fact about the teacher,
     * not about any one timetable slot.
     *
     * Until now the only record of it was the timetable itself, which answers a
     * different question: `jadwal` says what a teacher *is scheduled to* teach
     * this term, so a teacher between assignments appeared qualified for
     * nothing, and the schedule form happily offered any teacher for any
     * subject. With this table the form can refuse a pairing the school never
     * approved, and a teacher's subjects survive an empty timetable.
     *
     * Many-to-many in both directions, as the school actually works: most
     * teachers hold one subject, some hold two or three, and a subject taught
     * across 45 classes needs several teachers.
     */
    public function up(): void
    {
        Schema::create('guru_mata_pelajaran', function (Blueprint $table) {
            $table->string('guru_nip', 20);
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            // Marks the subject a teacher is principally responsible for, when
            // they hold more than one. Null-free: exactly one row per teacher
            // should carry it, which the application enforces on save.
            $table->boolean('utama')->default(false);
            $table->timestamps();

            $table->foreign('guru_nip')->references('nip')->on('guru')->cascadeOnDelete();
            // The pair is the identity of the row; a teacher cannot hold the
            // same subject twice.
            $table->primary(['guru_nip', 'mata_pelajaran_id']);
            $table->index('mata_pelajaran_id', 'guru_mapel_mapel_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guru_mata_pelajaran');
    }
};
