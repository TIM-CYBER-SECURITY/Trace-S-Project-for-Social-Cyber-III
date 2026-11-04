<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_code', 30)->unique();          // TS-2026-001
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('claim_text')->nullable();              // narasi / klaim yang diperiksa
            $table->string('category', 60)->nullable();
            $table->string('priority', 10)->default('SEDANG');   // RENDAH|SEDANG|TINGGI|KRITIS
            $table->string('status', 20)->default('OPEN');       // OPEN|IN_REVIEW|CLOSED
            $table->text('operator_notes')->nullable();
            $table->boolean('is_simulation')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('cases')->cascadeOnDelete();
            $table->string('source_code', 20);                    // S-001 (unik per kasus)
            $table->text('url')->nullable();
            $table->string('publisher')->nullable();
            $table->string('platform', 40)->nullable();
            $table->string('account_handle')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('retrieved_at')->nullable();
            // OK | GAGAL | TIDAK TERSEDIA | BELUM DIPERIKSA
            $table->string('access_status', 20)->default('BELUM DIPERIKSA');
            // Keaslian sumber dinilai terpisah dari kebenaran klaim: VERIFIED | UNVERIFIED | UNKNOWN
            $table->string('authenticity', 12)->default('UNKNOWN');
            $table->boolean('is_simulation')->default(false);
            $table->timestamps();
            $table->unique(['case_id', 'source_code']);
        });

        Schema::create('evidence_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->string('evidence_code', 20);                  // E-001 (unik per kasus)
            $table->string('type', 20)->default('TEXT');          // TEXT|URL|IMAGE|DOCUMENT|SCREENSHOT
            $table->string('role', 20)->default('OTHER');         // REFERENCE|PRIMARY|COMPARED|POST|OTHER
            $table->string('title')->nullable();
            $table->string('publisher')->nullable();
            $table->string('platform', 40)->nullable();
            $table->text('url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('retrieved_at')->nullable();
            $table->string('access_status', 20)->default('BELUM DIPERIKSA');
            $table->longText('extracted_text')->nullable();       // teks asli, tidak diparafrasa
            $table->string('raw_path')->nullable();               // berkas asli (disk lokal privat)
            $table->string('content_hash', 64)->nullable();       // SHA-256
            $table->string('collection_method', 40)->nullable();  // MANUAL_IMPORT|URL_FETCH|RSS|SIMULASI
            $table->text('collection_notes')->nullable();
            $table->json('engagement')->nullable();               // hanya yang benar-benar tersedia
            $table->boolean('is_simulation')->default(false);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['case_id', 'evidence_code']);
            $table->index('content_hash');
        });

        Schema::create('threat_analyses', function (Blueprint $table) {
            $table->id();
            $table->uuid('analysis_uuid')->unique();
            $table->foreignId('case_id')->nullable()->constrained('cases')->cascadeOnDelete();
            $table->string('module', 30);                         // credibility_scan|authenticity_compare|spread_analysis
            $table->longText('source_text')->nullable();
            $table->json('result');
            $table->unsignedTinyInteger('credibility_score')->nullable();
            $table->string('classification', 30)->nullable();
            $table->string('verification_status', 30)->nullable();
            $table->string('threat_level', 10)->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->string('model_name')->nullable();
            $table->string('model_version')->nullable();
            $table->string('driver', 30)->nullable();
            $table->boolean('is_simulation')->default(false);
            $table->string('validation_status', 20)->default('PENDING REVIEW');
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
            $table->index(['case_id', 'module']);
        });

        Schema::create('analyst_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained('threat_analyses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('decision', 20);                       // VALIDATED|REJECTED|RECLASSIFIED
            $table->text('reason')->nullable();
            $table->json('previous_result')->nullable();
            $table->json('final_result')->nullable();
            $table->timestamps();
        });

        Schema::create('evidence_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('reference_evidence_id')->constrained('evidence_items')->cascadeOnDelete();
            $table->foreignId('compared_evidence_id')->constrained('evidence_items')->cascadeOnDelete();
            $table->foreignId('analysis_id')->nullable()->constrained('threat_analyses')->nullOnDelete();
            $table->json('diff_json');
            $table->text('assessment')->nullable();
            $table->boolean('is_simulation')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('similarity_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('evidence_a_id')->constrained('evidence_items')->cascadeOnDelete();
            $table->foreignId('evidence_b_id')->constrained('evidence_items')->cascadeOnDelete();
            // LEXICAL|SEMANTIC|CLAIM_LEVEL|NUMERIC_ENTITY|CONTEXTUAL
            $table->string('comparison_type', 20);
            $table->string('method', 80);
            $table->string('model_name')->nullable();
            $table->string('model_version', 40)->nullable();
            $table->decimal('score', 5, 2)->nullable();            // 0-100, hanya nilai kemiripan
            $table->json('result_json')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->boolean('is_simulation')->default(false);
            $table->timestamps();
            $table->index(['case_id', 'comparison_type']);
        });

        Schema::create('claim_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('source_a_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('source_b_id')->constrained('sources')->cascadeOnDelete();
            $table->text('claim_a');
            $table->text('claim_b');
            $table->string('match_status', 15);                    // MATCH|PARTIAL_MATCH|CONFLICT|UNKNOWN
            $table->string('similarity_method', 80)->nullable();
            $table->decimal('similarity_score', 5, 2)->nullable();
            $table->json('result_json')->nullable();
            $table->timestamps();
        });

        Schema::create('source_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('source_a_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('source_b_id')->nullable()->constrained('sources')->nullOnDelete();
            // INDEPENDENT_REPORTING|COMMON_PRIMARY_SOURCE|SYNDICATED_REPUBLISHED|SECONDARY_CITATION|RELATIONSHIP_UNKNOWN
            $table->string('relationship_type', 30)->default('RELATIONSHIP_UNKNOWN');
            $table->foreignId('supporting_evidence_id')->nullable()->constrained('evidence_items')->nullOnDelete();
            $table->text('assessment')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['source_relationships', 'claim_comparisons', 'similarity_analyses', 'evidence_comparisons',
                  'analyst_reviews', 'threat_analyses', 'evidence_items', 'sources', 'cases'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
