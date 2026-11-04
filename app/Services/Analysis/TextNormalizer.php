<?php

namespace App\Services\Analysis;

/**
 * Normalisasi teks untuk perbandingan. Tidak pernah mengubah bukti asli:
 * hasil normalisasi hanya dipakai sebagai data turunan (Supplemental §6.2).
 */
class TextNormalizer
{
    /** Stopword bahasa Indonesia (daftar ringkas, cukup untuk TF-IDF dasar). */
    public const STOPWORDS = [
        'yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'pada', 'dengan', 'dalam', 'ini', 'itu', 'adalah', 'atau',
        'akan', 'telah', 'sudah', 'juga', 'oleh', 'karena', 'agar', 'bahwa', 'tersebut', 'sebagai', 'serta',
        'dapat', 'bisa', 'ada', 'saat', 'ketika', 'namun', 'tetapi', 'lebih', 'para', 'sebuah', 'suatu', 'si',
        'nya', 'pun', 'lah', 'kah', 'hanya', 'mereka', 'kami', 'kita', 'anda', 'saya', 'dia', 'ia', 'se',
        'bagi', 'antara', 'hingga', 'sampai', 'seperti', 'sebuah', 'yakni', 'yaitu', 'maka', 'jika', 'apabila',
        'the', 'of', 'to', 'and', 'in', 'is',
    ];

    public static function normalize(string $text): string
    {
        $t = mb_strtolower($text, 'UTF-8');
        // 7.841 / 1.200.000 -> 7841 / 1200000 (pemisah ribuan Indonesia)
        $t = preg_replace('/(?<=\d)\.(?=\d{3}(?!\d))/u', '', $t);
        // 12,5 -> 12.5 (desimal koma)
        $t = preg_replace('/(?<=\d),(?=\d)/u', '.', $t);
        $t = preg_replace('/[^\p{L}\p{N}%.]+/u', ' ', $t);
        $t = preg_replace('/(?<!\d)\.|\.(?!\d)/u', ' ', $t);
        return trim(preg_replace('/\s+/u', ' ', $t));
    }

    /** @return string[] */
    public static function tokens(string $text, bool $removeStopwords = true): array
    {
        $norm = self::normalize($text);
        if ($norm === '') {
            return [];
        }
        $tokens = explode(' ', $norm);
        if ($removeStopwords) {
            $tokens = array_values(array_filter($tokens, fn ($t) => !in_array($t, self::STOPWORDS, true)));
        }
        return $tokens;
    }

    /** @return string[] kalimat (tanpa mengubah isi) */
    public static function sentences(string $text): array
    {
        $text = trim(preg_replace('/[ \t]+/u', ' ', $text));
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/(?<=[.!?])\s+(?=\p{Lu}|["“‘\'\d])|\R{1,}/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }

    /** Kata asli (mempertahankan tanda baca) untuk diff. @return string[] */
    public static function words(string $text): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        return $words ?: [];
    }

    /** Kunci pembanding sebuah kata (tanpa tanda baca, huruf kecil). */
    public static function key(string $word): string
    {
        $k = mb_strtolower($word, 'UTF-8');
        $k = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}%]+$/u', '', $k);
        return $k ?? '';
    }
}
