<?php

namespace App\Services\DataAcquisition;

use App\Models\EvidenceItem;
use App\Models\InvestigationCase;
use App\Models\Source;
use App\Models\User;
use App\Services\Ai\Support\PromptGuard;
use App\Services\Support\AuditLogger;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Data Acquisition & Evidence Management (Master Prompt §9, Supplemental §4).
 * Modular connectors:
 * 1. Manual Evidence Import (P0)
 * 2. URL Fetch untuk halaman yang sah (P0)
 * 3. RSS/News Feed (P1)
 *
 * Menjamin:
 * - Tidak menerobos CAPTCHA/login/private.
 * - Anti-SSRF (menolak loopback/private/link-local/reserved IP dan skema non-HTTP).
 * - Membedakan status akses: OK | GAGAL | TIDAK TERSEDIA | BELUM DIPERIKSA.
 * - Simpan hash konten (SHA-256), timestamp, URL, penerbit.
 * - Konten dari web dibersihkan dan diperlakukan sebagai DATA TIDAK TEPERCAYA.
 */
class EvidenceCollectorService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    /** Validasi file unggahan berbasis ekstensi dan magic bytes (tanpa bergantung pada fileinfo/finfo). */
    public static function assertSafeUpload(UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $allowed = config('trace.upload.mimes', ['txt', 'pdf', 'png', 'jpg', 'jpeg', 'md', 'html']);
        if (! in_array($ext, $allowed, true)) {
            throw ValidationException::withMessages([
                'evidence_file' => 'Ekstensi berkas tidak diizinkan. Gunakan: ' . implode(', ', $allowed),
            ]);
        }

        $path = $file->getRealPath();
        $head = $path && is_file($path) ? (string) @file_get_contents($path, false, null, 0, 512) : '';

        $ok = match ($ext) {
            'pdf' => str_starts_with($head, '%PDF-'),
            'png' => str_starts_with($head, "\x89PNG\r\n\x1a\n"),
            'jpg', 'jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
            'txt', 'md', 'html' => ! str_contains($head, "\x00")
                && ! preg_match('/^(MZ|\x7FELF|%!PS|#!\s*\/)/', $head)
                && ($ext === 'html' || mb_check_encoding($head, 'UTF-8')),
            default => false,
        };

        if (! $ok) {
            throw ValidationException::withMessages([
                'evidence_file' => 'Isi berkas tidak cocok dengan ekstensi atau berpotensi tidak aman.',
            ]);
        }
    }

    /** Periksa apakah URL layak diakses tanpa memicu SSRF ke jaringan lokal/internal. */
    public static function validatePublicUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return 'URL tidak memiliki skema atau host yang valid.';
        }
        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return "Skema URL '{$scheme}' tidak diizinkan (hanya http/https).";
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'URL dengan kredensial tersemat tidak diizinkan.';
        }
        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443, 8080, 8443], true)) {
            return "Port URL ({$parts['port']}) tidak diizinkan oleh kebijakan keamanan.";
        }

        if (config('trace.fetch.allow_private', false)) {
            return null;
        }

        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === '' || $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.lan')
            || str_ends_with($host, '.corp')
            || $host === 'metadata.google.internal'
            || $host === '169.254.169.254'
            || $host === '100.100.100.200') {
            return 'Host internal/lokal atau metadata cloud diblokir oleh kebijakan anti-SSRF.';
        }

        // Tolak representasi IP tersamar (desimal integer murni, hex 0x..., atau segmen oktal)
        if (ctype_digit($host) || preg_match('/^0x[0-9a-f]+$/i', $host) || preg_match('/(^|\.)0\d+/', $host)) {
            return 'Format alamat host numerik tersamar ditolak oleh kebijakan anti-SSRF.';
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! self::isPublicIp($host)) {
                return "Alamat IP {$host} berada dalam rentang privat/loopback/tercadang.";
            }
            return null;
        }

        $ips = @gethostbynamel($host) ?: [];
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return "Host {$host} mengarah ke alamat IP non-publik ({$ip}).";
            }
        }
        if (function_exists('dns_get_record') && defined('DNS_AAAA')) {
            $aaaa = @dns_get_record($host, DNS_AAAA) ?: [];
            foreach ($aaaa as $rec) {
                $ip6 = $rec['ipv6'] ?? null;
                if ($ip6 && ! self::isPublicIp($ip6)) {
                    return "Host {$host} mengarah ke alamat IPv6 non-publik.";
                }
            }
        }

        return null;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * Manual Evidence Import (P0)
     */
    public function importManual(
        InvestigationCase $case,
        array $data,
        ?UploadedFile $file = null,
        ?User $user = null
    ): EvidenceItem {
        $extractedText = trim((string) ($data['extracted_text'] ?? ''));
        $rawPath = null;
        $notes = (string) ($data['collection_notes'] ?? '');

        if ($file && $file->isValid()) {
            self::assertSafeUpload($file);
            $ext = strtolower($file->getClientOriginalExtension());
            $safeName = Str::random(40) . ($ext ? '.' . $ext : '');
            $relDir = 'evidence/' . $case->case_code;
            $rawPath = $relDir . '/' . $safeName;
            if (class_exists(\finfo::class)) {
                $rawPath = $file->storeAs($relDir, $safeName, 'local');
            } else {
                $diskRoot = (string) config('filesystems.disks.local.root', storage_path('app/private'));
                $destDir = rtrim($diskRoot, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
                if (! is_dir($destDir) && ! @mkdir($destDir, 0750, true) && ! is_dir($destDir)) {
                    throw new Exception('Direktori penyimpanan bukti privat tidak dapat dibuat.');
                }
                file_put_contents($destDir . DIRECTORY_SEPARATOR . $safeName, (string) file_get_contents($file->getRealPath()), LOCK_EX);
                @chmod($destDir . DIRECTORY_SEPARATOR . $safeName, 0640);
            }
            if (empty($extractedText) && in_array($ext, ['txt', 'md', 'html'], true)) {
                $extractedText = (string) file_get_contents($file->getRealPath());
                if ($ext === 'html') {
                    $cleaned = preg_replace('/<(script|style|nav|header|footer|aside)[^>]*>.*?<\/\\1>/is', '', $extractedText);
                    $extractedText = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($cleaned))));
                }
            } elseif (empty($extractedText) && $ext === 'pdf') {
                $extractedText = PdfTextExtractor::extractFromFile((string) $file->getRealPath());
                if ($extractedText !== '') {
                    $notes .= "\n[PDF Extractor]: Teks diekstrak otomatis dari berkas PDF (" . mb_strlen($extractedText) . ' karakter).';
                } else {
                    $notes .= "\n[PDF Extractor]: Berkas PDF berhasil disimpan, tetapi tidak ada teks tersemat yang dapat diekstrak otomatis (kemungkinan PDF hasil scan/gambar atau terenkripsi).";
                }
            }
        }

        $hash = EvidenceItem::hashOf($extractedText ?: ($file ? file_get_contents($file->getRealPath()) : ''));

        // Cek prompt injection
        $guard = PromptGuard::scan($extractedText);
        if ($guard['flagged']) {
            $notes .= "\n[PERINGATAN KEAMANAN]: Teks mengandung indikasi prompt injection: " . implode(', ', $guard['patterns']);
        }

        $source = null;
        if (!empty($data['publisher']) || !empty($data['url'])) {
            $source = Source::create([
                'case_id' => $case->id,
                'source_code' => $case->nextSourceCode(),
                'url' => $data['url'] ?? null,
                'publisher' => $data['publisher'] ?? 'Penerbit Manual',
                'platform' => $data['platform'] ?? 'MANUAL_IMPORT',
                'published_at' => !empty($data['published_at']) ? $data['published_at'] : null,
                'retrieved_at' => now(),
                'access_status' => 'OK',
                'authenticity' => $data['authenticity'] ?? 'UNKNOWN',
                'is_simulation' => (bool) ($data['is_simulation'] ?? false),
            ]);
        }

        $item = EvidenceItem::create([
            'case_id' => $case->id,
            'source_id' => $source?->id,
            'evidence_code' => $case->nextEvidenceCode(),
            'type' => $data['type'] ?? 'TEXT',
            'role' => $data['role'] ?? 'OTHER',
            'title' => $data['title'] ?? 'Bukti Manual ' . now()->format('Y-m-d H:i'),
            'publisher' => $data['publisher'] ?? null,
            'platform' => $data['platform'] ?? 'MANUAL',
            'url' => $data['url'] ?? null,
            'published_at' => !empty($data['published_at']) ? $data['published_at'] : null,
            'retrieved_at' => now(),
            'access_status' => 'OK',
            'extracted_text' => $extractedText,
            'raw_path' => $rawPath,
            'content_hash' => $hash,
            'collection_method' => 'MANUAL_IMPORT',
            'collection_notes' => trim($notes),
            'engagement' => $data['engagement'] ?? null,
            'is_simulation' => (bool) ($data['is_simulation'] ?? false),
            'added_by' => $user?->id,
        ]);

        $this->audit->log('EVIDENCE_IMPORT_MANUAL', $item, [
            'case_id' => $case->id,
            'evidence_code' => $item->evidence_code,
            'has_file' => (bool) $file,
        ], $user?->id);

        return $item;
    }

    /**
     * URL Evidence Fetch (P0)
     */
    public function fetchUrl(
        InvestigationCase $case,
        string $url,
        array $extra = [],
        ?User $user = null
    ): EvidenceItem {
        $cfg = config('trace.fetch');
        $timeout = $cfg['timeout'] ?? 10;
        $maxBytes = $cfg['max_bytes'] ?? 2_000_000;
        $maxRedirects = (int) ($cfg['max_redirects'] ?? 3);

        $host = parse_url($url, PHP_URL_HOST);
        $extractedText = '';
        $accessStatus = 'OK';
        $title = $extra['title'] ?? ($host ? 'Web Capture: ' . $host : 'Web Document');
        $notes = '';

        $ssrfError = self::validatePublicUrl($url);
        if ($ssrfError !== null) {
            $accessStatus = 'GAGAL';
            $notes = 'Ditolak (kebijakan anti-SSRF): ' . $ssrfError;
            $this->audit->log('EVIDENCE_FETCH_BLOCKED_SSRF', $case, ['url' => mb_substr($url, 0, 200), 'reason' => $ssrfError], $user?->id, 'DENIED');
        } else {
            try {
                $currentUrl = $url;
                $response = null;
                for ($hop = 0; $hop <= $maxRedirects; $hop++) {
                    if ($err = self::validatePublicUrl($currentUrl)) {
                        $accessStatus = 'GAGAL';
                        $notes .= 'Redirect ditolak (kebijakan anti-SSRF): ' . $err;
                        $response = null;
                        break;
                    }
                    $response = Http::timeout($timeout)
                        ->withOptions(['allow_redirects' => false])
                        ->withUserAgent($cfg['user_agent'] ?? 'Trace-S/1.0')
                        ->get($currentUrl);

                    if ($response->status() >= 300 && $response->status() < 400 && ($loc = $response->header('Location'))) {
                        if ($hop === $maxRedirects) {
                            $accessStatus = 'GAGAL';
                            $notes .= "Batas redirect ({$maxRedirects}) terlampaui.";
                            $response = null;
                            break;
                        }
                        $currentUrl = str_starts_with($loc, 'http') ? $loc : (parse_url($currentUrl, PHP_URL_SCHEME) . '://' . parse_url($currentUrl, PHP_URL_HOST) . '/' . ltrim($loc, '/'));
                        continue;
                    }
                    break;
                }

                if ($response && $response->successful()) {
                    $html = $response->body();
                    if (strlen($html) > $maxBytes) {
                        $html = substr($html, 0, $maxBytes);
                        $notes .= "Teks dibatasi pada batas maksimum {$maxBytes} bytes. ";
                    }

                    // Ekstraksi judul jika ada
                    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                        $pageTitle = trim(html_entity_decode(strip_tags($matches[1])));
                        if (!empty($pageTitle)) {
                            $title = $pageTitle;
                        }
                    }

                    // Hapus script, style, nav, footer
                    $cleaned = preg_replace('/<(script|style|nav|header|footer|aside)[^>]*>.*?<\/\\1>/is', '', $html);
                    $extractedText = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($cleaned))));

                    if (empty($extractedText)) {
                        $accessStatus = 'TIDAK TERSEDIA';
                        $notes .= "Halaman berhasil diakses tetapi tidak ada teks yang dapat diekstraksi.";
                    }
                } elseif ($response) {
                    $accessStatus = 'GAGAL';
                    $notes .= "HTTP error: status " . $response->status();
                }
            } catch (Exception $e) {
                $accessStatus = 'GAGAL';
                $notes .= "Gagal menghubungi URL: " . $e->getMessage();
            }
        }

        $hash = EvidenceItem::hashOf($extractedText);
        $guard = PromptGuard::scan($extractedText);
        if ($guard['flagged']) {
            $notes .= "\n[PERINGATAN KEAMANAN]: Terdeteksi teks mencurigakan (prompt injection): " . implode(', ', $guard['patterns']);
        }

        $source = Source::create([
            'case_id' => $case->id,
            'source_code' => $case->nextSourceCode(),
            'url' => $url,
            'publisher' => $extra['publisher'] ?? ($host ?: 'Web Source'),
            'platform' => $extra['platform'] ?? 'WEB',
            'published_at' => $extra['published_at'] ?? null,
            'retrieved_at' => now(),
            'access_status' => $accessStatus,
            'authenticity' => 'UNKNOWN',
            'is_simulation' => (bool) ($extra['is_simulation'] ?? false),
        ]);

        $item = EvidenceItem::create([
            'case_id' => $case->id,
            'source_id' => $source->id,
            'evidence_code' => $case->nextEvidenceCode(),
            'type' => 'URL',
            'role' => $extra['role'] ?? 'REFERENCE',
            'title' => $title,
            'publisher' => $extra['publisher'] ?? ($host ?: 'Web Source'),
            'platform' => $extra['platform'] ?? 'WEB',
            'url' => $url,
            'published_at' => $extra['published_at'] ?? null,
            'retrieved_at' => now(),
            'access_status' => $accessStatus,
            'extracted_text' => $extractedText,
            'content_hash' => $hash,
            'collection_method' => 'URL_FETCH',
            'collection_notes' => trim($notes),
            'engagement' => $extra['engagement'] ?? null,
            'is_simulation' => (bool) ($extra['is_simulation'] ?? false),
            'added_by' => $user?->id,
        ]);

        $this->audit->log('EVIDENCE_URL_FETCH', $item, [
            'case_id' => $case->id,
            'url' => $url,
            'status' => $accessStatus,
        ], $user?->id);

        return $item;
    }
}
