<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Relances automatiques « interrupteur » : rattache un scénario au modèle dont il est issu.
        Schema::table('pm_automations', function (Blueprint $table) {
            $table->string('preset', 40)->nullable()->index()->after('trigger');
        });
    }

    public function down(): void
    {
        Schema::table('pm_automations', function (Blueprint $table) {
            $table->dropColumn('preset');
        });
    }
};
