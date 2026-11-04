<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Exceptions\AiUnavailableException;

/**
 * OpenAICompatibleDriver: vLLM, llama.cpp server, atau layanan berprotokol serupa.
 * Tidak mengasumsikan semua fitur OpenAI API didukung identik: response_format
 * tidak dipaksakan; JSON diminta lewat instruksi dan divalidasi backend.
 */
class OpenAICompatibleDriver extends OllamaDriver
{
    public function name(): string { return 'openai_compatible'; }

    public function checkHealth(): array
    {
        $t0 = microtime(true);
        try {
            $r = $this->http(5)->get($this->baseUrl . '/v1/models');
            $r->throw();
            return ['status' => 'ONLINE', 'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
                'runtime' => 'OpenAI-compatible', 'runtime_version' => null, 'error' => null];
        } catch (AiUnavailableException $e) {
            return ['status' => $e->state, 'latency_ms' => null, 'runtime' => 'OpenAI-compatible', 'runtime_version' => null, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['status' => 'OFFLINE', 'latency_ms' => null, 'runtime' => 'OpenAI-compatible', 'runtime_version' => null, 'error' => $e->getMessage()];
        }
    }

    public function listModels(): array
    {
        $r = $this->call(fn () => $this->http(10)->get($this->baseUrl . '/v1/models')->throw());
        return array_map(fn ($m) => [
            'name' => $m['id'] ?? '?', 'size' => null, 'quantization' => null,
            'context_length' => $m['max_model_len'] ?? null, 'status' => 'READY',
        ], $r->json('data', []));
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

    private function completion(array $messages, float $temperature): string
    {
        $model = $this->modelName();
        if (!$model) {
            throw new AiUnavailableException('AI_MODEL belum dikonfigurasi (NOT CONFIGURED).', 'OFFLINE');
        }
        $r = $this->call(fn () => $this->http()->post($this->baseUrl . '/v1/chat/completions', [
            'model' => $model, 'messages' => $messages, 'temperature' => $temperature, 'stream' => false,
        ])->throw());
        return (string) $r->json('choices.0.message.content', '');
    }

    public function chat(array $messages, array $options = []): array
    {
        return ['content' => $this->completion($messages, $options['temperature'] ?? 0.2), 'model' => (string) $this->modelName(), 'is_simulation' => false];
    }

    public function analyze(array $request): string
    {
        return $this->completion($request['messages'], 0.0);
    }

    public function embed(array $texts): array
    {
        if (!$this->embeddingModel) {
            throw new AiUnavailableException('AI_EMBEDDING_MODEL belum dikonfigurasi (NOT CONFIGURED).', 'OFFLINE');
        }
        $r = $this->call(fn () => $this->http(60)->post($this->baseUrl . '/v1/embeddings', [
            'model' => $this->embeddingModel, 'input' => array_values($texts),
        ])->throw());
        $data = $r->json('data');
        if (!is_array($data) || count($data) !== count($texts)) {
            throw new AiUnavailableException('Respons embedding tidak sesuai.', 'ERROR');
        }
        return array_map(fn ($d) => $d['embedding'], $data);
    }
}
