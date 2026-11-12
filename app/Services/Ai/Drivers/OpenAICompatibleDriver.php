<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\Support\JsonExtractor;

/**
 * OpenAICompatibleDriver: mendukung LM Studio (termasuk /api/v0/models & /v1/chat/completions),
 * vLLM, llama.cpp server, OpenWebUI, serta node AI Workstation LAN/Intranet (mis. PUSSIBERAD Qwen3-27B).
 * Toleran terhadap blok <think>...</think> dari model reasoning Qwen3/Claude-Opus-Distill.
 */
class OpenAICompatibleDriver extends OllamaDriver
{
    public function name(): string { return 'openai_compatible'; }

    public function checkHealth(): array
    {
        $t0 = microtime(true);
        try {
            // Probe LM Studio native API terlebih dahulu untuk mendapatkan metadata runtime & loaded models
            $lm = null;
            try {
                $probe = $this->http(2)->get($this->baseUrl . '/api/v0/models');
                if ($probe->successful() && is_array($probe->json('data'))) {
                    $lm = $probe;
                }
            } catch (\Throwable) {
                // Bukan LM Studio native v0; lanjut ke /v1/models standar
            }

            if ($lm !== null) {
                $latency = (int) round((microtime(true) - $t0) * 1000);
                $data = $lm->json('data', []);
                $loaded = [];
                foreach ($data as $m) {
                    if (($m['state'] ?? '') === 'loaded') {
                        $loaded[] = [
                            'name' => $m['id'] ?? '?',
                            'context_length' => $m['loaded_context_length'] ?? $m['max_context_length'] ?? null,
                            'quantization' => $m['quantization'] ?? null,
                        ];
                    }
                }
                return [
                    'status' => 'ONLINE',
                    'latency_ms' => $latency,
                    'runtime' => 'LM Studio (OpenAI API)',
                    'runtime_version' => 'v0/v1',
                    'loaded_models' => $loaded,
                    'model_count' => count($data),
                    'error' => null,
                ];
            }

            $r = $this->http(4)->get($this->baseUrl . '/v1/models');
            $r->throw();
            $latency = (int) round((microtime(true) - $t0) * 1000);
            $data = $r->json('data', []);
            return [
                'status' => 'ONLINE',
                'latency_ms' => $latency,
                'runtime' => 'OpenAI-compatible',
                'runtime_version' => 'v1',
                'loaded_models' => [],
                'model_count' => is_array($data) ? count($data) : 0,
                'error' => null,
            ];
        } catch (AiUnavailableException $e) {
            return ['status' => $e->state, 'latency_ms' => null, 'runtime' => 'OpenAI-compatible', 'runtime_version' => null, 'loaded_models' => [], 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['status' => 'OFFLINE', 'latency_ms' => null, 'runtime' => 'OpenAI-compatible', 'runtime_version' => null, 'loaded_models' => [], 'error' => $e->getMessage()];
        }
    }

    public function listModels(): array
    {
        // Coba /api/v0/models (LM Studio) terlebih dahulu untuk metadata lengkap
        try {
            $lm = $this->http(3)->get($this->baseUrl . '/api/v0/models');
            if ($lm->successful() && is_array($lm->json('data'))) {
                return array_map(function ($m) {
                    $id = (string) ($m['id'] ?? '?');
                    $state = strtolower((string) ($m['state'] ?? 'not-loaded'));
                    return [
                        'name' => $id,
                        'parameters' => $this->inferParamsFromId($id, null),
                        'architecture' => $m['arch'] ?? null,
                        'size' => null,
                        'quantization' => $m['quantization'] ?? null,
                        'context_length' => $m['loaded_context_length'] ?? $m['max_context_length'] ?? null,
                        'type' => $m['type'] ?? 'llm',
                        'vision' => str_contains(strtolower($id . ' ' . ($m['arch'] ?? '')), 'vl') || ($m['type'] ?? '') === 'vlm',
                        'loaded' => $state === 'loaded',
                        'status' => $state === 'loaded' ? 'LOADED' : 'READY',
                    ];
                }, $lm->json('data', []));
            }
        } catch (\Throwable) {
            // Fallback ke /v1/models
        }

        $r = $this->call(fn () => $this->http(6)->get($this->baseUrl . '/v1/models')->throw());
        return array_map(function ($m) {
            $id = (string) ($m['id'] ?? '?');
            return [
                'name' => $id,
                'parameters' => $this->inferParamsFromId($id),
                'size' => null,
                'quantization' => $m['quantization'] ?? null,
                'context_length' => $m['max_model_len'] ?? null,
                'type' => 'llm',
                'loaded' => true,
                'status' => 'READY',
            ];
        }, $r->json('data', []));
    }

    private function inferParamsFromId(string $id, ?string $arch = null): ?string
    {
        if (preg_match('/[-_](\d+(?:\.\d+)?b)\b/i', $id, $m)) {
            return strtoupper($m[1]);
        }
        return $arch ?: null;
    }

    public function getModelInfo(string $model): array
    {
        foreach ($this->listModels() as $m) {
            if ($m['name'] === $model) {
                return $m;
            }
        }
        return [];
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
        $matched = null;
        foreach ($models as $m) {
            if (($m['name'] ?? '') === $this->model) {
                $matched = $m;
                break;
            }
        }
        $hasModel = $this->model && $matched !== null;
        $isLoaded = !empty($matched['loaded']);
        return [
            'ok' => $hasModel,
            'status' => $h['status'],
            'latency_ms' => $h['latency_ms'],
            'message' => $hasModel
                ? "Terhubung ke {$h['runtime']} ({$h['latency_ms']} ms). Model aktif '{$this->model}' " . ($isLoaded ? 'termuat di memori dan siap.' : 'terdaftar di server (siap dimuat/JIT).')
                : 'Server online (' . count($names) . ' model terdeteksi), pilih salah satu model aktif: ' . ($this->model ?: '(belum dipilih)'),
            'details' => $h + ['models' => $names, 'active_loaded' => $isLoaded],
        ];
    }

    private function completion(array $messages, float $temperature): array
    {
        $model = $this->modelName();
        if (!$model) {
            throw new AiUnavailableException('Model AI aktif belum dipilih. Konfigurasikan di menu Server AI Lokal.', 'OFFLINE');
        }
        $r = $this->call(fn () => $this->http()->post($this->baseUrl . '/v1/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'stream' => false,
        ])->throw());

        $message = $r->json('choices.0.message', []);
        $raw = (string) ($message['content'] ?? '');
        $reasoning = $message['reasoning_content'] ?? $message['reasoning'] ?? JsonExtractor::extractThinking($raw);

        return [
            'raw' => $raw,
            'thinking' => is_string($reasoning) ? $reasoning : null,
            'model' => $model,
        ];
    }

    public function chat(array $messages, array $options = []): array
    {
        $res = $this->completion($messages, $options['temperature'] ?? 0.2);
        return [
            'content' => JsonExtractor::stripThinking($res['raw']),
            'thinking' => $res['thinking'],
            'model' => $res['model'],
            'is_simulation' => false,
        ];
    }

    public function analyze(array $request): string
    {
        $res = $this->completion($request['messages'], 0.0);
        return $res['raw'];
    }

    public function embed(array $texts): array
    {
        if (!$this->embeddingModel) {
            throw new AiUnavailableException('AI_EMBEDDING_MODEL belum dikonfigurasi (NOT CONFIGURED).', 'OFFLINE');
        }
        $r = $this->call(fn () => $this->http(60)->post($this->baseUrl . '/v1/embeddings', [
            'model' => $this->embeddingModel,
            'input' => array_values($texts),
        ])->throw());
        $data = $r->json('data');
        if (!is_array($data) || count($data) !== count($texts)) {
            throw new AiUnavailableException('Respons embedding tidak sesuai.', 'ERROR');
        }
        return array_map(fn ($d) => $d['embedding'], $data);
    }
}
