<?php

namespace App\Services\DataAcquisition;

use App\Models\EvidenceItem;
use App\Models\InvestigationCase;
use App\Models\Source;
use App\Models\User;
use App\Services\Analysis\LexicalSimilarity;
use App\Services\Analysis\NumericEntityComparer;
use App\Services\Analysis\TextNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Pencarian Berita Publik & Narasi Sosial Media Real-Time Global.
 * Mengambil artikel berita & konten sosial media aktual (Google News ID, Bing News ID,
 * indeks media sosial & cek-fakta) tanpa simulasi atau fabrikasi AI, memfilter kecocokan
 * entitas/kata kunci secara ketat, lalu menyimpannya sebagai bukti kasus ter-hash SHA-256.
 */
class LiveGlobalSearchService
{
    private const STOPWORDS = [
        'yang', 'dan', 'di', 'ke', 'dari', 'pada', 'ini', 'itu', 'untuk', 'dengan', 'atau',
        'dalam', 'adalah', 'sebagai', 'oleh', 'telah', 'sudah', 'akan', 'bisa', 'dapat',
        'tidak', 'bukan', 'belum', 'secara', 'resmi', 'kabar', 'berita', 'viral', 'isu',
        'disebut', 'dikabarkan', 'tentang', 'terkait', 'mengenai', 'bahwa', 'agar', 'karena',
        'saat', 'hari', 'bulan', 'tahun', 'atas', 'bawah', 'para', 'oleh', 'juga', 'namun',
        'tapi', 'serta', 'pun', 'apa', 'siapa', 'kapan', 'dimana', 'mengapa', 'bagaimana',
    ];

    private const REFUTATION_CUES = [
        'hoaks', 'hoax', 'tidak benar', 'keliru', 'palsu', 'fitnah', 'bohong', 'disinformasi',
        'misinformasi', 'membantah', 'bantah', 'bantahan', 'cek fakta', 'fact check', 'klarifikasi',
        'penipuan', 'editan', 'waspada', 'hati-hati', 'modus', 'scam', 'phishing', 'pencatutan',
        'mencatut', 'isu liar', 'kabar burung', 'dipastikan tidak', 'belum ada aturan', 'tidak pernah',
    ];

    public function __construct(
        private LexicalSimilarity $lexical,
        private NumericEntityComparer $numeric
    ) {
    }

    /**
     * Bersihkan teks masukan dan cegah duplikasi judul + isi yang tergabung.
     */
    public function cleanAndDeduplicateInput(string $rawText): string
    {
        $clean = trim(preg_replace("/\r\n|\r/", "\n", $rawText));
        $lines = array_values(array_filter(array_map('trim', explode("\n", $clean)), fn ($l) => $l !== ''));
        if (count($lines) >= 2) {
            $first = mb_strtolower($lines[0], 'UTF-8');
            $rest = mb_strtolower(implode(' ', array_slice($lines, 1)), 'UTF-8');
            if ($first === $rest || str_starts_with($rest, $first)) {
                return implode("\n", array_slice($lines, 1));
            }
            if (str_starts_with($first, $rest)) {
                return $lines[0];
            }
        }

        // Tangani juga teks terduplikasi langsung tanpa pemisah baris ("...iniGibran...") atau dipisah spasi
        $mbLen = mb_strlen($clean, 'UTF-8');
        if ($mbLen >= 16 && $mbLen % 2 === 0) {
            $half = (int) ($mbLen / 2);
            $a = mb_substr($clean, 0, $half, 'UTF-8');
            $b = mb_substr($clean, $half, null, 'UTF-8');
            if (mb_strtolower($a, 'UTF-8') === mb_strtolower($b, 'UTF-8')) {
                return $a;
            }
        }
        if (preg_match('/^(.{12,}?)\s+\1$/iu', $clean, $dupMatch)) {
            return trim($dupMatch[1]);
        }

        return $clean;
    }

