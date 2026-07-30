<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Error reports submitted by guru/siswa from the friendly error page. One row per
 * distinct problem per reporter: repeats of the same fault increment `jumlah`
 * instead of adding rows (see LaporanErrorController), so the admin inbox shows
 * "this broke 6 times" rather than six near-identical entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_error', function (Blueprint $table) {
            $table->id();
            // Nullable + nullOnDelete so removing an account keeps its reports.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ref', 12)->index();
            // md5(message|file|line) — what "the same error" means for dedupe.
            $table->string('tanda_tangan', 32)->index();
            $table->string('status', 20)->default('baru');
            $table->text('pesan')->nullable();
            $table->string('url', 500)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('exception_pesan')->nullable();
            $table->string('exception_file', 500)->nullable();
            $table->unsignedInteger('exception_line')->nullable();
            $table->unsignedInteger('jumlah')->default(1);
            $table->timestamps();

            // The inbox lists newest-open-first.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_error');
    }
};
