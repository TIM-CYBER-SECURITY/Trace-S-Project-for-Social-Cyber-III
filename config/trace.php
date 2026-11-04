<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Trace-S
|--------------------------------------------------------------------------
| Seluruh nilai yang berubah antar-lingkungan dibaca dari .env. Jangan
| menanamkan IP, token, kata sandi, atau nama model sebagai konfigurasi produksi.
*/

return [

    'name' => 'TRACE-S',
    'tagline' => 'COGNITIVE DEFENSE INITIATIVE',
    'classification_label' => env('TRACE_CLASSIFICATION_LABEL', 'PROTOTIPE - TIDAK UNTUK DISTRIBUSI'),

    // ------------------------------------------------------------------
    // RBAC: kemampuan => peran yang diizinkan (Supplemental §3)
    // ------------------------------------------------------------------
    'abilities' => [
        'dashboard.view'   => ['operator', 'analis', 'komandan', 'admin'],
        'case.view'        => ['operator', 'analis', 'komandan', 'admin'],
        'case.write'       => ['operator', 'analis'],
        'evidence.import'  => ['operator', 'analis'],
        'analysis.run'     => ['operator', 'analis'],
        'analysis.review'  => ['analis'],
        'spread.view'      => ['operator', 'analis', 'komandan'],
        'report.view'      => ['operator', 'analis', 'komandan'],
        'report.generate'  => ['operator', 'analis'],
        'report.export'    => ['analis', 'komandan'],
        'console.use'      => ['operator', 'analis', 'komandan'],
        'connector.use'    => ['operator', 'analis'],
        'connector.manage' => ['admin'],
        'ai.manage'        => ['admin'],
        'audit.view'       => ['admin'],
        'users.manage'     => ['admin'],
    ],

    // ------------------------------------------------------------------
    // AI Server: local-first, model-agnostic (Master §8, §14)
    // ------------------------------------------------------------------
    'ai' => [
        'driver'          => env('AI_DRIVER', 'mock'),           // mock | ollama | openai_compatible | external
        'base_url'        => env('AI_BASE_URL'),
        'model'           => env('AI_MODEL'),
        'embedding_model' => env('AI_EMBEDDING_MODEL'),
        'timeout'         => (int) env('AI_TIMEOUT', 180),
        'require_local'   => filter_var(env('AI_REQUIRE_LOCAL', true), FILTER_VALIDATE_BOOL),
        'allow_external'  => filter_var(env('AI_ALLOW_EXTERNAL', false), FILTER_VALIDATE_BOOL),
        'external_api_key' => env('AI_EXTERNAL_API_KEY'),         // hanya jika admin mengaktifkan ExternalDriver
        'api_key'         => env('AI_API_KEY'),                   // opsional, autentikasi layanan ke AI Server internal
        'max_retries'     => (int) env('AI_MAX_RETRIES', 2),      // retry terbatas untuk JSON tidak valid
        'max_input_chars' => (int) env('AI_MAX_INPUT_CHARS', 12000),
        'max_output_chars' => (int) env('AI_MAX_OUTPUT_CHARS', 20000),
        // Hanya untuk MockDriver: ok | offline | invalid_json | timeout | busy
        'mock_mode'       => env('AI_MOCK_MODE', 'ok'),
    ],

    // sync = langsung dijalankan, queue = masuk antrean database (php artisan queue:work)
    'analysis_mode' => env('TRACE_ANALYSIS_MODE', 'sync'),

    // ------------------------------------------------------------------
    // Pengambilan URL (Manual Evidence Import adalah P0, URL Fetch P0)
    // ------------------------------------------------------------------
    'fetch' => [
        'timeout'        => (int) env('TRACE_FETCH_TIMEOUT', 12),
        'max_bytes'      => (int) env('TRACE_FETCH_MAX_BYTES', 2_000_000),
        'max_redirects'  => 3,
        'allow_private'  => filter_var(env('TRACE_FETCH_ALLOW_PRIVATE', false), FILTER_VALIDATE_BOOL),
        'user_agent'     => 'Trace-S/1.0 (evidence-fetch; intranet)',
    ],

    'upload' => [
        'max_kb' => (int) env('TRACE_UPLOAD_MAX_KB', 5120),
        'mimes'  => ['txt', 'pdf', 'png', 'jpg', 'jpeg', 'md', 'html'],
        'disk'   => 'evidence',
    ],

    // ------------------------------------------------------------------
    // Metode analisis (nilai kemiripan selalu menyertakan metode + versi)
    // ------------------------------------------------------------------
    'analysis' => [
        'lexical_method'   => 'TF-IDF cosine (unigram+bigram, stopword ID)',
        'lexical_version'  => '1.0',
        'claim_method'     => 'Rule-based claim matcher (status/cakupan/tindakan/angka/entitas)',
        'claim_version'    => '1.0',
        'cluster_method'   => 'Single-link clustering (0.4 lexical + 0.4 entity + 0.2 semantic bila tersedia)',
        'cluster_version'  => '1.0',
        'cluster_threshold' => (float) env('TRACE_CLUSTER_THRESHOLD', 30),
        'near_duplicate'   => 80,        // ambang near-duplicate / SYNDICATED
        'coordination_window_minutes' => 60,
    ],

    // ------------------------------------------------------------------
    // Enumerasi yang divalidasi backend (Master §10, Supplemental §11)
    // ------------------------------------------------------------------
    'enums' => [
        'claim_type'          => ['FAKTA', 'OPINI', 'DUGAAN', 'KUTIPAN'],
        'check_status'        => ['TERVERIFIKASI', 'TERBANTAHKAN', 'BELUM TERVERIFIKASI', 'TIDAK CUKUP BUKTI'],
        'content_indicator'   => ['ASLI', 'HASIL EDIT', 'MISLEADING', 'INDIKASI HOAKS', 'BELUM DAPAT DIPASTIKAN'],
        'classification'      => ['VERIFIED', 'UNVERIFIED', 'MISLEADING', 'MANIPULATED_CONTENT', 'DISINFORMATION', 'PROPAGANDA'],
        'threat_level'        => ['RENDAH', 'SEDANG', 'TINGGI', 'KRITIS'],
        'finding_type'        => ['FAKTA', 'KLAIM_SUMBER', 'INTERPRETASI_AI', 'REKOMENDASI'],
        'access_status'       => ['OK', 'GAGAL', 'TIDAK TERSEDIA'],
        'validation_status'   => ['PENDING REVIEW', 'VALIDATED', 'REJECTED', 'RECLASSIFIED'],
        'module'              => ['credibility_scan', 'authenticity_compare', 'spread_analysis'],
        'cross_source_status' => [
            'CONSISTENT_WITH_PRIMARY_EVIDENCE', 'STRONG_CROSS_SOURCE_SUPPORT', 'PARTIAL_CROSS_SOURCE_SUPPORT',
            'CONFLICTING_SOURCES', 'INSUFFICIENT_SOURCE_EVIDENCE', 'UNVERIFIED_SOURCE_RELATIONSHIP',
        ],
        'relationship'        => ['INDEPENDENT_REPORTING', 'COMMON_PRIMARY_SOURCE', 'SYNDICATED_REPUBLISHED', 'SECONDARY_CITATION', 'RELATIONSHIP_UNKNOWN'],
        'authenticity'        => ['VERIFIED', 'UNVERIFIED', 'UNKNOWN'],
        'claim_match'         => ['MATCH', 'PARTIAL_MATCH', 'CONFLICT', 'UNKNOWN'],
        'numeric_consistency' => ['CONSISTENT', 'INCONSISTENT', 'NOT_APPLICABLE'],
        'context_consistency' => ['CONSISTENT', 'INCONSISTENT', 'UNKNOWN'],
        'change_type'         => ['ADDED', 'REMOVED', 'ALTERED', 'UNCHANGED'],
        'coordination'        => ['NOT_ASSESSED', 'INDICATORS_PRESENT', 'INSUFFICIENT_EVIDENCE'],
    ],

    // Titik peta Indonesia (persen dari kanvas) untuk Early Warning Dashboard
    'regions' => [
        'Medan'      => ['x' => 14, 'y' => 30],
        'Jakarta'    => ['x' => 33, 'y' => 63],
        'Surabaya'   => ['x' => 46, 'y' => 68],
        'Balikpapan' => ['x' => 55, 'y' => 45],
        'Makassar'   => ['x' => 61, 'y' => 62],
        'Jayapura'   => ['x' => 91, 'y' => 56],
    ],
];
