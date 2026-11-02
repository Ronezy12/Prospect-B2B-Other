<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Envoi réparti : comptes supplémentaires d'une campagne, et compte réellement
        // utilisé pour chaque destinataire (quotas comptés par compte).
        Schema::table('pm_campaigns', function (Blueprint $table) {
            $table->json('account_ids')->nullable()->after('mail_account_id');
        });
        Schema::table('pm_campaign_recipients', function (Blueprint $table) {
            $table->foreignId('mail_account_id')->nullable()->after('campaign_id')->constrained('pm_mail_accounts')->nullOnDelete();
            $table->index(['mail_account_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pm_campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['mail_account_id', 'sent_at']);
            $table->dropConstrainedForeignId('mail_account_id');
        });
        Schema::table('pm_campaigns', function (Blueprint $table) {
            $table->dropColumn('account_ids');
        });
    }
};