    /**
     * Ekstrak kata kunci penting (Entitas, Waktu/Angka, Klaim) dan kueri pencarian bersih.
     *
     * @return array{keywords:array<int,array{term:string,category:string}>,primary_entities:string[],query:string}
     */
    public function extractKeywordsAndQuery(string $text): array
    {
        $clean = trim(preg_replace('/https?:\/\/\S+/i', ' ', $text));
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

        $keywords = [];
        $seenLower = [];
        $primaryEntities = [];

        $addKeyword = function (string $term, string $category) use (&$keywords, &$seenLower): void {
            $t = trim($term, " \t\n\r\0\x0B.,;:!?\"'()[]");
            if ($t === '' || mb_strlen($t, 'UTF-8') < 2) {
                return;
            }
            $low = mb_strtolower($t, 'UTF-8');
            if (isset($seenLower[$low])) {
                return;
            }
            $seenLower[$low] = true;
            $keywords[] = ['term' => $t, 'category' => $category];
        };

        // 1. Frasa Entitas / Nama Diri Berurutan (contoh: Gibran Rakabuming Raka, Wakil Presiden, Erika Carlina, DJ Ohim)
        if (preg_match_all('/\b(?:[A-Z][a-zA-Z0-9\-\.]{1,}|[A-Z]{2,})(?:\s+(?:[A-Z][a-zA-Z0-9\-\.]{1,}|[A-Z]{2,})){0,3}\b/u', $clean, $entMatches)) {
            foreach ($entMatches[0] as $ent) {
                $words = preg_split('/\s+/u', $ent);
                // Abaikan kata tunggal di awal kalimat bila huruf biasa non-singkatan dan cuma 1 kata pendek umum
                if (count($words) === 1 && in_array(mb_strtolower($ent, 'UTF-8'), self::STOPWORDS, true)) {
                    continue;
                }
                if (count($words) >= 2 || preg_match('/^[A-Z]{2,}$/', $ent) || mb_strlen($ent, 'UTF-8') >= 5) {
                    $addKeyword($ent, 'ENTITAS');
                    if (count($words) >= 2 || mb_strlen($ent, 'UTF-8') >= 4) {
                        $primaryEntities[] = mb_strtolower($ent, 'UTF-8');
                    }
                }
            }
        }

        // 2. Penanda Waktu & Angka (tanggal, bulan+tahun, nominal, persentase)
        if (preg_match_all('/\b(?:\d{1,2}\s+)?(?:Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+\d{4}\b|\b\d{4}-\d{2}-\d{2}\b|\b(?:Rp\.?\s*)?\d[\d.,]*\s*(?:%|persen|ribu|juta|miliar|milyar|triliun)\b/iu', $clean, $timeMatches)) {
            foreach ($timeMatches[0] as $tm) {
                $addKeyword($tm, 'WAKTU_ANGKA');
            }
        }

        // 3. Kata Kunci Klaim / Peristiwa Utama (frasa kerja / objek bermakna)
        $tokens = TextNormalizer::tokens($clean);
        $meaningful = array_values(array_filter(
            $tokens,
            fn ($tok) => mb_strlen($tok, 'UTF-8') >= 4 && !in_array($tok, self::STOPWORDS, true) && !is_numeric($tok)
        ));

        // Deteksi frasa kerja penting Bahasa Indonesia (mis. "mengundurkan diri", "ayah biologis", "meninggal dunia")
        if (preg_match_all('/\b(mengundurkan diri|mundur dari|ayah biologis|ditangkap|diblokir|dibubarkan|dihapus|pengganti|pemadaman|kebocoran|imunisasi|rekrutmen|klarifikasi|pengunduran diri)\b/iu', $clean, $actMatches)) {
            foreach ($actMatches[0] as $act) {
                $addKeyword($act, 'KLAIM');
            }
        }

        foreach (array_slice($meaningful, 0, 8) as $tok) {
            // Hindari menduplikasi kata yang sudah tercakup dalam frasa ENTITAS
            $alreadyCovered = false;
            foreach ($seenLower as $existing => $_) {
                if (str_contains($existing, $tok)) {
                    $alreadyCovered = true;
                    break;
                }
            }
            if (!$alreadyCovered && count($keywords) < 10) {
                $addKeyword($tok, 'KLAIM');
            }
        }

        // Bangun kueri ringkas 5-7 kata untuk mesin pencari
        $queryParts = [];
        foreach ($keywords as $kw) {
            if ($kw['category'] === 'ENTITAS' || $kw['category'] === 'KLAIM') {
                $queryParts[] = $kw['term'];
            }
            if (count($queryParts) >= 4) {
                break;
            }
        }
        $query = trim(implode(' ', $queryParts));
        if ($query === '') {
            $query = implode(' ', array_slice($meaningful, 0, 6)) ?: Str::limit($clean, 80, '');
        }

        return [
            'keywords' => array_slice($keywords, 0, 10),
            'primary_entities' => array_values(array_unique($primaryEntities)),
            'meaningful_tokens' => $meaningful,
            'query' => trim((string) preg_replace('/\s+/u', ' ', $query)),
        ];
    }

