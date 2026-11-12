<?php

namespace App\Services\DataAcquisition;

/**
 * Ekstraktor Teks PDF Murni-PHP (Tanpa Ketergantungan Biner Eksternal).
 * Mendukung aliran teks PDF standar tidak terkompresi maupun /FlateDecode
 * dengan batas memori/ukuran ketat demi keamanan operasional.
 */
class PdfTextExtractor
{
    private const MAX_FILE_BYTES = 5_242_880; // 5 MB
    private const MAX_OUTPUT_CHARS = 120_000;
    private const MAX_STREAMS = 120;

    public static function extractFromFile(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }

        $size = @filesize($path);
        if ($size === false || $size <= 0 || $size > self::MAX_FILE_BYTES) {
            return '';
        }

        $raw = (string) @file_get_contents($path);
        return self::extractFromBinary($raw);
    }

    public static function extractFromBinary(string $raw): string
    {
        if ($raw === '' || !str_starts_with($raw, '%PDF-')) {
            return '';
        }

        $chunks = [];
        $processedStreams = 0;

        // 1. Ekstrak seluruh objek stream (... stream\r?\n <payload> \r?\nendstream)
        if (preg_match_all('/<<(.*?)>>\s*stream\r?\n(.*?)\r?\n?endstream/s', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                if ($processedStreams >= self::MAX_STREAMS) {
                    break;
                }
                $processedStreams++;
                $dict = $m[1];
                $payload = $m[2];

                // Skip image XObjects / non-text binary filters
                if (preg_match('/\/Subtype\s*\/Image\b/i', $dict) || preg_match('/\/DCTDecode\b|\/JPXDecode\b/i', $dict)) {
                    continue;
                }

                $decoded = $payload;
                if (preg_match('/\/FlateDecode\b/i', $dict)) {
                    $inflated = self::inflateFlate($payload);
                    if ($inflated === null) {
                        continue;
                    }
                    $decoded = $inflated;
                }

                $text = self::extractTextOperators($decoded);
                if ($text !== '') {
                    $chunks[] = $text;
                }
            }
        }

        // 2. Fallback: cari blok BT ... ET langsung pada dokumen jika stream tanpa header atau inline
        if ($chunks === []) {
            $inline = self::extractTextOperators($raw);
            if ($inline !== '') {
                $chunks[] = $inline;
            }
        }

        if ($chunks === []) {
            return '';
        }

        $joined = implode("\n", $chunks);
        // Normalisasi spasi sambil menjaga baris baru informatif
        $joined = preg_replace('/[ \t]+/u', ' ', $joined) ?? $joined;
        $joined = preg_replace('/\n{3,}/', "\n\n", $joined) ?? $joined;
        $joined = trim($joined);

        if (mb_strlen($joined, 'UTF-8') > self::MAX_OUTPUT_CHARS) {
            $joined = mb_substr($joined, 0, self::MAX_OUTPUT_CHARS, 'UTF-8');
        }

        return mb_check_encoding($joined, 'UTF-8') ? $joined : '';
    }

    private static function inflateFlate(string $data): ?string
    {
        if (strlen($data) > self::MAX_FILE_BYTES) {
            return null;
        }
        $out = @zlib_decode($data);
        if (is_string($out)) {
            return $out;
        }
        $out = @gzuncompress($data);
        if (is_string($out)) {
            return $out;
        }
        $out = @gzinflate($data);
        return is_string($out) ? $out : null;
    }

    private static function extractTextOperators(string $stream): string
    {
        // Cari blok teks BT ... ET terlebih dahulu agar lebih akurat
        $blocks = [];
        if (preg_match_all('/\bBT\b(.*?)\bET\b/s', $stream, $btMatches)) {
            $blocks = $btMatches[1];
        } elseif (str_contains($stream, 'Tj') || str_contains($stream, 'TJ')) {
            $blocks = [$stream];
        }

        if ($blocks === []) {
            return '';
        }

        $lines = [];
        foreach ($blocks as $block) {
            $buf = '';

            // Tangkap array [(...) -12 (...)] TJ serta literal (...) Tj / ' / " dan hex <...> Tj
            $offset = 0;
            $len = strlen($block);
            while ($offset < $len) {
                $ch = $block[$offset];
                if ($ch === '(') {
                    [$lit, $nextOffset] = self::readPdfLiteralString($block, $offset);
                    $offset = $nextOffset;
                    // Pastikan diikuti operator teks / spasi array
                    $lines[] = $lit;
                    continue;
                }
                if ($ch === '<' && ($offset + 1 < $len) && $block[$offset + 1] !== '<') {
                    $end = strpos($block, '>', $offset + 1);
                    if ($end !== false) {
                        $hex = substr($block, $offset + 1, $end - $offset - 1);
                        $decodedHex = self::decodePdfHex($hex);
                        if ($decodedHex !== '') {
                            $buf .= $decodedHex;
                        }
                        $offset = $end + 1;
                        continue;
                    }
                }
                $offset++;
            }

            if ($buf !== '') {
                $lines[] = $buf;
            }
        }

        $cleaned = [];
        foreach ($lines as $piece) {
            $p = trim(str_replace(["\r", "\0"], ['', ''], $piece));
            // Abaikan fragmen biner non-cetak
            $p = preg_replace('/[^\P{C}\t ]+/u', '', $p) ?? '';
            $p = trim($p);
            if ($p !== '') {
                $cleaned[] = $p;
            }
        }

        return implode(' ', $cleaned);
    }

    /** @return array{0:string,1:int} */
    private static function readPdfLiteralString(string $s, int $start): array
    {
        $len = strlen($s);
        $i = $start + 1;
        $depth = 1;
        $out = '';

        while ($i < $len && $depth > 0) {
            $c = $s[$i];
            if ($c === '\\' && $i + 1 < $len) {
                $next = $s[$i + 1];
                switch ($next) {
                    case 'n':
                        $out .= "\n";
                        $i += 2;
                        continue 2;
                    case 'r':
                        $out .= "\r";
                        $i += 2;
                        continue 2;
                    case 't':
                        $out .= "\t";
                        $i += 2;
                        continue 2;
                    case 'b':
                    case 'f':
                        $i += 2;
                        continue 2;
                    case '\\':
                    case '(':
                    case ')':
                        $out .= $next;
                        $i += 2;
                        continue 2;
                    case "\r":
                        $i += ($i + 2 < $len && $s[$i + 2] === "\n") ? 3 : 2;
                        continue 2;
                    case "\n":
                        $i += 2;
                        continue 2;
                    default:
                        if ($next >= '0' && $next <= '7') {
                            $oct = $next;
                            if ($i + 2 < $len && $s[$i + 2] >= '0' && $s[$i + 2] <= '7') {
                                $oct .= $s[$i + 2];
                                if ($i + 3 < $len && $s[$i + 3] >= '0' && $s[$i + 3] <= '7') {
                                    $oct .= $s[$i + 3];
                                }
                            }
                            $code = octdec($oct);
                            if ($code >= 32 || $code === 10 || $code === 9) {
                                $out .= chr($code);
                            }
                            $i += 1 + strlen($oct);
                            continue 2;
                        }
                        $out .= $next;
                        $i += 2;
                        continue 2;
                }
            }

            if ($c === '(') {
                $depth++;
                $out .= $c;
            } elseif ($c === ')') {
                $depth--;
                if ($depth > 0) {
                    $out .= $c;
                }
            } else {
                $out .= $c;
            }
            $i++;
        }

        if (!mb_check_encoding($out, 'UTF-8')) {
            $conv = @mb_convert_encoding($out, 'UTF-8', 'Windows-1252,ISO-8859-1');
            if (is_string($conv)) {
                $out = $conv;
            }
        }

        return [$out, $i];
    }

    private static function decodePdfHex(string $hex): string
    {
        $hex = preg_replace('/\s+/', '', $hex) ?? '';
        if ($hex === '' || !ctype_xdigit($hex)) {
            return '';
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }
        $bin = @hex2bin($hex);
        if (!is_string($bin) || $bin === '') {
            return '';
        }
        if (str_starts_with($bin, "\xFE\xFF")) {
            $utf = @mb_convert_encoding(substr($bin, 2), 'UTF-8', 'UTF-16BE');
            return is_string($utf) ? $utf : '';
        }
        // Jangan kembalikan glyph index acak non-ASCII tanpa encoding jelas kecuali ASCII printable
        if (preg_match('/^[\x20-\x7E\r\n\t]+$/', $bin)) {
            return $bin;
        }
        return '';
    }
}
