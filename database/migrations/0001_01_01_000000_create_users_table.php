<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `users` is the *account* table and nothing else: the credentials someone
     * signs in with. Who that person is at the school — their name, class,
     * homeroom, NIS/NIP — lives in `siswa` and `guru`, added a few migrations
     * later once `kelas` exists.
     *
     * The split matters because the two things have different lifetimes. A
     * student exists in the school's records whether or not anyone ever issued
     * them a login, and an account can be revoked without erasing the person's
     * attendance history. Holding both in one row forced every "is this a real
     * person or a login?" question to be answered by squinting at `role`.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Three credentials, because that is what the login form accepts:
            // a username or an email, plus a password.
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
