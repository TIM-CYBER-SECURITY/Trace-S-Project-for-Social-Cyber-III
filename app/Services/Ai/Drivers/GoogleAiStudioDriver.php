<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\Support\JsonExtractor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * GoogleAiStudioDriver: konektor Cloud API ke Google AI Studio (Gemini).
 * Digunakan sebagai Primary Cloud Engine pada mode HYBRID_AUTO dengan auto-failover ke AI Lokal.
 */
class GoogleAiStudioDriver implements AiDriver
{
    public function __construct(
        private ?string $apiKey,
        private string $model = 'gemini-2.0-flash',
        private int $timeout = 10,
        private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = trim($model) !== '' ? trim($model) : 'gemini-2.0-flash';
    }

    public function name(): string { return 'google_ai_studio'; }
    public function isSimulation(): bool { return false; }
    public function modelName(): ?string { return $this->model; }

    public function checkHealth(): array
    {
        if (!$this->apiKey) {
            return [
                'status' => 'NOT_CONFIGURED',
                'latency_ms' => null,
                'runtime' => 'Google AI Studio (Gemini API)',
                'runtime_version' => 'v1beta',
                'loaded_models' => [],
                'error' => 'API Key Google AI Studio belum dikonfigurasi.',
            ];
        }

        $t0 = microtime(true);
        try {
            $resp = Http::connectTimeout(min(4, $this->timeout))
                ->timeout(min(6, $this->timeout))
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->get($this->baseUrl . '/models?pageSize=20');

            $latency = (int) round((microtime(true) - $t0) * 1000);
            if ($resp->successful()) {
                return [
                    'status' => 'ONLINE',
                    'latency_ms' => $latency,
                    'runtime' => 'Google AI Studio (Gemini API)',
                    'runtime_version' => 'v1beta',
                    'loaded_models' => [['name' => $this->modelName()]],
                    'error' => null,
                ];
            }

            $msg = (string) ($resp->json('error.message') ?? ('HTTP ' . $resp->status()));
            return [
                'status' => $resp->status() === 429 ? 'BUSY' : 'ERROR',
                'latency_ms' => $latency,
                'runtime' => 'Google AI Studio (Gemini API)',
                'runtime_version' => 'v1beta',
                'loaded_models' => [],
                'error' => $msg,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'OFFLINE',
                'latency_ms' => null,
                'runtime' => 'Google AI Studio (Gemini API)',
                'runtime_version' => 'v1beta',
                'loaded_models' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    public function listModels(): array
    {
        return [
            ['name' => 'gemini-2.5-flash', 'parameters' => 'Gemini 2.5 Flash', 'status' => 'READY', 'loaded' => true],
            ['name' => 'gemini-2.0-flash', 'parameters' => 'Gemini 2.0 Flash', 'status' => 'READY', 'loaded' => true],
            ['name' => 'gemini-1.5-flash', 'parameters' => 'Gemini 1.5 Flash', 'status' => 'READY', 'loaded' => true],
            ['name' => 'gemini-1.5-pro', 'parameters' => 'Gemini 1.5 Pro', 'status' => 'READY', 'loaded' => true],
        ];
    }

    public function getModelInfo(string $model): array
    {
        foreach ($this->listModels() as $m) {
            if ($m['name'] === $model) {
                return $m;
            }
        }
        return ['name' => $model, 'parameters' => 'Gemini Cloud', 'status' => 'READY', 'loaded' => true];
    }

    public function validateConnection(): array
    {
        $h = $this->checkHealth();
        return [
            'ok' => $h['status'] === 'ONLINE',
            'status' => $h['status'],
            'latency_ms' => $h['latency_ms'],
            'message' => $h['status'] === 'ONLINE'
                ? "Google AI Studio ONLINE ({$h['latency_ms']} ms) • Model: {$this->modelName()} siap melayani."
                : 'Google AI Studio tidak tersedia (' . ($h['error'] ?? $h['status']) . '). Sistem akan Auto-Failover ke AI Lokal.',
            'details' => $h,
        ];
    }

    /** @param array<int,array{role:string,content:string}> $messages */
    private function generateContent(array $messages, float $temperature = 0.2, bool $jsonMode = false, int $maxTokens = 1400): string
    {
        if (!$this->apiKey) {
            throw new AiUnavailableException('API Key Google AI Studio belum diisi.', 'NOT_CONFIGURED');
        }

        $systemParts = [];
        $contents = [];
        foreach ($messages as $m) {
            $role = strtolower((string) ($m['role'] ?? 'user'));
            $txt = (string) ($m['content'] ?? '');
            if ($txt === '') {
                continue;
            }
            if ($role === 'system') {
                $systemParts[] = ['text' => $txt];
            } else {
                $contents[] = [
                    'role' => $role === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $txt]],
                ];
            }
        }

        if ($contents === []) {
            $contents[] = ['role' => 'user', 'parts' => [['text' => 'Analisis konten berikut.']]];
        }

        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
            ],
        ];
        if ($systemParts !== []) {
            $body['systemInstruction'] = ['parts' => $systemParts];
        }
        if ($jsonMode) {
            $body['generationConfig']['responseMimeType'] = 'application/json';
        }

        $url = "{$this->baseUrl}/models/{$this->modelName()}:generateContent";

        try {
            $resp = Http::connectTimeout(min(4, $this->timeout))
                ->timeout($this->timeout)
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->post($url, $body)
                ->throw();
        } catch (ConnectionException $e) {
            throw new AiUnavailableException('Google AI Studio putus koneksi/timeout: ' . $e->getMessage(), 'OFFLINE');
        } catch (RequestException $e) {
            $code = $e->response?->status();
            $detail = (string) ($e->response?->json('error.message') ?? '');
            throw new AiUnavailableException("Google AI Studio HTTP {$code}" . ($detail ? " ({$detail})" : ''), in_array($code, [429, 503], true) ? 'BUSY' : 'ERROR');
        }

        $text = (string) ($resp->json('candidates.0.content.parts.0.text') ?? '');
        if (trim($text) === '') {
            throw new AiUnavailableException('Google AI Studio mengembalikan respons kosong.', 'ERROR');
        }

        return $text;
    }

    public function chat(array $messages, array $options = []): array
    {
        $raw = $this->generateContent(
            $messages,
            (float) ($options['temperature'] ?? 0.2),
            false,
            isset($options['max_tokens']) ? (int) $options['max_tokens'] : 1200
        );

        return [
            'content' => JsonExtractor::stripThinking($raw),
            'thinking' => JsonExtractor::extractThinking($raw),
            'model' => $this->modelName(),
            'is_simulation' => false,
        ];
    }

    public function analyze(array $request): string
    {
        return $this->generateContent($request['messages'], 0.1, true, 1600);
    }

    public function embed(array $texts): array
    {
        throw new AiUnavailableException('Gunakan model embedding lokal untuk pembuatan vektor.', 'OFFLINE');
    }
}
