<?php

namespace App\Services\Analysis;

/**
 * Identifikasi klaim dari teks. Hasilnya adalah DATA TURUNAN; teks asli
 * tidak diubah (Supplemental §4 Tahap 4).
 */
class ClaimAnalyzer
{
    public function __construct(private NumericEntityComparer $numeric)
    {
    }

    /** Analisis satu kalimat menjadi struktur klaim. */
    public function analyzeSentence(string $sentence): array
    {
        $status = Lexicon::status($sentence);
        $scope = Lexicon::scope($sentence);
        $actions = Lexicon::actions($sentence);

        $type = 'FAKTA';
        if (preg_match('/["“”‘’]/u', $sentence)) {
            $type = 'KUTIPAN';
        } elseif ($status['status'] === 'DUGAAN') {
            $type = 'DUGAAN';
        } elseif ($status['status'] === 'OPINI') {
            $type = 'OPINI';
        }

        return [
            'text' => $sentence,
            'type' => $type,
            'status' => $status['status'],
            'status_cues' => $status['cues'],
            'scope' => $scope['scope'],
            'scope_cues' => $scope['cues'],
            'actions' => $actions['actions'],
            'action_cues' => $actions['cues'],
            'numbers' => array_column($this->numeric->numbers($sentence), 'raw'),
            'entities' => $this->numeric->entities($sentence),
            'uncertain' => in_array($status['status'], ['DUGAAN', 'WACANA_RENCANA'], true),
        ];
    }

    /**
     * Ekstraksi klaim utama: kalimat yang memuat tindakan/status/angka/entitas.
     *
     * @return array<int,array<string,mixed>>
     */
    public function extract(string $text, int $max = 10): array
    {
        $claims = [];
        foreach (TextNormalizer::sentences($text) as $s) {
            if (count(TextNormalizer::words($s)) < 4) {
                continue;
            }
            $c = $this->analyzeSentence($s);
            $weight = (count($c['actions']) ? 2 : 0) + ($c['status'] !== 'TIDAK_DIKETAHUI' ? 1 : 0)
                + (count($c['numbers']) ? 1 : 0) + min(2, count($c['entities']));
            $c['weight'] = $weight;
            $claims[] = $c;
        }
        usort($claims, fn ($a, $b) => $b['weight'] <=> $a['weight']);
        $claims = array_slice($claims, 0, $max);
        foreach ($claims as $i => &$c) {
            $c['id'] = 'c' . ($i + 1);
        }
        return $claims;
    }

    /** Klaim utama sebuah teks (kalimat berbobot tertinggi, atau seluruh teks bila pendek). */
    public function primaryClaim(string $text): ?array
    {
        $claims = $this->extract($text, 1);
        if ($claims) {
            return $claims[0];
        }
        $t = trim($text);
        return $t === '' ? null : $this->analyzeSentence($t);
    }
}
