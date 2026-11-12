<?php

namespace App\Services\Ai\Support;

class JsonExtractor
{
    /**
     * Hapus blok penalaran <think>...</think> dari model reasoning (Qwen3 High/Max Reasoning, DeepSeek-R1, dll).
     */
    public static function stripThinking(string $raw): string
    {
        $clean = preg_replace('/<think\b[^>]*>.*?<\/think>/is', '', $raw) ?? $raw;
        // Jika model terpotong sebelum </think> dan tidak ada teks setelahnya, tangani gracefully
        if (str_contains(strtolower($clean), '<think') && !str_contains(strtolower($clean), '</think>')) {
            $afterOpen = preg_replace('/<think\b[^>]*>.*$/is', '', $clean) ?? '';
            $clean = trim($afterOpen) !== '' ? $afterOpen : $clean;
        }
        return trim($clean);
    }

    /** Ekstrak isi blok <think>...</think> bila tersedia (untuk inspeksi/audit opsional). */
    public static function extractThinking(string $raw): ?string
    {
        if (preg_match_all('/<think\b[^>]*>(.*?)<\/think>/is', $raw, $m)) {
            $joined = trim(implode("\n\n", array_map('trim', $m[1])));
            return $joined !== '' ? $joined : null;
        }
        return null;
    }

    /** Ambil objek JSON dari keluaran model (toleran terhadap <think>, pagar kode, dan teks pengantar). */
    public static function extract(string $raw): ?array
    {
        $raw = self::stripThinking($raw);
        if ($raw === '') {
            return null;
        }

        // Hapus pembungkus markdown ```json ... ``` di awal/akhir atau di dalam teks
        $unwrapped = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw) ?? $raw;
        $decoded = self::decodeLenient(trim($unwrapped));
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $raw, $matches)) {
            $decoded = self::decodeLenient(trim($matches[1]));
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Cari pasangan kurung kurawal JSON seimbang terluar
        $len = strlen($raw);
        $depth = 0;
        $start = null;
        $inString = false;
        $escape = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $raw[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($inString) {
                if ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($ch === '"') {
                $inString = true;
                continue;
            }
            if ($ch === '{') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
            } elseif ($ch === '}') {
                if ($depth > 0) {
                    $depth--;
                    if ($depth === 0 && $start !== null) {
                        $candidate = substr($raw, $start, $i - $start + 1);
                        $decoded = self::decodeLenient($candidate);
                        if (is_array($decoded)) {
                            return $decoded;
                        }
                        $start = null;
                    }
                }
            }
        }

        return null;
    }

    /** Decode JSON secara ketat terlebih dahulu, lalu perbaiki trailing comma sintaksis jika perlu. */
    private static function decodeLenient(string $json): ?array
    {
        if ($json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $cleaned = preg_replace('/,\s*([}\]])/', '$1', $json);
        if ($cleaned !== null && $cleaned !== $json) {
            $decoded = json_decode($cleaned, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }
}
