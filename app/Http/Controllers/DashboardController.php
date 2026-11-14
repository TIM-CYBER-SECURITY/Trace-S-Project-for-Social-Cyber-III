<?php

namespace App\Http\Controllers;

use App\Models\Connector;
use App\Models\EvidenceItem;
use App\Models\InvestigationCase;
use App\Models\Narrative;
use App\Models\NarrativeCluster;
use App\Models\RssFeed;
use App\Models\ThreatAnalysis;
use App\Services\Analysis\Lexicon;
use App\Services\DataAcquisition\EvidenceCollectorService;
use App\Services\DataAcquisition\RssIngestionService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Peta agregasi wilayah ke segmen jaringan netral (non-PII, non-KODAM, subnet /24 + ASN ISP).
     * Hanya dipopulasikan untuk wilayah yang memiliki rekaman Narrative aktual di database.
     */
    private const NETWORK_CATALOG = [
        'Jakarta' => [
            'id' => 'EXP-JW-01',
            'island' => 'Jawa',
            'regency' => 'Jakarta Pusat',
            'district' => 'Kec. Gambir',
            'subnet' => '103.247.12.0/24',
            'asn' => 'AS23693 (PT Telkom Indonesia)',
        ],
        'Surabaya' => [
            'id' => 'EXP-JW-02',
            'island' => 'Jawa',
            'regency' => 'Kota Surabaya',
            'district' => 'Kec. Gubeng',
            'subnet' => '103.247.112.0/24',
            'asn' => 'AS23693 (PT Telkom Indonesia)',
        ],
        'Medan' => [
            'id' => 'EXP-SM-01',
            'island' => 'Sumatera',
            'regency' => 'Kota Medan',
            'district' => 'Kec. Medan Barat',
            'subnet' => '114.122.40.0/24',
            'asn' => 'AS17974 (PT Telekomunikasi Indonesia)',
        ],
        'Balikpapan' => [
            'id' => 'EXP-KL-01',
            'island' => 'Kalimantan',
            'regency' => 'Kota Balikpapan',
            'district' => 'Kec. Balikpapan Kota',
            'subnet' => '182.253.88.0/24',
            'asn' => 'AS7713 (PT Telekomunikasi Indonesia)',
        ],
        'Makassar' => [
            'id' => 'EXP-SL-01',
            'island' => 'Sulawesi',
            'regency' => 'Kota Makassar',
            'district' => 'Kec. Panakkukang',
            'subnet' => '125.164.20.0/24',
            'asn' => 'AS7713 (PT Telekomunikasi Indonesia)',
        ],
        'Jayapura' => [
            'id' => 'EXP-PP-01',
            'island' => 'Papua',
            'regency' => 'Kota Jayapura',
            'district' => 'Kec. Jayapura Utara',
            'subnet' => '103.136.44.0/24',
            'asn' => 'AS7713 (PT Telekomunikasi Indonesia)',
        ],
    ];

    public function index()
    {
        $orgId = Auth::user()->organization_id;

        $narrativesCount = Narrative::forOrganization($orgId)->count();
        $criticalCount = ThreatAnalysis::where('threat_level', 'KRITIS')
            ->whereHas('case', fn ($q) => $q->forOrganization($orgId))
            ->count();
        $activeClustersCount = NarrativeCluster::whereHas('case', fn ($q) => $q->forOrganization($orgId))->count();
        $casesCount = InvestigationCase::forOrganization($orgId)->count();

        $negatives = Narrative::forOrganization($orgId)->where('sentiment', 'NEGATIF')->count();
        $sentimentRatio = $narrativesCount > 0 ? round(($negatives / $narrativesCount) * 100, 1) : 0.0;

        $narratives = Narrative::forOrganization($orgId)->with(['case', 'cluster'])
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->take(25)
            ->get();

        $exposureGrid = $this->buildExposureGrid($orgId);
        $trajectory = $this->buildTrajectorySeries(now()->subHours(24), now(), $orgId);

        $connectors = Connector::orderBy('priority')->get();
        $rssFeeds = RssFeed::where('is_active', true)->orderBy('name')->get();
        $connectedSourcesCount = InvestigationCase::forOrganization($orgId)->withCount('sources')->get()->sum('sources_count');
        $recentCases = InvestigationCase::forOrganization($orgId)->with(['creator', 'analyses'])->latest()->take(5)->get();
        $hasSimulationData = Narrative::forOrganization($orgId)->where('is_simulation', true)->exists()
            || InvestigationCase::forOrganization($orgId)->where('is_simulation', true)->exists();

        return view('dashboard.index', compact(
            'narrativesCount',
            'criticalCount',
            'activeClustersCount',
            'casesCount',
            'sentimentRatio',
            'narratives',
            'exposureGrid',
            'trajectory',
            'connectors',
            'rssFeeds',
            'connectedSourcesCount',
            'recentCases',
            'hasSimulationData'
        ));
    }

    public function storeNarrative(Request $request, AuditLogger $audit)
    {
        $validated = $request->validate([
            'title' => 'required|string|min:5|max:250',
            'region' => 'required|string|max:60',
            'category' => 'required|string|max:60',
            'severity' => 'required|in:AUTO,RENDAH,SEDANG,TINGGI,KRITIS',
            'sentiment' => 'required|in:AUTO,NEGATIF,NETRAL,POSITIF',
            'case_id' => 'nullable|exists:cases,id',
        ]);

        $sentiment = $validated['sentiment'] === 'AUTO'
            ? RssIngestionService::inferSentiment($validated['title'])
            : $validated['sentiment'];
        $severity = $validated['severity'] === 'AUTO'
            ? RssIngestionService::inferSeverity($validated['title'])
            : $validated['severity'];

        $orgId = Auth::user()->organization_id;
        $scopedCaseId = null;
        if (!empty($validated['case_id'])) {
            $scopedCaseId = InvestigationCase::forOrganization($orgId)->findOrFail($validated['case_id'])->id;
        }

        $narrative = Narrative::create([
            'title' => trim($validated['title']),
            'region' => trim($validated['region']),
            'category' => trim($validated['category']),
            'sentiment' => $sentiment,
            'severity' => $severity,
            'case_id' => $scopedCaseId,
            'organization_id' => $orgId,
            'captured_at' => now(),
            'is_simulation' => false,
        ]);

        $audit->log('NARRATIVE_LOGGED', $narrative, [
            'region' => $narrative->region,
            'severity' => $narrative->severity,
            'sentiment' => $narrative->sentiment,
            'organization_id' => $orgId,
            'organization_code' => Auth::user()->organization?->code,
            'device_code' => \App\Models\User::computeDeviceCode($request->ip(), $request->userAgent()),
        ], Auth::id());

        return redirect()->route('early-warning')
            ->with('success', "Narasi operasional berhasil dicatat ({$narrative->region} • {$narrative->severity}/{$narrative->sentiment}).");
    }

    public function escalateNarrative(int $id, AuditLogger $audit)
    {
        $orgId = Auth::user()->organization_id;
        $narrative = Narrative::forOrganization($orgId)->findOrFail($id);
        if ($narrative->case_id && ($existing = InvestigationCase::forOrganization($orgId)->find($narrative->case_id))) {
            return redirect()->route('cases.show', $existing->id)
                ->with('success', "Narasi sudah terhubung dengan kasus {$existing->case_code}.");
        }

        $sev = strtoupper((string) ($narrative->severity ?: 'SEDANG'));
        $priority = in_array($sev, ['RENDAH', 'SEDANG', 'TINGGI', 'KRITIS'], true) ? $sev : 'SEDANG';

        $case = InvestigationCase::create([
            'case_code' => InvestigationCase::nextCode(),
            'title' => mb_substr(trim((string) $narrative->title), 0, 140),
            'claim_text' => (string) $narrative->title,
            'category' => $narrative->category ?: 'DISINFORMASI STRATEGIS / KOGNITIF',
            'priority' => $priority,
            'description' => "Dieskalasi dari Early Warning (Wilayah: " . ($narrative->region ?: 'Nasional') . ", Sentimen: " . ($narrative->sentiment ?: 'NETRAL') . ").",
            'status' => 'OPEN',
            'is_simulation' => false,
            'organization_id' => $orgId,
            'created_by' => Auth::id(),
        ]);

        $narrative->update(['case_id' => $case->id]);

        $audit->log('CASE_ESCALATED_FROM_NARRATIVE', $case, [
            'narrative_id' => $narrative->id,
            'case_code' => $case->case_code,
            'region' => $narrative->region,
        ], Auth::id());

        return redirect()->route('cases.show', $case->id)
            ->with('success', "Narasi dieskalasi menjadi kasus investigasi {$case->case_code}.");
    }

    public function syncRss(RssIngestionService $rss)
    {
        $result = $rss->syncActiveFeeds(Auth::user());
        $msg = "Sinkronisasi {$result['feeds_checked']} feed RSS selesai: {$result['imported']} narasi baru masuk ke database.";
        if (!empty($result['errors'])) {
            return redirect()->route('early-warning')
                ->with($result['imported'] > 0 ? 'warning' : 'error', $msg . ' Catatan: ' . implode(' | ', array_slice($result['errors'], 0, 2)));
        }
        return redirect()->route('early-warning')->with('success', $msg);
    }

    public function storeRssFeed(Request $request, AuditLogger $audit)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'feed_url' => 'required|url|max:500',
        ]);

        if ($err = EvidenceCollectorService::validatePublicUrl($validated['feed_url'])) {
            return back()->with('error', 'URL feed ditolak oleh kebijakan anti-SSRF: ' . $err)->withInput();
        }

        $feed = RssFeed::create([
            'name' => trim($validated['name']),
            'feed_url' => trim($validated['feed_url']),
            'is_active' => true,
        ]);

        $audit->log('RSS_FEED_ADDED', $feed, ['name' => $feed->name, 'url' => $feed->feed_url], Auth::id());

        return back()->with('success', "Sumber RSS '{$feed->name}' berhasil ditambahkan.");
    }

    public function toggleRssFeed(int $id, AuditLogger $audit)
    {
        $feed = RssFeed::findOrFail($id);
        $feed->update(['is_active' => !$feed->is_active]);

        $audit->log('RSS_FEED_TOGGLED', $feed, [
            'name' => $feed->name,
            'is_active' => $feed->is_active,
        ], Auth::id());

        return back()->with('success', "Sumber RSS '{$feed->name}' kini " . ($feed->is_active ? 'AKTIF' : 'NONAKTIF') . '.');
    }

    public function destroyRssFeed(int $id, AuditLogger $audit)
    {
        $feed = RssFeed::findOrFail($id);
        $name = $feed->name;

        $audit->log('RSS_FEED_DELETED', $feed, [
            'name' => $name,
            'url' => $feed->feed_url,
        ], Auth::id());

        $feed->delete();

        return back()->with('success', "Sumber RSS '{$name}' telah dihapus.");
    }

    public function sentiment()
    {
        $orgId = Auth::user()->organization_id;

        $narrativesCount = Narrative::forOrganization($orgId)->count();
        $negatives = Narrative::forOrganization($orgId)->where('sentiment', 'NEGATIF')->count();
        $positives = Narrative::forOrganization($orgId)->where('sentiment', 'POSITIF')->count();
        $neutrals = max(0, $narrativesCount - $negatives - $positives);

        $trajectory = $this->buildTrajectorySeries(now()->subHours(24), now(), $orgId);
        $tonePatterns = $this->computeLexiconTonePatterns($orgId);
        $connectors = Connector::orderBy('priority')->get();
        $recentCases = InvestigationCase::forOrganization($orgId)->latest()->take(5)->get();
        $hasSimulationData = Narrative::forOrganization($orgId)->where('is_simulation', true)->exists()
            || EvidenceItem::where('is_simulation', true)->whereHas('case', fn ($q) => $q->forOrganization($orgId))->exists();

        return view('sentiment.index', compact(
            'narrativesCount',
            'negatives',
            'positives',
            'neutrals',
            'trajectory',
            'tonePatterns',
            'connectors',
            'recentCases',
            'hasSimulationData'
        ));
    }

    /**
     * Polling JSON Feed nyata berbasis basis data Trace-S untuk rentang waktu yang dipilih.
     */
    public function threatFeed(Request $request)
    {
        $range = $request->query('range', '24h');
        $since = match ($range) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '7d' => now()->subDays(7),
            default => now()->subHours(24),
        };

        $orgId = Auth::user()->organization_id;

        $narrativeQuery = Narrative::forOrganization($orgId)->where(function ($q) use ($since) {
            $q->where('captured_at', '>=', $since)
                ->orWhere(function ($q2) use ($since) {
                    $q2->whereNull('captured_at')->where('created_at', '>=', $since);
                });
        });

        $narrativesCount = (clone $narrativeQuery)->count();
        $negatives = (clone $narrativeQuery)->where('sentiment', 'NEGATIF')->count();
        $sentimentRatio = $narrativesCount > 0 ? round(($negatives / $narrativesCount) * 100, 1) : 0.0;

        $criticalCount = ThreatAnalysis::where('threat_level', 'KRITIS')
            ->whereHas('case', fn ($q) => $q->forOrganization($orgId))
            ->where(function ($q) use ($since) {
                $q->where('analyzed_at', '>=', $since)->orWhere('created_at', '>=', $since);
            })->count();

        $activeClusters = NarrativeCluster::whereHas('case', fn ($q) => $q->forOrganization($orgId))
            ->where('created_at', '>=', $since)
            ->count();

        $rows = (clone $narrativeQuery)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->take(25)
            ->get();

        $logs = $rows->map(fn (Narrative $n) => [
            'id' => $n->id,
            'code' => 'NARR-' . str_pad((string) $n->id, 4, '0', STR_PAD_LEFT),
            'time' => ($n->captured_at ?? $n->created_at)?->format('H:i:s') ?? '-',
            'region' => $n->region ?? 'NATIONAL',
            'severity' => strtoupper((string) ($n->severity ?? 'RENDAH')),
            'sentiment' => strtoupper((string) ($n->sentiment ?? 'NETRAL')),
            'title' => $n->title,
            'category' => $n->category ?? 'DISINFORMASI',
            'case_id' => $n->case_id,
            'is_simulation' => (bool) $n->is_simulation,
        ])->values();

        return response()->json([
            'status' => 'OK',
            'is_simulation' => $rows->contains('is_simulation', true),
            'range' => $range,
            'metrics' => [
                'narratives_count' => $narrativesCount,
                'critical_count' => $criticalCount,
                'sentiment_ratio' => $sentimentRatio,
                'active_clusters' => $activeClusters,
            ],
            'trajectory' => $this->buildTrajectorySeries($since, now(), $orgId),
            'source_scope' => 'Active Trace-S internal database records',
            'data' => $logs,
        ]);
    }

    /**
     * Mengagregasi data Narrative aktual per wilayah ke Grid Paparan Jaringan netral (tanpa PII).
     * Tidak memproduksi data fiktif bila tabel narratives kosong.
     */
    private function buildExposureGrid(?int $orgId = null): array
    {
        $orgId = $orgId ?? Auth::user()?->organization_id;
        $narratives = Narrative::forOrganization($orgId)->with('cluster')->orderByDesc('captured_at')->get();
        if ($narratives->isEmpty()) {
            return [];
        }

        $grouped = $narratives->groupBy(fn (Narrative $n) => trim((string) ($n->region ?: 'Nasional')));
        $rows = [];
        $idx = 1;

        foreach ($grouped as $region => $items) {
            $meta = self::NETWORK_CATALOG[$region] ?? [
                'id' => 'EXP-REG-' . str_pad((string) $idx, 2, '0', STR_PAD_LEFT),
                'island' => 'Nasional',
                'regency' => $region,
                'district' => 'Agregasi Wilayah',
                'subnet' => 'Agregasi Subnet',
                'asn' => 'Agregat Jaringan Publik',
            ];

            $criticalOrHigh = $items->filter(fn (Narrative $n) => in_array(strtoupper((string) $n->severity), ['KRITIS', 'TINGGI'], true))->count();
            $velocity = $criticalOrHigh >= 2 || $items->count() >= 3
                ? 'HIGH'
                : ($criticalOrHigh >= 1 ? 'MODERATE' : 'NORMAL');

            $latest = $items->first();
            $rows[] = [
                'id' => $meta['id'],
                'island' => $meta['island'],
                'regency' => $meta['regency'],
                'district' => $meta['district'],
                'subnet' => $meta['subnet'],
                'asn' => $meta['asn'],
                'narrative_count' => $items->count(),
                'critical_count' => $criticalOrHigh,
                'velocity_status' => $velocity,
                'dominant_topic' => $latest?->title ?? '-',
                'is_simulation' => $items->contains('is_simulation', true),
            ];
            $idx++;
        }

        return $rows;
    }

    /**
     * Menghitung kurva seri waktu 7 bucket dari rekaman Narrative aktual pada jendela waktu [start, end].
     */
    private function buildTrajectorySeries(Carbon $start, Carbon $end, ?int $orgId = null): array
    {
        $orgId = $orgId ?? Auth::user()?->organization_id;
        $buckets = 7;
        $spanSeconds = max($buckets, $end->timestamp - $start->timestamp);
        $stepSeconds = $spanSeconds / ($buckets - 1);

        $points = [];
        $labels = [];
        for ($i = 0; $i < $buckets; $i++) {
            $t = Carbon::createFromTimestamp((int) round($start->timestamp + ($i * $stepSeconds)));
            $labels[] = $t->format('H:i');
            $points[$i] = ['total' => 0, 'negative' => 0, 'neutral' => 0, 'positive' => 0];
        }

        $items = Narrative::forOrganization($orgId)->where(function ($q) use ($start) {
            $q->where('captured_at', '>=', $start)
                ->orWhere(function ($q2) use ($start) {
                    $q2->whereNull('captured_at')->where('created_at', '>=', $start);
                });
        })->get();

        foreach ($items as $item) {
            $ts = ($item->captured_at ?? $item->created_at)?->timestamp ?? $end->timestamp;
            $ratio = max(0.0, min(1.0, ($ts - $start->timestamp) / $spanSeconds));
            $idx = (int) min($buckets - 1, round($ratio * ($buckets - 1)));
            $points[$idx]['total']++;
            $sent = strtoupper((string) $item->sentiment);
            if ($sent === 'NEGATIF') {
                $points[$idx]['negative']++;
            } elseif ($sent === 'POSITIF') {
                $points[$idx]['positive']++;
            } else {
                $points[$idx]['neutral']++;
            }
        }

        $maxVal = max(1, ...array_map(fn ($p) => $p['total'], $points));

        $toPath = function (string $key, int $height = 180) use ($points, $buckets, $maxVal): array {
            $coords = [];
            foreach ($points as $i => $p) {
                $x = (int) round(($i / max(1, $buckets - 1)) * 600);
                $val = $p[$key];
                $y = $val === 0
                    ? $height
                    : (int) max(10, round($height - (($val / $maxVal) * ($height - 20))));
                $coords[] = ['x' => $x, 'y' => $y, 'val' => $val];
            }
            $d = implode(' L ', array_map(fn ($c) => $c['x'] . ' ' . $c['y'], $coords));
            $path = 'M ' . $d;
            $area = $path . " L 600 {$height} L 0 {$height} Z";
            return ['path' => $path, 'area' => $area, 'coords' => $coords];
        };

        return [
            'labels' => $labels,
            'max_value' => $maxVal,
            'total' => $toPath('total', 190),
            'negative' => $toPath('negative', 190),
            'neutral' => $toPath('neutral', 190),
            'positive' => $toPath('positive', 190),
            'sentiment_negative' => $toPath('negative', 175),
            'sentiment_neutral' => $toPath('neutral', 175),
            'sentiment_positive' => $toPath('positive', 175),
        ];
    }

    /**
     * Menghitung distribusi pola bahasa berbasis Leksikon Indonesia atas korpus Narrative & EvidenceItem aktual.
     */
    private function computeLexiconTonePatterns(?int $orgId = null): array
    {
        $orgId = $orgId ?? Auth::user()?->organization_id;
        $texts = Narrative::forOrganization($orgId)->pluck('title')
            ->merge(
                EvidenceItem::whereNotNull('extracted_text')
                    ->whereHas('case', fn ($q) => $q->forOrganization($orgId))
                    ->pluck('extracted_text')
            )
            ->filter()
            ->values();

        $categories = [
            'termination_alarm' => [
                'id' => 'Pola Penekanan / Penghentian',
                'en' => 'Termination / Escalation Pattern',
                'cues' => array_merge(Lexicon::ACTIONS['TERMINATE'], Lexicon::ACTIONS['REDUCE']),
                'count' => 0,
            ],
            'rumor_uncertainty' => [
                'id' => 'Pola Dugaan / Rumor / Wacana',
                'en' => 'Rumor / Speculation / Plan Pattern',
                'cues' => array_merge(Lexicon::DUGAAN, Lexicon::RENCANA),
                'count' => 0,
            ],
            'opinion_dispute' => [
                'id' => 'Pola Opini / Penolakan',
                'en' => 'Opinion / Skepticism Pattern',
                'cues' => Lexicon::OPINI,
                'count' => 0,
            ],
            'official_statement' => [
                'id' => 'Pola Pernyataan Resmi / Konfirmasi',
                'en' => 'Official Statement Pattern',
                'cues' => Lexicon::RESMI_KUAT,
                'count' => 0,
            ],
            'continuity_support' => [
                'id' => 'Pola Kelanjutan / Klarifikasi',
                'en' => 'Continuity / Clarification Pattern',
                'cues' => Lexicon::ACTIONS['CONTINUE'],
                'count' => 0,
            ],
        ];

        $totalHits = 0;
        foreach ($texts as $t) {
            $txt = (string) $t;
            foreach ($categories as $k => $meta) {
                $matched = Lexicon::matchCues($txt, $meta['cues']);
                if ($matched !== []) {
                    $categories[$k]['count'] += count($matched);
                    $totalHits += count($matched);
                }
            }
        }

        $out = [];
        foreach ($categories as $k => $meta) {
            $out[] = [
                'key' => $k,
                'id' => $meta['id'],
                'en' => $meta['en'],
                'count' => $meta['count'],
                'pct' => $totalHits > 0 ? round(($meta['count'] / $totalHits) * 100, 1) : 0.0,
            ];
        }

        return $out;
    }
}
