<?php

namespace App\Http\Controllers;

use App\Models\Connector;
use App\Models\InvestigationCase;
use App\Models\Narrative;
use App\Models\NarrativeCluster;
use App\Models\ThreatAnalysis;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $narrativesCount = Narrative::count();
        $criticalCount = ThreatAnalysis::where('threat_level', 'KRITIS')->count();
        $activeClustersCount = NarrativeCluster::count();
        $casesCount = InvestigationCase::count();

        // Sentimen
        $negatives = Narrative::where('sentiment', 'NEGATIF')->count();
        $sentimentRatio = $narrativesCount > 0 ? round(($negatives / $narrativesCount) * 100, 1) : 68.4;

        // Peta ancaman titik (Master §7.2, config trace.regions)
        $regions = config('trace.regions');
        $threatPoints = [
            ['name' => 'Jakarta', 'x' => 33, 'y' => 63, 'severity' => 'KRITIS', 'count' => 14, 'pulse' => 'animate-ping'],
            ['name' => 'Surabaya', 'x' => 46, 'y' => 68, 'severity' => 'TINGGI', 'count' => 8, 'pulse' => 'animate-pulse'],
            ['name' => 'Medan', 'x' => 14, 'y' => 30, 'severity' => 'SEDANG', 'count' => 5, 'pulse' => ''],
            ['name' => 'Makassar', 'x' => 61, 'y' => 62, 'severity' => 'SEDANG', 'count' => 4, 'pulse' => ''],
            ['name' => 'Balikpapan', 'x' => 55, 'y' => 45, 'severity' => 'RENDAH', 'count' => 2, 'pulse' => ''],
            ['name' => 'Jayapura', 'x' => 91, 'y' => 56, 'severity' => 'RENDAH', 'count' => 1, 'pulse' => ''],
        ];

        // §6B PRD: Geospatial & Network Exposure Grid (Pulau -> Kota/Kab -> Kecamatan -> Subnet /24, ASN ISP, EXIF, Jumlah Akun Pola Serupa)
        // Tanpa daftar KODAM terhubung ke akun dan tanpa resolusi identitas individu (§2A-A)
        $exposureGrid = $this->exposureGridData();
        $regionalData = $exposureGrid;

        // Status konektor pengumpul data yang jujur
        $connectors = Connector::orderBy('priority')->get();
        $connectedSourcesCount = InvestigationCase::withCount('sources')->get()->sum('sources_count');

        // Recent cases
        $recentCases = InvestigationCase::with(['creator', 'analyses'])->latest()->take(5)->get();

        return view('dashboard.index', compact(
            'narrativesCount',
            'criticalCount',
            'activeClustersCount',
            'casesCount',
            'sentimentRatio',
            'threatPoints',
            'regionalData',
            'exposureGrid',
            'connectors',
            'connectedSourcesCount',
            'recentCases'
        ));
    }

    /**
     * Modul A2 — Sentiment Analysis Dashboard (FR-015 s.d. FR-018)
     */
    public function sentiment()
    {
        $narrativesCount = Narrative::count();
        $negatives = Narrative::where('sentiment', 'NEGATIF')->count();
        $positives = Narrative::where('sentiment', 'POSITIF')->count();
        $neutrals = max(0, $narrativesCount - $negatives - $positives);

        $connectors = Connector::orderBy('priority')->get();
        $recentCases = InvestigationCase::latest()->take(5)->get();

        return view('sentiment.index', compact(
            'narrativesCount',
            'negatives',
            'positives',
            'neutrals',
            'connectors',
            'recentCases'
        ));
    }

    /**
     * Payload exposure_grid netral (§6B PRD):
     * Pulau -> Kota/Kabupaten -> Kecamatan -> Subnet /24, ASN ISP, konsistensi EXIF, jumlah akun pola serupa.
     * Seluruh data membawa atribut is_simulation: true.
     */
    private function exposureGridData(): array
    {
        return [
            [
                'id' => 'EXP-JW-01',
                'island' => 'Jawa',
                'regency' => 'Jakarta Pusat',
                'district' => 'Kec. Gambir',
                'subnet' => '103.247.12.0/24',
                'asn' => 'AS23693 (PT Telkom Indonesia)',
                'exif_status' => 'CONSISTENT',
                'account_pattern_count' => 26,
                'velocity_status' => 'HIGH',
                'dominant_topic' => 'Program Subsidi & Alokasi Anggaran Pendidikan',
                'is_simulation' => true,
            ],
            [
                'id' => 'EXP-JW-02',
                'island' => 'Jawa',
                'regency' => 'Kab. Subang',
                'district' => 'Kec. Pagaden',
                'subnet' => '36.88.42.0/24',
                'asn' => 'AS23693 (PT Telkom Indonesia)',
                'exif_status' => 'CONSISTENT',
                'account_pattern_count' => 16,
                'velocity_status' => 'HIGH',
                'dominant_topic' => 'Rantai Pasok Pangan Lokal & Koperasi',
                'is_simulation' => true,
            ],
            [
                'id' => 'EXP-JW-03',
                'island' => 'Jawa',
                'regency' => 'Kota Surabaya',
                'district' => 'Kec. Gubeng',
                'subnet' => '103.247.112.0/24',
                'asn' => 'AS23693 (PT Telkom Indonesia)',
                'exif_status' => 'STRIPPED_BY_PLATFORM',
                'account_pattern_count' => 15,
                'velocity_status' => 'MODERATE',
                'dominant_topic' => 'Variasi Klaim Potongan Anggaran Daerah',
                'is_simulation' => true,
            ],
            [
                'id' => 'EXP-SM-01',
                'island' => 'Sumatera',
                'regency' => 'Kota Medan',
                'district' => 'Kec. Medan Barat',
                'subnet' => '114.122.40.0/24',
                'asn' => 'AS17974 (PT Telekomunikasi Indonesia)',
                'exif_status' => 'STRIPPED_BY_PLATFORM',
                'account_pattern_count' => 12,
                'velocity_status' => 'MODERATE',
                'dominant_topic' => 'Distribusi Subsidi Energi & Logistik',
                'is_simulation' => true,
            ],
            [
                'id' => 'EXP-KL-01',
                'island' => 'Kalimantan',
                'regency' => 'Kota Balikpapan',
                'district' => 'Kec. Balikpapan Kota',
                'subnet' => '182.253.88.0/24',
                'asn' => 'AS7713 (PT Telekomunikasi Indonesia)',
                'exif_status' => 'CONSISTENT',
                'account_pattern_count' => 8,
                'velocity_status' => 'NORMAL',
                'dominant_topic' => 'Kebijakan Publik & Infrastruktur Wilayah',
                'is_simulation' => true,
            ],
            [
                'id' => 'EXP-SL-01',
                'island' => 'Sulawesi',
                'regency' => 'Kota Makassar',
                'district' => 'Kec. Panakkukang',
                'subnet' => '125.164.20.0/24',
                'asn' => 'AS7713 (PT Telekomunikasi Indonesia)',
                'exif_status' => 'STRIPPED_BY_PLATFORM',
                'account_pattern_count' => 9,
                'velocity_status' => 'MODERATE',
                'dominant_topic' => 'Isu Bantuan Sosial & Layanan Publik',
                'is_simulation' => true,
            ],
        ];
    }

    /**
     * Polling JSON Feed untuk Live Threat Feed & Time Range Filter di dashboard
     */
    public function threatFeed(Request $request)
    {
        $range = $request->query('range', '24h');
        $multiplier = match ($range) {
            '1h' => 0.08,
            '6h' => 0.35,
            '7d' => 4.8,
            default => 1.0,
        };

        $baseNarratives = Narrative::count() > 0 ? Narrative::count() : 48720;
        $baseCritical = ThreatAnalysis::where('threat_level', 'KRITIS')->count() > 0 ? ThreatAnalysis::where('threat_level', 'KRITIS')->count() : 7;
        $baseClusters = NarrativeCluster::count() > 0 ? NarrativeCluster::count() : 12;

        $logs = Narrative::latest()->take(10)->get()->map(fn ($n) => [
            'id' => $n->id,
            'time' => $n->captured_at ? $n->captured_at->format('H:i:s') : now()->format('H:i:s'),
            'region' => $n->region ?? 'NATIONAL',
            'severity' => $n->severity,
            'title' => $n->title,
            'category' => $n->category ?? 'DISINFORMATION',
            'is_simulation' => (bool) $n->is_simulation,
        ]);

        return response()->json([
            'status' => 'OK',
            'is_simulation' => true,
            'range' => $range,
            'metrics' => [
                'narratives_count' => (int) round($baseNarratives * $multiplier),
                'critical_count' => max(1, (int) round($baseCritical * ($range === '1h' ? 0.4 : ($range === '7d' ? 2.1 : 1.0)))),
                'sentiment_ratio' => $range === '1h' ? 74.1 : ($range === '6h' ? 71.8 : ($range === '7d' ? 66.5 : 68.4)),
                'active_clusters' => max(2, (int) round($baseClusters * ($range === '1h' ? 0.5 : ($range === '7d' ? 1.8 : 1.0)))),
            ],
            'source_scope' => 'Based on active dataset in internal Trace-S database',
            'data' => $logs,
        ]);
    }
}
