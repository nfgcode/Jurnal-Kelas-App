<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_workflow_coordinators', function (Blueprint $t) {
            $t->string('source_year', 9)->nullable()->after('actor_id');
            $t->string('target_year', 9)->nullable()->after('source_year');
            $t->foreign('source_year')->references('kode')->on('tahun_ajaran')->restrictOnDelete();
            $t->foreign('target_year')->references('kode')->on('tahun_ajaran')->restrictOnDelete();
            $t->index(['source_year', 'target_year'], 'coordinator_years_index');
        });
    }

    public function down(): void
    {
        Schema::table('academic_workflow_coordinators', function (Blueprint $t) {
            $t->dropForeign(['source_year']);
            $t->dropForeign(['target_year']);
            $t->dropIndex('coordinator_years_index');
            $t->dropColumn(['source_year', 'target_year']);
        });
    }
};
