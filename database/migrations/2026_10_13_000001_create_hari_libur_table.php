<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * School-free days the timetable would otherwise count: national holidays,
 * exam weeks, PKL. One row per date, so a range is just several rows.
 *
 * Read by the nightly journal backfill (no "guru tidak hadir" placeholders on
 * these days) and by every completeness figure built on school days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hari_libur', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->string('keterangan', 150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hari_libur');
    }
};
