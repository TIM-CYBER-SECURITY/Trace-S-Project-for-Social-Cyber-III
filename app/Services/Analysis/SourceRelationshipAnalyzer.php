<?php

namespace App\Services\Analysis;

use App\Models\EvidenceItem;
use App\Models\InvestigationCase;
use App\Models\SourceRelationship;
use Illuminate\Support\Collection;

/**
 * Source Independence Analysis (Supplemental §5.4).
 *
 * Prinsip: sistem TIDAK menganggap sumber independen tanpa bukti. Hubungan
 * otomatis hanya dapat berupa SYNDICATED_REPUBLISHED, SECONDARY_CITATION,
 * COMMON_PRIMARY_SOURCE, atau RELATIONSHIP_UNKNOWN. INDEPENDENT_REPORTING
 * hanya muncul bila analis menetapkannya (dengan bukti pendukung).
 */
class SourceRelationshipAnalyzer
{
    public function __construct(private LexicalSimilarity $lexical)
    {
    }

    /** @return string[] referensi ternormalisasi yang dikutip pada teks */
    public function citations(string $text): array
    {
        $refs = [];
        if (preg_match_all('#https?://[^\s)\]>"\']+#iu', $text, $m)) {
            foreach ($m[0] as $u) {
                $refs[] = $this->normUrl($u);
            }
        }
        $pattern = '/(?:mengutip|dikutip dari|berdasarkan|bersumber dari|sumber|menurut|siaran pers|rilis resmi|rilis|keterangan resmi|pernyataan resmi)\s*[:\-]?\s*([\p{L}\p{N}][\p{L}\p{N} .\/\-]{3,70}?)(?=[,;:\n]|\.\s|\.$|$)/iu';
        if (preg_match_all($pattern, $text, $m)) {
            foreach ($m[1] as $name) {
                $n = TextNormalizer::normalize($name);
                if (mb_strlen($n) >= 5) {
                    $refs[] = $n;
                }
            }
        }
        if (preg_match_all('/\b(?:nomor|no\.?)\s*([\w.\/-]{3,30})/iu', $text, $m)) {
            foreach ($m[1] as $no) {
                $refs[] = 'nomor ' . mb_strtolower($no, 'UTF-8');
            }
        }
        return array_values(array_unique($refs));
    }

    private function normUrl(string $u): string
    {
        $u = mb_strtolower(rtrim($u, '.,/'), 'UTF-8');
        return preg_replace('#^https?://(www\.)?#', '', $u);
    }

    private function host(?string $url): ?string
    {
        $h = $url ? parse_url($url, PHP_URL_HOST) : null;
        return $h ? preg_replace('/^www\./', '', mb_strtolower($h, 'UTF-8')) : null;
    }

