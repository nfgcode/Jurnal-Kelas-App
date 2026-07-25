<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Application-level audit of who saved a meeting's attendance roster. Unlike the
 * MySQL trigger behind jurnal_audit, this is written from PHP so it can capture
 * the acting *app* user (a DB trigger only sees the DB user) and works the same
 * on MySQL and SQLite. One row per save action, not per student.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurnal_id')->constrained('jurnal')->cascadeOnDelete();
            // The editor. Nullable + nullOnDelete so removing a user keeps the
            // audit trail rather than cascading it away.
            $table->foreignId('diedit_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('jumlah_siswa');
            $table->timestamp('created_at')->nullable();

            $table->index(['jurnal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi_log');
    }
};
