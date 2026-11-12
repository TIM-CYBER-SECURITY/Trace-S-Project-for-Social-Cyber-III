<?php

namespace Database\Seeders;

use App\Models\ActorLink;
use App\Models\AiModel;
use App\Models\AiServer;
use App\Models\Connector;
use App\Models\EvidenceComparison;
use App\Models\EvidenceItem;
use App\Models\InformationRecord;
use App\Models\InvestigationCase;
use App\Models\Narrative;
use App\Models\NarrativeCluster;
use App\Models\NarrativeClusterItem;
use App\Models\Organization;
use App\Models\RecordVersion;
use App\Models\Report;
use App\Models\Role;
use App\Models\RssFeed;
use App\Models\Skill;
use App\Models\Source;
use App\Models\SourceRelationship;
use App\Models\ThreatActor;
use App\Models\ThreatAnalysis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Skills\SkillService;
use App\Services\Support\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Data referensi otorisasi (peran & izin). Bukan akun; aman untuk semua lingkungan.
        $this->call(RbacSeeder::class);

        // Fixture simulasi TIDAK PERNAH dimuat di produksi (tanpa akun/data demo).
        if (app()->environment('production')) {
            return;
        }

        $org = Organization::firstOrCreate(
            ['name' => 'Satuan Uji Simulasi (Non-Produksi)'],
            ['status' => 'ACTIVE']
        );
        $unit = Unit::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'Tim Simulasi Awal'],
            ['status' => 'ACTIVE']
        );

        // 1. Pemilik fixture simulasi: akun sistem yang tidak dapat login
        //    (status SYSTEM, kata sandi acak yang tidak pernah diungkap). Tidak ada kredensial demo.
        $operator = User::create([
            'name' => 'SIMULATION FIXTURE OWNER',
            'trace_id' => null,
            'password' => Str::random(64),
            'role_id' => Role::where('name', 'OPERATOR')->value('id'),
            'organization_id' => $org->id,
            'unit_id' => $unit->id,
            'position' => 'System Fixture Placeholder',
            'account_status' => 'SYSTEM',
            'approval_status' => 'APPROVED',
            'must_change_password' => true,
        ]);
        $analis = $komandan = $admin = $operator;

        // 2. AI Server & Model Registry
        $mockServer = AiServer::create([
            'name' => 'Internal AI Inference Engine (MockDriver)',
            'driver' => 'mock',
            'base_url' => null,
            'status' => 'SIMULASI',
            'runtime' => 'Trace-S Local Inference Host',
            'runtime_version' => '1.0-sim',
            'latency_ms' => 12,
            'is_active' => true,
            'last_checked_at' => now(),
        ]);

        $ollamaServer = AiServer::create([
            'name' => 'Local Ollama Node (Air-Gapped Intranet)',
            'driver' => 'ollama',
            'base_url' => 'http://127.0.0.1:11434',
            'status' => 'NOT CONFIGURED',
            'runtime' => 'Ollama Container',
            'runtime_version' => '0.5.1',
            'latency_ms' => null,
            'is_active' => false,
            'last_checked_at' => null,
        ]);

        AiModel::create([
            'server_id' => $mockServer->id,
            'name' => 'TraceS-Mock-IndoIntel-8B',
            'profile' => 'BASIC',
            'size' => '8.0 GB (Emulated)',
            'quantization' => 'Q4_K_M',
            'context_length' => 8192,
            'status' => 'READY',
            'is_active' => true,
            'is_simulation' => true,
        ]);

        AiModel::create([
            'server_id' => $ollamaServer->id,
            'name' => 'llama3.1:8b-instruct-q4_K_M',
            'profile' => 'BASIC',
            'size' => '4.7 GB',
            'quantization' => 'Q4_K_M',
            'context_length' => 16384,
            'status' => 'NOT INSTALLED',
            'is_active' => false,
            'is_simulation' => false,
        ]);

        // 3. Connectors (Jujur sesuai Master §4, §9)
        Connector::create(['key' => 'manual', 'name' => 'Manual Evidence Import', 'priority' => 'P0', 'status' => 'ACTIVE', 'description' => 'Impor teks, dokumen PDF/Word, dan berkas bukti digital langsung oleh operator.']);
        Connector::create(['key' => 'url_fetch', 'name' => 'URL Evidence Fetch', 'priority' => 'P0', 'status' => 'ACTIVE', 'description' => 'Pengambilan konten web terbuka secara sah dengan parsing teks dan sanitasi anti-injection.']);
        Connector::create(['key' => 'rss', 'name' => 'News RSS Feed Connector', 'priority' => 'P1', 'status' => 'ACTIVE', 'description' => 'Pemantau berkala sindikasi berita terpercaya nasional.']);
        Connector::create(['key' => 'platform_api', 'name' => 'Social Platform Official API', 'priority' => 'P2', 'status' => 'SIMULASI', 'description' => 'Konektor API Instagram/TikTok/X resmi (Saat ini berjalan dalam mode simulasi dataset).']);
        Connector::create(['key' => 'web_search', 'name' => 'Public Web Search Gateway', 'priority' => 'P2', 'status' => 'NOT CONFIGURED', 'description' => 'Gateway pencarian publik intranet PUSSIBERAD (memerlukan konfigurasi proxy internal).']);

        // RSS Feeds
        RssFeed::create(['name' => 'Antara News - Politik', 'feed_url' => 'https://www.antaranews.com/rss/politik.xml', 'is_active' => true, 'last_fetched_at' => now()]);
        RssFeed::create(['name' => 'Kemensetneg Siaran Pers', 'feed_url' => 'https://www.setneg.go.id/rss/siaran-pers', 'is_active' => true, 'last_fetched_at' => now()]);

        // 4. Skenario Operasional Terpadu: Kasus TS-2026-001 (Supplemental §9)
        $case = InvestigationCase::create([
            'case_code' => 'TS-2026-001',
            'title' => 'Cross-Platform Narrative Manipulation & Digital Content Authenticity Investigation',
            'category' => 'DISINFORMASI STRATEGIS / KOGNITIF',
            'priority' => 'TINGGI',
            'status' => 'IN_REVIEW',
            'claim_text' => 'Prabowo resmi menghentikan MBG dan KDMP, mengalirkan APBN lebih untuk pendidikan.',
            'description' => 'Investigasi persebaran narasi viral di 6 platform sosial mengenai klaim sepihak penghentian program prioritas nasional (Makan Bergizi Gratis & KDMP) dan pembelokan anggaran APBN.',
            'operator_notes' => 'Materi viral berawal dari Instagram Meme Account, merembet ke TikTok, YouTube, X, Facebook, dan portal web independen. Terdeteksi perubahan substansi kata "wacana/pertimbangan" menjadi "resmi dibatalkan".',
            'is_simulation' => true,
            'created_by' => $operator->id,
        ]);

        // Sumber Referensi (Fiktif berlabel SIMULASI sesuai Master §4.3)
        $srcNewsA = Source::create([
            'case_id' => $case->id,
            'source_code' => 'S-001',
            'publisher' => 'Portal Berita Fiktif A (Simulasi)',
            'platform' => 'NEWS_PORTAL',
            'url' => 'https://news-a-fiktif.co.id/nasional/kebijakan-anggaran-mbg',
            'published_at' => now()->subDays(2),
            'retrieved_at' => now()->subDays(1),
            'access_status' => 'OK',
            'authenticity' => 'UNVERIFIED',
            'is_simulation' => true,
        ]);

        $srcNewsB = Source::create([
            'case_id' => $case->id,
            'source_code' => 'S-002',
            'publisher' => 'Portal Berita Fiktif B (Simulasi)',
            'platform' => 'NEWS_PORTAL',
            'url' => 'https://news-b-fiktif.id/fiskal/evaluasi-alokasi-program',
            'published_at' => now()->subDays(2)->addHours(3),
            'retrieved_at' => now()->subDays(1),
            'access_status' => 'OK',
            'authenticity' => 'UNVERIFIED',
            'is_simulation' => true,
        ]);

        $srcGov = Source::create([
            'case_id' => $case->id,
            'source_code' => 'S-003',
            'publisher' => 'Kementerian Koordinator Bidang Kebijakan (Simulasi Akun Resmi)',
            'platform' => 'GOV_PORTAL',
            'url' => 'https://kemenko-fiktif.go.id/siaran-pers/evaluasi-semester-1',
            'published_at' => now()->subDays(3),
            'retrieved_at' => now()->subDays(1),
            'access_status' => 'OK',
            'authenticity' => 'VERIFIED',
            'is_simulation' => true,
        ]);

        // Evidence Items
        // E-001: Rujukan Berita A
        $e001 = EvidenceItem::create([
            'case_id' => $case->id,
            'source_id' => $srcNewsA->id,
            'evidence_code' => 'E-001',
            'type' => 'URL',
            'role' => 'REFERENCE',
            'title' => 'Pemerintah Kaji Penyesuaian Alokasi Program Prioritas',
            'publisher' => 'Portal Berita Fiktif A',
            'platform' => 'NEWS',
            'url' => $srcNewsA->url,
            'published_at' => $srcNewsA->published_at,
            'retrieved_at' => now()->subDays(1),
            'access_status' => 'OK',
            'extracted_text' => 'Pemerintah mempertimbangkan penyesuaian alokasi anggaran pada beberapa program kerja tahun anggaran 2026. Menurut siaran pers resmi Kementerian, kajian efisiensi dilakukan untuk memastikan penyerapan APBN tepat sasaran.',
            'content_hash' => hash('sha256', 'Pemerintah mempertimbangkan penyesuaian alokasi anggaran pada beberapa program kerja tahun anggaran 2026.'),
            'collection_method' => 'URL_FETCH',
            'is_simulation' => true,
            'added_by' => $operator->id,
        ]);

        // E-002: Rujukan Berita B
        $e002 = EvidenceItem::create([
            'case_id' => $case->id,
            'source_id' => $srcNewsB->id,
            'evidence_code' => 'E-002',
            'type' => 'URL',
            'role' => 'REFERENCE',
            'title' => 'Anggaran Program Menghadapi Review Kebijakan Fiskal',
            'publisher' => 'Portal Berita Fiktif B',
            'platform' => 'NEWS',
            'url' => $srcNewsB->url,
            'published_at' => $srcNewsB->published_at,
            'retrieved_at' => now()->subDays(1),
            'access_status' => 'OK',
            'extracted_text' => 'Mengutip siaran pers resmi Kementerian, penyesuaian alokasi anggaran sedang dipertimbangkan untuk beberapa sektor. Kebijakan ini masih berupa wacana evaluasi fiskal.',
            'content_hash' => hash('sha256', 'Mengutip siaran pers resmi Kementerian, penyesuaian alokasi anggaran sedang dipertimbangkan untuk beberapa sektor.'),
            'collection_method' => 'URL_FETCH',
            'is_simulation' => true,
            'added_by' => $operator->id,
        ]);

        // E-003: Sumber Primer (Siaran Pers Pemerintah)
        $e003 = EvidenceItem::create([
            'case_id' => $case->id,
            'source_id' => $srcGov->id,
            'evidence_code' => 'E-003',
            'type' => 'DOCUMENT',
            'role' => 'PRIMARY',
            'title' => 'Siaran Pers Resmi No. 104/SP/2026: Evaluasi Kebijakan Fiskal',
            'publisher' => 'Kementerian Koordinator (Simulasi)',
            'platform' => 'GOV_DOC',
            'url' => $srcGov->url,
            'published_at' => $srcGov->published_at,
            'retrieved_at' => now()->subDays(1),
            'access_status' => 'OK',
            'extracted_text' => 'Pemerintah mempertimbangkan penyesuaian alokasi anggaran pada beberapa program. Seluruh program utama tetap berjalan sesuai pentahapan rencana strategis tahun 2026.',
            'content_hash' => hash('sha256', 'Pemerintah mempertimbangkan penyesuaian alokasi anggaran pada beberapa program. Seluruh program utama tetap berjalan.'),
            'collection_method' => 'MANUAL_IMPORT',
            'is_simulation' => true,
            'added_by' => $operator->id,
        ]);

        // E-004: Konten Pembanding / Meme Inti Viral (Subject/Compared)
        $e004 = EvidenceItem::create([
            'case_id' => $case->id,
            'source_id' => null,
            'evidence_code' => 'E-004',
            'type' => 'SCREENSHOT',
            'role' => 'COMPARED',
            'title' => 'Instagram Meme Viral: Klaim Pembatalan MBG dan KDMP',
            'publisher' => '@meme_nusantara_fiktif',
            'platform' => 'INSTAGRAM',
            'url' => 'https://instagram.com/p/fiktif-001',
            'published_at' => now()->subHours(18),
            'retrieved_at' => now()->subHours(6),
            'access_status' => 'OK',
            'extracted_text' => 'Prabowo resmi menghentikan MBG dan KDMP, mengalirkan APBN lebih untuk pendidikan.',
            'content_hash' => hash('sha256', 'Prabowo resmi menghentikan MBG dan KDMP, mengalirkan APBN lebih untuk pendidikan.'),
            'collection_method' => 'MANUAL_IMPORT',
            'engagement' => [
                'views' => 8000000,
                'likes' => 621000,
                'comments' => 7841,
                'reposts' => 9297,
                'shares' => 7521,
            ],
            'is_simulation' => true,
            'added_by' => $operator->id,
        ]);

        // 7 Akun Meme Pembanding Lainnya (Supplemental §9)
        $variations = [
            ['code' => 'E-005', 'account' => '@politik_santuy_fiktif', 'platform' => 'INSTAGRAM', 'text' => 'MBG dan KDMP resmi dibatalkan oleh Prabowo.', 'likes' => 124000],
            ['code' => 'E-006', 'account' => '@info_warga_fiktif', 'platform' => 'TIKTOK', 'text' => 'Anggaran MBG dialihkan sepenuhnya, program dihentikan.', 'likes' => 290000],
            ['code' => 'E-007', 'account' => '@berita_viral_fiktif', 'platform' => 'TIKTOK', 'text' => 'Prabowo umumkan penghentian MBG mulai bulan depan.', 'likes' => 88000],
            ['code' => 'E-008', 'account' => '@suara_rakyat_fiktif', 'platform' => 'YOUTUBE', 'text' => 'KDMP ikut dihentikan, MBG juga tidak dilanjutkan.', 'likes' => 45000],
            ['code' => 'E-009', 'account' => '@opini_terkini_fiktif', 'platform' => 'X', 'text' => 'Breaking: Prabowo cabut MBG dan KDMP.', 'likes' => 31200],
            ['code' => 'E-010', 'account' => '@wacana_kita_fiktif', 'platform' => 'FACEBOOK', 'text' => 'Program bantuan MBG resmi stop oleh presiden.', 'likes' => 15000],
            ['code' => 'E-011', 'account' => '@portal_bebas_fiktif', 'platform' => 'WEB', 'text' => 'Prabowo setop MBG KDMP alihkan ke pendidikan.', 'likes' => 6200],
        ];

        $postEvidenceItems = [$e004];

        foreach ($variations as $idx => $v) {
            $postEvidenceItems[] = EvidenceItem::create([
                'case_id' => $case->id,
                'source_id' => null,
                'evidence_code' => $v['code'],
                'type' => 'POST',
                'role' => 'POST',
                'title' => "Variasi Konten #{$idx}: {$v['platform']}",
                'publisher' => $v['account'],
                'platform' => $v['platform'],
                'url' => "https://{$v['platform']}.com/post/simulasi-{$v['code']}",
                'published_at' => now()->subHours(18 - ($idx * 2)),
                'retrieved_at' => now()->subHours(4),
                'access_status' => 'OK',
                'extracted_text' => $v['text'],
                'content_hash' => hash('sha256', $v['text']),
                'collection_method' => 'SIMULASI',
                'engagement' => ['likes' => $v['likes']],
                'is_simulation' => true,
                'added_by' => $operator->id,
            ]);
        }

        // 5. Threat Actors & Patient Zero (Master §7.4, Supplemental §8)
        $actorsData = [
            ['username' => '@meme_nusantara_fiktif', 'platform' => 'INSTAGRAM', 'rate' => 36.5, 'age' => 42, 'earliest' => true, 'ind' => 'TINGGI'],
            ['username' => '@politik_santuy_fiktif', 'platform' => 'INSTAGRAM', 'rate' => 14.0, 'age' => 180, 'earliest' => false, 'ind' => 'SEDANG'],
            ['username' => '@info_warga_fiktif', 'platform' => 'TIKTOK', 'rate' => 28.2, 'age' => 22, 'earliest' => false, 'ind' => 'TINGGI'],
            ['username' => '@berita_viral_fiktif', 'platform' => 'TIKTOK', 'rate' => 8.5, 'age' => 310, 'earliest' => false, 'ind' => 'RENDAH'],
            ['username' => '@suara_rakyat_fiktif', 'platform' => 'YOUTUBE', 'rate' => 4.2, 'age' => 720, 'earliest' => false, 'ind' => 'RENDAH'],
            ['username' => '@opini_terkini_fiktif', 'platform' => 'X', 'rate' => 42.0, 'age' => 9, 'earliest' => false, 'ind' => 'TINGGI'],
            ['username' => '@wacana_kita_fiktif', 'platform' => 'FACEBOOK', 'rate' => 6.0, 'age' => 540, 'earliest' => false, 'ind' => 'RENDAH'],
            ['username' => '@portal_bebas_fiktif', 'platform' => 'WEB', 'rate' => 2.0, 'age' => 1200, 'earliest' => false, 'ind' => 'RENDAH'],
        ];

        $createdActors = [];
        foreach ($actorsData as $ad) {
            $createdActors[] = ThreatActor::create([
                'case_id' => $case->id,
                'platform' => $ad['platform'],
                'username' => $ad['username'],
                'automation_indicator' => $ad['ind'],
                'indicator_basis' => "Laju unggahan {$ad['rate']}/jam, umur akun {$ad['age']} hari. Memerlukan tinjauan analis.",
                'post_rate' => $ad['rate'],
                'account_age_days' => $ad['age'],
                'status' => 'PERLU DITINJAU',
                'first_seen_at' => now()->subHours(24),
                'is_earliest_in_dataset' => $ad['earliest'],
                'is_simulation' => true,
            ]);
        }

        // Actor Links (SNA Connections)
        $nodeA1 = $createdActors[0]; // Patient Zero
        for ($i = 1; $i < count($createdActors); $i++) {
            ActorLink::create([
                'actor_a_id' => $nodeA1->id,
                'actor_b_id' => $createdActors[$i]->id,
                'link_type' => 'SIMILAR_CONTENT',
                'weight' => round(0.75 + ($i * 0.03), 2),
                'basis' => 'Kesamaan inti klaim disinformasi dan penyebaran dalam rentang 18 jam.',
            ]);
        }

        // 6. Narrative Cluster: NC-2026-001
        $cluster = NarrativeCluster::create([
            'case_id' => $case->id,
            'cluster_code' => 'NC-2026-001',
            'title' => 'Disinformasi Penghentian Program MBG & KDMP Prabowo',
            'main_claim' => 'Prabowo resmi menghentikan MBG dan KDMP, mengalirkan APBN lebih untuk pendidikan.',
            'coordination_assessment' => 'INDICATORS_PRESENT',
            'analyst_status' => 'PENDING REVIEW',
            'method' => 'Single-link clustering (lexical + entity overlap)',
            'earliest_observed_at' => now()->subHours(18),
            'is_simulation' => true,
        ]);

        foreach ($postEvidenceItems as $pe) {
            NarrativeClusterItem::create([
                'cluster_id' => $cluster->id,
                'evidence_id' => $pe->id,
                'main_claim_id' => $e004->id,
                'similarity_score' => $pe->id === $e004->id ? 100.0 : 82.5,
                'matching_status' => $pe->id === $e004->id ? 'MATCH' : 'PARTIAL_MATCH',
                'differences' => ['redaksi' => 'Variasi kata penghentian / pembatalan / pencabutan'],
            ]);
        }

        // 7. Seed Threat Analysis (Credibility Scan Result)
        $scanResult = [
            'analysis_id' => (string) Str::uuid(),
            'module' => 'credibility_scan',
            'analyzed_at' => now()->subHours(5)->toIso8601String(),
            'model_name' => 'TraceS-Mock-IndoIntel-8B (SIMULASI)',
            'model_version' => '1.0',
            'is_simulation' => true,
            'summary' => '[SIMULASI] Narasi mengklaim penghentian resmi program MBG & KDMP, bertentangan dengan materi rujukan primer yang hanya menyebut wacana penyesuaian alokasi.',
            'claims' => [
                ['id' => 'c1', 'text' => 'Prabowo resmi menghentikan MBG dan KDMP', 'type' => 'FAKTA', 'check_status' => 'TERBANTAHKAN'],
                ['id' => 'c2', 'text' => 'Mengalirkan APBN lebih untuk pendidikan', 'type' => 'DUGAAN', 'check_status' => 'BELUM TERVERIFIKASI'],
            ],
            'verification_status' => 'TERBANTAHKAN',
            'content_indicator' => 'INDIKASI HOAKS',
            'classification' => 'DISINFORMATION',
            'credibility_score' => 24,
            'threat_level' => 'KRITIS',
            'confidence' => 0.88,
            'linguistic_analysis' => [
                'emotion' => 'Terdapat diksi sensasional dan klaim definitif ("resmi", "cabut").',
                'fallacy' => 'False dilemma dan extrapolasi wacana kebijakan menjadi keputusan final.',
                'source_verification' => 'Konten viral tidak mencantumkan rujukan primer; bertentangan dengan siaran pers No. 104.',
            ],
            'findings' => [
                [
                    'statement' => 'Materi referensi E-003 Kementerian Koordinator menyebutkan "mempertimbangkan penyesuaian alokasi", bukan pembatalan.',
                    'type' => 'FAKTA',
                    'evidence_ids' => ['E-003'],
                ],
                [
                    'statement' => 'Konten meme viral E-004 mengubah status dari rencana evaluasi menjadi keputusan resmi sepihak.',
                    'type' => 'INTERPRETASI_AI',
                    'evidence_ids' => ['E-004'],
                ],
                [
                    'statement' => 'Direkomendasikan penerbitan klarifikasi resmi rujukan primer dan monitoring eskalasi sentimen.',
                    'type' => 'REKOMENDASI',
                    'evidence_ids' => [],
                ],
            ],
            'evidence' => [
                ['id' => 'E-003', 'source_id' => 'S-003', 'excerpt' => 'Pemerintah mempertimbangkan penyesuaian alokasi anggaran pada beberapa program...'],
                ['id' => 'E-004', 'source_id' => null, 'excerpt' => 'Prabowo resmi menghentikan MBG dan KDMP...'],
            ],
            'sources' => [
                ['id' => 'S-003', 'url' => 'https://kemenko-fiktif.go.id/siaran-pers/evaluasi-semester-1', 'publisher' => 'Kementerian Koordinator', 'published_at' => now()->subDays(3)->toIso8601String(), 'retrieved_at' => now()->toIso8601String(), 'access_status' => 'OK'],
            ],
            'recommendations' => [
                'Susun draf rilis pers klarifikasi berbasis bukti E-003.',
                'Monitor 8 akun penyebar utama pada Narrative Cluster NC-2026-001.',
                'Tindakan eksternal memerlukan validasi dan otorisasi komandan.',
            ],
            'limitations' => [
                'Hasil dihitung menggunakan MockDriver berlabel SIMULASI.',
                'Skor kredibilitas 24% adalah indikator metrik algoritma, BUKAN probabilitas kebenaran.',
            ],
            'tactical_summary' => 'Konten terindikasi disinformasi kognitif berkategori kritis. Terjadi alterasi makna wacana evaluasi menjadi pembatalan resmi. Diperlukan tinjauan analis.',
            'validation_status' => 'PENDING REVIEW',
        ];

        ThreatAnalysis::create([
            'analysis_uuid' => $scanResult['analysis_id'],
            'case_id' => $case->id,
            'module' => 'credibility_scan',
            'source_text' => $case->claim_text,
            'result' => $scanResult,
            'credibility_score' => 24,
            'classification' => 'DISINFORMATION',
            'verification_status' => 'TERBANTAHKAN',
            'threat_level' => 'KRITIS',
            'confidence' => 0.88,
            'model_name' => 'TraceS-Mock-IndoIntel-8B',
            'model_version' => '1.0',
            'driver' => 'mock',
            'is_simulation' => true,
            'validation_status' => 'PENDING REVIEW',
            'scanned_by' => $operator->id,
            'analyzed_at' => now()->subHours(5),
        ]);

        // 8. Authenticity Comparison pre-run: E-003 vs E-004
        $compResult = [
            'comparable' => true,
            'reference' => ['id' => 'E-003', 'title' => $e003->title, 'publisher' => $e003->publisher, 'hash' => $e003->content_hash, 'text' => $e003->extracted_text],
            'compared' => ['id' => 'E-004', 'title' => $e004->title, 'publisher' => $e004->publisher, 'hash' => $e004->content_hash, 'text' => $e004->extracted_text],
            'lexical' => ['score' => 14.8, 'jaccard' => 8.2, 'shared_terms' => ['pemerintah', 'anggaran', 'program'], 'comparable' => true, 'method' => 'TF-IDF cosine', 'version' => '1.0'],
            'semantic' => ['available' => true, 'score' => 64.2, 'method' => 'Hashing trigram karakter (SIMULASI)', 'model' => 'MockDriver', 'version' => '1.0', 'is_simulation' => true, 'note' => null],
            'claim' => [
                'match_status' => 'CONFLICT',
                'differences' => [
                    'status' => ['wacana / rencana', 'keputusan resmi'],
                    'tindakan' => ['penyesuaian / pengalihan', 'penghentian / pembatalan'],
                ],
                'explanation' => 'Terdapat perbedaan substansi status dan tindakan; klaim tidak boleh dianggap setara.',
            ],
            'numeric_entity' => [
                'numeric_consistency' => 'NOT_APPLICABLE',
                'numbers_removed' => [],
                'numbers_added' => [],
                'dates_changed' => false,
                'entities_removed' => [],
                'entities_added' => ['KDMP', 'Prabowo'],
                'entities_shared' => ['MBG'],
            ],
            'context' => ['consistency' => 'INCONSISTENT', 'notes' => ['Status pernyataan berbeda (wacana vs keputusan resmi).']],
            'changes' => [
                [
                    'element' => 'status keputusan',
                    'original' => 'mempertimbangkan',
                    'compared' => 'resmi',
                    'change_type' => 'ALTERED',
                    'meaning_impact' => 'Status berubah dari wacana / rencana menjadi keputusan resmi; berpotensi mendistorsi status kebijakan negara.',
                    'evidence_ids' => ['E-003', 'E-004'],
                ],
                [
                    'element' => 'tindakan',
                    'original' => 'penyesuaian alokasi',
                    'compared' => 'menghentikan',
                    'change_type' => 'ALTERED',
                    'meaning_impact' => 'Tindakan berubah dari penyesuaian alokasi anggaran menjadi penghentian total.',
                    'evidence_ids' => ['E-003', 'E-004'],
                ],
            ],
            'assessment' => 'Ditemukan 2 indikasi perubahan materi substansial (status keputusan, tindakan) antara E-003 dan E-004. Perubahan tersebut mengubah makna dari rencana evaluasi menjadi keputusan penghentian.',
            'limitations' => [
                'Perbedaan teks tidak membuktikan manipulasi gambar secara digital; gunakan istilah "indikasi perubahan materi".',
                'Image comparison: NOT AVAILABLE.',
            ],
            'is_simulation' => true,
        ];

        EvidenceComparison::create([
            'case_id' => $case->id,
            'reference_evidence_id' => $e003->id,
            'compared_evidence_id' => $e004->id,
            'diff_json' => $compResult,
            'assessment' => $compResult['assessment'],
            'is_simulation' => true,
        ]);

        // 9. Source Relationships Seed
        SourceRelationship::create([
            'case_id' => $case->id,
            'source_a_id' => $srcNewsA->id,
            'source_b_id' => $srcNewsB->id,
            'relationship_type' => 'COMMON_PRIMARY_SOURCE',
            'assessment' => 'Kedua portal berita mengutip satu siaran pers primer yang sama dari kementerian.',
            'reviewed_by' => $analis->id,
            'reviewed_at' => now()->subHours(2),
        ]);

        // 10. Initial Intelligence Brief Report (Supplemental §14)
        Report::create([
            'case_id' => $case->id,
            'report_code' => 'REP-TS-2026-001-01',
            'title' => 'INTEL BRIEF: Disinformasi Penghentian Program MBG dan KDMP',
            'classification_label' => 'PROTOTIPE - TIDAK UNTUK DISTRIBUSI',
            'content' => [
                'section_1_case_identity' => [
                    'case_code' => 'TS-2026-001',
                    'title' => $case->title,
                    'category' => $case->category,
                    'priority' => 'TINGGI',
                    'status' => 'IN_REVIEW',
                    'created_at' => $case->created_at->toIso8601String(),
                    'classification_label' => 'PROTOTIPE - TIDAK UNTUK DISTRIBUSI',
                    'is_simulation' => true,
                ],
                'section_2_executive_summary' => [
                    'summary' => 'Investigasi awal menemukan penyebaran narasi viral di 6 platform sosial yang membelokkan wacana penyesuaian anggaran menjadi klaim penghentian resmi program strategis. Bukti primer No. 104 membantah klaim pembatalan tersebut.',
                    'marker' => 'INTERPRETASI AI',
                ],
                'section_3_data_scope' => [
                    'scope' => 'Analisis didasarkan pada dataset terkumpul (11 item bukti digital: 3 sumber rujukan dan 8 unggahan sosial media).',
                    'sources_connected' => ['NEWS', 'GOV_PORTAL', 'INSTAGRAM', 'TIKTOK', 'YOUTUBE', 'X', 'FACEBOOK', 'WEB'],
                ],
                'section_11_key_findings' => [
                    ['marker' => 'FAKTA TERDOKUMENTASI', 'statement' => 'Siaran Pers Resmi No. 104 (E-003) menyatakan program tetap berjalan dan hanya menjalani evaluasi berkala.'],
                    ['marker' => 'KLAIM SUMBER', 'statement' => 'Konten E-004 di Instagram mengklaim secara sepihak pembatalan resmi program oleh presiden.'],
                    ['marker' => 'INTERPRETASI AI', 'statement' => 'Terjadi eskalasi penyebaran dengan 8 variasi klaim terkoordinasi dalam rentang waktu 18 jam.'],
                    ['marker' => 'TEMUAN ANALIS', 'statement' => 'Dukungan lintas sumber pada berita A dan B mengacu pada satu rujukan primer (bukan 2 bukti independen).'],
                ],
                'section_12_threat_indicators' => [
                    'threat_level' => 'KRITIS',
                    'credibility_score' => 24,
                    'verification_status' => 'TERBANTAHKAN',
                    'basis' => 'Distorsi informasi tingkat tinggi berpotensi mempengaruhi kepercayaan publik terhadap stabilitas kebijakan pangan dan pendidikan.',
                ],
                'section_15_recommendations' => [
                    '1. Siapkan counter-narrative berbasis data faktual dari Siaran Pers No. 104.',
                    '2. Pantau pergerakan akun Node-A1 (@meme_nusantara_fiktif) dan jaringannya.',
                    '3. Laporkan draf klarifikasi kepada Komandan PUSSIBERAD.',
                ],
            ],
            'status' => 'FINAL',
            'is_simulation' => true,
            'generated_by' => $analis->id,
        ]);

        // 11. Narratives untuk Dashboard Live Feed
        $narrativeSamples = [
            ['title' => 'Viral meme klaim penghentian program MBG dan KDMP menyebar di Instagram', 'region' => 'Jakarta', 'sentiment' => 'NEGATIF', 'severity' => 'KRITIS'],
            ['title' => 'Akun TikTok memotong video pidato menteri terkait alokasi APBN', 'region' => 'Surabaya', 'sentiment' => 'NEGATIF', 'severity' => 'TINGGI'],
            ['title' => 'Portal berita lokal mengutip siaran pers evaluasi berkala', 'region' => 'Medan', 'sentiment' => 'NETRAL', 'severity' => 'SEDANG'],
            ['title' => 'Akun anonim X menyebarkan tagar penolakan anggaran fiktif', 'region' => 'Jakarta', 'sentiment' => 'NEGATIF', 'severity' => 'TINGGI'],
            ['title' => 'Klarifikasi resmi kementerian direspons positif di portal berita', 'region' => 'Makassar', 'sentiment' => 'POSITIF', 'severity' => 'RENDAH'],
            ['title' => 'Muncul video editan suara presiden di grup WhatsApp publik', 'region' => 'Balikpapan', 'sentiment' => 'NEGATIF', 'severity' => 'SEDANG'],
            ['title' => 'Isu penghentian bantuan pangan beredar di forum regional', 'region' => 'Jayapura', 'sentiment' => 'NEGATIF', 'severity' => 'RENDAH'],
        ];

        foreach ($narrativeSamples as $ns) {
            Narrative::create([
                'title' => $ns['title'],
                'category' => 'DISINFORMASI',
                'region' => $ns['region'],
                'sentiment' => $ns['sentiment'],
                'severity' => $ns['severity'],
                'captured_at' => now()->subMinutes(rand(10, 360)),
                'case_id' => $case->id,
                'cluster_id' => $cluster->id,
                'is_simulation' => true,
            ]);
        }

        // 12. Katalog Skill awal (status DISABLED; diaktifkan eksplisit oleh Administrator)
        $skillService = app(SkillService::class);
        foreach (['claim-extractor', 'lexical-similarity'] as $skillKey) {
            if (! Skill::where('skill_key', $skillKey)->exists()) {
                $skillService->install($operator, $skillKey);
            }
        }

        // 13. Contoh Rekaman Informasi terstruktur (Fixture Simulasi)
        $infoRecord = InformationRecord::create([
            'record_code' => 'INF-2026-000001',
            'title' => '[SIMULASI] Ringkasan Verifikasi Narasi Evaluasi Anggaran Program Nasional',
            'description' => 'Rekaman contoh untuk uji alur kerja peninjauan informasi.',
            'category' => 'narrative_observation',
            'status' => 'APPROVED',
            'organization_id' => $org->id,
            'created_by' => $operator->id,
            'reviewed_by' => $operator->id,
            'current_version' => 1,
            'approved_version' => 1,
            'submitted_at' => now()->subHours(5),
            'approved_at' => now()->subHours(3),
        ]);
        RecordVersion::create([
            'record_id' => $infoRecord->id,
            'version_number' => 1,
            'title' => $infoRecord->title,
            'description' => $infoRecord->description,
            'category' => $infoRecord->category,
            'content' => "Catatan Simulasi: Berdasarkan dataset contoh kasus TS-2026-001, klaim penghentian total program nasional berasal dari modifikasi wacana evaluasi anggaran. Materi primer menunjukkan evaluasi berkala tetap berlangsung tanpa pembatalan program.",
            'changed_by' => $operator->id,
            'change_reason' => 'Seed fixture awal non-produksi',
        ]);

        // 14. Audit Log Seed (Append-Only dengan SHA-256 hash chain)
        $audit = app(AuditLogger::class);
        $audit->log('SYSTEM_INITIALIZATION', ['type' => 'TraceSApp', 'id' => '1'], [
            'mode' => 'LOCAL_FIRST',
            'env' => app()->environment(),
            'simulation_fixture' => true,
        ], null, 'SUCCESS', ['type' => 'SYSTEM', 'label' => 'seeder']);

        $audit->log('CASE_CREATED', $case, [
            'case_code' => $case->case_code,
            'priority' => $case->priority,
        ], $operator->id, 'SUCCESS', ['type' => 'SYSTEM', 'label' => 'seeder']);
    }
}
