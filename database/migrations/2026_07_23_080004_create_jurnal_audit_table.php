<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change history for journals, written by the AFTER UPDATE/DELETE triggers on
 * MySQL (a later migration). The table itself is portable and exists on every
 * engine; on SQLite it simply stays empty (no trigger fires). No FK to jurnal,
 * so a delete's audit row survives the row it describes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jurnal_id')->index();
            $table->string('aksi', 20);            // 'update' | 'delete'
            $table->text('materi_lama')->nullable();
            $table->text('materi_baru')->nullable();
            $table->timestamp('diubah_pada')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_audit');
    }
};
