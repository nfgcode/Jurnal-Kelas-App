<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users_role_index(role) is the leftmost prefix of users_role_status_index
 * (role, status), so every lookup it could serve is already served by the wider
 * index — it was pure overhead: another B-tree to update on each insert and on
 * each of the frequent last_active_at writes, for no read ever gained.
 *
 * users_status_index(status) stays: status is not the leftmost column of the
 * composite, so filtering on status alone cannot use it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('role', 'users_role_index');
        });
    }
};
