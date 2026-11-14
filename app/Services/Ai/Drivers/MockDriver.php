<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Analysis\ClaimAnalyzer;
use App\Services\Analysis\Lexicon;

/**
 * MockDriver: WAJIB ada dari awal (Master §8). Keluaran SELALU is_simulation=true
 * dan tidak boleh ditampilkan sebagai hasil model sungguhan. Dipakai untuk
 * pengembangan UI, pengujian, dan demonstrasi tanpa AI Server.
 *
 * Mode kegagalan (AI_MOCK_MODE) untuk menguji degradasi anggun:
 * ok | offline | invalid_json | timeout | busy
 */
class MockDriver implements AiDriver
{
    public function __construct(private ClaimAnalyzer $claims, private ?string $mode = null)
    {
        $this->mode = $mode ?? config('trace.ai.mock_mode', 'ok');
    }

    public function name(): string { return 'mock'; }
    public function isSimulation(): bool { return true; }
    public function modelName(): ?string { return 'LOCAL-STANDBY-ENGINE'; }

    private function guard(): void
    {
        match ($this->mode) {
            'offline' => throw new AiUnavailableException('AI Server offline (mode uji MockDriver).', 'OFFLINE'),
            'timeout' => throw new AiUnavailableException('AI Server tidak merespons dalam batas waktu (mode uji MockDriver).', 'ERROR'),
            'busy' => throw new AiUnavailableException('AI Server penuh / antrean sibuk (mode uji MockDriver).', 'BUSY'),
            default => null,
        };
    }

    public function checkHealth(): array
    {
        $status = match ($this->mode) {
            'offline' => 'OFFLINE', 'timeout' => 'ERROR', 'busy' => 'BUSY', default => 'STANDBY',
        };
        return ['status' => $status, 'latency_ms' => 1, 'runtime' => 'MockDriver', 'runtime_version' => '1.0',
            'error' => $status === 'STANDBY' ? null : 'Mode uji: ' . $this->mode];
    }

    public function listModels(): array
    {
        $this->guard();
        return [[
            'name' => 'LOCAL-STANDBY-ENGINE', 'size' => null, 'quantization' => null,
            'context_length' => null, 'status' => 'READY',
        ]];
    }

    public function getModelInfo(string $model): array
    {
        return ['name' => $model, 'simulation' => true];
    }

    public function validateConnection(): array
    {
        $h = $this->checkHealth();
        return ['ok' => $h['status'] === 'STANDBY', 'message' => 'Engine lokal dalam status STANDBY.', 'details' => $h];
    }

