<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Models\AiServer;
use App\Models\User;
use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Drivers\ExternalDriver;
use App\Services\Ai\Drivers\MockDriver;
use App\Services\Ai\Drivers\OllamaDriver;
use App\Services\Ai\Drivers\OpenAICompatibleDriver;
use App\Services\Ai\Exceptions\AiInvalidOutputException;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\Support\JsonExtractor;
use App\Services\Ai\Support\LocalGuard;
use App\Services\Analysis\ClaimAnalyzer;
use App\Services\Support\AppSettings;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * AiManager: memilih driver secara model-agnostic (runtime DB atau .env),
 * memvalidasi keluaran terstruktur JSON, menyinkronkan model nyata dari server AI lokal,
 * dan menegakkan degradasi anggun tanpa mengarang hasil palsu.
 */
class AiManager
{
    private ?AiDriver $driver = null;

    public function __construct(
        private PromptBuilder $prompts,
        private AnalysisSchemaValidator $validator,
        private ClaimAnalyzer $claims,
        private AppSettings $settings,
    ) {
    }

    public function setDriver(?AiDriver $driver): void
    {
        $this->driver = $driver;
    }

    public function resetDriver(): void
    {
        $this->driver = null;
    }

    /** Konfigurasi AI aktif: pengaturan runtime DB mengalahkan nilai default .env. */
    public function resolvedConfig(): array
    {
        $cfg = config('trace.ai');

        try {
            if ($this->settings->has('ai.driver')) {
                $cfg['driver'] = (string) $this->settings->get('ai.driver');
            }
            if ($this->settings->has('ai.base_url')) {
                $cfg['base_url'] = (string) $this->settings->get('ai.base_url');
            }
            if ($this->settings->has('ai.model')) {
                $cfg['model'] = (string) $this->settings->get('ai.model');
            }
            if ($this->settings->has('ai.embedding_model')) {
                $cfg['embedding_model'] = (string) $this->settings->get('ai.embedding_model');
            }
            if ($this->settings->has('ai.timeout')) {
                $cfg['timeout'] = max(10, (int) $this->settings->get('ai.timeout'));
            }
            if ($this->settings->has('ai.allow_external')) {
                $cfg['allow_external'] = filter_var($this->settings->get('ai.allow_external'), FILTER_VALIDATE_BOOL);
            }
            if ($this->settings->has('ai.api_key_enc')) {
                $cfg['api_key'] = Crypt::decryptString((string) $this->settings->get('ai.api_key_enc'));
            }
        } catch (\Throwable) {
            // Fallback aman ke config(.env) saat bootstrap migrasi awal
        }

        return $cfg;
    }

    public function driverName(): string
    {
        return $this->driver?->name() ?? ($this->resolvedConfig()['driver'] ?? 'mock');
    }

    /** Model aktif: pilihan administrator (DB) mengalahkan nilai .env. */
    public function activeModelName(): ?string
    {
        $cfg = $this->resolvedConfig();
        if (($cfg['driver'] ?? 'mock') === 'mock') {
            return null;
        }
        try {
            $active = AiModel::where('is_active', true)
                ->whereHas('server', fn ($q) => $q->where('driver', $cfg['driver'])->where('is_active', true))
                ->first();
            if ($active) {
                return $active->name;
            }
            $activeAny = AiModel::where('is_active', true)
                ->whereHas('server', fn ($q) => $q->where('driver', $cfg['driver']))
                ->first();
            if ($activeAny) {
                return $activeAny->name;
            }
        } catch (\Throwable) {
            // tabel belum ada pada saat bootstrap awal
        }
        return !empty($cfg['model']) ? $cfg['model'] : null;
    }

