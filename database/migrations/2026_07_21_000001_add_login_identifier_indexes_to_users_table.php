<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NIP (guru) and NIS (siswa) are now login identifiers, so they have to be
     * unique. Both stay nullable — an admin has neither, and MySQL allows any
     * number of NULLs in a unique index.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('nip');
            $table->unique('nis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nip']);
            $table->dropUnique(['nis']);
        });
    }
};
