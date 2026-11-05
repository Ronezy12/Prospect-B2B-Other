<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Signature fournie en HTML (fichier glissé, code ou signature copiée depuis Gmail), stockée nettoyée.
        Schema::table('pm_mail_accounts', function (Blueprint $table) {
            $table->mediumText('signature_html')->nullable()->after('signature');
        });
    }

    public function down(): void
    {
        Schema::table('pm_mail_accounts', function (Blueprint $table) {
            $table->dropColumn('signature_html');
        });
    }
};
