<?php

namespace App\Services\Analysis;

/**
 * Detektor Anomali Teks, Timpa Teks (Text-Overlay), & Indikasi Editan.
 * Membandingkan konten yang diperiksa terhadap sumber rujukan terverifikasi
 * dan menandai segmen angka/frasa yang menyimpang atau bersifat manipulatif.
 */
class ManipulationDetector
{
    public function __construct(private NumericEntityComparer $numeric)
    {
    }

    /**
     * @param  string  $inputText
     * @param  array<int,array<string,mixed>>  $referenceItems  [['code'=>string,'text'=>string,'title'=>?string,'is_reference'=>bool]]
     * @param  array<int,array<string,mixed>>  $claims
     * @return array<int,array{segment:string,type:string,severity:string,reason:string,reference_code:?string}>
     */
    public function detect(string $inputText, array $referenceItems = [], array $claims = []): array
    {
        $text = trim($inputText);
        if ($text === '') {
            return [];
        }

        $highlights = [];
        $seenSegments = [];

        $addHighlight = function (string $segment, string $type, string $severity, string $reason, ?string $refCode = null) use (&$highlights, &$seenSegments, $text): void {
            $seg = trim($segment);
            if ($seg === '' || mb_strlen($seg) < 2) {
                return;
            }
            if (mb_stripos($text, $seg, 0, 'UTF-8') === false) {
                return;
            }
            $key = mb_strtolower($seg, 'UTF-8');
            if (isset($seenSegments[$key])) {
                return;
            }
            $seenSegments[$key] = true;
            $highlights[] = [
                'segment' => $seg,
                'type' => $type,
                'severity' => $severity,
                'reason' => $reason,
                'reference_code' => $refCode,
            ];
        };

        // 1. Perbandingan Angka, Persentase, Nominal, dan Tahun terhadap Sumber Rujukan
        $inputNums = $this->numeric->numbers($text);
        if ($inputNums !== [] && $referenceItems !== []) {
            foreach ($referenceItems as $ref) {
                $refText = (string) ($ref['text'] ?? '');
                if ($refText === '') {
                    continue;
                }
                $refNums = $this->numeric->numbers($refText);
                if ($refNums === []) {
                    continue;
                }
                foreach ($inputNums as $in) {
                    $sameKindRef = array_values(array_filter($refNums, fn ($rn) => $rn['kind'] === $in['kind']));
                    if ($sameKindRef === []) {
                        continue;
                    }
                    $exactMatch = false;
                    foreach ($sameKindRef as $rn) {
                        if (abs($rn['value'] - $in['value']) < 0.0001) {
                            $exactMatch = true;
                            break;
                        }
                    }
                    if (!$exactMatch) {
                        $refRawList = implode(', ', array_slice(array_column($sameKindRef, 'raw'), 0, 2));
                        $addHighlight(
                            $in['raw'],
                            'NUMERIC_TAMPER',
                            'HIGH',
                            "Nilai '{$in['raw']}' berbeda dengan rujukan ({$refRawList}) — potensi perubahan angka / timpa teks.",
                            $ref['code'] ?? null
                        );
                    }
                }
            }
        }

        // 2. Perbandingan Tanggal & Penanda Waktu terhadap Sumber Rujukan (DATE_TAMPER)
        if ($referenceItems !== []) {
            $inputDates = $this->numeric->dates($text);
            if ($inputDates !== []) {
                $rawDateMatches = [];
                if (preg_match_all('/\b\d{1,2}\s+(?:januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)\s+\d{4}\b|\b\d{4}-\d{2}-\d{2}\b/iu', $text, $dm)) {
                    $rawDateMatches = $dm[0];
                }
                foreach ($referenceItems as $ref) {
                    $refDates = $this->numeric->dates((string) ($ref['text'] ?? ''));
                    if ($refDates === []) {
                        continue;
                    }
                    foreach ($inputDates as $idx => $isoDate) {
                        if (!in_array($isoDate, $refDates, true)) {
                            $seg = $rawDateMatches[$idx] ?? $isoDate;
                            $addHighlight(
                                $seg,
                                'DATE_TAMPER',
                                'HIGH',
                                "Tanggal '{$seg}' ({$isoDate}) menyimpang dari tanggal dokumen rujukan (" . implode(', ', array_slice($refDates, 0, 2)) . ').',
                                $ref['code'] ?? null
                            );
                        }
                    }
                }
            }
        }

        // 3. Deteksi Frasa Sisipan / Timpa Teks dibanding Sumber Rujukan
        if ($referenceItems !== []) {
            $sentences = TextNormalizer::sentences($text) ?: [$text];
            foreach ($sentences as $sentence) {
                $sTokens = array_unique(TextNormalizer::tokens($sentence));
                if (count($sTokens) < 4) {
                    continue;
                }
                $bestOverlap = 0.0;
                $bestRefCode = null;
                foreach ($referenceItems as $ref) {
                    $refTokens = array_unique(TextNormalizer::tokens((string) ($ref['text'] ?? '')));
                    if ($refTokens === []) {
                        continue;
                    }
                    $shared = count(array_intersect($sTokens, $refTokens));
                    $ratio = $shared / max(1, count($sTokens));
                    if ($ratio > $bestOverlap) {
                        $bestOverlap = $ratio;
                        $bestRefCode = $ref['code'] ?? null;
                    }
                }
                // Jika kemiripan sebagian sedang (ada kerangka sama tapi banyak sisipan/pengubahan kata)
                if ($bestOverlap > 0.15 && $bestOverlap < 0.48) {
                    $addHighlight(
                        $sentence,
                        'TEXT_OVERLAY',
                        'MEDIUM',
                        'Kalimat memiliki sebagian istilah rujukan namun redaksinya menyimpang signifikan (indikasi penyuntingan / timpa narasi).',
                        $bestRefCode
                    );
                }
            }
        }

        // 4. Penanda Leksikal Sensasional / Manipulasi / Viral Bait
        $manipulativePatterns = [
            '/\b(sebarkan\s+sebelum\s+dihapus|viral!?|hoax|hoaks|resmi\s+dibubarkan|100%\s+valid|breaking\s+news|darurat\s+nasional|rahasia\s+bocor|bocoran\s+internal|dihentikan\s+permanen)\b/iu',
            '/!!+/',
        ];
        foreach ($manipulativePatterns as $pat) {
            if (preg_match_all($pat, $text, $matches)) {
                foreach ($matches[0] as $hit) {
                    $addHighlight(
                        $hit,
                        'TEXT_OVERLAY',
                        'MEDIUM',
                        'Penanda framing sensasional / clickbait yang sering disisipkan pada konten hasil editan atau timpa teks.'
                    );
                }
            }
        }

        // 5. Klaim yang Dibantah oleh AI Lokal (cocokkan fragmen klaim di dalam teks input)
        foreach ($claims as $cl) {
            $status = strtoupper((string) ($cl['check_status'] ?? ''));
            if (!in_array($status, ['TERBANTAHKAN', 'REFUTED'], true)) {
                continue;
            }
            $clText = trim((string) ($cl['text'] ?? ''));
            if ($clText !== '' && mb_stripos($text, $clText, 0, 'UTF-8') !== false) {
                $addHighlight($clText, 'CLAIM_DISPUTED', 'HIGH', 'Klaim terbantahkan oleh analisis bukti & AI Lokal.');
            }
        }

        return array_slice($highlights, 0, 12);
    }

