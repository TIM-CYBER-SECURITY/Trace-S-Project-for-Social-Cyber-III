<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('information_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_code', 24)->unique();                // INF-2026-000001
            $table->string('title');                                    // snapshot versi terkini
            $table->text('description')->nullable();
            $table->string('category', 40)->index();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('current_version')->default(1);
            $table->unsignedInteger('approved_version')->nullable();    // versi terakhir yang disetujui
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->boolean('is_simulation')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('record_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('information_records')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 40);
            $table->longText('content');
            $table->foreignId('changed_by')->constrained('users');
            $table->string('change_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['record_id', 'version_number']);
        });

        Schema::create('record_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('information_records')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('reviewer_id')->constrained('users');
            $table->string('decision', 20);                             // UNDER REVIEW | APPROVED | REJECTED | REVISION REQUIRED
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('message');
            $table->string('notification_type', 30)->index();           // REGISTRATION | ACCOUNT | SUBMISSION | SKILL | SECURITY | SYSTEM
            $table->string('link', 200)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['recipient_id', 'read_at']);
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('skill_key', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('version', 20);
            $table->string('status', 10)->default('DISABLED');          // ENABLED | DISABLED
            $table->string('installation_status', 14)->default('INSTALLED'); // INSTALLED | REMOVED
            $table->string('tool', 60);                                 // tool pada allowlist ToolRegistry
            $table->json('input_schema')->nullable();
            $table->json('configuration')->nullable();                  // mis. max_input_chars
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('user_skill_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->primary(['user_id', 'skill_id']);
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('skill_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('status', 10);                               // SUCCESS | FAILED
            $table->json('input')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['skill_executions', 'app_settings', 'user_skill_permissions', 'skills', 'app_notifications',
                  'record_reviews', 'record_versions', 'information_records'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
