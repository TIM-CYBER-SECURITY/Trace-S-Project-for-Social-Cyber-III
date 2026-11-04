<?php

namespace App\Services\Analysis;

use App\Models\InvestigationCase;
use App\Models\NarrativeCluster;
use App\Models\NarrativeClusterItem;
use App\Models\User;
use App\Services\Ai\AnalysisSchemaValidator;
use App\Services\Support\AuditLogger;

/**
 * Narrative Clustering (Supplemental §7): menghubungkan unggahan yang memiliki
 * inti narasi sama meski redaksi berbeda. Setiap variasi tetap disimpan sebagai
 * bukti terpisah; status kecocokan klaim ditampilkan per anggota, sehingga dua
 * unggahan tidak otomatis dianggap satu klaim yang sama.
 */
class NarrativeClusterer
{
    public function __construct(
        private LexicalSimilarity $lexical,
        private SemanticSimilarity $semantic,
        private ClaimMatcher $claims,
        private NumericEntityComparer $numeric,
        private AnalysisSchemaValidator $validator,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @return array{clusters:array,unclustered:array,methods:array,is_simulation:bool}
     */
    public function run(InvestigationCase $case, ?User $user = null, bool $persist = true): array
    {
        $posts = $case->evidence()->whereIn('role', ['POST', 'SUBJECT'])->whereNotNull('extracted_text')
            ->where('access_status', 'OK')->orderBy('id')->get()->filter(fn ($e) => trim($e->extracted_text) !== '')->values();

        $limitations = [];
        $isSim = $posts->contains(fn ($e) => $e->is_simulation);
        $texts = [];
        $ents = [];
        foreach ($posts as $p) {
            $texts[$p->id] = $p->extracted_text;
            $ents[$p->id] = array_map(fn ($e) => mb_strtolower($e, 'UTF-8'), $this->numeric->entities($p->extracted_text));
        }

        $lex = $texts ? $this->lexical->matrix($texts) : [];
        $sem = count($texts) >= 2 ? $this->semantic->matrix($texts) : ['available' => false, 'matrix' => [], 'meta' => ['is_simulation' => false], 'note' => null];
        if ($sem['available'] && ($sem['meta']['is_simulation'] ?? false)) {
            $isSim = true;
            $limitations[] = 'Komponen semantic berasal dari MockDriver (SIMULASI).';
        }
        if (!$sem['available']) {
            $limitations[] = 'Komponen semantic tidak tersedia; skor topik memakai lexical + entitas saja.';
        }

        // Skor topik gabungan (0-100)
        $topic = function ($i, $j) use ($lex, $ents, $sem) {
            $l = $lex[$i][$j] ?? 0;
            $ea = $ents[$i] ?? [];
            $eb = $ents[$j] ?? [];
            $union = array_unique(array_merge($ea, $eb));
            $en = $union ? count(array_intersect($ea, $eb)) / count($union) * 100 : 0;
            if ($sem['available']) {
                return 0.4 * $l + 0.4 * $en + 0.2 * ($sem['matrix'][$i][$j] ?? 0);
            }
            return 0.5 * $l + 0.5 * $en;
        };

        $threshold = (float) config('trace.analysis.cluster_threshold');
        $ids = $posts->pluck('id')->all();
        $parent = array_combine($ids, $ids);
        $find = function ($x) use (&$parent, &$find) {
            return $parent[$x] === $x ? $x : ($parent[$x] = $find($parent[$x]));
        };
        $pair = [];
        foreach ($ids as $x => $i) {
            foreach (array_slice($ids, $x + 1) as $j) {
                $s = $topic($i, $j);
                $pair[$i][$j] = $pair[$j][$i] = $s;
                if ($s >= $threshold) {
                    $parent[$find($i)] = $find($j);
                }
            }
        }

        $groups = [];
        foreach ($ids as $i) {
            $groups[$find($i)][] = $i;
        }

        $clusters = [];
        $unclustered = [];
        $seq = 0;
        foreach ($groups as $members) {
            if (count($members) < 2) {
                $unclustered[] = $posts->firstWhere('id', $members[0])->evidence_code;
                continue;
            }
            $seq++;
            // Klaim utama = medoid (anggota dengan rata-rata kemiripan tertinggi terhadap yang lain)
            $best = null;
            $bestAvg = -1;
            foreach ($members as $i) {
                $avg = array_sum(array_map(fn ($j) => $i === $j ? 0 : $pair[$i][$j], $members)) / (count($members) - 1);
                if ($avg > $bestAvg) {
                    $bestAvg = $avg;
                    $best = $i;
                }
            }
            $main = $posts->firstWhere('id', $best);
            $items = [];
            $times = [];
            $platforms = [];
            $eng = ['views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'reposts' => 0];
            $engN = array_fill_keys(array_keys($eng), 0);
            foreach ($members as $i) {
                $e = $posts->firstWhere('id', $i);
                $m = $i === $best
                    ? ['match_status' => 'MATCH', 'differences' => [], 'explanation' => 'Klaim utama klaster (medoid).']
                    : $this->claims->compare($main->extracted_text, $e->extracted_text);
                $items[] = [
                    'evidence_id' => $e->evidence_code, 'db_id' => $e->id, 'platform' => $e->platform, 'account' => $e->publisher,
                    'text' => $e->extracted_text, 'published_at' => $e->published_at?->toIso8601String(),
                    'similarity_to_main' => $i === $best ? 100.0 : round($pair[$best][$i], 1),
                    'matching_status' => $m['match_status'], 'differences' => $m['differences'], 'explanation' => $m['explanation'],
                    'is_main' => $i === $best,
                ];
                if ($e->published_at) {
                    $times[] = $e->published_at;
                }
                $platforms[$e->platform ?: 'tidak diketahui'] = ($platforms[$e->platform ?: 'tidak diketahui'] ?? 0) + 1;
                foreach ($eng as $k => $_) {
                    $v = $e->engagementValue($k);
                    if ($v !== null) {
                        $eng[$k] += $v;
                        $engN[$k]++;
                    }
                }
            }

            usort($times, fn ($a, $b) => $a <=> $b);
            $earliest = $times ? $times[0] : null;

            // Indikator koordinasi: harus ada >= 2 sinyal independen
            $window = config('trace.analysis.coordination_window_minutes');
            $timeProx = false;
            if (count($times) >= 3) {
                for ($t = 0; $t + 2 < count($times); $t++) {
                    if ($times[$t]->diffInMinutes($times[$t + 2]) <= $window) {
                        $timeProx = true;
                        break;
                    }
                }
            }
            $nearDup = 0;
            foreach ($members as $x => $i) {
                foreach (array_slice($members, $x + 1) as $j) {
                    if (($lex[$i][$j] ?? 0) >= config('trace.analysis.near_duplicate')) {
                        $nearDup++;
                    }
                }
            }
            $dupSignal = $nearDup >= 2;
            $accounts = collect($items)->pluck('account')->filter()->unique()->count();

            if (count($members) < 3 || count($times) < 3 || $accounts < 3) {
                $coord = 'INSUFFICIENT_EVIDENCE';
                $cNote = 'Data waktu/akun belum memadai untuk menilai pola koordinasi.';
            } elseif ($timeProx && $dupSignal) {
                $coord = 'INDICATORS_PRESENT';
                $cNote = "Terdapat indikator yang perlu ditinjau: ≥3 unggahan dalam {$window} menit dan teks hampir identik. Ini bukan kesimpulan koordinasi atau niat.";
            } else {
                $coord = 'INSUFFICIENT_EVIDENCE';
                $cNote = $timeProx
                    ? 'Waktu publikasi berdekatan, tetapi kedekatan waktu saja tidak cukup sebagai bukti koordinasi.'
                    : 'Tidak ditemukan dua sinyal independen (kedekatan waktu + teks hampir identik).';
            }

            $code = 'NC-' . now()->year . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $cl = [
                'cluster_id' => $code,
                'main_claim' => mb_substr($main->extracted_text, 0, 400),
                'main_evidence_id' => $main->evidence_code,
                'variations' => array_values(array_map(fn ($it) => $it['text'], $items)),
                'platform_distribution' => collect($platforms)->map(fn ($c, $p) => ['platform' => $p, 'count' => $c])->values()->all(),
                'earliest_observed_in_dataset' => $earliest?->toIso8601String(),
                'coordination_assessment' => $coord,
                'coordination_note' => $cNote,
                'limitations' => array_merge($limitations, [
                    'Titik publikasi paling awal hanya berlaku pada dataset terkumpul, bukan publikasi pertama di internet.',
                    'Kemiripan pola unggahan/akun/waktu hanyalah indikator tinjauan, bukan bukti koordinasi atau niat jahat.',
                    'Engagement mengikuti definisi tiap platform dan bukan ukuran kebenaran maupun jumlah orang unik.',
                ]),
                'items' => $items,
                'engagement' => ['totals' => $eng, 'reported_by' => $engN],
                'count' => count($items),
                'matrix' => $this->matrixFor($members, $posts, $pair),
                'is_simulation' => $isSim,
            ];
            $cl['contract_errors'] = $this->validator->validateCluster($cl);
            $clusters[] = $cl;
        }

        $out = [
            'clusters' => $clusters,
            'unclustered' => $unclustered,
            'methods' => [
                'cluster' => config('trace.analysis.cluster_method') . ' v' . config('trace.analysis.cluster_version'),
                'threshold' => $threshold,
            ],
            'is_simulation' => $isSim,
        ];

        if ($persist) {
            $this->persist($case, $out, $user);
        }
        return $out;
    }

    private function matrixFor(array $members, $posts, array $pair): array
    {
        $codes = array_map(fn ($i) => $posts->firstWhere('id', $i)->evidence_code, $members);
        $rows = [];
        foreach ($members as $x => $i) {
            $row = [];
            foreach ($members as $y => $j) {
                $row[] = $i === $j ? 100.0 : round($pair[$i][$j], 1);
            }
            $rows[] = $row;
        }
        return ['labels' => $codes, 'rows' => $rows];
    }

    private function persist(InvestigationCase $case, array $out, ?User $user): void
    {
        $existing = NarrativeCluster::where('case_id', $case->id)->get();
        $reviewed = $existing->where('analyst_status', '!=', 'PENDING REVIEW');
        // Klaster yang telah ditinjau analis tidak dihapus diam-diam
        NarrativeCluster::where('case_id', $case->id)->where('analyst_status', 'PENDING REVIEW')->delete();

        foreach ($out['clusters'] as $cl) {
            $code = $cl['cluster_id'];
            if ($reviewed->contains('cluster_code', $code)) {
                $code .= '-R' . now()->format('His');
            }
            $row = NarrativeCluster::create([
                'case_id' => $case->id, 'cluster_code' => $code,
                'title' => mb_substr($cl['main_claim'], 0, 120), 'main_claim' => $cl['main_claim'],
                'coordination_assessment' => $cl['coordination_assessment'],
                'method' => $out['methods']['cluster'], 'earliest_observed_at' => $cl['earliest_observed_in_dataset'],
                'is_simulation' => $cl['is_simulation'],
            ]);
            foreach ($cl['items'] as $it) {
                NarrativeClusterItem::create([
                    'cluster_id' => $row->id, 'evidence_id' => $it['db_id'],
                    'main_claim_id' => $case->evidence()->where('evidence_code', $cl['main_evidence_id'])->value('id'),
                    'similarity_score' => $it['similarity_to_main'], 'matching_status' => $it['matching_status'],
                    'differences' => $it['differences'],
                ]);
            }
        }
        $this->audit->log('CLUSTER_RUN', $case, ['clusters' => count($out['clusters'])], $user?->id);
    }
}