    public function driver(): AiDriver
    {
        if ($this->driver) {
            return $this->driver;
        }
        $cfg = $this->resolvedConfig();
        $model = $this->activeModelName();
        $driver = (string) ($cfg['driver'] ?? 'mock');

        return $this->driver = match ($driver) {
            'mock' => new MockDriver($this->claims),
            'ollama' => new OllamaDriver($cfg['base_url'], $model, (int) ($cfg['timeout'] ?? 180), $cfg['embedding_model'], $cfg['api_key'] ?? null),
            'openai_compatible' => new OpenAICompatibleDriver($cfg['base_url'], $model, (int) ($cfg['timeout'] ?? 180), $cfg['embedding_model'], $cfg['api_key'] ?? null),
            'external' => new ExternalDriver($cfg['base_url'], $model, (int) ($cfg['timeout'] ?? 180), $cfg['embedding_model'], $cfg['api_key'] ?: ($cfg['external_api_key'] ?? null)),
            default => throw new AiUnavailableException("AI_DRIVER '{$driver}' tidak dikenal.", 'ERROR'),
        };
    }

    public function isSimulation(): bool
    {
        return $this->driver()->isSimulation();
    }

    /** Status ringkas untuk top bar dan panel admin (tidak melempar eksepsi). */
    public function status(): array
    {
        $cfg = $this->resolvedConfig();
        try {
            $d = $this->driver();
            $h = $d->checkHealth();
            return [
                'driver' => $d->name(),
                'status' => $h['status'],
                'model' => $d->modelName(),
                'active_model' => $d->modelName(),
                'base_url' => $d->name() === 'mock' ? null : ($cfg['base_url'] ?? null),
                'timeout' => (int) ($cfg['timeout'] ?? 180),
                'runtime' => $h['runtime'] ?? null,
                'runtime_version' => $h['runtime_version'] ?? null,
                'loaded_models' => $h['loaded_models'] ?? [],
                'is_simulation' => $d->isSimulation(),
                'latency_ms' => $h['latency_ms'],
                'error' => $h['error'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'driver' => $cfg['driver'] ?? 'mock',
                'status' => 'ERROR',
                'model' => $cfg['model'] ?? null,
                'active_model' => $cfg['model'] ?? null,
                'base_url' => $cfg['base_url'] ?? null,
                'timeout' => (int) ($cfg['timeout'] ?? 180),
                'runtime' => null,
                'runtime_version' => null,
                'loaded_models' => [],
                'is_simulation' => false,
                'latency_ms' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Simpan konfigurasi runtime AI (menggantikan keharusan mengedit .env secara manual),
     * tegakkan LocalGuard untuk endpoint intranet/localhost, dan sinkronkan model nyata.
     */
    public function configureRuntime(array $params, ?User $by, AuditLogger $audit): array
    {
        $driver = (string) ($params['driver'] ?? 'ollama');
        $baseUrl = isset($params['base_url']) && trim((string) $params['base_url']) !== ''
            ? rtrim(trim((string) $params['base_url']), '/')
            : null;
        $model = isset($params['model']) && trim((string) $params['model']) !== ''
            ? trim((string) $params['model'])
            : null;
        $embeddingModel = isset($params['embedding_model']) && trim((string) $params['embedding_model']) !== ''
            ? trim((string) $params['embedding_model'])
            : null;
        $timeout = max(10, min(900, (int) ($params['timeout'] ?? 180)));

        if (in_array($driver, ['ollama', 'openai_compatible'], true)) {
            if (!$baseUrl) {
                throw new AiUnavailableException('URL Base Server AI wajib diisi untuk mode lokal/intranet.', 'OFFLINE');
            }
            LocalGuard::assertLocal($baseUrl);
        }

        if ($driver === 'external' && !filter_var($this->resolvedConfig()['allow_external'] ?? false, FILTER_VALIDATE_BOOL)) {
            if (empty($params['allow_external'])) {
                throw new AiUnavailableException('Driver External nonaktif (kebijakan AI_ALLOW_EXTERNAL).', 'ERROR');
            }
        }

        $prevDriver = $this->resolvedConfig()['driver'] ?? 'mock';
        $prevUrl = $this->resolvedConfig()['base_url'] ?? null;
        $prevModel = $this->activeModelName();

        $this->settings->setInternal('ai.driver', $driver, $by);
        $this->settings->setInternal('ai.base_url', $driver === 'mock' ? '' : ($baseUrl ?? ''), $by);
        if ($model !== null) {
            $this->settings->setInternal('ai.model', $model, $by);
        } elseif ($driver === 'mock') {
            $this->settings->setInternal('ai.model', '', $by);
        }
        $this->settings->setInternal('ai.embedding_model', $embeddingModel ?? '', $by);
        $this->settings->setInternal('ai.timeout', (string) $timeout, $by);

        if (!empty($params['clear_api_key'])) {
            $this->settings->setInternal('ai.api_key_enc', null, $by);
        } elseif (!empty($params['api_key'])) {
            $this->settings->setInternal('ai.api_key_enc', Crypt::encryptString((string) $params['api_key']), $by);
        }

        $this->resetDriver();
        $server = $this->syncServerRecord();
        $sync = $this->syncModelsFromServer($server, $model);

        $audit->log('AI_SERVER_CONFIGURED', $server, [
            'from' => ['driver' => $prevDriver, 'base_url' => $prevUrl, 'model' => $prevModel],
            'to' => ['driver' => $driver, 'base_url' => $baseUrl, 'model' => $this->activeModelName(), 'timeout' => $timeout],
            'sync_ok' => $sync['ok'],
            'models_synced' => $sync['count'],
        ], $by?->id);

        return ['server' => $server, 'sync' => $sync];
    }

    /**
     * Analisis terstruktur dengan validasi skema, normalisasi enum untuk LLM open-weights, dan retry terbatas.
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
        $max = max(1, 1 + (int) ($this->resolvedConfig()['max_retries'] ?? 2));
        $lastErrors = [];
        $lastRaw = null;

        for ($attempt = 1; $attempt <= $max; $attempt++) {
            $raw = $driver->analyze(['module' => $module, 'messages' => $messages, 'payload' => $payload]);
            $raw = mb_substr($raw, 0, config('trace.ai.max_output_chars'));
            $lastRaw = $raw;
            $data = JsonExtractor::extract($raw);

            if ($data === null) {
                $lastErrors = ['Keluaran bukan JSON yang valid.'];
                $messages[] = ['role' => 'assistant', 'content' => mb_substr(JsonExtractor::stripThinking($raw), 0, 500)];
                $messages[] = ['role' => 'user', 'content' => 'Keluaran sebelumnya bukan JSON valid. Kirim ulang HANYA objek JSON sesuai skema tanpa tag <think> atau teks lain.'];
                continue;
            }

            $data = $this->normalizeAnalysisData($data, $module, $driver);

            $errors = $this->validator->validateAnalysis($data, $validEvidenceIds);
            if (!$errors) {
                $data['analysis_id'] = (string) Str::uuid();
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
            $messages[] = ['role' => 'assistant', 'content' => mb_substr(JsonExtractor::stripThinking($raw), 0, 500)];
            $messages[] = ['role' => 'user', 'content' => 'JSON tidak sesuai skema: ' . implode('; ', array_slice($errors, 0, 8)) . '. Perbaiki dan kirim ulang HANYA JSON valid.'];
        }

        throw new AiInvalidOutputException(
            'Keluaran AI tidak sesuai skema setelah ' . $max . ' percobaan. Hasil tidak disimpan; minta pemeriksaan ulang.',
            $lastErrors,
            $lastRaw
        );
    }

    /** Normalisasi halus variasi kapitalisasi/separator LLM open-weights tanpa melonggarkan kontrak enum/bukti. */
    private function normalizeAnalysisData(array $data, string $module, AiDriver $driver): array
    {
        $data['module'] = $module;
        $data['is_simulation'] = $driver->isSimulation();
        $data['model_name'] = !empty($data['model_name']) ? (string) $data['model_name'] : $driver->modelName();
        $data['model_version'] = $data['model_version'] ?? null;

        $normUpperSpaced = fn (?string $val) => $val === null ? '' : strtoupper(trim(str_replace('_', ' ', $val)));
        $normUpperUnderscore = fn (?string $val) => $val === null ? '' : strtoupper(trim(str_replace(' ', '_', $val)));

        if (isset($data['verification_status']) && is_string($data['verification_status'])) {
            $data['verification_status'] = $normUpperSpaced($data['verification_status']);
        }
        if (isset($data['content_indicator']) && is_string($data['content_indicator'])) {
            $data['content_indicator'] = $normUpperSpaced($data['content_indicator']);
        }
        if (isset($data['classification']) && is_string($data['classification'])) {
            $data['classification'] = $normUpperUnderscore($data['classification']);
        }
        if (isset($data['threat_level']) && is_string($data['threat_level'])) {
            $data['threat_level'] = strtoupper(trim($data['threat_level']));
        }
        if (isset($data['credibility_score']) && is_numeric($data['credibility_score'])) {
            $data['credibility_score'] = max(0, min(100, (int) round((float) $data['credibility_score'])));
        }
        if (isset($data['confidence']) && is_numeric($data['confidence'])) {
            $conf = (float) $data['confidence'];
            if ($conf > 1.0 && $conf <= 100.0) {
                $conf = round($conf / 100.0, 3);
            }
            $data['confidence'] = max(0.0, min(1.0, $conf));
        }

        if (isset($data['claims']) && is_array($data['claims'])) {
            foreach ($data['claims'] as $i => $c) {
                if (!is_array($c)) {
                    continue;
                }
                if (isset($c['type']) && is_string($c['type'])) {
                    $data['claims'][$i]['type'] = strtoupper(trim($c['type']));
                }
                if (isset($c['check_status']) && is_string($c['check_status'])) {
                    $data['claims'][$i]['check_status'] = $normUpperSpaced($c['check_status']);
                }
            }
        }

        if (isset($data['findings']) && is_array($data['findings'])) {
            foreach ($data['findings'] as $i => $f) {
                if (!is_array($f)) {
                    continue;
                }
                if (isset($f['type']) && is_string($f['type'])) {
                    $data['findings'][$i]['type'] = $normUpperUnderscore($f['type']);
                }
                if (!array_key_exists('evidence_ids', $f) || !is_array($f['evidence_ids'])) {
                    $data['findings'][$i]['evidence_ids'] = [];
                }
            }
        }

        $data['evidence'] = is_array($data['evidence'] ?? null) ? $data['evidence'] : [];
        $data['sources'] = is_array($data['sources'] ?? null) ? $data['sources'] : [];
        foreach ($data['sources'] as $i => $s) {
            if (is_array($s) && isset($s['access_status']) && is_string($s['access_status'])) {
                $data['sources'][$i]['access_status'] = $normUpperSpaced($s['access_status']);
            }
        }
        $data['recommendations'] = is_array($data['recommendations'] ?? null) ? array_values(array_map('strval', $data['recommendations'])) : [];
        $data['limitations'] = is_array($data['limitations'] ?? null) ? array_values(array_map('strval', $data['limitations'])) : [];

        return $data;
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

    /** Sinkronkan baris ai_servers dari konfigurasi aktif untuk halaman admin. */
    public function syncServerRecord(): AiServer
    {
        $cfg = $this->resolvedConfig();
        $driver = (string) ($cfg['driver'] ?? 'mock');
        $baseUrl = $driver === 'mock' ? null : ($cfg['base_url'] ?: null);
        $health = $this->status();

        $defaultName = match ($driver) {
            'ollama' => 'Local Ollama Node (' . ($baseUrl ?: '127.0.0.1:11434') . ')',
            'openai_compatible' => ($health['runtime'] ?? 'LM Studio / OpenAI-Compatible') . ' (' . ($baseUrl ?: '127.0.0.1:1234') . ')',
            'external' => 'External Gateway (Controlled)',
            default => 'Internal AI Inference Engine (MockDriver)',
        };

        $server = AiServer::firstOrCreate(
            ['driver' => $driver],
            ['name' => $defaultName, 'base_url' => $baseUrl, 'is_active' => true]
        );

        $server->update([
            'name' => $driver === 'mock' ? 'Internal AI Inference Engine (MockDriver)' : $defaultName,
            'base_url' => $baseUrl,
            'status' => $health['status'] ?? 'OFFLINE',
            'runtime' => $health['runtime'] ?? $server->runtime,
            'runtime_version' => $health['runtime_version'] ?? $server->runtime_version,
            'latency_ms' => $health['latency_ms'],
            'last_error' => $health['error'],
            'is_active' => true,
            'last_checked_at' => now(),
        ]);

        AiServer::where('id', '!=', $server->id)->update(['is_active' => false]);

        return $server->fresh();
    }

    /**
     * Ambil daftar model nyata langsung dari server AI lokal/intranet dan sinkronkan ke tabel ai_models.
     *
     * @return array{ok:bool,count:int,models:array,error:?string}
     */
    public function syncModelsFromServer(?AiServer $server = null, ?string $preferredModel = null): array
    {
        $server = $server ?? $this->syncServerRecord();
        $driver = $this->driver();

        if ($driver->isSimulation()) {
            return ['ok' => true, 'count' => $server->models()->count(), 'models' => $server->models()->get()->all(), 'error' => null];
        }

        try {
            $remote = $driver->listModels();
        } catch (\Throwable $e) {
            return ['ok' => false, 'count' => 0, 'models' => [], 'error' => $e->getMessage()];
        }

        $targetName = $preferredModel ?: $this->activeModelName();
        $remoteNames = array_column($remote, 'name');

        // Hapus entri placeholder yang tidak benar-benar ada di server (hanya untuk server live)
        if ($remoteNames !== []) {
            AiModel::where('server_id', $server->id)
                ->whereNotIn('name', $remoteNames)
                ->delete();
        }

        $synced = [];
        $firstLlmName = null;
        foreach ($remote as $rm) {
            $name = (string) ($rm['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $isEmbedding = ($rm['type'] ?? '') === 'embedding' || str_contains(strtolower($name), 'embed');
            if (!$isEmbedding && $firstLlmName === null) {
                $firstLlmName = $name;
            }

            $profile = $this->inferProfile($name, $rm['parameters'] ?? null);
            $modelRow = AiModel::updateOrCreate(
                ['server_id' => $server->id, 'name' => $name],
                [
                    'profile' => $profile,
                    'size' => $rm['size'] ?? ($rm['parameters'] ?? null),
                    'quantization' => $rm['quantization'] ?? null,
                    'context_length' => isset($rm['context_length']) ? (int) $rm['context_length'] : null,
                    'status' => !empty($rm['loaded']) ? 'READY' : ($rm['status'] ?? 'READY'),
                    'is_simulation' => false,
                ]
            );
            $synced[] = $modelRow;
        }

        // Aktifkan model pilihan atau model LLM pertama yang terdeteksi jika model saat ini tidak valid
        $chosenName = ($targetName && in_array($targetName, $remoteNames, true))
            ? $targetName
            : ($firstLlmName ?? ($remoteNames[0] ?? null));

        if ($chosenName !== null) {
            AiModel::where('server_id', $server->id)->update(['is_active' => false]);
            AiModel::where('server_id', $server->id)->where('name', $chosenName)->update(['is_active' => true]);
            $this->settings->setInternal('ai.model', $chosenName);
            $this->resetDriver();
        }

        return ['ok' => true, 'count' => count($synced), 'models' => $synced, 'error' => null];
    }

    private function inferProfile(string $name, ?string $params = null): string
    {
        $hay = strtolower($name . ' ' . ($params ?? ''));
        if (str_contains($hay, '-vl') || str_contains($hay, 'vision')) {
            return 'MULTIMODAL';
        }
        if (preg_match('/\b(27b|32b|34b|70b|72b)\b/', $hay)) {
            return 'ADVANCED';
        }
        if (preg_match('/\b(14b|13b|12b)\b/', $hay)) {
            return 'STANDARD';
        }
        if (preg_match('/\b(0\.5b|1\.5b|3b|4b)\b/', $hay)) {
            return 'ENTRY';
        }
        return 'BASIC';
    }
}
