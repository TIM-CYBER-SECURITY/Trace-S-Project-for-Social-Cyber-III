<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Models\AiServer;
use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Drivers\ExternalDriver;
use App\Services\Ai\Drivers\MockDriver;
use App\Services\Ai\Drivers\OllamaDriver;
use App\Services\Ai\Drivers\OpenAICompatibleDriver;
use App\Services\Ai\Exceptions\AiInvalidOutputException;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\Support\JsonExtractor;
use App\Services\Analysis\ClaimAnalyzer;

/**
 * AiManager: memilih driver lewat konfigurasi (model-agnostic), memvalidasi
 * keluaran terstruktur, dan menegakkan degradasi anggun: bila AI Server tidak
 * tersedia, TIDAK ada hasil palsu yang dikembalikan.
 */
class AiManager
{
    private ?AiDriver $driver = null;

    public function __construct(
        private PromptBuilder $prompts,
        private AnalysisSchemaValidator $validator,
        private ClaimAnalyzer $claims,
    ) {
    }

    public function setDriver(?AiDriver $driver): void
    {
        $this->driver = $driver;
    }

    public function driverName(): string
    {
        return $this->driver?->name() ?? config('trace.ai.driver', 'mock');
    }

    /** Model aktif: pilihan administrator (DB) mengalahkan nilai .env. */
    public function activeModelName(): ?string
    {
        if (config('trace.ai.driver') === 'mock') {
            return null;
        }
        try {
            $active = AiModel::where('is_active', true)
                ->whereHas('server', fn ($q) => $q->where('driver', config('trace.ai.driver')))
                ->first();
            if ($active) {
                return $active->name;
            }
        } catch (\Throwable) {
            // tabel belum ada pada saat bootstrap awal
        }
        return config('trace.ai.model');
    }

    public function driver(): AiDriver
    {
        if ($this->driver) {
            return $this->driver;
        }
        $cfg = config('trace.ai');
        $model = $this->activeModelName();

        return $this->driver = match ($cfg['driver']) {
            'mock' => new MockDriver($this->claims),
            'ollama' => new OllamaDriver($cfg['base_url'], $model, $cfg['timeout'], $cfg['embedding_model'], $cfg['api_key']),
            'openai_compatible' => new OpenAICompatibleDriver($cfg['base_url'], $model, $cfg['timeout'], $cfg['embedding_model'], $cfg['api_key']),
            'external' => new ExternalDriver($cfg['base_url'], $model, $cfg['timeout'], $cfg['embedding_model'], $cfg['external_api_key']),
            default => throw new AiUnavailableException("AI_DRIVER '{$cfg['driver']}' tidak dikenal.", 'ERROR'),
        };
    }

    public function isSimulation(): bool
    {
        return $this->driver()->isSimulation();
    }

    /** Status ringkas untuk top bar (tidak melempar eksepsi). */
    public function status(): array
    {
        try {
            $d = $this->driver();
            $h = $d->checkHealth();
            return [
                'driver' => $d->name(),
                'status' => $h['status'],
                'model' => $d->modelName(),
                'is_simulation' => $d->isSimulation(),
                'latency_ms' => $h['latency_ms'],
                'error' => $h['error'] ?? null,
            ];
        } catch (\Throwable $e) {
            return ['driver' => config('trace.ai.driver'), 'status' => 'ERROR', 'model' => null, 'is_simulation' => false, 'latency_ms' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analisis terstruktur dengan validasi skema dan retry terbatas.
     *
     * @param  array{text:string,evidence:array,case:?array,flags:array}  $payload
     * @param  string[]  $validEvidenceIds
     * @return array{result:array,driver:string,model_name:?string,model_version:?string,is_simulation:bool,attempts:int}
     *
     * @throws AiUnavailableException|AiInvalidOutputException
     */
    public function analyze(string $module, array $payload, array $validEvidenceIds): array
    {
        $driver = $this->driver();
        $messages = $this->prompts->analysis($module, $payload);
        $max = max(1, 1 + config('trace.ai.max_retries'));
        $lastErrors = [];
        $lastRaw = null;

        for ($attempt = 1; $attempt <= $max; $attempt++) {
            $raw = $driver->analyze(['module' => $module, 'messages' => $messages, 'payload' => $payload]);
            $raw = mb_substr($raw, 0, config('trace.ai.max_output_chars'));
            $lastRaw = $raw;
            $data = JsonExtractor::extract($raw);

            if ($data === null) {
                $lastErrors = ['Keluaran bukan JSON yang valid.'];
                $messages[] = ['role' => 'assistant', 'content' => mb_substr($raw, 0, 500)];
                $messages[] = ['role' => 'user', 'content' => 'Keluaran sebelumnya bukan JSON valid. Kirim ulang HANYA objek JSON sesuai skema.'];
                continue;
            }

            // Backend yang menentukan status simulasi dan status validasi, bukan model.
            $data['module'] = $module;
            $data['is_simulation'] = $driver->isSimulation();
            $data['model_name'] = $data['model_name'] ?? $driver->modelName();
            $data['model_version'] = $data['model_version'] ?? null;

            $errors = $this->validator->validateAnalysis($data, $validEvidenceIds);
            if (!$errors) {
                $data['analysis_id'] = (string) \Illuminate\Support\Str::uuid();
                $data['analyzed_at'] = now()->toIso8601String();
                $data['validation_status'] = 'PENDING REVIEW';   // keputusan manusia, bukan AI
                return [
                    'result' => $data,
                    'driver' => $driver->name(),
                    'model_name' => $driver->modelName(),
                    'model_version' => $data['model_version'],
                    'is_simulation' => $driver->isSimulation(),
                    'attempts' => $attempt,
                ];
            }

            $lastErrors = $errors;
            $messages[] = ['role' => 'assistant', 'content' => mb_substr($raw, 0, 500)];
            $messages[] = ['role' => 'user', 'content' => "JSON tidak sesuai skema: " . implode('; ', array_slice($errors, 0, 8)) . ". Perbaiki dan kirim ulang HANYA JSON."];
        }

        throw new AiInvalidOutputException(
            'Keluaran AI tidak sesuai skema setelah ' . $max . ' percobaan. Hasil tidak disimpan; minta pemeriksaan ulang.',
            $lastErrors,
            $lastRaw
        );
    }

    /** @param array<int,array{role:string,content:string}> $messages */
    public function chat(array $messages, array $options = []): array
    {
        return $this->driver()->chat($messages, $options);
    }

    /** @return array<int,array<int,float>> */
    public function embed(array $texts): array
    {
        return $this->driver()->embed($texts);
    }

    /** Sinkronkan baris ai_servers dari konfigurasi (.env) untuk halaman admin. */
    public function syncServerRecord(): AiServer
    {
        $cfg = config('trace.ai');
        $server = AiServer::firstOrCreate(
            ['driver' => $cfg['driver']],
            ['name' => 'AI Server (' . $cfg['driver'] . ')', 'base_url' => $cfg['driver'] === 'mock' ? null : $cfg['base_url'], 'is_active' => true]
        );
        if ($server->base_url !== ($cfg['driver'] === 'mock' ? null : $cfg['base_url'])) {
            $server->update(['base_url' => $cfg['driver'] === 'mock' ? null : $cfg['base_url']]);
        }
        AiServer::where('id', '!=', $server->id)->update(['is_active' => false]);
        return $server;
    }
}
