<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('academic_workflow_operations', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 20);
            $t->string('state', 24)->default('draft')->index();
            $t->string('source_year', 9)->nullable();
            $t->string('target_year', 9)->nullable();
            $t->foreign('source_year')->references('kode')->on('tahun_ajaran')->restrictOnDelete();
            $t->foreign('target_year')->references('kode')->on('tahun_ajaran')->restrictOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->json('source_metadata');
            $t->json('snapshot')->nullable();
            $t->string('snapshot_hash', 64)->nullable();
            $t->string('validation', 16)->default('unverified');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('confirmation_id', 64)->nullable();
            $t->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('confirmed_version')->nullable();
            $t->string('confirmed_hash', 64)->nullable();
            foreach (['validated_at','confirmed_at','confirmation_expires_at','started_at','completed_at','failed_at'] as $column) $t->timestamp($column)->nullable();
            $t->json('result')->nullable();
            $t->string('error_code', 40)->nullable();
            $t->timestamps();
        });
        Schema::create('academic_workflow_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operation_id')->constrained('academic_workflow_operations')->restrictOnDelete();
            $t->string('student_ref', 20);
            $t->string('decision', 20)->nullable();
            $t->foreignId('target_class_id')->nullable()->constrained('kelas')->restrictOnDelete();
            $t->string('nisn', 20)->nullable();
            $t->string('email', 254)->nullable();
            $t->boolean('exclusion_reason_present')->default(false);
            $t->boolean('excluded')->default(false);
            $t->string('validation',16)->default('unverified');
            $t->json('errors')->nullable();
            $t->timestamps();
            $t->unique(['operation_id','student_ref']);
        });
        Schema::create('academic_workflow_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operation_id')->constrained('academic_workflow_operations')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('kind',20);
            $t->string('event',24);
            $t->string('previous_state',24)->nullable();
            $t->string('next_state',24);
            $t->string('outcome',16);
            $t->json('student_refs');
            $t->json('metadata');
            $t->timestamp('created_at');
        });
        Schema::create('academic_workflow_coordinators', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('academic_workflow_stages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coordinator_id')->constrained('academic_workflow_coordinators')->restrictOnDelete();
            $t->unsignedTinyInteger('position');
            $t->string('kind',20);
            $t->foreignId('operation_id')->nullable()->unique()->constrained('academic_workflow_operations')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['coordinator_id','kind']);
            $t->unique(['coordinator_id','position']);
        });
    }
    public function down(): void
    {
        foreach (['stages','coordinators','audits','items','operations'] as $name) Schema::dropIfExists('academic_workflow_'.$name);
    }
};
