<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\Support\JsonExtractor;
use App\Services\Ai\Support\LocalGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * OllamaDriver: engine inferensi lokal/intranet melalui Ollama REST API.
 * Endpoint: /api/tags, /api/ps, /api/version, /api/chat, /api/show, /api/embed.
 */
class OllamaDriver implements AiDriver
{
    public function __construct(
        protected ?string $baseUrl,
        protected ?string $model,
        protected int $timeout = 180,
        protected ?string $embeddingModel = null,
        protected ?string $apiKey = null,
    ) {
        $this->baseUrl = $baseUrl ? rtrim($baseUrl, '/') : null;
    }

    public function name(): string { return 'ollama'; }
    public function isSimulation(): bool { return false; }
    public function modelName(): ?string { return $this->model; }

    protected function http(?int $timeout = null): PendingRequest
    {
        if (!$this->baseUrl) {
            throw new AiUnavailableException('AI_BASE_URL belum dikonfigurasi (NOT CONFIGURED).', 'OFFLINE');
        }
        LocalGuard::assertLocal($this->baseUrl);
        $req = Http::timeout($timeout ?? $this->timeout)->acceptJson();
        return $this->apiKey ? $req->withToken($this->apiKey) : $req;
    }

    /** Bungkus kegagalan jaringan menjadi AiUnavailableException (tanpa hasil palsu). */
    protected function call(callable $fn)
    {
        try {
            return $fn();
        } catch (ConnectionException $e) {
            throw new AiUnavailableException('AI Server tidak dapat dihubungi: ' . $e->getMessage(), 'OFFLINE');
        } catch (RequestException $e) {
            $code = $e->response?->status();
            throw new AiUnavailableException("AI Server mengembalikan HTTP {$code}.", in_array($code, [429, 503], true) ? 'BUSY' : 'ERROR');
        }
    }

