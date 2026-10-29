<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            // individuel | prospection | newsletter | operationnel
            $table->string('type', 15);
            // brouillon | programmee | en_cours | en_pause | terminee | annulee
            $table->string('status', 15)->default('brouillon');
            $table->foreignId('template_id')->nullable()->constrained('pm_templates')->nullOnDelete();
            // Version figée au lancement : le contenu envoyé ne change plus.
            $table->foreignId('template_version_id')->nullable()->constrained('pm_template_versions')->restrictOnDelete();
            $table->foreignId('mail_account_id')->nullable()->constrained('pm_mail_accounts')->restrictOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('pm_segments')->nullOnDelete();
            $table->foreignId('list_id')->nullable()->constrained('pm_newsletter_lists')->nullOnDelete();
            $table->json('audience')->nullable();
            $table->json('exclusions')->nullable();
            $table->string('from_name', 120)->nullable();
            $table->string('reply_to', 190)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_test_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('launched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('pm_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('pm_campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('pm_contacts')->nullOnDelete();
            $table->string('email', 190);
            $table->json('merge_data')->nullable();
            // en_attente | envoi | accepte | echec | exclu | annule | a_verifier
            $table->string('status', 15)->default('en_attente');
            $table->string('exclusion_reason', 60)->nullable();
            $table->char('idempotency_key', 64)->unique();
            $table->string('provider_message_id', 190)->nullable()->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'contact_id']);
            $table->index(['campaign_id', 'status']);
        });

        Schema::create('pm_email_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->nullable()->constrained('pm_campaign_recipients')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('pm_campaigns')->nullOnDelete();
            $table->string('provider', 20);
            $table->string('provider_event_id', 190)->nullable();
            // demande | accepte | echec_temporaire | echec | desabonnement | (phase 7 : livre, ouvert, clic, rebond…)
            $table->string('type', 30);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'provider_event_id']);
            $table->index(['campaign_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_email_events');
        Schema::dropIfExists('pm_campaign_recipients');
        Schema::dropIfExists('pm_campaigns');
    }
};
