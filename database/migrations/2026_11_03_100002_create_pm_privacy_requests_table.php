<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registre des demandes d'exercice des droits (RGPD art. 15 à 21) : délai d'un mois.
        // L'adresse n'est conservée que sous forme d'empreinte HMAC.
        Schema::create('pm_privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->char('email_hash', 64)->nullable()->index();
            $table->string('requester_label', 120)->nullable();
            $table->string('channel', 30)->nullable();
            $table->date('received_at');
            $table->date('due_at');
            $table->string('status', 20)->default('ouverte');
            $table->text('note')->nullable();
            $table->text('outcome')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_privacy_requests');
    }
};
