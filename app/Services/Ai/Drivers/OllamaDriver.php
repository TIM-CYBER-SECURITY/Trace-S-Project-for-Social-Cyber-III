<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\Support\LocalGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * OllamaDriver: prioritas untuk prototipe dan deployment lokal sederhana.
 * Endpoint: /api/tags, /api/version, /api/chat, /api/show, /api/embed.
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
            $r = $this->http(5)->get($this->baseUrl . '/api/version');
            $r->throw();
            return [
                'status' => 'ONLINE', 'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
                'runtime' => 'Ollama', 'runtime_version' => $r->json('version'), 'error' => null,
            ];
        } catch (AiUnavailableException $e) {
            return ['status' => $e->state, 'latency_ms' => null, 'runtime' => 'Ollama', 'runtime_version' => null, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['status' => 'OFFLINE', 'latency_ms' => null, 'runtime' => 'Ollama', 'runtime_version' => null, 'error' => $e->getMessage()];
        }
    }

    public function listModels(): array
    {
        $r = $this->call(fn () => $this->http(10)->get($this->baseUrl . '/api/tags')->throw());
        $out = [];
        foreach ($r->json('models', []) as $m) {
            $bytes = $m['size'] ?? null;
            $out[] = [
                'name' => $m['name'] ?? $m['model'] ?? '?',
                'size' => $bytes ? round($bytes / 1073741824, 2) . ' GB' : null,
                'quantization' => $m['details']['quantization_level'] ?? null,
                'context_length' => null,
                'status' => 'READY',
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
            return ['ok' => false, 'message' => 'AI Server tidak online: ' . ($h['error'] ?? $h['status']), 'details' => $h];
        }
        try {
            $models = $this->listModels();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Daftar model gagal diambil: ' . $e->getMessage(), 'details' => $h];
        }
        $names = array_column($models, 'name');
        $hasModel = $this->model && in_array($this->model, $names, true);
        return [
            'ok' => $hasModel,
            'message' => $hasModel ? 'Koneksi dan model aktif terverifikasi.' : 'Server online, tetapi model aktif tidak terpasang: ' . ($this->model ?: '(belum dipilih)'),
            'details' => $h + ['models' => $names],
        ];
    }

    private function requireModel(): string
    {
        if (!$this->model) {
            throw new AiUnavailableException('AI_MODEL belum dikonfigurasi (NOT CONFIGURED).', 'OFFLINE');
        }
        return $this->model;
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $this->requireModel();
        $r = $this->call(fn () => $this->http()->post($this->baseUrl . '/api/chat', [
            'model' => $model, 'messages' => $messages, 'stream' => false,
            'options' => ['temperature' => $options['temperature'] ?? 0.2],
        ])->throw());
        return ['content' => (string) $r->json('message.content', ''), 'model' => $model, 'is_simulation' => false];
    }

    public function analyze(array $request): string
    {
        $model = $this->requireModel();
        $r = $this->call(fn () => $this->http()->post($this->baseUrl . '/api/chat', [
            'model' => $model, 'messages' => $request['messages'], 'stream' => false, 'format' => 'json',
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
            'model' => $this->embeddingModel, 'input' => array_values($texts),
        ])->throw());
        $vectors = $r->json('embeddings');
        if (!is_array($vectors) || count($vectors) !== count($texts)) {
            throw new AiUnavailableException('Respons embedding tidak sesuai.', 'ERROR');
        }
        return $vectors;
    }
}
