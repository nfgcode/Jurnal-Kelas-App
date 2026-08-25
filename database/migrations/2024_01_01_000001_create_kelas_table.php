<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A rombel: one group of students, in one grade, of one competency area,
     * for one school year.
     *
     * Created before `guru` and `siswa`, so `wali_kelas_nip` and `ketua_nis`
     * are declared here as plain columns and pick up their foreign keys in the
     * migration that creates those tables.
     */
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kelas');
            $table->enum('tingkat', ['X', 'XI', 'XII']);
            $table->string('jurusan_kode', 20)->nullable();
            // Which of the parallel classes of that grade+jurusan this is:
            // "XII AKL 2" is paralel 2. Stored rather than parsed back out of
            // the name, so ordering and "how many classes does AKL run?" are
            // questions the database can answer.
            $table->unsignedTinyInteger('paralel')->default(1);
            $table->string('ruangan_kode', 20)->nullable();
            $table->unsignedSmallInteger('kapasitas')->default(36);
            $table->string('tahun_ajaran_kode', 9);
            $table->string('wali_kelas_nip', 20)->nullable();
            // The student who may fill this class's journal on the teacher's
            // behalf. One column, so a second ketua is not merely discouraged
            // by application code — it is unrepresentable.
            $table->string('ketua_nis', 20)->nullable();
            $table->timestamps();

            $table->foreign('jurusan_kode')->references('kode')->on('jurusan')->nullOnDelete();
            $table->foreign('tahun_ajaran_kode')->references('kode')->on('tahun_ajaran')->cascadeOnUpdate();

            // The identity of a rombel within a school year.
            $table->unique(['tahun_ajaran_kode', 'tingkat', 'jurusan_kode', 'paralel'], 'kelas_rombel_unique');
            $table->index('tingkat', 'kelas_tingkat_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
