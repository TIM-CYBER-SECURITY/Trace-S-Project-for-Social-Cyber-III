<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('narrative_clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('cases')->cascadeOnDelete();
            $table->string('cluster_code', 20);                    // NC-2026-001
            $table->string('title');
            $table->text('main_claim')->nullable();
            // NOT_ASSESSED|INDICATORS_PRESENT|INSUFFICIENT_EVIDENCE
            $table->string('coordination_assessment', 24)->default('NOT_ASSESSED');
            $table->string('analyst_status', 20)->default('PENDING REVIEW');
            $table->string('method', 120)->nullable();
            $table->timestamp('earliest_observed_at')->nullable();
            $table->boolean('is_simulation')->default(false);
            $table->timestamps();
            $table->unique(['case_id', 'cluster_code']);
        });

        Schema::create('narrative_cluster_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('narrative_clusters')->cascadeOnDelete();
            $table->foreignId('evidence_id')->constrained('evidence_items')->cascadeOnDelete();
            $table->foreignId('main_claim_id')->nullable()->constrained('evidence_items')->nullOnDelete();
            $table->decimal('similarity_score', 5, 2)->nullable();
            $table->string('matching_status', 15)->default('UNKNOWN'); // MATCH|PARTIAL_MATCH|CONFLICT|UNKNOWN
            $table->json('differences')->nullable();                // status, waktu, cakupan, angka, dst.
            $table->timestamps();
            $table->unique(['cluster_id', 'evidence_id']);
        });

        Schema::create('narratives', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 60)->nullable();
            $table->string('region', 60)->nullable();
            $table->string('sentiment', 12)->nullable();            // NEGATIF|NETRAL|POSITIF
            $table->string('severity', 10)->default('RENDAH');      // RENDAH|SEDANG|TINGGI|KRITIS
            $table->timestamp('captured_at')->nullable();
            $table->foreignId('cluster_id')->nullable()->constrained('narrative_clusters')->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->boolean('is_simulation')->default(false);
            $table->timestamps();
        });

        Schema::create('threat_actors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('cases')->cascadeOnDelete();
            $table->string('platform', 40);
            $table->string('username');
            // Bukan "bot probability": indikator otomatisasi yang dapat dijelaskan
            $table->string('automation_indicator', 14)->default('TIDAK DINILAI'); // TIDAK DINILAI|RENDAH|SEDANG|TINGGI
            $table->text('indicator_basis')->nullable();
            $table->decimal('post_rate', 8, 2)->nullable();         // posting / jam
            $table->unsignedInteger('account_age_days')->nullable();
            $table->foreignId('cluster_id')->nullable()->constrained('narrative_clusters')->nullOnDelete();
            $table->string('status', 20)->default('PERLU DITINJAU');
            $table->timestamp('first_seen_at')->nullable();
            $table->boolean('is_earliest_in_dataset')->default(false);
            $table->boolean('is_simulation')->default(false);
            $table->timestamps();
        });

        Schema::create('actor_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_a_id')->constrained('threat_actors')->cascadeOnDelete();
            $table->foreignId('actor_b_id')->constrained('threat_actors')->cascadeOnDelete();
            $table->string('link_type', 30)->default('SIMILAR_CONTENT'); // SIMILAR_CONTENT|TIME_PROXIMITY|REPOST
            $table->decimal('weight', 5, 2)->nullable();
            $table->text('basis')->nullable();
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('report_code', 30)->unique();
            $table->string('title');
            $table->string('classification_label', 40)->default('PROTOTIPE - TIDAK UNTUK DISTRIBUSI');
            $table->json('content');
            $table->string('status', 20)->default('DRAFT');
            $table->boolean('is_simulation')->default(false);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('console_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('console_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('console_sessions')->cascadeOnDelete();
            $table->string('role', 12);                              // user|assistant|system
            $table->longText('content');
            $table->json('sources')->nullable();
            $table->json('tool_calls')->nullable();
            $table->boolean('is_simulation')->default(false);
            $table->timestamps();
        });

        Schema::create('ai_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver', 30);                            // mock|ollama|openai_compatible|external
            $table->string('base_url')->nullable();
            $table->string('status', 12)->default('OFFLINE');        // ONLINE|OFFLINE|ERROR|BUSY|SIMULASI
            $table->string('runtime')->nullable();
            $table->string('runtime_version', 40)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->unsignedInteger('queue_length')->default(0);
            $table->text('last_error')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('ai_servers')->cascadeOnDelete();
            $table->string('name');
            $table->string('profile', 12)->default('BASIC');         // ENTRY|BASIC|STANDARD|ADVANCED|MULTIMODAL
            $table->string('size', 30)->nullable();
            $table->string('quantization', 30)->nullable();
            $table->unsignedInteger('context_length')->nullable();
            $table->string('status', 14)->default('NOT INSTALLED');  // READY|LOADING|NOT INSTALLED|ERROR
            $table->boolean('is_active')->default(false);
            $table->boolean('is_simulation')->default(false);
            $table->timestamps();
        });

        Schema::create('ai_model_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->string('previous_model')->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->text('reason');
            $table->timestamps();
        });

        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('job_uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->string('type', 30);
            $table->json('payload')->nullable();
            $table->string('status', 10)->default('QUEUED');         // QUEUED|RUNNING|DONE|FAILED
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->foreignId('analysis_id')->nullable()->constrained('threat_analyses')->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('connectors', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('name');
            $table->string('priority', 2)->default('P0');
            $table->string('status', 16)->default('NOT AVAILABLE');  // ACTIVE|NOT CONFIGURED|NOT AVAILABLE|SIMULASI
            $table->text('description')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('rss_feeds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('feed_url');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('feed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_id')->constrained('rss_feeds')->cascadeOnDelete();
            $table->string('title');
            $table->text('link')->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('guid_hash', 64)->unique();
            $table->timestamps();
        });

        // Audit log: append-only (dijaga di level model)
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('target_type', 60)->nullable();
            $table->string('target_id', 60)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['system_logs', 'feed_items', 'rss_feeds', 'connectors', 'ai_jobs', 'ai_model_changes', 'ai_models',
                  'ai_servers', 'console_messages', 'console_sessions', 'reports', 'actor_links', 'threat_actors',
                  'narratives', 'narrative_cluster_items', 'narrative_clusters'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
