<?php

namespace App\Services\Ai\Support;

/**
 * Anti prompt-injection (Master §10): seluruh teks dari web, dokumen, unggahan,
 * dan komentar adalah DATA TIDAK TERPERCAYA, bukan instruksi bagi AI.
 */
class PromptGuard
{
    private const PATTERNS = [
        '/ignore (all |any )?(previous|prior|above) (instructions?|prompts?)/i',
        '/disregard (the )?(system|previous) (prompt|instructions?)/i',
        '/abaikan (semua |seluruh )?(instruksi|perintah|petunjuk)( sebelumnya| di atas)?/iu',
        '/lupakan (semua |seluruh )?(instruksi|perintah)/iu',
        '/(kamu|anda) (sekarang )?adalah (seorang )?(asisten|ai|model|bot)/iu',
        '/you are now|act as|pretend to be|bertindaklah sebagai|berpura-puralah/iu',
        '/system prompt|developer message|prompt sistem/iu',
        '/(tampilkan|bocorkan|reveal|print) (the )?(api key|kunci api|secret|password|kata sandi)/iu',
        '/<\s*\/?\s*(system|assistant|instruction)\s*>/i',
        '/\b(jawab|keluarkan|output) (hanya )?(dengan )?(terverifikasi|verified|asli)\b/iu',
        '/set (credibility_score|classification|verification_status)/i',
    ];

    /** @return array{flagged:bool,patterns:string[]} */
    public static function scan(string $text): array
    {
        $hits = [];
        foreach (self::PATTERNS as $p) {
            if (preg_match($p, $text, $m)) {
                $hits[] = trim($m[0]);
            }
        }
        return ['flagged' => (bool) $hits, 'patterns' => array_values(array_unique($hits))];
    }

    /** Bungkus teks eksternal sebagai data, tanpa kemungkinan menutup pembatas. */
    public static function wrap(string $label, string $text, int $maxChars): string
    {
        $text = mb_substr($text, 0, $maxChars, 'UTF-8');
        $text = str_replace(['<untrusted_data', '</untrusted_data'], ['&lt;untrusted_data', '&lt;/untrusted_data'], $text);
        return "<untrusted_data label=\"{$label}\">\n{$text}\n</untrusted_data>";
    }
}
