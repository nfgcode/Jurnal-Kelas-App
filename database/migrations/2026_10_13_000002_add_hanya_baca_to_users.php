<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun pantau: an admin account that sees everything an admin sees and changes
 * nothing — for the kepala sekolah and waka kurikulum, who need the dashboard
 * and the recaps but should not be one click away from deleting a class.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('hanya_baca')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('hanya_baca');
        });
    }
};
