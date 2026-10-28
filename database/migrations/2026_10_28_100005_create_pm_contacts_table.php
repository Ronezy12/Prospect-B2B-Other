<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('pm_companies')->nullOnDelete();
            $table->string('civility', 10)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('job_title', 150)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('email_normalized', 190)->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->string('linkedin_url', 255)->nullable();
            // Conformité : typologie de la cible, base légale, origine de la donnée.
            $table->string('audience_type', 10)->default('b2b');
            $table->string('legal_basis', 30)->default('a_determiner');
            $table->foreignId('source_id')->nullable()->constrained('pm_sources')->restrictOnDelete();
            $table->date('collected_at')->nullable();
            $table->string('collection_note', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_contacts');
    }
};
