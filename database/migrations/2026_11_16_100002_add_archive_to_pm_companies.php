<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Archivage d'une fiche : mise de côté réversible (cycle « archive »), distincte de la suppression.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_companies', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archived_from', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pm_companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn(['archived_at', 'archived_from']);
        });
    }
};
