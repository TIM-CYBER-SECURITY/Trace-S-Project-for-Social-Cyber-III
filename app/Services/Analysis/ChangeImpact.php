<?php

namespace App\Services\Analysis;

/**
 * Menjelaskan dampak perubahan redaksi terhadap makna (Supplemental §6).
 * Hanya menyatakan "indikasi perubahan materi"; bukan klaim manipulasi digital.
 */
class ChangeImpact
{
    public function __construct(private NumericEntityComparer $numeric)
    {
    }

    /**
     * @return array<int,array{element:string,original:string,compared:string,change_type:string,meaning_impact:string}>
     */
    public function explain(string $orig, string $cmp): array
    {
        $orig = trim($orig);
        $cmp = trim($cmp);
        $changes = [];

        $sa = Lexicon::status($orig);
        $sb = Lexicon::status($cmp);
        if ($orig !== '' && $cmp !== '' && $sa['status'] !== $sb['status']
            && $sa['status'] !== 'TIDAK_DIKETAHUI' && $sb['status'] !== 'TIDAK_DIKETAHUI') {
            $changes[] = [
                'element' => 'status keputusan',
                'original' => implode(', ', $sa['cues']),
                'compared' => implode(', ', $sb['cues']),
                'change_type' => 'ALTERED',
                'meaning_impact' => 'Status berubah dari ' . Lexicon::statusLabel($sa['status']) . ' menjadi ' . Lexicon::statusLabel($sb['status'])
                    . '; berpotensi mengubah kedudukan keputusan.',
            ];
        }

        $ca = Lexicon::scope($orig);
        $cb = Lexicon::scope($cmp);
        if ($ca['scope'] !== $cb['scope'] && ($ca['scope'] !== 'UNSPECIFIED' || $cb['scope'] !== 'UNSPECIFIED')) {
            $type = $ca['scope'] === 'UNSPECIFIED' ? 'ADDED' : ($cb['scope'] === 'UNSPECIFIED' ? 'REMOVED' : 'ALTERED');
            $changes[] = [
                'element' => 'cakupan',
                'original' => implode(', ', $ca['cues']) ?: '-',
                'compared' => implode(', ', $cb['cues']) ?: '-',
                'change_type' => $type,
                'meaning_impact' => 'Cakupan berubah dari ' . Lexicon::scopeLabel($ca['scope']) . ' menjadi ' . Lexicon::scopeLabel($cb['scope'])
                    . '; ruang lingkup kebijakan/peristiwa dapat berbeda.',
            ];
        }

        $aa = Lexicon::actions($orig);
        $ab = Lexicon::actions($cmp);
        $actA = $aa['actions'];
        $actB = $ab['actions'];
        sort($actA);
        sort($actB);
        if ($actA !== $actB && ($actA || $actB)) {
            $changes[] = [
                'element' => 'tindakan',
                'original' => implode(', ', $aa['cues']) ?: '-',
                'compared' => implode(', ', $ab['cues']) ?: '-',
                'change_type' => !$actA ? 'ADDED' : (!$actB ? 'REMOVED' : 'ALTERED'),
                'meaning_impact' => 'Jenis tindakan berubah dari ' . (implode(' / ', array_map([Lexicon::class, 'actionLabel'], $actA)) ?: 'tidak dinyatakan')
                    . ' menjadi ' . (implode(' / ', array_map([Lexicon::class, 'actionLabel'], $actB)) ?: 'tidak dinyatakan') . '.',
            ];
        }

        $num = $this->numeric->compare($orig, $cmp);
        if ($num['numeric_consistency'] === 'INCONSISTENT') {
            $changes[] = [
                'element' => 'angka',
                'original' => implode(', ', $num['numbers_removed']) ?: '-',
                'compared' => implode(', ', $num['numbers_added']) ?: '-',
                'change_type' => !$num['numbers_removed'] ? 'ADDED' : (!$num['numbers_added'] ? 'REMOVED' : 'ALTERED'),
                'meaning_impact' => 'Angka berbeda antara materi referensi dan pembanding; periksa ke sumber primer.',
            ];
        }
        if ($num['dates_changed']) {
            $changes[] = [
                'element' => 'tanggal',
                'original' => implode(', ', $num['dates_a']) ?: '-',
                'compared' => implode(', ', $num['dates_b']) ?: '-',
                'change_type' => !$num['dates_a'] ? 'ADDED' : (!$num['dates_b'] ? 'REMOVED' : 'ALTERED'),
                'meaning_impact' => 'Tanggal berbeda; konteks waktu peristiwa dapat bergeser.',
            ];
        }
        if ($num['temporal_a'] !== $num['temporal_b'] && ($num['temporal_a'] || $num['temporal_b'])) {
            $changes[] = [
                'element' => 'waktu relatif',
                'original' => implode(', ', $num['temporal_a']) ?: '-',
                'compared' => implode(', ', $num['temporal_b']) ?: '-',
                'change_type' => !$num['temporal_a'] ? 'ADDED' : (!$num['temporal_b'] ? 'REMOVED' : 'ALTERED'),
                'meaning_impact' => 'Penanda waktu berbeda; rujukan periode perlu dicek.',
            ];
        }
        foreach (['entities_removed' => 'REMOVED', 'entities_added' => 'ADDED'] as $key => $type) {
            if ($num[$key]) {
                $changes[] = [
                    'element' => 'entitas',
                    'original' => $type === 'REMOVED' ? implode(', ', $num[$key]) : '-',
                    'compared' => $type === 'ADDED' ? implode(', ', $num[$key]) : '-',
                    'change_type' => $type,
                    'meaning_impact' => $type === 'ADDED'
                        ? 'Entitas baru muncul pada materi pembanding; periksa apakah ada penambahan konteks/atribusi.'
                        : 'Entitas pada materi referensi tidak ada pada pembanding; konteks atau atribusi mungkin hilang.',
                ];
            }
        }

        return $changes;
    }
}