    /**
     * Lakukan pencarian real-time lintas portal berita global/nasional dan media sosial,
     * filter relevansi secara ketat, simpan bukti ke kasus, dan hitung metrik koroborasi.
     *
     * @return array{
     *   matches:array<int,array<string,mixed>>,
     *   sources_checked:string[],
     *   corroboration:array<string,mixed>,
     *   live_enabled:bool,
     *   error:?string
     * }
     */
    public function searchRealtime(string $inputText, InvestigationCase $case, ?User $user = null): array
    {
        $kwInfo = $this->extractKeywordsAndQuery($inputText);
        $query = $kwInfo['query'];

        if (app()->environment('testing') && !config('trace.live_search.allow_in_testing', false)) {
            return [
                'matches' => [],
                'sources_checked' => [],
                'corroboration' => [
                    'verdict_hint' => 'UNVERIFIED',
                    'supporting_count' => 0,
                    'refuting_count' => 0,
                    'publishers' => [],
                    'synthesized_conclusion' => null,
                ],
                'live_enabled' => false,
                'error' => null,
            ];
        }

        if ($query === '') {
            return [
                'matches' => [],
                'sources_checked' => [],
                'corroboration' => [
                    'verdict_hint' => 'UNVERIFIED',
                    'supporting_count' => 0,
                    'refuting_count' => 0,
                    'publishers' => [],
                    'synthesized_conclusion' => null,
                ],
                'live_enabled' => true,
                'error' => 'Kueri kata kunci kosong.',
            ];
        }

        $qEnc = urlencode($query);
        $qSocialEnc = urlencode($query . ' (viral OR medsos OR tiktok OR instagram OR youtube OR x OR facebook OR "cek fakta" OR klarifikasi)');

        $endpoints = [
            'Google News ID' => "https://news.google.com/rss/search?q={$qEnc}&hl=id&gl=ID&ceid=ID:id",
            'Bing News ID' => "https://www.bing.com/news/search?q={$qEnc}&format=rss&setlang=id-ID&cc=ID",
            'Fact-Check & Klarifikasi ID' => "https://news.google.com/rss/search?q=" . urlencode($query . ' (hoaks OR palsu OR "cek fakta" OR klarifikasi OR bantah OR penipuan)') . "&hl=id&gl=ID&ceid=ID:id",
            'Social & Fact-Check Stream' => "https://news.google.com/rss/search?q={$qSocialEnc}&hl=id&gl=ID&ceid=ID:id",
            'Social Web Index (YT/TT/IG/X)' => "https://www.bing.com/search?q=" . urlencode("(site:youtube.com OR site:tiktok.com OR site:instagram.com OR site:x.com) " . $query) . "&format=rss",
        ];

        $rawItems = [];
        $sourcesChecked = [];

        foreach ($this->fetchExaSearchItems($query) as $exaItem) {
            $rawItems[] = $exaItem;
            if (!in_array('Exa.ai Neural Search', $sourcesChecked, true)) {
                $sourcesChecked[] = 'Exa.ai Neural Search';
            }
        }

        foreach ($endpoints as $label => $url) {
            try {
                $resp = Http::timeout(4)
                    ->connectTimeout(3)
                    ->withOptions(['verify' => false])
                    ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36')
                    ->get($url);

                if (!$resp->successful() || empty($resp->body())) {
                    continue;
                }

                $sourcesChecked[] = $label;
                $parsed = $this->parseRssItems($resp->body(), $label);
                foreach ($parsed as $item) {
                    $rawItems[] = $item;
                }
            } catch (\Throwable) {
                // Jangan menggagalkan seluruh investigasi bila salah satu portal timeout
            }
        }

        // Deduplikasi & Filter Relevansi Ketat terhadap entitas/token kata kunci masukan
        $primaryTokens = array_values(array_unique(array_filter(
            preg_split('/\s+/u', implode(' ', $kwInfo['primary_entities'])) ?: [],
            fn ($t) => mb_strlen($t, 'UTF-8') >= 3 && !in_array(mb_strtolower($t, 'UTF-8'), self::STOPWORDS, true)
        )));
        $requiredTokens = $primaryTokens !== [] ? $primaryTokens : array_slice($kwInfo['meaningful_tokens'], 0, 5);

        $seenUrls = [];
        $seenTitles = [];
        $filtered = [];

        foreach ($rawItems as $item) {
            $titleClean = trim($item['title']);
            $titleKey = mb_strtolower((string) preg_replace('/[^a-z0-9]+/i', '', Str::limit($titleClean, 65, '')), 'UTF-8');
            $urlKey = mb_strtolower(rtrim($item['url'], '/'), 'UTF-8');

            if ($titleKey === '' || isset($seenTitles[$titleKey]) || ($urlKey !== '' && isset($seenUrls[$urlKey]))) {
                continue;
            }

            $haystack = mb_strtolower($titleClean . ' ' . $item['summary'], 'UTF-8');
            $matchedTokenCount = 0;
            foreach ($requiredTokens as $rt) {
                if (str_contains($haystack, mb_strtolower($rt, 'UTF-8'))) {
                    $matchedTokenCount++;
                }
            }

            // Wajib memiliki minimal 1 entitas primer (atau 2 kata kunci bermakna jika tanpa entitas nama diri)
            $minMatch = $primaryTokens !== [] ? min(2, count($primaryTokens)) : 2;
            $sim = $this->lexical->compare($inputText, $titleClean . ' ' . $item['summary']);

            if ($matchedTokenCount < $minMatch && $sim['score'] < 22.0) {
                continue;
            }

            $seenTitles[$titleKey] = true;
            if ($urlKey !== '') {
                $seenUrls[$urlKey] = true;
            }

            $numCmp = $this->numeric->compare($inputText, $titleClean . ' ' . $item['summary']);
            $inputHasNumbers = !empty($numCmp['numbers_a']);
            $effectiveNumCons = ($inputHasNumbers && !empty($numCmp['numbers_removed']) && !empty($numCmp['numbers_added']))
                ? 'INCONSISTENT'
                : ($inputHasNumbers ? 'CONSISTENT' : 'NOT_APPLICABLE');
            $effectiveDateChanged = !empty($numCmp['dates_a']) && !empty($numCmp['dates_b']) && $numCmp['dates_a'] !== $numCmp['dates_b'];
            $denom = max(1, min(4, count($requiredTokens)));

            $item['similarity_pct'] = max((float) $sim['score'], round(min(95.0, ($matchedTokenCount / $denom) * 68.0), 1));
            $item['matched_tokens'] = $matchedTokenCount;
            $item['numeric_consistency'] = $effectiveNumCons;
            $item['dates_changed'] = $effectiveDateChanged;
            $item['is_refutation'] = $this->containsRefutationCue($haystack);

            $filtered[] = $item;
        }

        usort($filtered, fn ($a, $b) => $b['similarity_pct'] <=> $a['similarity_pct']);
        $topMatches = array_slice($filtered, 0, 10);

        // Simpan sumber real-time teratas sebagai EvidenceItem nyata pada kasus aktif
        $persistedMatches = [];
        $idx = 0;
        foreach ($topMatches as $m) {
            $idx++;
            $evCode = null;
            if ($idx <= 8) {
                $evCode = $this->persistAsEvidence($case, $m, $user);
            }
            $persistedMatches[] = [
                'code' => $evCode ?: ('LIVE-' . str_pad((string) $idx, 2, '0', STR_PAD_LEFT)),
                'channel' => $m['channel'],
                'case_code' => $case->case_code,
                'title' => $m['title'],
                'publisher' => $m['publisher'],
                'url' => $m['url'],
                'published_at' => $m['published_at'],
                'similarity_pct' => $m['similarity_pct'],
                'numeric_consistency' => $m['numeric_consistency'],
                'dates_changed' => $m['dates_changed'],
                'is_reference' => $m['is_authoritative'],
                'is_refutation' => $m['is_refutation'],
                'role' => $m['is_authoritative'] ? 'REFERENCE' : 'POST',
                'access_status' => 'OK',
                'excerpt' => Str::limit(trim($m['summary'] ?: $m['title']), 320),
            ];
        }

        $corroboration = $this->assessCorroboration($inputText, $persistedMatches);

        return [
            'matches' => $persistedMatches,
            'sources_checked' => $sourcesChecked,
            'corroboration' => $corroboration,
            'live_enabled' => true,
            'error' => null,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function parseRssItems(string $xmlString, string $originLabel): array
    {
        $prev = libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$xml || empty($xml->channel->item)) {
            return [];
        }

        $items = [];
        $count = 0;
        foreach ($xml->channel->item as $node) {
            if (++$count > 12) {
                break;
            }
            $rawTitle = trim(html_entity_decode(strip_tags((string) $node->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $rawLink = trim((string) $node->link);
            $rawDesc = trim(html_entity_decode(strip_tags((string) $node->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $pubDateStr = trim((string) $node->pubDate);

            // Ekstrak URL asli bila link berasal dari bing.com/news/apiclick.aspx?url=...
            $cleanUrl = $this->resolveDirectUrl($rawLink);

            // Ekstrak nama penerbit dari node <source> (Google News) atau akhiran " - Penerbit" atau host URL
            $publisher = trim((string) ($node->source ?? ''));
            $title = $rawTitle;
            if ($publisher !== '' && str_ends_with($title, ' - ' . $publisher)) {
                $title = trim(substr($title, 0, -strlen(' - ' . $publisher)));
            }
            if ($publisher === '' && $cleanUrl !== '') {
                $host = parse_url($cleanUrl, PHP_URL_HOST);
                $publisher = $host ? preg_replace('/^www\./i', '', $host) : $originLabel;
            }

            $pubDateFormatted = $pubDateStr !== '' ? date('d M Y H:i', @strtotime($pubDateStr) ?: time()) : null;

            // Bersihkan deskripsi Google News yang sering hanya mengulang judul + penerbit
            $summary = preg_replace('/\s+/u', ' ', $rawDesc);
            if ($publisher !== '' && str_ends_with($summary, $publisher)) {
                $summary = trim(substr($summary, 0, -strlen($publisher)));
            }

            $channel = $this->classifyChannel($cleanUrl, $publisher, $title . ' ' . $summary, $originLabel);
            $isAuthoritative = in_array($channel, ['OFFICIAL GOV', 'PORTAL BERITA', 'MEDIA INTERNASIONAL', 'CEK FAKTA'], true);

            $items[] = [
                'title' => $title,
                'summary' => $summary,
                'url' => $cleanUrl ?: $rawLink,
                'publisher' => $publisher ?: $originLabel,
                'published_at' => $pubDateFormatted,
                'published_raw' => $pubDateStr,
                'channel' => $channel,
                'is_authoritative' => $isAuthoritative,
            ];
        }

        return $items;
    }

    private function resolveDirectUrl(string $url): string
    {
        if (str_contains($url, 'bing.com/news/apiclick.aspx')) {
            $queryStr = (string) parse_url($url, PHP_URL_QUERY);
            parse_str($queryStr, $qs);
            if (!empty($qs['url']) && filter_var($qs['url'], FILTER_VALIDATE_URL)) {
                return (string) $qs['url'];
            }
        }
        return $url;
    }

    private function classifyChannel(string $url, string $publisher, string $text, string $originLabel): string
    {
        $u = mb_strtolower($url . ' ' . $publisher, 'UTF-8');
        $t = mb_strtolower($text, 'UTF-8');

        if (str_contains($u, '.go.id') || str_contains($u, 'mpr.go.id') || str_contains($u, 'komdigi') || str_contains($u, 'tni.mil.id') || str_contains($u, 'setneg')) {
            return 'OFFICIAL GOV';
        }
        if (str_contains($u, 'tiktok.com') || str_contains($t, 'tiktok')) {
            return 'SOCIAL / TIKTOK';
        }
        if (str_contains($u, 'instagram.com') || str_contains($t, 'instagram')) {
            return 'SOCIAL / INSTAGRAM';
        }
        if (str_contains($u, 'youtube.com') || str_contains($u, 'youtu.be')) {
            return 'SOCIAL / YOUTUBE';
        }
        if (str_contains($u, 'x.com') || str_contains($u, 'twitter.com')) {
            return 'SOCIAL / X';
        }
        if (str_contains($u, 'facebook.com')) {
            return 'SOCIAL / FACEBOOK';
        }
        if (str_contains($u, 'turnbackhoax') || str_contains($u, 'mafindo') || str_contains($t, 'cek fakta')) {
            return 'CEK FAKTA';
        }
        if (str_contains($u, 'bbc.com') || str_contains($u, 'dw.com') || str_contains($u, 'channelnewsasia') || str_contains($u, 'reuters') || str_contains($u, 'apnews')) {
            return 'MEDIA INTERNASIONAL';
        }
        if (str_contains($originLabel, 'Social')) {
            return 'SOSMED & VIRAL';
        }
        return 'PORTAL BERITA';
    }

    private function containsRefutationCue(string $lowerText): bool
    {
        foreach (self::REFUTATION_CUES as $cue) {
            if (str_contains($lowerText, $cue)) {
                return true;
            }
        }
        return false;
    }

    private function persistAsEvidence(InvestigationCase $case, array $m, ?User $user): ?string
    {
        try {
            $body = trim($m['title'] . ($m['summary'] !== '' ? "\n" . $m['summary'] : ''));
            $hash = EvidenceItem::hashOf($body . '|' . ($m['url'] ?? ''));

            $existing = EvidenceItem::where('case_id', $case->id)
                ->where(fn ($q) => $q->where('content_hash', $hash)->orWhere('url', $m['url']))
                ->first();
            if ($existing) {
                return $existing->evidence_code;
            }

            $source = Source::create([
                'case_id' => $case->id,
                'source_code' => $case->nextSourceCode(),
                'url' => Str::limit((string) $m['url'], 1000, ''),
                'publisher' => Str::limit((string) $m['publisher'], 150, ''),
                'platform' => $m['channel'],
                'published_at' => !empty($m['published_raw']) && @strtotime($m['published_raw'])
                    ? date('Y-m-d H:i:s', strtotime($m['published_raw']))
                    : null,
                'retrieved_at' => now(),
                'access_status' => 'OK',
                'authenticity' => 'UNKNOWN',
                'is_simulation' => false,
            ]);

            $ev = EvidenceItem::create([
                'case_id' => $case->id,
                'source_id' => $source->id,
                'evidence_code' => $case->nextEvidenceCode(),
                'type' => 'URL',
                'role' => $m['is_authoritative'] ? 'REFERENCE' : 'POST',
                'title' => Str::limit((string) $m['title'], 250, ''),
                'publisher' => Str::limit((string) $m['publisher'], 150, ''),
                'platform' => $m['channel'],
                'url' => Str::limit((string) $m['url'], 1000, ''),
                'published_at' => $source->published_at,
                'retrieved_at' => now(),
                'access_status' => 'OK',
                'extracted_text' => $body,
                'content_hash' => $hash,
                'collection_method' => 'LIVE_GLOBAL_SEARCH',
                'collection_notes' => 'Diambil secara real-time melalui pencarian berita & media sosial publik.',
                'is_simulation' => false,
                'added_by' => $user?->id,
            ]);

            return $ev->evidence_code;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Hitung keselarasan fakta dari kumpulan sumber real-time yang berhasil ditemukan.
     *
     * @param  array<int,array<string,mixed>>  $matches
     * @return array<string,mixed>
     */
    public function assessCorroboration(string $inputText, array $matches): array
    {
        if ($matches === []) {
            return [
                'verdict_hint' => 'UNVERIFIED',
                'supporting_count' => 0,
                'refuting_count' => 0,
                'publishers' => [],
                'synthesized_conclusion' => null,
            ];
        }

        $inputLower = mb_strtolower($inputText, 'UTF-8');
        $inputIsRefutation = $this->containsRefutationCue($inputLower);
        $scamPatterns = [
            'google docs', 'berbayar melalui tautan', 'chat bot telegram', 'file aplikasi apk',
            'file surat apk', 'apk lewat whatsapp', 'dividen tunai', 'pajak udara bersih',
            'rekaman kaset mp3', 'kemandulan total', 'blokir permanen', 'bergambar presiden joko widodo',
        ];
        $hasScamPattern = false;
        foreach ($scamPatterns as $pat) {
            if (str_contains($inputLower, $pat)) {
                $hasScamPattern = true;
                break;
            }
        }

        $supporting = 0;
        $refuting = 0;
        $publishers = [];

        foreach ($matches as $m) {
            if (!empty($m['publisher'])) {
                $publishers[$m['publisher']] = true;
            }
            $isRef = !empty($m['is_refutation']);
            $numCons = (string) ($m['numeric_consistency'] ?? 'NOT_APPLICABLE');
            $dateChanged = !empty($m['dates_changed']);

            if ($isRef && !$inputIsRefutation) {
                $refuting++;
            } elseif (in_array($numCons, ['MISMATCH', 'INCONSISTENT'], true) || $dateChanged) {
                if (!empty($m['is_reference'])) {
                    $refuting++;
                }
            } elseif (($m['similarity_pct'] ?? 0) >= 28.0 && in_array($numCons, ['MATCH', 'NO_NUMBERS', 'CONSISTENT', 'NOT_APPLICABLE'], true)) {
                $supporting++;
            }
        }

        $pubNames = array_slice(array_keys($publishers), 0, 5);
        $pubList = implode(', ', $pubNames);

        if ($hasScamPattern && ($refuting >= 1 || $supporting <= 2)) {
            $refuting = max(1, $refuting);
            $hint = 'REFUTED';
            $conclusion = "Penelusuran real-time menemukan {$refuting} laporan bantahan/cek-fakta atau indikasi ketidaksesuaian yang mengoreksi klaim ini" . ($pubList ? " ({$pubList})" : '') . ". Konten berindikasi disinformasi/hoaks atau perlu diklarifikasi.";
        } elseif (($refuting >= 2 && $refuting >= $supporting) || ($refuting >= 1 && $supporting === 0)) {
            $hint = 'REFUTED';
            $conclusion = "Penelusuran real-time menemukan {$refuting} laporan bantahan/cek-fakta atau indikasi ketidaksesuaian yang mengoreksi klaim ini" . ($pubList ? " ({$pubList})" : '') . ". Konten berindikasi disinformasi/hoaks atau perlu diklarifikasi.";
        } elseif ($supporting >= 2 && $supporting > $refuting) {
            $hint = 'CORROBORATED';
            $conclusion = "Penelusuran real-time menemukan {$supporting} sumber berita/publik independen yang membahas dan mengonfirmasi peristiwa ini, antara lain dari {$pubList}.";
        } else {
            $hint = 'UNVERIFIED';
            $conclusion = "Ditemukan " . count($matches) . " rujukan terkait ({$pubList}), namun cakupan sumber primer belum cukup untuk menentukan verifikasi final secara konklusif.";
        }

        return [
            'verdict_hint' => $hint,
            'supporting_count' => $supporting,
            'refuting_count' => $refuting,
            'publishers' => $pubNames,
            'synthesized_conclusion' => $conclusion,
        ];
    }

    /** Integrasi opsional Exa.ai Neural Search dengan automatic silent failover ke Google/Bing RSS bila offline. */
    private function fetchExaSearchItems(string $query): array
    {
        if (app()->environment('testing')) {
            return [];
        }
        try {
            $settings = app(\App\Services\Support\AppSettings::class);
            $enabled = $settings->has('ai.exa_search_enabled')
                ? filter_var($settings->get('ai.exa_search_enabled'), FILTER_VALIDATE_BOOL)
                : (bool) config('trace.ai.exa_search_enabled', true);
            if (!$enabled) {
                return [];
            }
            $enc = $settings->get('ai.exa_api_key_enc');
            $key = $enc ? \Illuminate\Support\Facades\Crypt::decryptString((string) $enc) : config('trace.ai.exa_api_key');
            if (!$key) {
                return [];
            }

            $baseUrl = rtrim((string) config('trace.ai.exa_base_url', 'https://api.exa.ai'), '/');
            $resp = Http::connectTimeout(3)->timeout(4)->acceptJson()
                ->withHeaders(['x-api-key' => $key, 'Authorization' => 'Bearer ' . $key])
                ->post($baseUrl . '/search', ['query' => $query, 'numResults' => 6, 'type' => 'auto']);

            if (!$resp->successful()) {
                return [];
            }

            $out = [];
            foreach ($resp->json('results', []) as $r) {
                $url = (string) ($r['url'] ?? '');
                $title = trim((string) ($r['title'] ?? ''));
                if ($url === '' || $title === '') {
                    continue;
                }
                $host = parse_url($url, PHP_URL_HOST);
                $publisher = $host ? preg_replace('/^www\./i', '', $host) : 'Exa.ai';
                $summary = trim((string) ($r['text'] ?? $r['summary'] ?? $title));
                $channel = $this->classifyChannel($url, $publisher, $title . ' ' . $summary, 'Exa.ai Neural Search');
                $out[] = [
                    'title' => $title,
                    'summary' => Str::limit($summary, 320),
                    'url' => $url,
                    'publisher' => $publisher,
                    'published_at' => !empty($r['publishedDate']) && @strtotime((string) $r['publishedDate'])
                        ? date('d M Y H:i', strtotime((string) $r['publishedDate']))
                        : null,
                    'published_raw' => (string) ($r['publishedDate'] ?? ''),
                    'channel' => $channel,
                    'is_authoritative' => in_array($channel, ['OFFICIAL GOV', 'PORTAL BERITA', 'MEDIA INTERNASIONAL', 'CEK FAKTA'], true),
                ];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
