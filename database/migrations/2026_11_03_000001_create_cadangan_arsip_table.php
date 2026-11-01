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
        Schema::create('cadangan_arsip', function (Blueprint $table) {
            $table->id();
            $table->string('nama_berkas', 191)->unique();
            $table->string('format', 32); // sql_gz, sql, zip_berkas, json_gz
            $table->string('tipe', 32); // manual, otomatis_bulanan
            $table->string('lokasi_privat', 255); // path relative to storage/app/backups
            $table->unsignedBigInteger('ukuran_bytes');
            $table->char('checksum_sha256', 64);
            $table->string('status_validasi', 32)->default('terverifikasi'); // terverifikasi, gagal, parsial
            $table->boolean('penting')->default(false); // pinned protection
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('terakhir_divalidasi_at')->nullable();
            $table->json('metadata_manifest')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('actor_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['format', 'tipe']);
            $table->index(['status_validasi', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cadangan_arsip');
    }
};
