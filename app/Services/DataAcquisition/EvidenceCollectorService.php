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
use Illuminate\Support\Facades\Storage;

/**
 * Data Acquisition & Evidence Management (Master Prompt §9, Supplemental §4).
 * Modular connectors:
 * 1. Manual Evidence Import (P0)
 * 2. URL Fetch untuk halaman yang sah (P0)
 * 3. RSS/News Feed (P1)
 *
 * Menjamin:
 * - Tidak menerobos CAPTCHA/login/private.
 * - Membedakan status akses: OK | GAGAL | TIDAK TERSEDIA | BELUM DIPERIKSA.
 * - Simpan hash konten (SHA-256), timestamp, URL, penerbit.
 * - Konten dari web dibersihkan dan diperlakukan sebagai DATA TIDAK TEPERCAYA.
 */
class EvidenceCollectorService
{
    public function __construct(private AuditLogger $audit)
    {
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

        if ($file && $file->isValid()) {
            $rawPath = $file->store('evidence/' . $case->case_code, 'local');
            if (empty($extractedText) && in_array(strtolower($file->getClientOriginalExtension()), ['txt', 'md', 'json', 'csv'])) {
                $extractedText = (string) file_get_contents($file->getRealPath());
            }
        }

        $hash = EvidenceItem::hashOf($extractedText ?: ($file ? file_get_contents($file->getRealPath()) : ''));

        // Cek prompt injection
        $guard = PromptGuard::scan($extractedText);
        $notes = $data['collection_notes'] ?? '';
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

        $host = parse_url($url, PHP_URL_HOST);
        $extractedText = '';
        $accessStatus = 'OK';
        $title = $extra['title'] ?? ($host ? 'Web Capture: ' . $host : 'Web Document');
        $notes = '';

        try {
            $response = Http::timeout($timeout)
                ->withUserAgent($cfg['user_agent'] ?? 'Trace-S/1.0')
                ->get($url);

            if ($response->successful()) {
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
            } else {
                $accessStatus = 'GAGAL';
                $notes .= "HTTP error: status " . $response->status();
            }
        } catch (Exception $e) {
            $accessStatus = 'GAGAL';
            $notes .= "Gagal menghubungi URL: " . $e->getMessage();
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
