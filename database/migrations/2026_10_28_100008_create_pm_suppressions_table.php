<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Liste d'opposition centrale. Seule une empreinte HMAC de l'adresse est
        // conservée : elle survit à l'effacement des fiches et empêche toute
        // réintroduction par import, scénario ou synchronisation.
        Schema::create('pm_suppressions', function (Blueprint $table) {
            $table->id();
            $table->char('email_hash', 64);
            $table->string('scope', 20)->default('all');
            $table->string('reason', 40);
            $table->string('origin', 40);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['email_hash', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_suppressions');
    }
};
