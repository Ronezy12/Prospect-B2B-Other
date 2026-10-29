<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Profils publics des prospects (URL saisie ou importée, jamais collectée automatiquement).
        Schema::create('pm_social_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('pm_companies')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('pm_contacts')->nullOnDelete();
            $table->string('platform', 20);
            $table->string('url', 255);
            $table->char('fingerprint', 64);
            $table->string('status', 30)->default('a_contacter');
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'fingerprint']);
            $table->index('fingerprint');
            $table->index(['status', 'next_action_at']);
        });

        // Actions réalisées par un commercial sur la plateforme (saisie manuelle) ou reçues par l'API officielle.
        Schema::create('pm_social_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('pm_social_profiles')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('pm_companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('origin', 20)->default('manuel');
            $table->text('body')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });

        // Opposition « ne plus me contacter » sur un réseau : empreinte HMAC du profil, conservée
        // même si la fiche est supprimée, pour qu'aucun import ou synchronisation ne le réintroduise.
        Schema::create('pm_social_oppositions', function (Blueprint $table) {
            $table->id();
            $table->char('fingerprint', 64)->unique();
            $table->string('platform', 20);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('pm_social_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('platform', 20)->nullable();
            $table->string('kind', 30);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Connexion officielle à une Page Facebook (Messenger) et au compte Instagram professionnel lié.
        Schema::create('pm_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('provider', 20)->default('meta');
            $table->text('settings');
            $table->string('webhook_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_result', 255)->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_social_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('pm_social_accounts')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('participant_id', 64);
            $table->string('participant_label', 150)->nullable();
            $table->foreignId('company_id')->nullable()->constrained('pm_companies')->nullOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained('pm_social_profiles')->nullOnDelete();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->boolean('unread')->default(false);
            $table->string('status', 20)->default('ouverte');
            $table->timestamps();

            $table->unique(['account_id', 'channel', 'participant_id']);
            $table->index(['status', 'last_message_at']);
        });

        Schema::create('pm_social_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('pm_social_conversations')->cascadeOnDelete();
            $table->string('direction', 10);
            $table->string('external_id', 190)->nullable()->unique();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('recu');
            $table->string('error', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_social_messages');
        Schema::dropIfExists('pm_social_conversations');
        Schema::dropIfExists('pm_social_accounts');
        Schema::dropIfExists('pm_social_templates');
        Schema::dropIfExists('pm_social_oppositions');
        Schema::dropIfExists('pm_social_interactions');
        Schema::dropIfExists('pm_social_profiles');
    }
};
