<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Created before `guru`, so `wali_kelas_nip` is declared here as a plain
     * column and picks up its foreign key in the migration that creates the
     * teacher table. Same dance as before the split — only the referenced key
     * changed, from a surrogate id to the teacher's NIP.
     */
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kelas');
            $table->enum('tingkat', ['X', 'XI', 'XII']);
            $table->string('jurusan')->nullable();
            $table->string('tahun_ajaran');
            $table->string('wali_kelas_nip', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
