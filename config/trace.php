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
    'version' => '2.0.0',
    'tagline' => 'COGNITIVE DEFENSE INITIATIVE',
    'classification_label' => env('TRACE_CLASSIFICATION_LABEL', 'PROTOTIPE - TIDAK UNTUK DISTRIBUSI'),

    // ------------------------------------------------------------------
    // RBAC: kemampuan => peran yang diizinkan
    // ------------------------------------------------------------------
    // Dua peran operasional (ADMINISTRATOR, OPERATOR). Matriks ini adalah sumber
    // seeder RBAC; otorisasi aktual dibaca dari tabel roles/permissions (deny-by-default).
    'roles' => [
        'ADMINISTRATOR' => 'Mengelola aplikasi, akun Operator, Skills, konfigurasi, dan review informasi.',
        'OPERATOR'      => 'Menggunakan aplikasi dan mengajukan informasi dalam lingkup yang diizinkan.',
    ],

    'permissions' => [
        // key => [deskripsi, [peran]]
        'dashboard.view'     => ['Melihat dashboard intelijen', ['ADMINISTRATOR', 'OPERATOR']],
        'case.view'          => ['Melihat arsip kasus', ['ADMINISTRATOR', 'OPERATOR']],
        'case.write'         => ['Membuat/mengubah kasus', ['ADMINISTRATOR', 'OPERATOR']],
        'evidence.import'    => ['Mengimpor bukti', ['ADMINISTRATOR', 'OPERATOR']],
        'analysis.run'       => ['Menjalankan analisis', ['ADMINISTRATOR', 'OPERATOR']],
        'analysis.review'    => ['Memvalidasi hasil analisis (VALIDATE/REJECT/RECLASSIFY)', ['ADMINISTRATOR']],
        'spread.view'        => ['Melihat jaringan sebaran', ['ADMINISTRATOR', 'OPERATOR']],
        'report.view'        => ['Melihat laporan', ['ADMINISTRATOR', 'OPERATOR']],
        'report.generate'    => ['Membuat laporan', ['ADMINISTRATOR', 'OPERATOR']],
        'report.export'      => ['Mengekspor laporan PDF', ['ADMINISTRATOR']],
        'console.use'        => ['Menggunakan konsol analis AI', ['ADMINISTRATOR', 'OPERATOR']],
        'connector.use'      => ['Menggunakan konektor data', ['ADMINISTRATOR', 'OPERATOR']],
        'connector.manage'   => ['Mengelola konektor', ['ADMINISTRATOR']],
        'ai.manage'          => ['Mengelola AI server/model lokal', ['ADMINISTRATOR']],

        'info.view'          => ['Melihat informasi yang diizinkan', ['ADMINISTRATOR', 'OPERATOR']],
        'info.submit'        => ['Membuat dan mengajukan informasi', ['ADMINISTRATOR', 'OPERATOR']],
        'info.update'        => ['Memperbarui informasi yang diizinkan', ['ADMINISTRATOR', 'OPERATOR']],
        'info.review'        => ['Mereview pengajuan informasi', ['ADMINISTRATOR']],
        'info.approve'       => ['Menyetujui/menolak pengajuan informasi', ['ADMINISTRATOR']],
        'info.delete'        => ['Mengarsipkan/menghapus (soft delete) rekaman terlindungi', ['ADMINISTRATOR']],
        'history.view_own'   => ['Melihat riwayat aktivitas pribadi', ['ADMINISTRATOR', 'OPERATOR']],

        'accounts.manage'    => ['Mengelola akun Operator', ['ADMINISTRATOR']],
        'trace_id.manage'    => ['Mengelola rekaman Trace-ID', ['ADMINISTRATOR']],

        'skills.use'         => ['Menjalankan Skills yang diizinkan', ['ADMINISTRATOR', 'OPERATOR']],
        'skills.install'     => ['Memasang/menghapus Skills', ['ADMINISTRATOR']],
        'skills.configure'   => ['Mengonfigurasi dan menugaskan Skills', ['ADMINISTRATOR']],

        'system.configure'   => ['Mengubah konfigurasi sistem', ['ADMINISTRATOR']],
        'system.health'      => ['Melihat kesehatan sistem', ['ADMINISTRATOR']],
        'audit.view'         => ['Melihat audit log penuh', ['ADMINISTRATOR']],
    ],

    'record_categories' => [
        'narrative_observation' => 'Observasi Narasi',
        'source_assessment'     => 'Penilaian Sumber',
        'situational_note'      => 'Catatan Situasional',
        'general'               => 'Umum',
    ],

    // ------------------------------------------------------------------
    // Registry Trace-ID eksternal (di luar kode aplikasi & web root)
    // ------------------------------------------------------------------
    'provisioning' => [
        'path'         => env('TRACE_PROVISIONING_PATH', base_path('provisioning')),
        'registry'     => env('TRACE_REGISTRY_PATH'),              // default: {path}/registry
        'issued_path'  => env('TRACE_ISSUED_PATH'),                // default: {path}/issued
        'password_length' => 16,
    ],

    'security' => [
        'login_max_attempts'   => (int) env('TRACE_LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_seconds'  => (int) env('TRACE_LOGIN_DECAY', 300),
        'password_min_length'  => 12,
        'idle_timeout_minutes' => (int) env('SESSION_LIFETIME', 120),
    ],

    // ------------------------------------------------------------------
    // AI Server: Hybrid Adaptive (Cloud Google AI Studio / Exa.ai -> Auto-Failover Local)
    // ------------------------------------------------------------------
    'ai' => [
        'driver'            => env('AI_DRIVER', 'mock'),           // mock | ollama | openai_compatible | external
        'base_url'          => env('AI_BASE_URL', env('AI_SERVER_URL')),
        'model'             => env('AI_MODEL', env('AI_SERVER_MODEL')),
        'embedding_model'   => env('AI_EMBEDDING_MODEL'),
        'timeout'           => (int) env('AI_TIMEOUT', env('AI_REQUEST_TIMEOUT', 180)),
        'require_local'     => filter_var(env('AI_REQUIRE_LOCAL', true), FILTER_VALIDATE_BOOL),
        'allow_external'    => filter_var(env('AI_ALLOW_EXTERNAL', false), FILTER_VALIDATE_BOOL),
        'external_api_key'  => env('AI_EXTERNAL_API_KEY'),
        'api_key'           => env('AI_API_KEY', env('AI_SERVER_API_KEY')),
        'max_retries'       => (int) env('AI_MAX_RETRIES', 2),
        'max_input_chars'   => (int) env('AI_MAX_INPUT_CHARS', 12000),
        'max_output_chars'  => (int) env('AI_MAX_OUTPUT_CHARS', 20000),
        'mock_mode'         => env('AI_MOCK_MODE', 'ok'),

        // Hybrid Cloud API + Auto-Failover ke AI Lokal
        'engine_mode'       => env('AI_ENGINE_MODE', 'HYBRID_AUTO'), // HYBRID_AUTO | FORCE_LOCAL | FORCE_CLOUD
        'cloud_provider'    => env('AI_CLOUD_PROVIDER', 'google_ai_studio'), // google_ai_studio | exa_ai
        'cloud_timeout'     => (int) env('AI_CLOUD_TIMEOUT', 10),
        'google_api_key'    => env('GOOGLE_AI_STUDIO_API_KEY'),
        'google_model'      => env('GOOGLE_AI_STUDIO_MODEL', 'gemini-2.0-flash'),
        'google_base_url'   => env('GOOGLE_AI_STUDIO_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'exa_api_key'       => env('EXA_API_KEY'),
        'exa_model'         => env('EXA_AI_MODEL', 'exa'),
        'exa_base_url'      => env('EXA_AI_BASE_URL', 'https://api.exa.ai'),
        'exa_search_enabled' => filter_var(env('EXA_SEARCH_ENABLED', true), FILTER_VALIDATE_BOOL),
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
    // Enumerasi yang divalidasi backend
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
];
