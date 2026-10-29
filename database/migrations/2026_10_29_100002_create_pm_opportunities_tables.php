<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('pm_companies')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('pm_contacts')->nullOnDelete();
            $table->foreignId('pipeline_id')->constrained('pm_pipelines')->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('pm_pipeline_stages')->restrictOnDelete();
            $table->string('title', 190);
            $table->string('priority', 10)->default('normale');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('next_action', 255)->nullable();
            $table->date('follow_up_at')->nullable()->index();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->string('lost_reason', 255)->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pipeline_id', 'stage_id', 'position']);
            $table->index(['owner_id', 'closed_at']);
        });

        Schema::create('pm_stage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('pm_opportunities')->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('pm_pipeline_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->nullable()->constrained('pm_pipeline_stages')->nullOnDelete();
            // Noms figés : l'historique reste lisible si une étape est renommée ou supprimée.
            $table->string('from_name', 80)->nullable();
            $table->string('to_name', 80);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->index();
        });

        Schema::create('pm_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('pm_companies')->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('pm_opportunities')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('pm_contacts')->nullOnDelete();
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->string('type', 20)->default('relance');
            $table->string('priority', 10)->default('normale');
            $table->dateTime('due_at')->index();
            // a_faire | fait | annule
            $table->string('status', 10)->default('a_faire');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assignee_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_tasks');
        Schema::dropIfExists('pm_stage_history');
        Schema::dropIfExists('pm_opportunities');
    }
};
