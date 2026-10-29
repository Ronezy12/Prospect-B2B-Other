<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_automations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('trigger', 60)->index();
            // brouillon | active | en_pause | arretee
            $table->string('status', 15)->default('brouillon');
            $table->foreignId('active_version_id')->nullable();
            $table->unsignedInteger('current_version')->default(0);
            // Limite de fréquence : au plus N déclenchements par fiche sur la période.
            $table->unsignedSmallInteger('max_runs_per_subject')->default(1);
            $table->unsignedSmallInteger('frequency_days')->default(30);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pm_automation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('pm_automations')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('definition');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['automation_id', 'version']);
        });

        Schema::create('pm_automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('pm_automations')->cascadeOnDelete();
            $table->foreignId('automation_version_id')->constrained('pm_automation_versions')->restrictOnDelete();
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id');
            // Clé de l'événement déclencheur : un même événement ne lance jamais deux exécutions.
            $table->string('trigger_key', 190);
            // en_attente | en_cours | termine | arrete | erreur
            $table->string('status', 15)->default('en_attente');
            $table->string('current_step', 40)->nullable();
            $table->timestamp('resume_at')->nullable();
            $table->json('context')->nullable();
            $table->boolean('is_test')->default(false);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['automation_version_id', 'subject_type', 'subject_id', 'trigger_key'], 'pm_runs_dedupe');
            $table->index(['status', 'resume_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('pm_automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('pm_automation_runs')->cascadeOnDelete();
            $table->string('step_id', 40)->nullable();
            $table->string('type', 30);
            $table->string('result', 20);
            $table->string('message', 255);
            $table->timestamp('created_at')->useCurrent();

            $table->index('run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_automation_logs');
        Schema::dropIfExists('pm_automation_runs');
        Schema::dropIfExists('pm_automation_versions');
        Schema::dropIfExists('pm_automations');
    }
};
