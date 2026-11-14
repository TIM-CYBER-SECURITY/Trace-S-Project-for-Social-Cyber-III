<?php

namespace App\Http\Controllers;

use App\Models\InvestigationCase;
use App\Models\ThreatActor;
use App\Services\Analysis\NarrativeClusterer;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SpreadAnalysisController extends Controller
{
    public function __construct(
        private NarrativeClusterer $clusterer,
        private AuditLogger $audit
    ) {
    }

    public function index(Request $request)
    {
        $orgId = Auth::user()->organization_id;
        $cases = InvestigationCase::forOrganization($orgId)->with('actors')->latest()->get();
        $selectedCase = null;
        $caseId = $request->query('case_id');

        if ($caseId) {
            $selectedCase = InvestigationCase::forOrganization($orgId)->with(['actors', 'clusters.items.evidence', 'evidence'])->find($caseId);
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

    /**
     * Menjalankan Narrative Clustering dan menurunkan observasi pola akun non-PII dari bukti POST/SUBJECT.
     */
    public function cluster(Request $request)
    {
        $validated = $request->validate([
            'case_id' => 'required|exists:cases,id',
        ]);

        $case = InvestigationCase::forOrganization(Auth::user()->organization_id)->with(['evidence', 'actors'])->findOrFail($validated['case_id']);
        $res = $this->clusterer->run($case, Auth::user(), true);

        // Turunkan observasi handle publik bila belum ada rekaman aktor dari dataset POST/SUBJECT
        $derivedActors = 0;
        if ($case->actors()->count() === 0) {
            $posts = $case->evidence()
                ->whereIn('role', ['POST', 'SUBJECT'])
                ->whereNotNull('publisher')
                ->orderByRaw('COALESCE(published_at, retrieved_at, created_at) asc')
                ->get();

            $firstCluster = $case->clusters()->latest('id')->first();
            foreach ($posts->unique(fn ($p) => mb_strtolower((string) $p->publisher) . '|' . strtoupper((string) $p->platform)) as $idx => $post) {
                $handle = trim((string) $post->publisher);
                if ($handle === '') {
                    continue;
                }
                ThreatActor::create([
                    'case_id' => $case->id,
                    'platform' => strtoupper((string) ($post->platform ?: 'WEB')),
                    'username' => $handle,
                    'automation_indicator' => 'TIDAK DINILAI',
                    'indicator_basis' => 'Ekstraksi handle publik dari bukti ' . $post->evidence_code . ' (memerlukan tinjauan laju posting analis).',
                    'post_rate' => null,
                    'account_age_days' => null,
                    'cluster_id' => $firstCluster?->id,
                    'status' => 'PERLU DITINJAU',
                    'first_seen_at' => $post->published_at ?? $post->retrieved_at ?? now(),
                    'is_earliest_in_dataset' => $idx === 0,
                    'is_simulation' => (bool) ($case->is_simulation || $post->is_simulation),
                ]);
                $derivedActors++;
            }
        }

        $clusterCount = count($res['clusters'] ?? []);
        $unclusteredCount = count($res['unclustered'] ?? []);

        $this->audit->log('NETWORK_CLUSTER_EXECUTED', $case, [
            'case_code' => $case->case_code,
            'clusters' => $clusterCount,
            'unclustered' => $unclusteredCount,
            'derived_actors' => $derivedActors,
        ], Auth::id());

        return redirect()->route('network.index', ['case_id' => $case->id])
            ->with('success', "Klasterisasi narasi selesai: {$clusterCount} klaster terbentuk ({$unclusteredCount} entri tunggal), {$derivedActors} pola akun diturunkan.");
    }

    /**
     * Mencatat observasi pola akun publik (non-PII) pada kasus aktif.
     */
    public function storeActor(Request $request)
    {
        $validated = $request->validate([
            'case_id' => 'required|exists:cases,id',
            'username' => 'required|string|max:120',
            'platform' => 'required|string|max:40',
            'post_rate' => 'nullable|numeric|min:0|max:10000',
            'account_age_days' => 'nullable|integer|min:0|max:20000',
            'automation_indicator' => 'required|in:TIDAK DINILAI,RENDAH,SEDANG,TINGGI',
            'indicator_basis' => 'nullable|string|max:500',
            'is_earliest_in_dataset' => 'nullable|boolean',
        ]);

        $case = InvestigationCase::forOrganization(Auth::user()->organization_id)->findOrFail($validated['case_id']);
        $isEarliest = (bool) ($validated['is_earliest_in_dataset'] ?? false);
        if ($isEarliest) {
            ThreatActor::where('case_id', $case->id)->update(['is_earliest_in_dataset' => false]);
        }

        $actor = ThreatActor::create([
            'case_id' => $case->id,
            'platform' => strtoupper(trim($validated['platform'])),
            'username' => trim($validated['username']),
            'automation_indicator' => $validated['automation_indicator'],
            'indicator_basis' => $validated['indicator_basis'] ?? 'Observasi manual pola akun publik oleh analis.',
            'post_rate' => $validated['post_rate'] ?? null,
            'account_age_days' => $validated['account_age_days'] ?? null,
            'cluster_id' => $case->clusters()->latest('id')->value('id'),
            'status' => 'PERLU DITINJAU',
            'first_seen_at' => now(),
            'is_earliest_in_dataset' => $isEarliest,
            'is_simulation' => (bool) $case->is_simulation,
        ]);

        $this->audit->log('ACTOR_PATTERN_RECORDED', $actor, [
            'case_code' => $case->case_code,
            'platform' => $actor->platform,
            'username' => $actor->username,
            'automation_indicator' => $actor->automation_indicator,
        ], Auth::id());

        return redirect()->route('network.index', ['case_id' => $case->id])
            ->with('success', "Observasi pola akun publik {$actor->username} ({$actor->platform}) dicatat ke kasus {$case->case_code}.");
    }
}
