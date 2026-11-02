<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La signature configurée dans Gmail n'est pas ajoutée aux envois SMTP : chaque adresse a la sienne ici.
        Schema::table('pm_mail_accounts', function (Blueprint $table) {
            $table->text('signature')->nullable()->after('reply_to');
        });
    }

    public function down(): void
    {
        Schema::table('pm_mail_accounts', function (Blueprint $table) {
            $table->dropColumn('signature');
        });
    }
};
