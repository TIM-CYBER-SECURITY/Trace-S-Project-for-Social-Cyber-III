<?php

namespace App\Http\Controllers;

use App\Models\InvestigationCase;
use App\Models\ThreatActor;
use App\Services\Analysis\NarrativeClusterer;
use Illuminate\Http\Request;

class SpreadAnalysisController extends Controller
{
    public function __construct(private NarrativeClusterer $clusterer)
    {
    }

    public function index(Request $request)
    {
        $cases = InvestigationCase::with('actors')->latest()->get();
        $selectedCase = null;
        $caseId = $request->query('case_id');

        if ($caseId) {
            $selectedCase = InvestigationCase::with(['actors', 'clusters.items.evidence', 'evidence'])->find($caseId);
        } elseif ($cases->isNotEmpty()) {
            $selectedCase = $cases->first();
        }

        $actors = $selectedCase ? $selectedCase->actors()->orderBy('id')->get() : collect();
        $posts = $selectedCase ? $selectedCase->evidence()->whereIn('role', ['POST', 'SUBJECT'])->orderBy('published_at')->get() : collect();

        // Earliest post in dataset
        $earliestPost = $posts->first();

        // Platform stats
        $platformDistribution = $posts->groupBy('platform')->map->count();

        // Clusters
        $clusters = $selectedCase ? $selectedCase->clusters()->with('items.evidence')->get() : collect();

        return view('network.index', compact('cases', 'selectedCase', 'actors', 'posts', 'earliestPost', 'platformDistribution', 'clusters'));
    }
}
