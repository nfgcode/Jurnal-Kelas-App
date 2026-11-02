<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('staged_restore_approvals', function (Blueprint $table) {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->unsignedBigInteger('arsip_id');
            $table->char('checksum_sha256', 64);
            $table->unsignedBigInteger('admin_id');
            $table->timestamp('used_at');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('arsip_id')
                ->references('id')
                ->on('cadangan_arsip')
                ->onDelete('cascade');

            $table->foreign('admin_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['arsip_id', 'used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staged_restore_approvals');
    }
};
