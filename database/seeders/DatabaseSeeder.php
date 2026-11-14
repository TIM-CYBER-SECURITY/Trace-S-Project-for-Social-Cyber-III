<?php

namespace Database\Seeders;

use App\Models\AiServer;
use App\Models\Connector;
use App\Models\RssFeed;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder inti platform Trace-S: hanya memuat tabel otorisasi RBAC,
     * konektor sumber data, umpan RSS publik awal, dan node AI lokal default.
     * Tidak ada akun dummy, kasus fiktif, maupun model simulasi.
     */
    public function run(): void
    {
        $this->call(RbacSeeder::class);

        $baseUrl = rtrim((string) (config('trace.ai.base_url') ?: 'http://127.0.0.1:11434'), '/');
        $driver = (string) config('trace.ai.driver', 'ollama');
        if (! in_array($driver, ['ollama', 'openai_compatible', 'external'], true)) {
            $driver = 'ollama';
        }

        AiServer::firstOrCreate(
            ['driver' => $driver, 'base_url' => $baseUrl],
            [
                'name' => 'Local Ollama Node (' . $baseUrl . ')',
                'status' => 'OFFLINE',
                'runtime' => $driver === 'openai_compatible' ? 'OpenAI-Compatible' : 'Ollama',
                'runtime_version' => null,
                'latency_ms' => null,
                'is_active' => true,
                'last_checked_at' => null,
            ]
        );

        $connectors = [
            ['key' => 'manual', 'name' => 'Manual Evidence Import', 'priority' => 'P0', 'status' => 'ACTIVE', 'description' => 'Impor teks, dokumen PDF/Word, dan berkas bukti digital langsung oleh operator.'],
            ['key' => 'url_fetch', 'name' => 'URL Evidence Fetch', 'priority' => 'P0', 'status' => 'ACTIVE', 'description' => 'Pengambilan konten web terbuka secara sah dengan parsing teks dan sanitasi anti-injection.'],
            ['key' => 'rss', 'name' => 'News RSS Feed Connector', 'priority' => 'P1', 'status' => 'ACTIVE', 'description' => 'Pemantau berkala sindikasi berita terpercaya nasional.'],
            ['key' => 'platform_api', 'name' => 'Social Platform Official API', 'priority' => 'P2', 'status' => 'ACTIVE', 'description' => 'Konektor indeks media sosial terbuka melalui mesin koroborasi Exa.ai dan situs publik.'],
            ['key' => 'web_search', 'name' => 'Public Web Search Gateway', 'priority' => 'P2', 'status' => 'ACTIVE', 'description' => 'Gateway pencarian web terbuka multi-instansi melalui Google Search Grounding dan Exa.ai.'],
        ];
        foreach ($connectors as $c) {
            Connector::updateOrCreate(['key' => $c['key']], $c);
        }

        RssFeed::firstOrCreate(
            ['feed_url' => 'https://www.antaranews.com/rss/politik.xml'],
            ['name' => 'Antara News - Politik', 'is_active' => true, 'last_fetched_at' => now()]
        );
        RssFeed::firstOrCreate(
            ['feed_url' => 'https://www.setneg.go.id/rss/siaran-pers'],
            ['name' => 'Kemensetneg Siaran Pers', 'is_active' => true, 'last_fetched_at' => now()]
        );
    }
}
