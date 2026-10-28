<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_user')->nullable();
            $table->string('role_user', 20)->nullable();
            $table->string('aksi', 50)->index();
            $table->string('tipe_entitas', 50)->index();
            $table->string('entitas_id', 50)->nullable()->index();
            $table->enum('status', ['berhasil', 'gagal', 'diproses'])->default('berhasil');
            $table->text('deskripsi');
            $table->unsignedInteger('jumlah_data')->default(1);
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_log');
    }
};
