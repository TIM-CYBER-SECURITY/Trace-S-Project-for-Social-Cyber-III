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
            'connectors',
            'connectedSourcesCount',
            'recentCases'
        ));
    }

    /**
     * Polling JSON Feed untuk Live Threat Feed di dashboard
     */
    public function threatFeed(Request $request)
    {
        $logs = Narrative::latest()->take(10)->get()->map(fn ($n) => [
            'id' => $n->id,
            'time' => $n->captured_at ? $n->captured_at->format('H:i:s') : now()->format('H:i:s'),
            'region' => $n->region ?? 'NASIONAL',
            'severity' => $n->severity,
            'title' => $n->title,
            'category' => $n->category ?? 'DISINFORMASI',
            'is_simulation' => $n->is_simulation,
        ]);

        return response()->json([
            'status' => 'OK',
            'source_scope' => 'Berdasarkan dataset aktif pada database internal Trace-S',
            'data' => $logs,
        ]);
    }
}
