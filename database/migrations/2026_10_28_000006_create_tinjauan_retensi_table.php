<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tinjauan_retensi', function (Blueprint $table) {
            $table->id();
            $table->string('siswa_nis', 20)->index();
            $table->enum('status_tinjauan', ['perlu_tinjauan', 'ditinjau', 'ditunda', 'disetujui_arsip'])
                ->default('perlu_tinjauan');
            $table->text('catatan')->nullable();
            $table->foreignId('ditinjau_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditinjau_pada')->nullable();
            $table->timestamps();

            $table->foreign('siswa_nis')->references('nis')->on('siswa')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tinjauan_retensi');
    }
};
