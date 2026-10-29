<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 20);
            $table->unsignedInteger('current_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Versions immuables : une campagne conserve exactement le contenu envoyé.
        Schema::create('pm_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('pm_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('subject', 190);
            $table->string('preheader', 190)->nullable();
            $table->longText('html');
            $table->longText('design')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_template_versions');
        Schema::dropIfExists('pm_templates');
    }
};
