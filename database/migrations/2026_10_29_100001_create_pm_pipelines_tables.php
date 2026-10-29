<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une pipeline par secteur racine ; les étapes sont propres à chaque pipeline.
        Schema::create('pm_pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->unique()->constrained('pm_sectors')->restrictOnDelete();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pm_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('pm_pipelines')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('position')->default(0);
            // open : étape en cours ; won / lost : étapes de clôture.
            $table->string('type', 10)->default('open');
            $table->unsignedTinyInteger('probability')->default(0);
            $table->timestamps();

            $table->unique(['pipeline_id', 'name']);
            $table->index(['pipeline_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_pipeline_stages');
        Schema::dropIfExists('pm_pipelines');
    }
};
