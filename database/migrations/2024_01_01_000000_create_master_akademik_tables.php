<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two things every class is filed under: which competency area it
     * belongs to, and which school year it runs in.
     *
     * Both used to be free text repeated on every `kelas` row — "Teknik
     * Komputer dan Jaringan" written out six times, "2026/2027" forty-five
     * times. Nothing stopped a typo from inventing an eleventh jurusan that
     * then showed up as its own entry in every filter dropdown, and renaming a
     * competency area meant an UPDATE across the class list.
     *
     * Keyed by code rather than a surrogate id, matching `ruangan`, `guru` and
     * `siswa`: the school already says "TKJ" and "2026/2027", so those are the
     * identifiers the rows carry.
     */
    public function up(): void
    {
        Schema::create('jurusan', function (Blueprint $table) {
            $table->string('kode', 20)->primary();
            $table->string('nama');
            // Bidang keahlian — the broader family a competency area sits in
            // (Teknologi Informasi, Bisnis dan Manajemen, …). Nullable because
            // an SMA has jurusan (IPA/IPS) but no bidang above them.
            $table->string('bidang')->nullable();
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index('bidang', 'jurusan_bidang_index');
        });

        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->string('kode', 9)->primary(); // "2026/2027"
            $table->date('mulai')->nullable();
            $table->date('selesai')->nullable();
            // Exactly one year is the current one; the application keeps it so
            // when a new year is activated.
            $table->boolean('aktif')->default(false);
            $table->timestamps();

            $table->index('aktif', 'tahun_ajaran_aktif_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahun_ajaran');
        Schema::dropIfExists('jurusan');
    }
};
