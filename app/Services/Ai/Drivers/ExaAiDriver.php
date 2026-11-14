<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\Support\JsonExtractor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * ExaAiDriver: konektor Cloud API ke Exa.ai (Answer / Chat Completions & Neural Search).
 * Mendukung mode HYBRID_AUTO dengan Auto-Failover instan ke AI Lokal bila jaringan/API terputus.
 */
class ExaAiDriver implements AiDriver
{
    public function __construct(
        private ?string $apiKey,
        private string $model = 'exa',
        private int $timeout = 12,
        private string $baseUrl = 'https://api.exa.ai',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = trim($model) !== '' ? trim($model) : 'exa';
    }

    public function name(): string { return 'exa_ai'; }
    public function isSimulation(): bool { return false; }
    public function modelName(): ?string { return $this->model; }

    public function checkHealth(): array
    {
        if (!$this->apiKey) {
            return [
                'status' => 'NOT_CONFIGURED',
                'latency_ms' => null,
                'runtime' => 'Exa.ai Neural API',
                'runtime_version' => 'v1',
                'loaded_models' => [],
                'error' => 'API Key Exa.ai belum dikonfigurasi.',
            ];
        }

        $t0 = microtime(true);
        try {
            $resp = Http::connectTimeout(min(4, $this->timeout))
                ->timeout(min(6, $this->timeout))
                ->acceptJson()
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])
                ->post($this->baseUrl . '/search', [
                    'query' => 'indonesia',
                    'numResults' => 1,
                ]);

            $latency = (int) round((microtime(true) - $t0) * 1000);
            if ($resp->successful()) {
                return [
                    'status' => 'ONLINE',
                    'latency_ms' => $latency,
                    'runtime' => 'Exa.ai Neural API',
                    'runtime_version' => 'v1',
                    'loaded_models' => [['name' => $this->modelName()]],
                    'error' => null,
                ];
            }

            $msg = (string) ($resp->json('error') ?? $resp->json('message') ?? ('HTTP ' . $resp->status()));
            return [
                'status' => $resp->status() === 429 ? 'BUSY' : 'ERROR',
                'latency_ms' => $latency,
                'runtime' => 'Exa.ai Neural API',
                'runtime_version' => 'v1',
                'loaded_models' => [],
                'error' => is_string($msg) ? $msg : 'Error API Exa.ai',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'OFFLINE',
                'latency_ms' => null,
                'runtime' => 'Exa.ai Neural API',
                'runtime_version' => 'v1',
                'loaded_models' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    public function listModels(): array
    {
        return [
            ['name' => 'exa', 'parameters' => 'Exa Answer / Search Reasoning', 'status' => 'READY', 'loaded' => true],
            ['name' => 'exa-pro', 'parameters' => 'Exa Pro Deep Research', 'status' => 'READY', 'loaded' => true],
        ];
    }

    public function getModelInfo(string $model): array
    {
        foreach ($this->listModels() as $m) {
            if ($m['name'] === $model) {
                return $m;
            }
        }
        return ['name' => $model, 'parameters' => 'Exa.ai Cloud', 'status' => 'READY', 'loaded' => true];
    }

    public function validateConnection(): array
    {
        $h = $this->checkHealth();
        return [
            'ok' => $h['status'] === 'ONLINE',
            'status' => $h['status'],
            'latency_ms' => $h['latency_ms'],
            'message' => $h['status'] === 'ONLINE'
                ? "Exa.ai Neural API ONLINE ({$h['latency_ms']} ms) • Model: {$this->modelName()} siap."
                : 'Exa.ai tidak tersedia (' . ($h['error'] ?? $h['status']) . '). Sistem akan Auto-Failover ke AI Lokal.',
            'details' => $h,
        ];
    }

    private function completion(array $messages): string
    {
        if (!$this->apiKey) {
            throw new AiUnavailableException('API Key Exa.ai belum diisi.', 'NOT_CONFIGURED');
        }

        try {
            $resp = Http::connectTimeout(min(4, $this->timeout))
                ->timeout($this->timeout)
                ->acceptJson()
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])
                ->post($this->baseUrl . '/chat/completions', [
                    'model' => $this->modelName(),
                    'messages' => $messages,
                    'stream' => false,
                ])
                ->throw();
        } catch (ConnectionException $e) {
            throw new AiUnavailableException('Exa.ai putus koneksi/timeout: ' . $e->getMessage(), 'OFFLINE');
        } catch (RequestException $e) {
            $code = $e->response?->status();
            $detail = (string) ($e->response?->json('error') ?? $e->response?->json('message') ?? '');
            throw new AiUnavailableException("Exa.ai HTTP {$code}" . ($detail ? " ({$detail})" : ''), in_array($code, [429, 503], true) ? 'BUSY' : 'ERROR');
        }

        $text = (string) ($resp->json('choices.0.message.content') ?? '');
        if (trim($text) === '') {
            throw new AiUnavailableException('Exa.ai mengembalikan respons kosong.', 'ERROR');
        }

        return $text;
    }

    public function chat(array $messages, array $options = []): array
    {
        $raw = $this->completion($messages);
        return [
            'content' => JsonExtractor::stripThinking($raw),
            'thinking' => JsonExtractor::extractThinking($raw),
            'model' => $this->modelName(),
            'is_simulation' => false,
        ];
    }

    public function analyze(array $request): string
    {
        return $this->completion($request['messages']);
    }

    public function embed(array $texts): array
    {
        throw new AiUnavailableException('Gunakan model embedding lokal untuk pembuatan vektor.', 'OFFLINE');
    }
}
