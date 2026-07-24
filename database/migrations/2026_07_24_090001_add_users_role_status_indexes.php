<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The users table had no non-unique index at all, yet nearly every screen
 * counts and filters it by role and status (dashboard KPIs, the admin people
 * list, "guru aktif", per-role tallies) and sorts the list by last activity.
 * These cover exactly those shapes. Portable — apply on MySQL and the SQLite
 * test database alike.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('role', 'users_role_index');
            $table->index('status', 'users_status_index');
            $table->index(['role', 'status'], 'users_role_status_index');
            $table->index('last_active_at', 'users_last_active_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_index');
            $table->dropIndex('users_status_index');
            $table->dropIndex('users_role_status_index');
            $table->dropIndex('users_last_active_at_index');
        });
    }
};
