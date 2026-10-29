<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Comptes d'envoi (SMTP ou API). Les secrets sont chiffrés avec la clé applicative.
        Schema::create('pm_mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('driver', 20);
            $table->string('from_email', 190);
            $table->string('from_name', 120);
            $table->string('reply_to', 190)->nullable();
            $table->text('settings')->nullable();
            $table->unsignedInteger('hourly_limit')->default(200);
            $table->unsignedInteger('daily_limit')->default(1000);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_result', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_mail_accounts');
    }
};
