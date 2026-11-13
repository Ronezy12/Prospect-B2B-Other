<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mode simple (écrans réduits à l'essentiel), activé par défaut pour tout le monde.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('simple_mode')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('simple_mode');
        });
    }
};
