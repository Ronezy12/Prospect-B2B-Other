<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Résultat de livraison, distinct du statut d'envoi : « accepté » ≠ « livré ».
        Schema::table('pm_campaign_recipients', function (Blueprint $table) {
            $table->string('delivery_status', 20)->nullable()->after('status')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
        });

        Schema::table('pm_campaigns', function (Blueprint $table) {
            $table->boolean('track_opens')->default(false)->after('reply_to');
            $table->boolean('track_clicks')->default(false)->after('track_opens');
        });

        // Consentement à la mesure d'audience des e-mails (recommandation CNIL 2026).
        Schema::table('pm_contacts', function (Blueprint $table) {
            $table->timestamp('tracking_consent_at')->nullable()->after('collection_note');
            $table->string('tracking_consent_source', 255)->nullable()->after('tracking_consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('pm_contacts', fn (Blueprint $t) => $t->dropColumn(['tracking_consent_at', 'tracking_consent_source']));
        Schema::table('pm_campaigns', fn (Blueprint $t) => $t->dropColumn(['track_opens', 'track_clicks']));
        Schema::table('pm_campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['delivery_status']);
            $table->dropColumn(['delivery_status', 'delivered_at', 'bounced_at', 'opened_at', 'open_count', 'clicked_at', 'click_count', 'replied_at', 'unsubscribed_at']);
        });
    }
};