    /**
     * Render teks HTML aman dengan penanda <mark> pada segmen yang terdeteksi bermasalah.
     *
     * @param  array<int,array{segment:string,type:string,severity:string,reason:string,reference_code:?string}>  $highlights
     */
    public function renderHighlightedHtml(string $text, array $highlights): string
    {
        if ($highlights === []) {
            return nl2br(e($text));
        }

        // Cari posisi kemunculan segmen (tanpa tumpang tindih)
        $ranges = [];
        $lowerText = mb_strtolower($text, 'UTF-8');
        foreach ($highlights as $hl) {
            $seg = $hl['segment'] ?? '';
            if ($seg === '') {
                continue;
            }
            $pos = mb_stripos($text, $seg, 0, 'UTF-8');
            if ($pos === false) {
                continue;
            }
            $len = mb_strlen($seg, 'UTF-8');
            $end = $pos + $len;

            $overlaps = false;
            foreach ($ranges as $r) {
                if (!($end <= $r['start'] || $pos >= $r['end'])) {
                    $overlaps = true;
                    break;
                }
            }
            if (!$overlaps) {
                $ranges[] = [
                    'start' => $pos,
                    'end' => $end,
                    'hl' => $hl,
                ];
            }
        }

        if ($ranges === []) {
            return nl2br(e($text));
        }

        usort($ranges, fn ($a, $b) => $a['start'] <=> $b['start']);

        $out = '';
        $cursor = 0;
        $totalLen = mb_strlen($text, 'UTF-8');

        foreach ($ranges as $r) {
            if ($r['start'] > $cursor) {
                $out .= nl2br(e(mb_substr($text, $cursor, $r['start'] - $cursor, 'UTF-8')));
            }
            $exact = mb_substr($text, $r['start'], $r['end'] - $r['start'], 'UTF-8');
            $hl = $r['hl'];
            $cssClass = ($hl['severity'] ?? 'MEDIUM') === 'HIGH' ? 'hl-tamper' : 'hl-overlay';
            $title = e('[' . ($hl['type'] ?? 'ANOMALY') . '] ' . ($hl['reason'] ?? ''));
            $out .= '<mark class="' . $cssClass . '" title="' . $title . '">' . e($exact) . '</mark>';
            $cursor = $r['end'];
        }

        if ($cursor < $totalLen) {
            $out .= nl2br(e(mb_substr($text, $cursor, $totalLen - $cursor, 'UTF-8')));
        }

        return $out;
    }
}