    public function chat(array $messages, array $options = []): array
    {
        $this->guard();
        $ctx = $options['context_summary'] ?? [];
        $lines = ['[SIMULASI - MockDriver] Ringkasan berdasarkan hasil tool backend yang benar-benar dieksekusi:'];
        foreach ($ctx as $k => $v) {
            $lines[] = '- ' . $k . ': ' . (is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE));
        }
        if (count($lines) === 1) {
            $lines[] = '- Tidak ada hasil tool yang relevan. Pilih kasus atau ajukan pertanyaan yang lebih spesifik.';
        }
        $lines[] = 'Catatan: jawaban ini bukan keluaran model sungguhan. Keputusan akhir tetap pada analis.';
        return ['content' => implode("\n", $lines), 'model' => $this->modelName(), 'is_simulation' => true];
    }

    public function analyze(array $request): string
    {
        $this->guard();
        if ($this->mode === 'invalid_json') {
            return 'Berikut hasil analisis: {"summary": tidak valid';
        }

        $payload = $request['payload'];
        $text = (string) ($payload['text'] ?? '');
        $evidence = $payload['evidence'] ?? [];
        $claims = array_map(fn ($c) => [
            'id' => $c['id'],
            'text' => $c['text'],
            'type' => $c['type'],
            'check_status' => 'BELUM TERVERIFIKASI',
        ], $this->claims->extract($text, 6));
        if (!$claims && trim($text) !== '') {
            $claims[] = ['id' => 'c1', 'text' => mb_substr(trim($text), 0, 300), 'type' => 'FAKTA', 'check_status' => 'BELUM TERVERIFIKASI'];
        }

        $okEvidence = array_values(array_filter($evidence, fn ($e) => ($e['access_status'] ?? '') === 'OK'));
        $insufficient = count($okEvidence) < 1;

        // Rubrik simulasi sederhana (BUKAN metode produksi)
        $score = 60;
        $emotive = Lexicon::matchCues($text, ['viral', 'sebarkan', 'breaking', 'heboh', 'terbongkar', 'bocoran', 'gawat', 'wajib baca']);
        $score -= count($emotive) * 8;
        if (preg_match_all('/!{2,}/', $text)) { $score -= 6; }
        if (preg_match('/\b[A-Z]{6,}\b/', $text)) { $score -= 4; }
        $score += min(20, count($okEvidence) * 10);
        $score = max(5, min(95, $score));

        $terminate = false;
        foreach ($claims as $c) {
            $a = Lexicon::actions($c['text']);
            if (in_array('TERMINATE', $a['actions'], true)) { $terminate = true; }
        }
        $threat = ($terminate && $emotive) ? 'SEDANG' : 'RENDAH';

        $findings = [];
        foreach ($okEvidence as $e) {
            $findings[] = [
                'statement' => 'Materi pembanding tersedia pada ' . $e['id'] . ' (' . ($e['publisher'] ?: 'penerbit tidak diketahui') . ').',
                'type' => 'KLAIM_SUMBER',
                'evidence_ids' => [$e['id']],
            ];
        }
        $findings[] = [
            'statement' => $insufficient
                ? 'Belum ada bukti pembanding yang berhasil diperoleh; klaim tidak dapat diverifikasi pada tahap ini.'
                : 'Klaim belum diverifikasi terhadap sumber primer; periksa Cross-Source dan Authenticity Comparator.',
            'type' => 'INTERPRETASI_AI',
            'evidence_ids' => [],
        ];
        $findings[] = [
            'statement' => 'Lengkapi sumber primer dan jalankan Cross-Source Verification sebelum menyimpulkan.',
            'type' => 'REKOMENDASI',
            'evidence_ids' => [],
        ];

        $result = [
            'module' => $request['module'],
            'summary' => '[SIMULASI] Ringkasan netral: ' . mb_substr(trim($text), 0, 220) . (mb_strlen($text) > 220 ? '…' : ''),
            'is_simulation' => true,
            'model_name' => $this->modelName(),
            'model_version' => '1.0',
            'claims' => $claims,
            'verification_status' => $insufficient ? 'TIDAK CUKUP BUKTI' : 'BELUM TERVERIFIKASI',
            'content_indicator' => 'BELUM DAPAT DIPASTIKAN',
            'classification' => 'UNVERIFIED',
            'credibility_score' => $score,
            'threat_level' => $threat,
            'confidence' => 0.4,
            'linguistic_analysis' => [
                'emotion' => $emotive ? 'Terdapat diksi bernada seruan: ' . implode(', ', $emotive) . '.' : 'Tidak ditemukan diksi emotif yang menonjol (aturan sederhana).',
                'fallacy' => 'Tidak dinilai pada mode simulasi.',
                'source_verification' => $insufficient ? 'Tidak ada sumber pembanding yang berhasil diperoleh.' : 'Sumber pembanding tersedia, keaslian belum diperiksa.',
            ],
            'findings' => $findings,
            'evidence' => array_map(fn ($e) => ['id' => $e['id'], 'source_id' => $e['source_id'] ?? null, 'excerpt' => mb_substr((string) ($e['excerpt'] ?? ''), 0, 400)], $okEvidence),
            'sources' => array_map(fn ($e) => [
                'id' => $e['source_id'] ?? $e['id'], 'url' => $e['url'] ?? '', 'publisher' => $e['publisher'] ?? '',
                'published_at' => $e['published_at'] ?? null, 'retrieved_at' => $e['retrieved_at'] ?? now()->toIso8601String(),
                'access_status' => $e['access_status'],
            ], $evidence),
            'recommendations' => [
                'Jalankan Cross-Source Verification terhadap sumber referensi.',
                'Bandingkan dengan materi asli melalui Authenticity Comparator.',
                'Jangan tetapkan label akhir sebelum validasi analis.',
            ],
            'limitations' => [
                'Hasil ini dari MockDriver (SIMULASI), bukan model AI sungguhan.',
                'Skor kredibilitas memakai rubrik sederhana dan bukan probabilitas kebenaran.',
                $insufficient ? 'Bukti pembanding belum tersedia.' : 'Keaslian sumber belum diverifikasi.',
            ],
            'tactical_summary' => 'Materi dianalisis pada mode simulasi. Status verifikasi: ' . ($insufficient ? 'tidak cukup bukti' : 'belum terverifikasi') . '. Diperlukan validasi analis sebelum tindak lanjut.',
        ];

        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /** Embedding simulasi: hashing trigram karakter (bukan semantik sungguhan). */
    public function embed(array $texts): array
    {
        $this->guard();
        $dim = 128;
        $out = [];
        foreach ($texts as $t) {
            $v = array_fill(0, $dim, 0.0);
            $s = ' ' . mb_strtolower(preg_replace('/\s+/u', ' ', $t), 'UTF-8') . ' ';
            $len = mb_strlen($s, 'UTF-8');
            for ($i = 0; $i < $len - 2; $i++) {
                $g = mb_substr($s, $i, 3, 'UTF-8');
                $v[crc32($g) % $dim] += 1.0;
            }
            $out[] = $v;
        }
        return $out;
    }
}