    public function checkHealth(): array
    {
        $t0 = microtime(true);
        try {
            $r = $this->http(3)->get($this->baseUrl . '/api/version');
            $r->throw();
            $latency = (int) round((microtime(true) - $t0) * 1000);
            $ps = $this->runningProcesses();

            return [
                'status' => 'ONLINE',
                'latency_ms' => $latency,
                'runtime' => 'Ollama',
                'runtime_version' => $r->json('version'),
                'loaded_models' => $ps,
                'error' => null,
            ];
        } catch (AiUnavailableException $e) {
            return ['status' => $e->state, 'latency_ms' => null, 'runtime' => 'Ollama', 'runtime_version' => null, 'loaded_models' => [], 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['status' => 'OFFLINE', 'latency_ms' => null, 'runtime' => 'Ollama', 'runtime_version' => null, 'loaded_models' => [], 'error' => $e->getMessage()];
        }
    }

    /** Ambil proses model yang sedang ter-load di memori/VRAM dari Ollama /api/ps. */
    public function runningProcesses(): array
    {
        try {
            $r = $this->http(2)->get($this->baseUrl . '/api/ps');
            if (!$r->successful()) {
                return [];
            }
            $out = [];
            foreach ($r->json('models', []) as $p) {
                $vram = (int) ($p['size_vram'] ?? 0);
                $ram = (int) ($p['size'] ?? 0);
                $out[] = [
                    'name' => $p['name'] ?? $p['model'] ?? '?',
                    'size_gb' => $ram > 0 ? round($ram / 1073741824, 2) : null,
                    'vram_gb' => $vram > 0 ? round($vram / 1073741824, 2) : 0.0,
                    'context_length' => $p['context_length'] ?? null,
                    'expires_at' => $p['expires_at'] ?? null,
                ];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    public function listModels(): array
    {
        $r = $this->call(fn () => $this->http(5)->get($this->baseUrl . '/api/tags')->throw());
        $running = collect($this->runningProcesses())->keyBy('name');
        $out = [];
        foreach ($r->json('models', []) as $m) {
            $name = $m['name'] ?? $m['model'] ?? '?';
            $bytes = $m['size'] ?? null;
            $details = $m['details'] ?? [];
            $isLoaded = $running->has($name);
            $out[] = [
                'name' => $name,
                'parameters' => $details['parameter_size'] ?? null,
                'size' => $bytes ? round($bytes / 1073741824, 2) . ' GB' : null,
                'quantization' => $details['quantization_level'] ?? null,
                'context_length' => $details['context_length'] ?? ($running->get($name)['context_length'] ?? null),
                'family' => $details['family'] ?? null,
                'loaded' => $isLoaded,
                'status' => $isLoaded ? 'LOADED' : 'READY',
            ];
        }
        return $out;
    }

    public function getModelInfo(string $model): array
    {
        $r = $this->call(fn () => $this->http(10)->post($this->baseUrl . '/api/show', ['model' => $model])->throw());
        return $r->json() ?? [];
    }

    public function validateConnection(): array
    {
        $h = $this->checkHealth();
        if ($h['status'] !== 'ONLINE') {
            return [
                'ok' => false,
                'status' => $h['status'],
                'latency_ms' => $h['latency_ms'],
                'message' => 'AI Server tidak online: ' . ($h['error'] ?? $h['status']),
                'details' => $h,
            ];
        }
        try {
            $models = $this->listModels();
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 'ERROR',
                'latency_ms' => $h['latency_ms'],
                'message' => 'Daftar model gagal diambil: ' . $e->getMessage(),
                'details' => $h,
            ];
        }
        $names = array_column($models, 'name');
        $hasModel = $this->model && in_array($this->model, $names, true);
        return [
            'ok' => $hasModel,
            'status' => $h['status'],
            'latency_ms' => $h['latency_ms'],
            'message' => $hasModel
                ? "Terhubung ke Ollama v{$h['runtime_version']} ({$h['latency_ms']} ms). Model aktif '{$this->model}' siap digunakan."
                : 'Server online, tetapi model aktif belum dipilih atau tidak terpasang: ' . ($this->model ?: '(belum dipilih)'),
            'details' => $h + ['models' => $names],
        ];
    }

    protected function requireModel(): string
    {
        if (!$this->model) {
            throw new AiUnavailableException('Model AI aktif belum dipilih. Buka menu Server AI Lokal untuk memilih model.', 'OFFLINE');
        }
        return $this->model;
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $this->requireModel();
        $r = $this->call(fn () => $this->http()->post($this->baseUrl . '/api/chat', [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'options' => ['temperature' => $options['temperature'] ?? 0.2],
        ])->throw());

        $raw = (string) $r->json('message.content', '');
        $thinking = $r->json('message.thinking') ?: JsonExtractor::extractThinking($raw);

        return [
            'content' => JsonExtractor::stripThinking($raw),
            'thinking' => $thinking,
            'model' => $model,
            'is_simulation' => false,
        ];
    }

    public function analyze(array $request): string
    {
        $model = $this->requireModel();
        $r = $this->call(fn () => $this->http()->post($this->baseUrl . '/api/chat', [
            'model' => $model,
            'messages' => $request['messages'],
            'stream' => false,
            'format' => 'json',
            'options' => ['temperature' => 0],
        ])->throw());
        return (string) $r->json('message.content', '');
    }

    public function embed(array $texts): array
    {
        if (!$this->embeddingModel) {
            throw new AiUnavailableException('AI_EMBEDDING_MODEL belum dikonfigurasi (NOT CONFIGURED).', 'OFFLINE');
        }
        $r = $this->call(fn () => $this->http(60)->post($this->baseUrl . '/api/embed', [
            'model' => $this->embeddingModel,
            'input' => array_values($texts),
        ])->throw());
        $vectors = $r->json('embeddings');
        if (!is_array($vectors) || count($vectors) !== count($texts)) {
            throw new AiUnavailableException('Respons embedding tidak sesuai.', 'ERROR');
        }
        return $vectors;
    }
}
