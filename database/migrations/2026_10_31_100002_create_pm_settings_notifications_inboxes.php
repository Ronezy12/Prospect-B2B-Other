<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 190);
            $table->text('body')->nullable();
            $table->string('url', 255)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'read_at']);
        });

        // Boîtes de réception consultées en IMAP pour détecter les réponses.
        Schema::create('pm_inboxes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('settings');
            $table->string('folder', 120)->default('INBOX');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('last_uid')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_result', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_inboxes');
        Schema::dropIfExists('pm_notifications');
        Schema::dropIfExists('pm_settings');
    }
};
