<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('color', 7)->nullable();
            $table->timestamps();
        });

        Schema::create('pm_taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained('pm_tags')->cascadeOnDelete();
            $table->morphs('taggable');
            $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_taggables');
        Schema::dropIfExists('pm_tags');
    }
};
