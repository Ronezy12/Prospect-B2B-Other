<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name', 255);
            $table->string('stored_path', 255)->nullable();
            $table->string('file_type', 10);
            $table->char('file_hash', 64);
            $table->json('headers')->nullable();
            $table->json('mapping')->nullable();
            $table->json('options')->nullable();
            // uploaded → mapped → queued → processing → completed | failed | cancelled
            $table->string('status', 20)->default('uploaded');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->string('failure_message', 255)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('pm_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('pm_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            // created | updated | skipped_duplicate | skipped_suppressed | error
            $table->string('result', 30);
            $table->json('messages')->nullable();
            $table->foreignId('company_id')->nullable()->constrained('pm_companies')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('pm_contacts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['import_id', 'row_number']);
            $table->index(['import_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_import_rows');
        Schema::dropIfExists('pm_imports');
    }
};
