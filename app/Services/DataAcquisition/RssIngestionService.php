<?php

namespace App\Services\DataAcquisition;

use App\Models\Connector;
use App\Models\FeedItem;
use App\Models\Narrative;
use App\Models\RssFeed;
use App\Models\User;
use App\Services\Analysis\Lexicon;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * News RSS / Atom Feed Connector (P1): mengimpor berita publik ke Narrative Feed (is_simulation=false)
 * dengan perlindungan anti-SSRF dan anti-XXE.
 */
class RssIngestionService
{
    private const REGION_KEYWORDS = [
        'Jakarta' => ['jakarta', 'dkj', 'dki', 'jabodetabek'],
        'Surabaya' => ['surabaya', 'jatim', 'jawa timur', 'sidoarjo'],
        'Medan' => ['medan', 'sumut', 'sumatera utara'],
        'Balikpapan' => ['balikpapan', 'ikn', 'nusantara', 'kaltim', 'kalimantan timur'],
        'Makassar' => ['makassar', 'sulsel', 'sulawesi selatan'],
        'Jayapura' => ['jayapura', 'papua', 'timika', 'merauke'],
    ];

    public function __construct(private AuditLogger $audit)
    {
    }

    /** @return array{feeds_checked:int,imported:int,errors:array<int,string>} */
    public function syncActiveFeeds(?User $user = null, int $maxPerFeed = 10): array
    {
        $feeds = RssFeed::where('is_active', true)->get();
        $imported = 0;
        $errors = [];

        foreach ($feeds as $feed) {
            if ($ssrf = EvidenceCollectorService::validatePublicUrl($feed->feed_url)) {
                $feed->update(['last_fetched_at' => now(), 'last_status' => 'BLOCKED_SSRF']);
                $errors[] = "{$feed->name}: {$ssrf}";
                continue;
            }

            try {
                $resp = Http::timeout((int) config('trace.fetch.timeout', 12))
                    ->withUserAgent((string) config('trace.fetch.user_agent', 'Trace-S/2.0'))
                    ->get($feed->feed_url);

                if (!$resp->successful()) {
                    $feed->update(['last_fetched_at' => now(), 'last_status' => 'HTTP_' . $resp->status()]);
                    $errors[] = "{$feed->name}: HTTP {$resp->status()}";
                    continue;
                }

                $count = $this->ingestXml($feed, $resp->body(), $maxPerFeed);
                $imported += $count;
                $feed->update(['last_fetched_at' => now(), 'last_status' => 'OK']);
            } catch (\Throwable $e) {
                $feed->update(['last_fetched_at' => now(), 'last_status' => 'ERROR']);
                $errors[] = "{$feed->name}: {$e->getMessage()}";
            }
        }

        Connector::where('key', 'rss')->update(['last_run_at' => now(), 'status' => 'ACTIVE']);

        $this->audit->log('RSS_FEEDS_SYNCED', ['type' => 'Connector', 'id' => 'rss'], [
            'feeds_checked' => $feeds->count(),
            'imported' => $imported,
            'error_count' => count($errors),
        ], $user?->id, $errors && $imported === 0 ? 'FAILURE' : 'SUCCESS');

        return [
            'feeds_checked' => $feeds->count(),
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    public function ingestXml(RssFeed $feed, string $xmlBody, int $maxPerFeed = 10): int
    {
        // Anti-XXE hardening: tolak deklarasi ENTITY / DOCTYPE XML
        if (preg_match('/<!ENTITY\b/i', $xmlBody) || preg_match('/<!DOCTYPE\s+html\b/i', substr($xmlBody, 0, 500))) {
            $msg = preg_match('/<!DOCTYPE\s+html\b/i', substr($xmlBody, 0, 500))
                ? 'Feed mengembalikan halaman HTML, bukan berkas RSS/Atom XML.'
                : 'Berkas XML ditolak oleh kebijakan keamanan anti-XXE.';
            throw new \RuntimeException($msg);
        }
        if (preg_match('/<!DOCTYPE\b/i', $xmlBody)) {
            throw new \RuntimeException('Berkas XML ditolak oleh kebijakan keamanan anti-XXE.');
        }

        $prev = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($xmlBody, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
            if ($xml === false) {
                throw new \RuntimeException('XML feed tidak valid atau rusak.');
            }

            $entries = [];
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $entries[] = [
                        'title' => trim((string) ($item->title ?? '')),
                        'link' => trim((string) ($item->link ?? '')),
                        'summary' => trim(strip_tags((string) ($item->description ?? ''))),
                        'guid' => trim((string) ($item->guid ?? $item->link ?? $item->title ?? '')),
                        'pub' => trim((string) ($item->pubDate ?? '')),
                    ];
                }
            } elseif (isset($xml->entry)) {
                foreach ($xml->entry as $entry) {
                    $link = '';
                    if (isset($entry->link['href'])) {
                        $link = (string) $entry->link['href'];
                    } elseif (isset($entry->link)) {
                        $link = (string) $entry->link;
                    }
                    $entries[] = [
                        'title' => trim((string) ($entry->title ?? '')),
                        'link' => trim($link),
                        'summary' => trim(strip_tags((string) ($entry->summary ?? $entry->content ?? ''))),
                        'guid' => trim((string) ($entry->id ?? $link ?? $entry->title ?? '')),
                        'pub' => trim((string) ($entry->published ?? $entry->updated ?? '')),
                    ];
                }
            }

            $imported = 0;
            foreach (array_slice($entries, 0, $maxPerFeed) as $e) {
                if ($e['title'] === '' || $e['guid'] === '') {
                    continue;
                }
                $hash = hash('sha256', $feed->id . '|' . $e['guid']);
                if (FeedItem::where('guid_hash', $hash)->exists()) {
                    continue;
                }

                $pubAt = null;
                if ($e['pub'] !== '') {
                    try {
                        $pubAt = Carbon::parse($e['pub']);
                    } catch (\Throwable) {
                        $pubAt = now();
                    }
                }

                FeedItem::create([
                    'feed_id' => $feed->id,
                    'title' => mb_substr($e['title'], 0, 250),
                    'link' => $e['link'] ?: null,
                    'summary' => mb_substr($e['summary'], 0, 2000) ?: null,
                    'published_at' => $pubAt ?? now(),
                    'guid_hash' => $hash,
                ]);

                $text = $e['title'] . ' ' . $e['summary'];
                Narrative::create([
                    'title' => mb_substr("[{$feed->name}] " . $e['title'], 0, 250),
                    'category' => 'RSS_MONITORING',
                    'region' => self::detectRegion($text),
                    'sentiment' => self::inferSentiment($text),
                    'severity' => self::inferSeverity($text),
                    'captured_at' => $pubAt ?? now(),
                    'is_simulation' => false,
                ]);
                $imported++;
            }

            return $imported;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }

    public static function detectRegion(string $text): string
    {
        $lower = mb_strtolower($text, 'UTF-8');
        foreach (self::REGION_KEYWORDS as $region => $words) {
            foreach ($words as $w) {
                if (str_contains($lower, $w)) {
                    return $region;
                }
            }
        }
        return 'Nasional';
    }

    public static function inferSentiment(string $text): string
    {
        $neg = Lexicon::matchCues($text, array_merge(
            Lexicon::ACTIONS['TERMINATE'],
            Lexicon::ACTIONS['REDUCE'],
            ['hoaks', 'hoax', 'disinformasi', 'fitnah', 'serangan', 'bohong', 'palsu', 'konflik', 'krisis', 'ancaman', 'batal', 'ditolak']
        ));
        $pos = Lexicon::matchCues($text, array_merge(
            Lexicon::ACTIONS['CONTINUE'],
            ['resmi', 'klarifikasi', 'berjalan', 'dukung', 'aman', 'stabil', 'terkendali', 'berhasil']
        ));

        if (count($neg) > count($pos)) {
            return 'NEGATIF';
        }
        if (count($pos) > count($neg)) {
            return 'POSITIF';
        }
        return 'NETRAL';
    }

    public static function inferSeverity(string $text): string
    {
        $crit = Lexicon::matchCues($text, ['darurat', 'kritis', 'serangan', 'sabotase', 'darurat militer', 'rusuh', 'provokasi']);
        if (count($crit) >= 2) {
            return 'KRITIS';
        }
        $high = Lexicon::matchCues($text, array_merge(Lexicon::ACTIONS['TERMINATE'], ['hoaks', 'disinformasi', 'palsu', 'fitnah']));
        if ($crit !== [] || count($high) >= 2) {
            return 'TINGGI';
        }
        if ($high !== []) {
            return 'SEDANG';
        }
        return 'RENDAH';
    }
}