    /**
     * @param  Collection<int,EvidenceItem>  $items  bukti referensi/primer (dengan source)
     * @return array{relationships:array,groups:array,independent_units:int,confirmed_independent_pairs:int,statement:string}
     */
    public function analyze(InvestigationCase $case, Collection $items): array
    {
        $items = $items->values();
        $reviewed = SourceRelationship::where('case_id', $case->id)->whereNotNull('reviewed_by')->get();
        $rels = [];
        $parent = [];
        foreach ($items as $it) {
            $parent[$it->id] = $it->id;
        }
        $find = function ($x) use (&$parent, &$find) {
            return $parent[$x] === $x ? $x : ($parent[$x] = $find($parent[$x]));
        };

        for ($i = 0; $i < $items->count(); $i++) {
            for ($j = $i + 1; $j < $items->count(); $j++) {
                $a = $items[$i];
                $b = $items[$j];
                $type = 'RELATIONSHIP_UNKNOWN';
                $basis = 'Belum ditemukan bukti hubungan antar-sumber; tidak dianggap independen.';
                $supporting = null;

                $override = $reviewed->first(fn ($r) => ($r->source_a_id === $a->source_id && $r->source_b_id === $b->source_id)
                    || ($r->source_a_id === $b->source_id && $r->source_b_id === $a->source_id));

                if ($override) {
                    $type = $override->relationship_type;
                    $basis = 'Ditetapkan analis: ' . ($override->assessment ?: '(tanpa catatan)');
                    $supporting = $override->supporting_evidence_id;
                } else {
                    $ta = (string) $a->extracted_text;
                    $tb = (string) $b->extracted_text;
                    $sim = ($ta !== '' && $tb !== '') ? $this->lexical->compare($ta, $tb)['score'] : 0;
                    $citeA = $this->citations($ta);
                    $citeB = $this->citations($tb);
                    $hostA = $this->host($a->url);
                    $hostB = $this->host($b->url);
                    $pubA = TextNormalizer::normalize((string) $a->publisher);
                    $pubB = TextNormalizer::normalize((string) $b->publisher);

                    $aCitesB = $this->cites($citeA, $hostB, $pubB, $b->url);
                    $bCitesA = $this->cites($citeB, $hostA, $pubA, $a->url);
                    $shared = array_values(array_intersect($citeA, $citeB));

                    if (($a->content_hash && $a->content_hash === $b->content_hash) || $sim >= config('trace.analysis.near_duplicate')) {
                        $type = 'SYNDICATED_REPUBLISHED';
                        $basis = "Isi hampir identik (kemiripan kata {$sim}); diperlakukan sebagai publikasi ulang.";
                    } elseif ($aCitesB || $bCitesA) {
                        $type = 'SECONDARY_CITATION';
                        $basis = ($aCitesB ? "{$a->evidence_code} mengutip {$b->evidence_code}" : "{$b->evidence_code} mengutip {$a->evidence_code}") . ' (berdasarkan kutipan sumber pada teks).';
                    } elseif ($shared) {
                        $type = 'COMMON_PRIMARY_SOURCE';
                        $basis = 'Keduanya merujuk rujukan yang sama: "' . implode('", "', array_slice($shared, 0, 3)) . '".';
                    }
                }

                if (in_array($type, ['SYNDICATED_REPUBLISHED', 'SECONDARY_CITATION', 'COMMON_PRIMARY_SOURCE'], true)) {
                    $parent[$find($a->id)] = $find($b->id);
                }

                $rels[] = [
                    'source_a_id' => $a->source_id, 'source_b_id' => $b->source_id,
                    'evidence_a' => $a->evidence_code, 'evidence_b' => $b->evidence_code,
                    'type' => $type, 'basis' => $basis, 'supporting_evidence_id' => $supporting,
                    'reviewed' => (bool) $override,
                ];
            }
        }

        $groups = [];
        foreach ($items as $it) {
            $groups[$find($it->id)][] = $it->evidence_code;
        }
        $groups = array_values($groups);
        $confirmed = count(array_filter($rels, fn ($r) => $r['type'] === 'INDEPENDENT_REPORTING'));
        $allUnknown = $rels && count(array_filter($rels, fn ($r) => $r['type'] === 'RELATIONSHIP_UNKNOWN')) === count($rels);

        $n = $items->count();
        $units = count($groups);
        if ($n === 0) {
            $statement = 'Belum ada sumber referensi untuk dianalisis.';
        } elseif ($allUnknown) {
            $statement = "Terdapat {$n} sumber, tetapi hubungan antar-sumber belum diketahui (RELATIONSHIP_UNKNOWN). Sumber tidak dapat dianggap independen tanpa bukti.";
        } elseif ($units < $n) {
            $statement = "Terdapat {$n} sumber yang membahas topik ini, tetapi setelah diperiksa hanya {$units} kelompok rujukan yang berbeda. Jumlah pemberitaan tidak dapat diperlakukan sebagai {$n} bukti independen.";
        } else {
            $statement = "Terdapat {$n} sumber dengan {$units} kelompok rujukan terpisah, dengan {$confirmed} pasangan ditetapkan INDEPENDENT_REPORTING oleh analis.";
        }

        return [
            'relationships' => $rels,
            'groups' => $groups,
            'independent_units' => $units,
            'confirmed_independent_pairs' => $confirmed,
            'statement' => $statement,
        ];
    }

    private function cites(array $citations, ?string $host, string $publisherNorm, ?string $url): bool
    {
        $normUrl = $url ? $this->normUrl($url) : null;
        foreach ($citations as $c) {
            if ($normUrl && str_contains($c, $normUrl)) {
                return true;
            }
            if ($host && str_contains($c, $host)) {
                return true;
            }
            if ($publisherNorm !== '' && mb_strlen($publisherNorm) >= 5 && str_contains($c, $publisherNorm)) {
                return true;
            }
        }
        return false;
    }
}
