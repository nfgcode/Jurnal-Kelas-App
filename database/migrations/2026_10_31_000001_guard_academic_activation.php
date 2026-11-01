<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fail closed on existing corruption; never silently choose an active year.
        if (DB::table('tahun_ajaran')->where('aktif', true)->count() > 1) {
            throw new RuntimeException('Multiple active academic years require explicit correction.');
        }
        Schema::create('academic_operation_lock', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('academic_operation_lock')->insert(['id' => 1]);
        Schema::table('tahun_ajaran', function (Blueprint $table) {
            $table->unsignedTinyInteger('active_slot')->nullable()->virtualAs('CASE WHEN aktif = 1 THEN 1 ELSE NULL END');
            $table->unique('active_slot', 'tahun_ajaran_single_active');
        });
    }

    public function down(): void
    {
        Schema::table('tahun_ajaran', function (Blueprint $table) {
            $table->dropUnique('tahun_ajaran_single_active');
            $table->dropColumn('active_slot');
        });
        Schema::dropIfExists('academic_operation_lock');
    }
};
