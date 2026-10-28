<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('name_normalized', 190)->nullable()->index();
            // SIREN (9) ou SIRET (14) ; unicité vérifiée applicativement sur le SIREN.
            $table->string('registration_number', 14)->nullable();
            $table->string('siren', 9)->nullable()->index();
            $table->foreignId('sector_id')->nullable()->constrained('pm_sectors')->restrictOnDelete();
            $table->foreignId('subsector_id')->nullable()->constrained('pm_sectors')->restrictOnDelete();
            $table->string('website', 255)->nullable();
            $table->string('domain', 190)->nullable()->index();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('postal_code', 12)->nullable()->index();
            $table->string('city', 120)->nullable()->index();
            $table->string('country', 2)->default('FR');
            $table->foreignId('source_id')->nullable()->constrained('pm_sources')->restrictOnDelete();
            $table->date('acquired_at')->nullable();
            $table->string('status', 30)->default('nouveau');
            // Séparation logique prospects / clients : la conversion est une action explicite.
            $table->string('lifecycle', 20)->default('prospect');
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lifecycle', 'status']);
            $table->index(['owner_id', 'status']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_companies');
    }
};
