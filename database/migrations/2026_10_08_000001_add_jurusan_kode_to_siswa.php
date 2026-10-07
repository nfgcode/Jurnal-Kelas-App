<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives each student their own jurusan, keyed by the code the school uses
 * (`jurusan.kode`), as the kelas table already does.
 *
 * Until now a student's jurusan was only ever read through their class. The
 * column starts out as a copy of that — filled from `kelas.jurusan_kode` below —
 * and how it is kept up to date afterwards (on create, on a class change, on
 * import) is settled in the backend work that follows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->string('jurusan_kode', 20)->nullable()->after('kelas_id');

            // A renamed jurusan code follows through to its students; a deleted
            // jurusan leaves them without one rather than blocking the delete.
            $table->foreign('jurusan_kode')->references('kode')->on('jurusan')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->index(['jurusan_kode', 'nama'], 'siswa_jurusan_nama_index');
        });

        // Start from what the class says. Correlated subquery: works the same on
        // MySQL and on the SQLite the test suite runs on.
        DB::table('siswa')
            ->whereNotNull('kelas_id')
            ->update(['jurusan_kode' => DB::raw('(SELECT kelas.jurusan_kode FROM kelas WHERE kelas.id = siswa.kelas_id)')]);
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropForeign(['jurusan_kode']);
            $table->dropIndex('siswa_jurusan_nama_index');
            $table->dropColumn('jurusan_kode');
        });
    }
};
