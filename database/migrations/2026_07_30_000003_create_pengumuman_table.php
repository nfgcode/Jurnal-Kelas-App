<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-written banners shown to guru/siswa inside the app — e.g. "server dimatikan
 * 17.00" — so a notice can be posted without taking the app down with
 * `artisan down` (which would lock the admin out too).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->id();
            $table->text('pesan');
            $table->string('tipe', 20)->default('info');
            $table->boolean('aktif')->default(true);
            // Optional window; null means "from now" / "until switched off".
            $table->timestamp('mulai')->nullable();
            $table->timestamp('selesai')->nullable();
            $table->foreignId('dibuat_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['aktif', 'mulai', 'selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};
