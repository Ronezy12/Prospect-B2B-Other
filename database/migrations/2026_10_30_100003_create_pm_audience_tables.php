<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->json('filters');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pm_newsletter_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Abonnement = consentement tracé (source, date, preuve) ; jamais supprimé, seulement désabonné.
        Schema::create('pm_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('pm_newsletter_lists')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('pm_contacts')->cascadeOnDelete();
            $table->string('status', 15)->default('abonne');
            $table->string('consent_source', 255);
            $table->timestamp('consented_at');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['list_id', 'contact_id']);
            $table->index(['list_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_subscriptions');
        Schema::dropIfExists('pm_newsletter_lists');
        Schema::dropIfExists('pm_segments');
    }
};
