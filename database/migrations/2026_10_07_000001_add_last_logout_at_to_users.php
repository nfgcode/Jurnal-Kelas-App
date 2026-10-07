<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a user last signed out, so the Akun Pengguna "Online / Offline" status
 * turns Offline the moment they log out instead of waiting for the activity
 * window to lapse. Online = active in the last few minutes and not signed out
 * since ({@see App\Models\User::isOnline()}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_logout_at')->nullable()->after('last_active_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_logout_at');
        });
    }
};
