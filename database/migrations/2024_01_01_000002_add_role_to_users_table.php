<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add role fields and kelas_id FK to users table.
     * Also adds the wali_kelas_id FK constraint on kelas table
     * now that users table exists.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'guru', 'siswa'])->default('siswa')->after('email');
            $table->string('nip')->nullable()->after('role');
            $table->string('nis')->nullable()->after('nip');
            $table->foreignId('kelas_id')->nullable()->after('nis')->constrained('kelas')->nullOnDelete();
        });

        // Now add the FK constraint for wali_kelas_id on kelas table
        Schema::table('kelas', function (Blueprint $table) {
            $table->foreign('wali_kelas_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropForeign(['wali_kelas_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['kelas_id']);
            $table->dropColumn(['role', 'nip', 'nis', 'kelas_id']);
        });
    }
};
