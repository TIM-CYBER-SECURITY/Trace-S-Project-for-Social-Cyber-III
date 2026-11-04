<?php

namespace App\Services\Analysis;

/**
 * Leksikon pemaknaan (status keputusan, cakupan, tindakan) untuk bahasa Indonesia.
 * Aturan transparan berbasis kata kunci: dipakai untuk claim-level matching
 * dan penjelasan dampak perubahan. Bukan model statistik; keterbatasannya
 * dicantumkan pada hasil analisis.
 */
class Lexicon
{
    public const DUGAAN = ['diduga', 'dugaan', 'konon', 'katanya', 'disinyalir', 'kabarnya', 'santer', 'isu', 'rumor', 'desas-desus', 'desas desus', 'dikabarkan', 'beredar kabar'];
    public const OPINI = ['menurut saya', 'sebaiknya', 'seharusnya', 'menurut hemat', 'kurang tepat', 'tidak layak', 'sangat buruk', 'luar biasa'];
    public const RENCANA = [
        'mempertimbangkan', 'pertimbangkan', 'dipertimbangkan', 'berencana', 'rencana', 'direncanakan', 'merencanakan',
        'akan', 'wacana', 'mewacanakan', 'usulan', 'mengusulkan', 'diusulkan', 'mengkaji', 'dikaji', 'kajian',
        'menyiapkan', 'kemungkinan', 'opsi', 'berpotensi', 'bakal', 'menjajaki', 'menyusun', 'rancangan',
    ];
    public const RESMI = [
        'resmi', 'secara resmi', 'ditetapkan', 'menetapkan', 'memutuskan', 'diputuskan', 'mengumumkan', 'diumumkan',
        'berlaku', 'efektif', 'ditandatangani', 'mengesahkan', 'disahkan', 'telah', 'sudah',
    ];
    /** Subset RESMI yang kuat (tidak sekadar "telah/sudah"). */
    public const RESMI_KUAT = ['resmi', 'secara resmi', 'ditetapkan', 'menetapkan', 'memutuskan', 'diputuskan', 'mengumumkan', 'diumumkan', 'ditandatangani', 'mengesahkan', 'disahkan'];

    public const SCOPE_ALL = ['seluruh', 'semua', 'sepenuhnya', 'keseluruhan', 'semuanya', 'total', 'tanpa kecuali'];
    public const SCOPE_PARTIAL = ['beberapa', 'sebagian', 'tertentu', 'sejumlah', 'sebagian besar'];

    public const ACTIONS = [
        'TERMINATE' => ['menghentikan', 'dihentikan', 'penghentian', 'hentikan', 'membatalkan', 'dibatalkan', 'batal', 'pembatalan', 'mencabut', 'dicabut', 'cabut', 'setop', 'stop', 'menyetop', 'menutup', 'ditutup', 'menghapus', 'dihapus', 'mengakhiri', 'diakhiri', 'dibubarkan'],
        'REDUCE'    => ['dipotong', 'memotong', 'pemotongan', 'dipangkas', 'memangkas', 'pemangkasan', 'mengurangi', 'dikurangi', 'pengurangan'],
        'ADJUST'    => ['menyesuaikan', 'disesuaikan', 'penyesuaian', 'mengalihkan', 'dialihkan', 'realokasi', 'dialokasikan ulang', 'perubahan alokasi', 'alokasi', 'mengubah', 'diubah', 'perubahan'],
        'CONTINUE'  => ['melanjutkan', 'dilanjutkan', 'berlanjut', 'tetap berjalan', 'tetap dilanjutkan'],
    ];

    /** @return string[] cue yang ditemukan pada teks ternormalisasi (batas kata dijaga). */
    public static function matchCues(string $text, array $cues): array
    {
        $padded = ' ' . TextNormalizer::normalize($text) . ' ';
        $found = [];
        foreach ($cues as $cue) {
            if (str_contains($padded, ' ' . $cue . ' ')) {
                $found[] = $cue;
            }
        }
        return $found;
    }

    /** @return array{status:string,cues:string[]} */
    public static function status(string $text): array
    {
        if ($c = self::matchCues($text, self::DUGAAN)) {
            return ['status' => 'DUGAAN', 'cues' => $c];
        }
        if ($c = self::matchCues($text, self::OPINI)) {
            return ['status' => 'OPINI', 'cues' => $c];
        }
        $strong = self::matchCues($text, self::RESMI_KUAT);
        $plan = self::matchCues($text, self::RENCANA);
        $weak = array_diff(self::matchCues($text, self::RESMI), $strong);

        if ($strong) {
            return ['status' => 'KEPUTUSAN_RESMI', 'cues' => $strong];
        }
        if ($plan) {
            return ['status' => 'WACANA_RENCANA', 'cues' => $plan];
        }
        if ($weak) {
            return ['status' => 'KEPUTUSAN_RESMI', 'cues' => array_values($weak)];
        }
        return ['status' => 'TIDAK_DIKETAHUI', 'cues' => []];
    }

    /** @return array{scope:string,cues:string[]} */
    public static function scope(string $text): array
    {
        if ($c = self::matchCues($text, self::SCOPE_ALL)) {
            return ['scope' => 'ALL', 'cues' => $c];
        }
        if ($c = self::matchCues($text, self::SCOPE_PARTIAL)) {
            return ['scope' => 'PARTIAL', 'cues' => $c];
        }
        return ['scope' => 'UNSPECIFIED', 'cues' => []];
    }

    /** @return array{actions:string[],cues:string[]} */
    public static function actions(string $text): array
    {
        $actions = [];
        $cues = [];
        foreach (self::ACTIONS as $type => $list) {
            $c = self::matchCues($text, $list);
            if ($c) {
                $actions[] = $type;
                $cues = array_merge($cues, $c);
            }
        }
        return ['actions' => $actions, 'cues' => $cues];
    }

    public static function statusLabel(string $status): string
    {
        return [
            'WACANA_RENCANA' => 'wacana / rencana',
            'KEPUTUSAN_RESMI' => 'keputusan resmi',
            'DUGAAN' => 'dugaan',
            'OPINI' => 'opini',
            'TIDAK_DIKETAHUI' => 'tidak diketahui',
        ][$status] ?? strtolower($status);
    }

    public static function actionLabel(string $action): string
    {
        return [
            'TERMINATE' => 'penghentian / pembatalan',
            'REDUCE' => 'pemotongan / pengurangan',
            'ADJUST' => 'penyesuaian / pengalihan',
            'CONTINUE' => 'kelanjutan',
        ][$action] ?? strtolower($action);
    }

    public static function scopeLabel(string $scope): string
    {
        return ['ALL' => 'seluruh / menyeluruh', 'PARTIAL' => 'sebagian / terbatas', 'UNSPECIFIED' => 'tidak dinyatakan'][$scope] ?? $scope;
    }
}
