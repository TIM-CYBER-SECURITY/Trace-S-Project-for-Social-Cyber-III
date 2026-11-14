<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Models\AiModelChange;
use App\Models\AiServer;
use App\Models\User;
use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Drivers\ExaAiDriver;
use App\Services\Ai\Drivers\ExternalDriver;
use App\Services\Ai\Drivers\GoogleAiStudioDriver;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
    private bool $hasExplicitDriver = false;
    private ModelProfileResolver $profiles;
    private HardwareTelemetry $telemetry;

    public function __construct(
        private PromptBuilder $prompts,
        private AnalysisSchemaValidator $validator,
        private ClaimAnalyzer $claims,
        private AppSettings $settings,
        ?ModelProfileResolver $profiles = null,
        ?HardwareTelemetry $telemetry = null,
    ) {
        $this->profiles = $profiles ?? new ModelProfileResolver();
        $this->telemetry = $telemetry ?? new HardwareTelemetry($this->profiles);
    }

    public function setDriver(?AiDriver $driver): void
    {
        $this->driver = $driver;
        $this->hasExplicitDriver = $driver !== null;
        Cache::forget('trace_ai_status_snapshot');
    }

    public function resetDriver(): void
    {
        $this->driver = null;
        $this->hasExplicitDriver = false;
        $this->settings->flushCache();
        Cache::forget('trace_ai_status_snapshot');
        Cache::forget('trace_ai_status_snapshot_global');
        try {
            foreach (\App\Models\Organization::pluck('id') as $oid) {
                Cache::forget("trace_ai_status_snapshot_{$oid}");
            }
        } catch (\Throwable) {
            // Ignore when database/table is not initialized yet.
        }
    }

    /** Konfigurasi AI aktif: perangkat lokal (global) + Cloud AI terisolasi per Instansi (organization_id). */
    public function resolvedConfig(?int $orgId = null): array
    {
        $cfg = config('trace.ai');
        if ($orgId === null) {
            try {
                $orgId = Auth::guard('web')->check() ? Auth::guard('web')->user()?->organization_id : null;
            } catch (\Throwable) {
                $orgId = null;
            }
        }

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
            if ($this->settings->hasForOrg('ai.allow_external', $orgId)) {
                $cfg['allow_external'] = filter_var($this->settings->getForOrg('ai.allow_external', $orgId), FILTER_VALIDATE_BOOL);
            }
            if ($this->settings->has('ai.api_key_enc')) {
                $cfg['api_key'] = Crypt::decryptString((string) $this->settings->get('ai.api_key_enc'));
            }
            if ($this->settings->hasForOrg('ai.engine_mode', $orgId)) {
                $cfg['engine_mode'] = (string) $this->settings->getForOrg('ai.engine_mode', $orgId);
            }
            if ($this->settings->hasForOrg('ai.cloud_provider', $orgId)) {
                $cfg['cloud_provider'] = (string) $this->settings->getForOrg('ai.cloud_provider', $orgId);
            }
            if ($this->settings->hasForOrg('ai.cloud_timeout', $orgId)) {
                $cfg['cloud_timeout'] = max(3, min(60, (int) $this->settings->getForOrg('ai.cloud_timeout', $orgId)));
            }
            if ($this->settings->hasForOrg('ai.google_model', $orgId)) {
                $cfg['google_model'] = (string) $this->settings->getForOrg('ai.google_model', $orgId);
            }
            if ($this->settings->hasForOrg('ai.google_api_key_enc', $orgId)) {
                $cfg['google_api_key'] = Crypt::decryptString((string) $this->settings->getForOrg('ai.google_api_key_enc', $orgId));
            }
            if ($this->settings->hasForOrg('ai.exa_model', $orgId)) {
                $cfg['exa_model'] = (string) $this->settings->getForOrg('ai.exa_model', $orgId);
            }
            if ($this->settings->hasForOrg('ai.exa_api_key_enc', $orgId)) {
                $cfg['exa_api_key'] = Crypt::decryptString((string) $this->settings->getForOrg('ai.exa_api_key_enc', $orgId));
            }
            if ($this->settings->hasForOrg('ai.exa_search_enabled', $orgId)) {
                $cfg['exa_search_enabled'] = filter_var($this->settings->getForOrg('ai.exa_search_enabled', $orgId), FILTER_VALIDATE_BOOL);
            }
        } catch (\Throwable) {
            // Fallback aman ke config(.env) saat bootstrap migrasi awal
        }

        $maskKey = fn (?string $k): ?string => (! $k || strlen($k) < 8)
            ? ($k ? '••••••••' : null)
            : (substr($k, 0, 4) . '••••••••' . substr($k, -3));

        $cfg['organization_id'] = $orgId;
        $cfg['has_google_key'] = ! empty($cfg['google_api_key']);
        $cfg['has_exa_key'] = ! empty($cfg['exa_api_key']);
        $cfg['google_key_masked'] = $maskKey($cfg['google_api_key'] ?? null);
        $cfg['exa_key_masked'] = $maskKey($cfg['exa_api_key'] ?? null);

        return $cfg;
    }

    public function driverName(): string
    {
        return $this->driver?->name() ?? ($this->resolvedConfig()['driver'] ?? 'mock');
    }

    /** Model aktif: pilihan administrator (pengaturan runtime / DB) yang sesuai dengan server aktif. */
    public function activeModelName(): ?string
    {
        $cfg = $this->resolvedConfig();
        if (($cfg['driver'] ?? 'mock') === 'mock') {
            return null;
        }
        try {
            $baseUrl = !empty($cfg['base_url']) ? rtrim((string) $cfg['base_url'], '/') : null;
            $activeServerQuery = fn ($q) => $q->where('driver', $cfg['driver'])
                ->when($baseUrl, fn ($sq) => $sq->where('base_url', $baseUrl))
                ->where('is_active', true);

            $active = AiModel::where('is_active', true)
                ->whereHas('server', $activeServerQuery)
                ->first();
            if ($active) {
                return $active->name;
            }
            if (!empty($cfg['model'])) {
                return $cfg['model'];
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

    /** Profil kemampuan & rekomendasi runtime untuk model aktif saat ini. */
    public function activeModelProfile(): array
    {
        $name = $this->activeModelName();
        if (!$name) {
            return [
                'capability_type' => 'SIMULATION',
                'is_reasoning' => false,
                'is_vision' => false,
                'is_embedding' => false,
                'recommended_timeout' => 30,
                'recommended_temperature' => 0.2,
                'notes' => 'Mode simulasi aktif.',
            ];
        }
        try {
            $row = AiModel::where('name', $name)
                ->whereHas('server', fn ($q) => $q->where('is_active', true))
                ->first();
            if ($row && $row->capability_type) {
                $hw = $this->telemetry->snapshot();
                $fit = $this->profiles->evaluateHardwareFit($row->estimated_ram_gb, $hw);
                return [
                    'display_name' => $row->display_name ?? $row->name,
                    'family' => $row->family,
                    'parameter_size' => $row->parameter_size,
                    'capability_type' => $row->capability_type,
                    'is_reasoning' => (bool) $row->is_reasoning,
                    'is_vision' => (bool) $row->is_vision,
                    'is_embedding' => (bool) $row->is_embedding,
                    'estimated_ram_gb' => $row->estimated_ram_gb,
                    'recommended_timeout' => $row->recommended_timeout ?? 180,
                    'recommended_temperature' => $row->recommended_temperature ?? 0.2,
                    'hardware_fit' => $fit['status'],
                    'hardware_fit_label' => $fit['label'],
                    'notes' => $row->notes,
                ];
            }
        } catch (\Throwable) {
        }
        return $this->profiles->resolve($name, [], $this->telemetry->snapshot());
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
        if ($this->shouldAttemptCloud()) {
            return false;
        }
        return $this->driver()->isSimulation();
    }

    /** Buat instansi Cloud AI Driver (Google AI Studio / Exa.ai) bila terkonfigurasi. */
    public function cloudDriver(?array $cfg = null): ?AiDriver
    {
        $cfg = $cfg ?? $this->resolvedConfig();
        $provider = (string) ($cfg['cloud_provider'] ?? 'google_ai_studio');
        $timeout = max(3, min(60, (int) ($cfg['cloud_timeout'] ?? 10)));

        return match ($provider) {
            'exa_ai' => new ExaAiDriver(
                $cfg['exa_api_key'] ?? null,
                (string) ($cfg['exa_model'] ?? 'exa'),
                $timeout,
                (string) ($cfg['exa_base_url'] ?? 'https://api.exa.ai')
            ),
            default => new GoogleAiStudioDriver(
                $cfg['google_api_key'] ?? null,
                (string) ($cfg['google_model'] ?? 'gemini-2.0-flash'),
                $timeout,
                (string) ($cfg['google_base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta')
            ),
        };
    }

    /** Tentukan apakah analisis perlu mencoba Cloud AI API terlebih dahulu. */
    public function shouldAttemptCloud(?array $cfg = null): bool
    {
        if ($this->hasExplicitDriver) {
            return false;
        }
        $cfg = $cfg ?? $this->resolvedConfig();
        $mode = (string) ($cfg['engine_mode'] ?? 'HYBRID_AUTO');
        if ($mode === 'FORCE_LOCAL') {
            return false;
        }
        $provider = (string) ($cfg['cloud_provider'] ?? 'google_ai_studio');
        $hasKey = $provider === 'exa_ai'
            ? !empty($cfg['exa_api_key'])
            : !empty($cfg['google_api_key']);

        return $mode === 'FORCE_CLOUD' || $hasKey;
    }

    /** Simpan konfigurasi Hybrid Cloud AI (Google AI Studio & Exa.ai) terisolasi per Instansi. */
    public function configureCloudRuntime(array $params, ?User $by, AuditLogger $audit): array
    {
        $orgId = $by?->organization_id ?? (Auth::guard('web')->check() ? Auth::guard('web')->user()?->organization_id : null);
        $engineMode = (string) ($params['engine_mode'] ?? 'HYBRID_AUTO');
        $provider = (string) ($params['cloud_provider'] ?? 'google_ai_studio');
        $cloudTimeout = max(3, min(60, (int) ($params['cloud_timeout'] ?? 10)));
        $googleModel = trim((string) ($params['google_model'] ?? 'gemini-2.0-flash')) ?: 'gemini-2.0-flash';
        $exaModel = trim((string) ($params['exa_model'] ?? 'exa')) ?: 'exa';
        $exaSearch = !empty($params['exa_search_enabled']);

        $this->settings->setInternalForOrg('ai.engine_mode', $engineMode, $orgId, $by);
        $this->settings->setInternalForOrg('ai.cloud_provider', $provider, $orgId, $by);
        $this->settings->setInternalForOrg('ai.cloud_timeout', (string) $cloudTimeout, $orgId, $by);
        $this->settings->setInternalForOrg('ai.google_model', $googleModel, $orgId, $by);
        $this->settings->setInternalForOrg('ai.exa_model', $exaModel, $orgId, $by);
        $this->settings->setInternalForOrg('ai.exa_search_enabled', $exaSearch ? '1' : '0', $orgId, $by);

        if (!empty($params['clear_google_key'])) {
            $this->settings->setInternalForOrg('ai.google_api_key_enc', null, $orgId, $by);
        } elseif (!empty($params['google_api_key'])) {
            $this->settings->setInternalForOrg('ai.google_api_key_enc', Crypt::encryptString(trim((string) $params['google_api_key'])), $orgId, $by);
        }

        if (!empty($params['clear_exa_key'])) {
            $this->settings->setInternalForOrg('ai.exa_api_key_enc', null, $orgId, $by);
        } elseif (!empty($params['exa_api_key'])) {
            $this->settings->setInternalForOrg('ai.exa_api_key_enc', Crypt::encryptString(trim((string) $params['exa_api_key'])), $orgId, $by);
        }

        Cache::forget('trace_ai_status_snapshot');
        Cache::forget('trace_ai_status_snapshot_' . ($orgId ?? 'global'));
        $freshCfg = $this->resolvedConfig($orgId);

        $audit->log('AI_HYBRID_CLOUD_CONFIGURED', null, [
            'organization_id' => $orgId,
            'organization_code' => $by?->organization?->code,
            'engine_mode' => $engineMode,
            'cloud_provider' => $provider,
            'cloud_timeout' => $cloudTimeout,
            'google_model' => $googleModel,
            'exa_model' => $exaModel,
            'exa_search_enabled' => $exaSearch,
            'has_google_key' => $freshCfg['has_google_key'],
            'has_exa_key' => $freshCfg['has_exa_key'],
        ], $by?->id);

        return $freshCfg;
    }

    /** Uji koneksi Cloud AI (Google AI Studio / Exa.ai) dan kesiapan Local Fallback secara bersamaan. */
    public function testCloudConnection(): array
    {
        $cfg = $this->resolvedConfig();
        $cloud = $this->cloudDriver($cfg);
        $cloudRes = $cloud ? $cloud->validateConnection() : ['ok' => false, 'status' => 'NOT_CONFIGURED', 'message' => 'Cloud driver tidak aktif'];

        $localRes = ['status' => 'OFFLINE', 'model' => null];
        try {
            $d = $this->driver();
            $h = $d->checkHealth();
            $localRes = [
                'driver' => $d->name(),
                'status' => $h['status'] ?? 'OFFLINE',
                'model' => $d->modelName(),
            ];
        } catch (\Throwable $e) {
            $localRes['error'] = $e->getMessage();
        }

        return [
            'ok' => !empty($cloudRes['ok']),
            'engine_mode' => $cfg['engine_mode'] ?? 'HYBRID_AUTO',
            'cloud_provider' => $cfg['cloud_provider'] ?? 'google_ai_studio',
            'cloud' => $cloudRes,
            'local_fallback' => $localRes,
            'message' => ($cloudRes['message'] ?? '') . " | Standby Fallback Lokal: {$localRes['status']}" . ($localRes['model'] ? " ({$localRes['model']})" : ''),
        ];
    }

    /** Status ringkas untuk top bar dan panel admin (tidak melempar eksepsi). */
    public function status(bool $forceRefresh = false): array
    {
        $compute = function () use ($forceRefresh): array {
            $cfg = $this->resolvedConfig();
            $hw = $this->telemetry->snapshot($forceRefresh);
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
                    'is_simulation' => $d->isSimulation() && !$this->shouldAttemptCloud($cfg),
                    'latency_ms' => $h['latency_ms'],
                    'ai_ram_used_gb' => $hw['ai_process_ram_gb'] ?? null,
                    'ai_active_process' => $hw['ai_active_process'] ?? null,
                    'engine_mode' => $cfg['engine_mode'] ?? 'HYBRID_AUTO',
                    'cloud_provider' => $cfg['cloud_provider'] ?? 'google_ai_studio',
                    'cloud_model' => ($cfg['cloud_provider'] ?? '') === 'exa_ai' ? ($cfg['exa_model'] ?? 'exa') : ($cfg['google_model'] ?? 'gemini-2.0-flash'),
                    'cloud_configured' => $this->shouldAttemptCloud($cfg),
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
                    'ai_ram_used_gb' => $hw['ai_process_ram_gb'] ?? null,
                    'ai_active_process' => $hw['ai_active_process'] ?? null,
                    'engine_mode' => $cfg['engine_mode'] ?? 'HYBRID_AUTO',
                    'cloud_provider' => $cfg['cloud_provider'] ?? 'google_ai_studio',
                    'cloud_model' => ($cfg['cloud_provider'] ?? '') === 'exa_ai' ? ($cfg['exa_model'] ?? 'exa') : ($cfg['google_model'] ?? 'gemini-2.0-flash'),
                    'cloud_configured' => $this->shouldAttemptCloud($cfg),
                    'error' => $e->getMessage(),
                ];
            }
        };

        if (app()->environment('testing') || $this->driver !== null) {
            return $compute();
        }

        $orgId = Auth::guard('web')->check() ? Auth::guard('web')->user()?->organization_id : null;
        $cacheKey = 'trace_ai_status_snapshot_' . ($orgId ?? 'global');

        if ($forceRefresh) {
            Cache::forget($cacheKey);
            Cache::forget('trace_ai_status_snapshot');
        }

        return Cache::remember($cacheKey, now()->addSeconds(5), $compute);
    }

    /**
     * Simpan konfigurasi runtime AI (menggantikan keharusan mengedit .env secara manual),
     * tegakkan LocalGuard untuk endpoint intranet/localhost, dan sinkronkan model nyata.
     */
    public function configureRuntime(array $params, ?User $by, AuditLogger $audit): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(240);
        }
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

        $hw = $this->telemetry->snapshot();
        $modelProfile = $model ? $this->profiles->resolve($model, [], $hw) : null;
        $hasExplicitTimeout = array_key_exists('timeout', $params) && $params['timeout'] !== null && $params['timeout'] !== '';
        $timeout = $hasExplicitTimeout && empty($params['auto_adapt_timeout'])
            ? max(10, min(900, (int) $params['timeout']))
            : ($modelProfile['recommended_timeout'] ?? 180);

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
        if ($embeddingModel !== null) {
            $this->settings->setInternal('ai.embedding_model', $embeddingModel, $by);
        } elseif (array_key_exists('embedding_model', $params) && $params['embedding_model'] === '') {
            $this->settings->setInternal('ai.embedding_model', '', $by);
        }
        $this->settings->setInternal('ai.timeout', (string) $timeout, $by);

        if (!empty($params['clear_api_key'])) {
            $this->settings->setInternal('ai.api_key_enc', null, $by);
        } elseif (!empty($params['api_key'])) {
            $this->settings->setInternal('ai.api_key_enc', Crypt::encryptString((string) $params['api_key']), $by);
        }

        // Bebaskan RAM dari engine lokal sebelumnya bila berganti driver/endpoint agar tidak terjadi penumpukan memori
        if ($prevDriver === 'openai_compatible' && $this->isLocalLmStudioUrl($prevUrl)
            && ($driver !== 'openai_compatible' || !$this->isLocalLmStudioUrl($baseUrl))) {
            $prevPort = (int) (parse_url((string) $prevUrl, PHP_URL_PORT) ?: 1234);
            $this->telemetry->unloadAllLocalLmStudioModels($prevPort);
        } elseif ($prevDriver === 'ollama' && $prevUrl && $prevModel && ($driver !== 'ollama' || rtrim((string) $prevUrl, '/') !== rtrim((string) $baseUrl, '/'))) {
            $this->telemetry->unloadOllamaModel($prevUrl, $prevModel);
        }

        $runtimeAction = null;
        if ($driver === 'openai_compatible' && $baseUrl && $this->isLocalLmStudioUrl($baseUrl)) {
            $port = (int) (parse_url($baseUrl, PHP_URL_PORT) ?: 1234);
            $runtimeAction = $model
                ? $this->telemetry->switchLmStudioLoadedModel($model, $port, $modelProfile['estimated_ram_gb'] ?? null)
                : $this->telemetry->ensureLmStudioServerRunning($port);
        }

        $this->resetDriver();
        $server = $this->syncServerRecord();
        $sync = $this->syncModelsFromServer($server, $model);

        $audit->log('AI_SERVER_CONFIGURED', $server, [
            'from' => ['driver' => $prevDriver, 'base_url' => $prevUrl, 'model' => $prevModel],
            'to' => ['driver' => $driver, 'base_url' => $baseUrl, 'model' => $this->activeModelName(), 'timeout' => $timeout],
            'model_capability' => $modelProfile['capability_type'] ?? null,
            'sync_ok' => $sync['ok'],
            'models_synced' => $sync['count'],
        ], $by?->id);

        return ['server' => $server, 'sync' => $sync, 'runtime_action' => $runtimeAction, 'profile' => $modelProfile];
    }

    /**
     * Analisis terstruktur Hybrid Adaptive:
     * 1. Bila mode HYBRID_AUTO/FORCE_CLOUD & Cloud API key tersedia, coba Cloud Driver (Google AI Studio / Exa.ai).
     * 2. Bila koneksi putus / timeout / HTTP 5xx / kuota habis pada mode HYBRID_AUTO, Auto-Failover ke AI Lokal
     *    dan catat event AI_CLOUD_FAILOVER pada Audit Log imutabel.
     * 3. Lakukan normalisasi & validasi skema, serta pemulihan aman bila model lokal kecil (mis. 0.5B) tidak patuh skema JSON.
     *
     * @param  array{text:string,evidence:array,case:?array,flags:array}  $payload
     * @param  string[]  $validEvidenceIds
     * @return array{result:array,driver:string,model_name:?string,model_version:?string,is_simulation:bool,attempts:int,execution_tier?:string,failover_triggered?:bool,failover_reason?:?string}
     *
     * @throws AiUnavailableException|AiInvalidOutputException
     */
    public function analyze(string $module, array $payload, array $validEvidenceIds): array
    {
        $cfg = $this->resolvedConfig();
        $attemptCloud = $this->shouldAttemptCloud($cfg);
        $localDriver = $this->driver();

        if ($attemptCloud) {
            $cloudDriver = $this->cloudDriver($cfg);
            if ($cloudDriver !== null) {
                try {
                    $res = $this->executeAnalyzeWithDriver($cloudDriver, $module, $payload, $validEvidenceIds, 1, false);
                    $res['execution_tier'] = 'CLOUD_PRIMARY';
                    $res['failover_triggered'] = false;
                    $res['failover_reason'] = null;
                    $res['cloud_provider'] = $cloudDriver->name();
                    $res['result']['execution_tier'] = 'CLOUD_PRIMARY';
                    $res['result']['failover_triggered'] = false;
                    return $res;
                } catch (\Throwable $cloudEx) {
                    if (($cfg['engine_mode'] ?? 'HYBRID_AUTO') === 'FORCE_CLOUD') {
                        throw $cloudEx;
                    }
                    try {
                        app(AuditLogger::class)->log('AI_CLOUD_FAILOVER', null, [
                            'cloud_provider' => $cloudDriver->name(),
                            'cloud_model' => $cloudDriver->modelName(),
                            'reason' => mb_substr($cloudEx->getMessage(), 0, 300),
                            'fallback_driver' => $localDriver->name(),
                            'fallback_model' => $localDriver->modelName(),
                        ]);
                    } catch (\Throwable) {
                    }

                    $res = $this->executeAnalyzeWithDriver(
                        $localDriver,
                        $module,
                        $payload,
                        $validEvidenceIds,
                        max(1, 1 + (int) ($cfg['max_retries'] ?? 2)),
                        true
                    );
                    $res['execution_tier'] = 'LOCAL_FAILOVER';
                    $res['failover_triggered'] = true;
                    $res['failover_reason'] = mb_substr($cloudEx->getMessage(), 0, 250);
                    $res['cloud_provider_attempted'] = $cloudDriver->name();
                    $res['result']['execution_tier'] = 'LOCAL_FAILOVER';
                    $res['result']['failover_triggered'] = true;
                    $res['result']['failover_reason'] = $res['failover_reason'];
                    return $res;
                }
            }
        }

        $res = $this->executeAnalyzeWithDriver(
            $localDriver,
            $module,
            $payload,
            $validEvidenceIds,
            max(1, 1 + (int) ($cfg['max_retries'] ?? 2)),
            !$localDriver->isSimulation()
        );
        $res['execution_tier'] = $localDriver->isSimulation() ? 'MOCK_SIMULATION' : 'LOCAL_DIRECT';
        $res['failover_triggered'] = false;
        $res['failover_reason'] = null;
        $res['result']['execution_tier'] = $res['execution_tier'];
        $res['result']['failover_triggered'] = false;
        return $res;
    }

    /** Eksekusi inti analisis terstruktur pada driver target dengan retry & salvage aman untuk model kecil. */
    private function executeAnalyzeWithDriver(
        AiDriver $driver,
        string $module,
        array $payload,
        array $validEvidenceIds,
        int $max,
        bool $allowSalvageOnSmallModel
    ): array {
        $cfg = $this->resolvedConfig();
        if (function_exists('set_time_limit')) {
            @set_time_limit(max(240, ($max * ((int) ($cfg['timeout'] ?? 180))) + 60));
        }
        $messages = $this->prompts->analysis($module, $payload);
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

            $data = $this->normalizeAnalysisData($data, $module, $driver, $validEvidenceIds, $payload);

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

        // Untuk model lokal nyata berukuran kecil (mis. Qwen2.5-0.5B) yang merespons teks non-JSON valid,
        // selamatkan keluaran secara aman tanpa mengarang bukti palsu agar operasi taktis tidak terputus.
        if ($allowSalvageOnSmallModel && !$driver->isSimulation() && $lastRaw !== null && trim($lastRaw) !== '') {
            $plainText = trim(JsonExtractor::stripThinking($lastRaw));
            $fallbackSummary = mb_substr($plainText !== '' ? $plainText : (string) ($payload['corroboration_summary'] ?? $payload['text'] ?? 'Analisis selesai dengan skema minimal.'), 0, 1400);
            $salvaged = $this->normalizeAnalysisData([
                'summary' => $fallbackSummary,
                'tactical_summary' => !empty($payload['corroboration_summary'])
                    ? (string) $payload['corroboration_summary']
                    : $fallbackSummary,
                'verification_status' => 'BELUM TERVERIFIKASI',
                'content_indicator' => 'BELUM DAPAT DIPASTIKAN',
                'classification' => 'UNVERIFIED',
                'credibility_score' => 50,
                'threat_level' => 'SEDANG',
                'confidence' => 0.45,
                'limitations' => ['Model lokal kecil menghasilkan teks non-skema; hasil disusun otomatis berbasis bukti real-time & tinjauan operator.'],
                'recommendations' => ['Lakukan verifikasi silang manual pada daftar tautan sumber real-time di atas.'],
            ], $module, $driver, $validEvidenceIds, $payload);

            $salvagedErrors = $this->validator->validateAnalysis($salvaged, $validEvidenceIds);
            if (!$salvagedErrors) {
                $salvaged['analysis_id'] = (string) Str::uuid();
                $salvaged['analyzed_at'] = now()->toIso8601String();
                $salvaged['validation_status'] = 'PENDING REVIEW';
                return [
                    'result' => $salvaged,
                    'driver' => $driver->name(),
                    'model_name' => $driver->modelName(),
                    'model_version' => $salvaged['model_version'],
                    'is_simulation' => false,
                    'attempts' => $max,
                    'salvaged_from_raw' => true,
                ];
            }
        }

        throw new AiInvalidOutputException(
            'Keluaran AI tidak sesuai skema setelah ' . $max . ' percobaan. Hasil tidak disimpan; minta pemeriksaan ulang.',
            $lastErrors,
            $lastRaw
        );
    }

    /** Normalisasi variasi enum bilingual & struktur LLM open-weights tanpa mengarang Evidence ID fiktif. */
    private function normalizeAnalysisData(array $data, string $module, AiDriver $driver, array $validEvidenceIds = [], array $payload = []): array
    {
        $data['module'] = $module;
        $data['is_simulation'] = $driver->isSimulation();
        $data['model_name'] = !empty($data['model_name']) ? (string) $data['model_name'] : $driver->modelName();
        $data['model_version'] = $data['model_version'] ?? null;

        $normUpperSpaced = fn (?string $val) => $val === null ? '' : trim((string) preg_replace('/\s+/', ' ', strtoupper(str_replace(['_', '-'], ' ', $val))));
        $normUpperUnderscore = fn (?string $val) => $val === null ? '' : trim((string) preg_replace('/\s+/', '_', strtoupper(str_replace('-', '_', $val))));

        $mapCheckStatus = function (?string $val) use ($normUpperSpaced): string {
            $n = $normUpperSpaced($val);
            return match ($n) {
                'VERIFIED', 'TRUE', 'VALID', 'CONFIRMED', 'TERKONFIRMASI', 'BENAR', 'FAKTA' => 'TERVERIFIKASI',
                'REFUTED', 'FALSE', 'HOAX', 'HOAKS', 'DISPROVED', 'DEBUNKED', 'CONTRADICTED', 'SALAH', 'PALSU', 'DIBANTAH', 'DISINFORMATION' => 'TERBANTAHKAN',
                'UNVERIFIED', 'PENDING', 'UNKNOWN', 'TIDAK TERVERIFIKASI', 'BELUM DIPASTIKAN', 'BELUM BISA DIPASTIKAN', 'BELUM DAPAT DIPASTIKAN', 'UNCONFIRMED' => 'BELUM TERVERIFIKASI',
                'INSUFFICIENT', 'INSUFFICIENT EVIDENCE', 'NOT ENOUGH EVIDENCE', 'TIDAK CUKUP', 'BELUM CUKUP BUKTI', 'BELUM CUKUP', 'TIDAK ADA BUKTI', 'NO EVIDENCE', '' => 'TIDAK CUKUP BUKTI',
                default => in_array($n, ['TERVERIFIKASI', 'TERBANTAHKAN', 'BELUM TERVERIFIKASI', 'TIDAK CUKUP BUKTI'], true) ? $n : 'BELUM TERVERIFIKASI',
            };
        };

        $mapClaimType = function (?string $val): string {
            $n = strtoupper(trim((string) $val));
            return match ($n) {
                'FACT', 'BENAR', 'EVENT' => 'FAKTA',
                'OPINION', 'PERNYATAAN' => 'OPINI',
                'ALLEGATION', 'RUMOR', 'CLAIM', 'SPECULATION', 'KLAIM', 'TUDUHAN', 'HOAX', 'HOAKS' => 'DUGAAN',
                'QUOTE', 'CITATION' => 'KUTIPAN',
                default => in_array($n, ['FAKTA', 'OPINI', 'DUGAAN', 'KUTIPAN'], true) ? $n : 'FAKTA',
            };
        };

        $toScalarText = function ($val, int $maxLen = 1000, string $fallback = ''): string {
            if (is_array($val)) {
                $parts = array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', $val), fn ($v) => $v !== '');
                $txt = implode('; ', $parts);
            } elseif (is_scalar($val)) {
                $txt = trim((string) $val);
            } else {
                $txt = '';
            }
            if ($txt === '') {
                $txt = $fallback;
            }
            return mb_substr($txt, 0, $maxLen);
        };

        $data['verification_status'] = $mapCheckStatus(is_string($data['verification_status'] ?? null) ? $data['verification_status'] : null);

        $ciRaw = is_string($data['content_indicator'] ?? null) ? $normUpperSpaced($data['content_indicator']) : '';
        $data['content_indicator'] = match ($ciRaw) {
            'AUTHENTIC', 'ORIGINAL', 'GENUINE', 'TRUE', 'VERIFIED', 'VALID', 'TERVERIFIKASI' => 'ASLI',
            'EDITED', 'ALTERED', 'MANIPULATED', 'MANIPULATED CONTENT', 'MANIPULASI', 'DEEPFAKE', 'AI GENERATED' => 'HASIL EDIT',
            'MENYESATKAN', 'MISLEAD', 'CLICKBAIT', 'SEBAGIAN SALAH' => 'MISLEADING',
            'HOAX', 'HOAKS', 'FAKE', 'DISINFORMATION', 'FALSE', 'TERBANTAHKAN', 'PALSU', 'INDIKASI HOAX', 'INDIKASI PALSU' => 'INDIKASI HOAKS',
            'UNVERIFIED', 'UNCERTAIN', 'UNKNOWN', 'UNCONFIRMED', 'BELUM TERVERIFIKASI', 'TIDAK CUKUP BUKTI', 'BELUM CUKUP BUKTI', 'BELUM BISA DIPASTIKAN', '' => 'BELUM DAPAT DIPASTIKAN',
            default => in_array($ciRaw, ['ASLI', 'HASIL EDIT', 'MISLEADING', 'INDIKASI HOAKS', 'BELUM DAPAT DIPASTIKAN'], true) ? $ciRaw : 'BELUM DAPAT DIPASTIKAN',
        };

        $clRaw = is_string($data['classification'] ?? null) ? $normUpperUnderscore($data['classification']) : '';
        $data['classification'] = match ($clRaw) {
            'AUTHENTIC', 'TRUE', 'VALID', 'BENAR', 'TERVERIFIKASI' => 'VERIFIED',
            'HOAX', 'HOAKS', 'FAKE', 'FAKE_NEWS', 'FALSE', 'FABRICATED', 'SCAM', 'PHISHING', 'PENIPUAN', 'MISINFORMATION', 'INDIKASI_HOAKS', 'TERBANTAHKAN', 'FITNAH' => 'DISINFORMATION',
            'MANIPULATED', 'HASIL_EDIT', 'DEEPFAKE' => 'MANIPULATED_CONTENT',
            'MENYESATKAN', 'SEBAGIAN_SALAH' => 'MISLEADING',
            'BELUM_TERVERIFIKASI', 'TIDAK_CUKUP_BUKTI', 'BELUM_CUKUP_BUKTI', 'UNKNOWN', 'UNCERTAIN', '' => 'UNVERIFIED',
            default => in_array($clRaw, ['VERIFIED', 'UNVERIFIED', 'MISLEADING', 'MANIPULATED_CONTENT', 'DISINFORMATION', 'PROPAGANDA'], true) ? $clRaw : 'UNVERIFIED',
        };

        $tlRaw = is_string($data['threat_level'] ?? null) ? $normUpperSpaced($data['threat_level']) : '';
        $data['threat_level'] = match ($tlRaw) {
            'LOW', 'AMAN', 'MINIMAL', 'TIDAK ADA', 'NONE', 'SAFE' => 'RENDAH',
            'MEDIUM', 'MODERATE', 'WASPADA', 'MENENGAH', '' => 'SEDANG',
            'HIGH', 'BAHAYA', 'BERBAHAYA', 'SERIUS' => 'TINGGI',
            'CRITICAL', 'SEVERE', 'DARURAT', 'SANGAT TINGGI', 'EKSTREM' => 'KRITIS',
            default => in_array($tlRaw, ['RENDAH', 'SEDANG', 'TINGGI', 'KRITIS'], true) ? $tlRaw : 'SEDANG',
        };

        if (isset($data['credibility_score']) && is_numeric($data['credibility_score'])) {
            $data['credibility_score'] = max(0, min(100, (int) round((float) $data['credibility_score'])));
        } else {
            $data['credibility_score'] = match ($data['verification_status']) {
                'TERVERIFIKASI' => 80,
                'TERBANTAHKAN' => 15,
                default => 50,
            };
        }

        if (isset($data['confidence']) && is_numeric($data['confidence'])) {
            $conf = (float) $data['confidence'];
            if ($conf > 1.0 && $conf <= 100.0) {
                $conf = round($conf / 100.0, 3);
            }
            $data['confidence'] = max(0.0, min(1.0, $conf));
        } else {
            $data['confidence'] = 0.5;
        }

        $stripPlaceholder = function (string $val): string {
            $low = strtolower(trim($val));
            if ($low === 'string' || str_contains($low, 'kalimat kesimpulan analisis yang menyebut')) {
                return '';
            }
            return $val;
        };

        $summaryText = $stripPlaceholder($toScalarText($data['summary'] ?? null, 4000));
        if ($summaryText === '') {
            $summaryText = $stripPlaceholder($toScalarText($data['tactical_summary'] ?? null, 4000));
        }
        if ($summaryText === '' && !empty($data['claims'][0]) && is_array($data['claims'][0])) {
            $summaryText = $stripPlaceholder($toScalarText($data['claims'][0]['text'] ?? null, 4000));
        }
        if ($summaryText === '') {
            $summaryText = !empty($payload['corroboration_summary'])
                ? mb_substr(trim((string) $payload['corroboration_summary']), 0, 4000)
                : ('Evaluasi kredibilitas terhadap klaim: ' . mb_substr(trim((string) ($payload['text'] ?? 'Konten diperiksa')), 0, 500));
        }
        $data['summary'] = $summaryText;

        // Bangun peta ID bukti sah secara case-insensitive (tanpa mengarang ID baru)
        $validIdLookup = [];
        foreach ($validEvidenceIds as $vid) {
            $vStr = (string) $vid;
            $validIdLookup[strtolower($vStr)] = $vStr;
            $validIdLookup[strtolower(str_replace('-', '', $vStr))] = $vStr;
        }
        $resolveValidId = function ($candidate) use ($validIdLookup): ?string {
            if (!is_string($candidate) && !is_numeric($candidate)) {
                return null;
            }
            $raw = trim((string) $candidate);
            if ($raw === '') {
                return null;
            }
            $k1 = strtolower($raw);
            if (isset($validIdLookup[$k1])) {
                return $validIdLookup[$k1];
            }
            $k2 = strtolower(str_replace('-', '', $raw));
            return $validIdLookup[$k2] ?? null;
        };

        $normalizedClaims = [];
        if (isset($data['claims']) && is_array($data['claims'])) {
            foreach (array_values($data['claims']) as $i => $c) {
                if (is_string($c) && trim($c) !== '') {
                    $normalizedClaims[] = [
                        'id' => 'c' . ($i + 1),
                        'text' => mb_substr(trim($c), 0, 2000),
                        'type' => 'FAKTA',
                        'check_status' => $data['verification_status'] ?? 'BELUM TERVERIFIKASI',
                    ];
                    continue;
                }
                if (!is_array($c)) {
                    continue;
                }
                $txt = trim((string) ($c['text'] ?? $c['claim'] ?? $c['statement'] ?? ''));
                if ($txt === '') {
                    continue;
                }
                $normalizedClaims[] = [
                    'id' => !empty($c['id']) ? mb_substr((string) $c['id'], 0, 20) : 'c' . ($i + 1),
                    'text' => mb_substr($txt, 0, 2000),
                    'type' => $mapClaimType((string) ($c['type'] ?? 'FAKTA')),
                    'check_status' => $mapCheckStatus((string) ($c['check_status'] ?? ($data['verification_status'] ?? 'BELUM TERVERIFIKASI'))),
                ];
            }
        }
        if ($normalizedClaims === []) {
            $fallbackClaim = !empty($payload['text']) ? trim((string) $payload['text']) : (string) ($data['summary'] ?? 'Klaim diperiksa');
            $normalizedClaims[] = [
                'id' => 'c1',
                'text' => mb_substr($fallbackClaim, 0, 500),
                'type' => 'FAKTA',
                'check_status' => is_string($data['verification_status'] ?? null) ? $data['verification_status'] : 'BELUM TERVERIFIKASI',
            ];
        }
        $data['claims'] = array_slice($normalizedClaims, 0, 30);

        // Pastikan linguistic_analysis & tactical_summary lengkap & bertipe string
        if (isset($data['summary']) && is_string($data['summary']) && trim($data['summary']) !== '') {
            $la = is_array($data['linguistic_analysis'] ?? null) ? $data['linguistic_analysis'] : [];
            $data['linguistic_analysis'] = [
                'emotion' => $toScalarText($la['emotion'] ?? null, 1000, 'Belum teridentifikasi indikasi manipulasi emosi dominan oleh model.'),
                'fallacy' => $toScalarText($la['fallacy'] ?? null, 1000, 'Tidak terdapat kesesatan logika spesifik yang dicatat.'),
                'source_verification' => $toScalarText($la['source_verification'] ?? null, 1000, 'Memerlukan verifikasi rujukan silang operator.'),
            ];
            $tact = $stripPlaceholder($toScalarText($data['tactical_summary'] ?? null, 1500));
            $data['tactical_summary'] = $tact !== '' ? $tact : mb_substr(trim($data['summary']), 0, 1500);
        }

        $normalizedFindings = [];
        if (isset($data['findings']) && is_array($data['findings'])) {
            foreach ($data['findings'] as $f) {
                if (is_string($f) && trim($f) !== '') {
                    $normalizedFindings[] = [
                        'statement' => mb_substr(trim($f), 0, 2000),
                        'type' => 'INTERPRETASI_AI',
                        'evidence_ids' => [],
                    ];
                    continue;
                }
                if (!is_array($f)) {
                    continue;
                }
                $stmt = trim((string) ($f['statement'] ?? $f['text'] ?? ''));
                if ($stmt === '') {
                    continue;
                }
                $ftype = $normUpperUnderscore((string) ($f['type'] ?? 'INTERPRETASI_AI'));
                $ftype = match ($ftype) {
                    'FACT' => 'FAKTA',
                    'SOURCE_CLAIM', 'CLAIM', 'BUKTI' => 'KLAIM_SUMBER',
                    'AI_INTERPRETATION', 'INTERPRETATION', 'ANALYSIS', 'OPINION', 'OPINI' => 'INTERPRETASI_AI',
                    'RECOMMENDATION', 'RECOMENDATION' => 'REKOMENDASI',
                    default => in_array($ftype, ['FAKTA', 'KLAIM_SUMBER', 'INTERPRETASI_AI', 'REKOMENDASI'], true) ? $ftype : 'INTERPRETASI_AI',
                };
                $filteredIds = [];
                foreach (is_array($f['evidence_ids'] ?? null) ? $f['evidence_ids'] : [] as $rawId) {
                    $matched = $resolveValidId($rawId);
                    if ($matched !== null && !in_array($matched, $filteredIds, true)) {
                        $filteredIds[] = $matched;
                    }
                }
                // Bila model menyebut FAKTA/KLAIM_SUMBER tanpa Evidence ID sah, turunkan jujur menjadi INTERPRETASI_AI
                if (in_array($ftype, ['FAKTA', 'KLAIM_SUMBER'], true) && $filteredIds === []) {
                    $ftype = 'INTERPRETASI_AI';
                }
                $normalizedFindings[] = [
                    'statement' => mb_substr($stmt, 0, 2000),
                    'type' => $ftype,
                    'evidence_ids' => $filteredIds,
                ];
            }
        }
        if ($normalizedFindings === [] && !empty($data['summary']) && is_string($data['summary'])) {
            $normalizedFindings[] = [
                'statement' => mb_substr(trim($data['summary']), 0, 2000),
                'type' => 'INTERPRETASI_AI',
                'evidence_ids' => [],
            ];
        }
        $data['findings'] = array_slice($normalizedFindings, 0, 40);

        // Bersihkan daftar evidence & sources dari entri fiktif/malformed
        $cleanEvidence = [];
        if ($validEvidenceIds !== [] && is_array($data['evidence'] ?? null)) {
            foreach ($data['evidence'] as $e) {
                if (!is_array($e)) {
                    continue;
                }
                $matched = $resolveValidId($e['id'] ?? null);
                $excerpt = trim((string) ($e['excerpt'] ?? $e['text'] ?? ''));
                if ($matched !== null && $excerpt !== '') {
                    $cleanEvidence[] = [
                        'id' => $matched,
                        'excerpt' => mb_substr($excerpt, 0, 3000),
                    ];
                }
            }
        }
        $data['evidence'] = $cleanEvidence;

        $cleanSources = [];
        if ($validEvidenceIds !== [] && is_array($data['sources'] ?? null)) {
            foreach ($data['sources'] as $idx => $s) {
                if (!is_array($s)) {
                    continue;
                }
                $sid = trim((string) ($s['id'] ?? ''));
                if ($sid === '') {
                    $sid = 's' . ($idx + 1);
                }
                $st = $normUpperSpaced((string) ($s['access_status'] ?? 'TIDAK TERSEDIA'));
                $st = match ($st) {
                    'AVAILABLE', 'SUCCESS', 'ACCESSIBLE', 'VALID', '200' => 'OK',
                    'FAILED', 'ERROR', 'BLOCKED' => 'GAGAL',
                    'UNAVAILABLE', 'UNKNOWN', 'NOT AVAILABLE', '' => 'TIDAK TERSEDIA',
                    default => in_array($st, ['OK', 'GAGAL', 'TIDAK TERSEDIA'], true) ? $st : 'TIDAK TERSEDIA',
                };
                $cleanSources[] = [
                    'id' => mb_substr($sid, 0, 30),
                    'access_status' => $st,
                ];
            }
        }
        $data['sources'] = $cleanSources;

        $data['recommendations'] = is_array($data['recommendations'] ?? null)
            ? array_values(array_filter(array_map(fn ($v) => is_scalar($v) ? mb_substr(trim((string) $v), 0, 1000) : '', $data['recommendations']), fn ($v) => $v !== ''))
            : [];
        $data['limitations'] = is_array($data['limitations'] ?? null)
            ? array_values(array_filter(array_map(fn ($v) => is_scalar($v) ? mb_substr(trim((string) $v), 0, 1000) : '', $data['limitations']), fn ($v) => $v !== ''))
            : [];

        return $data;
    }

    /** @param array<int,array{role:string,content:string}> $messages */
    public function chat(array $messages, array $options = []): array
    {
        $cfg = $this->resolvedConfig();
        if (function_exists('set_time_limit')) {
            @set_time_limit(max(120, ((int) ($cfg['timeout'] ?? 180)) + 60));
        }

        if ($this->shouldAttemptCloud($cfg)) {
            $cloud = $this->cloudDriver($cfg);
            if ($cloud !== null) {
                try {
                    $out = $cloud->chat($messages, $options);
                    $out['execution_tier'] = 'CLOUD_PRIMARY';
                    $out['failover_triggered'] = false;
                    return $out;
                } catch (\Throwable $e) {
                    if (($cfg['engine_mode'] ?? 'HYBRID_AUTO') === 'FORCE_CLOUD') {
                        throw $e;
                    }
                    try {
                        app(AuditLogger::class)->log('AI_CLOUD_FAILOVER', null, [
                            'context' => 'console_chat',
                            'cloud_provider' => $cloud->name(),
                            'reason' => mb_substr($e->getMessage(), 0, 300),
                            'fallback_model' => $this->activeModelName(),
                        ]);
                    } catch (\Throwable) {
                    }
                }
            }
        }

        if (!array_key_exists('temperature', $options)) {
            $prof = $this->activeModelProfile();
            $options['temperature'] = $prof['recommended_temperature'] ?? 0.2;
        }
        return $this->driver()->chat($messages, $options);
    }

    /** @return array<int,array<int,float>> */
    public function embed(array $texts): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        return $this->driver()->embed($texts);
    }

    public function isLocalLmStudioUrl(?string $url): bool
    {
        if (!$url) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $port = (int) (parse_url($url, PHP_URL_PORT) ?: 1234);
        return in_array($host, ['127.0.0.1', 'localhost'], true) && $port === 1234;
    }

    /** Sinkronkan baris ai_servers berdasarkan kombinasi (driver + base_url) agar multi-server tidak saling menimpa. */
    public function syncServerRecord(): AiServer
    {
        $cfg = $this->resolvedConfig();
        $driver = (string) ($cfg['driver'] ?? 'mock');
        $baseUrl = $driver === 'mock' ? null : (!empty($cfg['base_url']) ? rtrim((string) $cfg['base_url'], '/') : null);
        $health = $this->status(true);

        $defaultName = match ($driver) {
            'ollama' => 'Local Ollama Node (' . ($baseUrl ?: '127.0.0.1:11434') . ')',
            'openai_compatible' => ($health['runtime'] ?? 'LM Studio / OpenAI-Compatible') . ' (' . ($baseUrl ?: '127.0.0.1:1234') . ')',
            'external' => 'External Gateway (Controlled)',
            default => 'Internal AI Inference Engine (MockDriver)',
        };

        if ($driver === 'mock') {
            $server = AiServer::firstOrCreate(
                ['driver' => 'mock'],
                ['name' => $defaultName, 'base_url' => null, 'is_active' => true]
            );
        } else {
            $server = AiServer::where('driver', $driver)
                ->where(function ($q) use ($baseUrl) {
                    $q->where('base_url', $baseUrl)->orWhereNull('base_url');
                })
                ->first();
            if (!$server) {
                $server = AiServer::create([
                    'name' => $defaultName,
                    'driver' => $driver,
                    'base_url' => $baseUrl,
                    'is_active' => true,
                ]);
            }
        }

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
     * Ambil daftar model nyata langsung dari server AI lokal/intranet dan sinkronkan ke tabel ai_models
     * dengan deteksi kapabilitas berbeda per model.
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

        $hw = $this->telemetry->snapshot();
        $targetName = $preferredModel ?: $this->activeModelName();

        try {
            $remote = $driver->listModels();
        } catch (\Throwable $e) {
            // Jika server LM Studio localhost sedang offline, tetap indeks katalog disk agar daftar model multi-AI terlihat
            if ($server->driver === 'openai_compatible' && $this->isLocalLmStudioUrl($server->base_url)) {
                $this->syncLocalLmStudioCatalog($server, $hw, $targetName);
            }
            return ['ok' => false, 'count' => 0, 'models' => [], 'error' => $e->getMessage()];
        }

        $remoteNames = array_values(array_filter(array_column($remote, 'name'), fn ($n) => $n !== ''));

        // Untuk server live, hapus entri simulasi lama dan pangkas model yang tidak ada di mesin pengguna
        AiModel::where('server_id', $server->id)->where('is_simulation', true)->delete();
        $ownedNames = $remoteNames;

        $synced = [];
        $firstLlmName = null;
        $firstEmbeddingName = null;
        foreach ($remote as $rm) {
            $name = (string) ($rm['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $resolved = $this->profiles->resolve($name, $rm, $hw);
            if ($resolved['is_embedding']) {
                if ($firstEmbeddingName === null) {
                    $firstEmbeddingName = $name;
                }
            } elseif ($firstLlmName === null) {
                $firstLlmName = $name;
            }

            $modelRow = $this->upsertProfiledModel($server, $name, $resolved, !empty($rm['loaded']) ? 'LOADED' : ($rm['status'] ?? 'READY'));
            $synced[] = $modelRow;
        }

        // Gabungkan katalog disk LM Studio jika server hanya melaporkan sebagian (mis. /v1/models hanya loaded model)
        if ($server->driver === 'openai_compatible' && $this->isLocalLmStudioUrl($server->base_url)) {
            $diskKeys = $this->syncLocalLmStudioCatalog($server, $hw, null, false);
            $ownedNames = array_values(array_unique(array_merge($ownedNames, $diskKeys)));
        }

        // Pangkas semua model lama yang sudah tidak dimiliki pada server/komputer pengguna
        if ($ownedNames !== [] && ($server->driver === 'ollama' || $this->isLoopbackUrl($server->base_url))) {
            AiModel::where('server_id', $server->id)->whereNotIn('name', $ownedNames)->delete();
        }

        // Hormati $preferredModel hanya bila bukan localhost atau memang terdaftar di daftar owned
        if ($preferredModel && !in_array($preferredModel, $ownedNames, true) && !$this->isLoopbackUrl($server->base_url)) {
            $resolvedPref = $this->profiles->resolve($preferredModel, [], $hw);
            $this->upsertProfiledModel($server, $preferredModel, $resolvedPref, 'READY');
        }

        // Otomatis daftarkan model embedding jika ditemukan dan belum disetel
        if ($firstEmbeddingName && !$this->settings->get('ai.embedding_model')) {
            $this->settings->setInternal('ai.embedding_model', $firstEmbeddingName);
        }

        // Pertahankan target model yang valid/eksplisit, atau pilih LLM pertama jika belum ada pilihan
        $chosenName = $preferredModel && ($ownedNames === [] || in_array($preferredModel, $ownedNames, true))
            ? $preferredModel
            : (($targetName && in_array($targetName, $ownedNames, true)) ? $targetName : ($firstLlmName ?? ($ownedNames[0] ?? null)));

        if ($chosenName !== null) {
            AiModel::where('server_id', $server->id)->update(['is_active' => false]);
            AiModel::where('server_id', $server->id)->where('name', $chosenName)->update(['is_active' => true]);
            $this->settings->setInternal('ai.model', $chosenName);
            $this->resetDriver();
        }

        return ['ok' => true, 'count' => count($synced), 'models' => $synced, 'error' => null];
    }

    /**
     * Pindai semua engine AI lokal & intranet (Ollama, LM Studio, Bionic, llama.cpp),
     * sinkronkan seluruh node & model yang benar-benar dimiliki di komputer pengguna.
     *
     * @return array{servers:array,telemetry:array,discovery:array}
     */
    public function discoverAndRegisterAllLocalNodes(?User $by = null): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        $hw = $this->telemetry->snapshot(true);
        $discovery = $this->telemetry->detectLocalEngines();
        $activeCfg = $this->resolvedConfig();
        $activeDriver = (string) ($activeCfg['driver'] ?? 'mock');
        $activeBaseUrl = $activeDriver === 'mock' ? null : rtrim((string) ($activeCfg['base_url'] ?? ''), '/');

        $lmsLocalKeys = array_values(array_filter(array_map(
            fn ($item) => (string) ($item['suggested_key'] ?? ''),
            $discovery['lmstudio_local_models'] ?? []
        )));

        foreach ($discovery['endpoints'] as $ep) {
            $drv = (string) ($ep['driver'] ?? 'openai_compatible');
            $url = rtrim((string) ($ep['base_url'] ?? ''), '/');
            if ($url === '') {
                continue;
            }
            $isActiveNode = ($activeDriver === $drv && $activeBaseUrl === $url);
            $isOnline = !empty($ep['online']);
            $hasLiveModels = $isOnline && !empty($ep['models']);
            $isLmsDefault = $this->isLocalLmStudioUrl($url);
            $hasLmsCatalog = $isLmsDefault && $lmsLocalKeys !== [];

            // Jangan buat/tampilkan node offline lokal yang tidak punya model apa pun kecuali sedang aktif dipilih
            if (!$isOnline && !$hasLmsCatalog && !$isActiveNode) {
                $stale = AiServer::where('driver', $drv)->where('base_url', $url)->first();
                if ($stale) {
                    AiModel::where('server_id', $stale->id)->delete();
                    $stale->delete();
                }
                continue;
            }

            $label = $ep['engine'] . ' (' . $url . ')';
            $server = AiServer::firstOrCreate(
                ['driver' => $drv, 'base_url' => $url],
                ['name' => $label, 'is_active' => $isActiveNode]
            );

            $server->update([
                'name' => $label,
                'status' => $isOnline ? 'ONLINE' : 'OFFLINE',
                'runtime' => $ep['engine'] . ($isOnline && !empty($ep['version']) ? ' ' . $ep['version'] : ''),
                'runtime_version' => $ep['version'] ?? $server->runtime_version,
                'latency_ms' => $ep['latency_ms'] ?? null,
                'last_error' => $ep['error'] ?? null,
                'is_active' => $isActiveNode,
                'last_checked_at' => now(),
            ]);

            $ownedNames = [];
            if ($hasLiveModels) {
                foreach ($ep['models'] as $m) {
                    $mName = (string) ($m['id'] ?? '');
                    if ($mName === '') {
                        continue;
                    }
                    $ownedNames[] = $mName;
                    $resolved = $this->profiles->resolve($mName, $m, $hw);
                    $this->upsertProfiledModel($server, $mName, $resolved, !empty($m['loaded']) ? 'LOADED' : 'READY');
                }
            }

            if ($isLmsDefault && !empty($discovery['lmstudio_local_models'])) {
                foreach ($discovery['lmstudio_local_models'] as $localM) {
                    $key = (string) ($localM['suggested_key'] ?? '');
                    if ($key === '') {
                        continue;
                    }
                    $ownedNames[] = $key;
                    $existing = AiModel::where('server_id', $server->id)->where('name', $key)->first();
                    $status = ($existing && $existing->status === 'LOADED' && $isOnline)
                        ? 'LOADED'
                        : ($isOnline ? 'READY' : 'OFFLINE');
                    $this->upsertProfiledModel($server, $key, $localM, $status);
                }
            }

            // Pangkas model yang tidak ada di host pada node lokal (anti-halusinasi model)
            $ownedNames = array_values(array_unique($ownedNames));
            if ($isOnline || $hasLmsCatalog) {
                if ($ownedNames === []) {
                    AiModel::where('server_id', $server->id)->delete();
                } else {
                    AiModel::where('server_id', $server->id)->whereNotIn('name', $ownedNames)->delete();
                }
            }
        }

        // Hapus entri simulasi lama dari node non-mock
        AiModel::where('is_simulation', true)
            ->whereHas('server', fn ($q) => $q->where('driver', '!=', 'mock'))
            ->delete();

        $allServers = AiServer::with(['models' => fn ($q) => $q->orderByDesc('is_active')->orderBy('name')])
            ->where('driver', '!=', 'mock')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();

        Cache::forget('trace_ai_status_snapshot');

        return [
            'servers' => $allServers->all(),
            'telemetry' => $hw,
            'discovery' => $discovery,
        ];
    }

    private function isLoopbackUrl(?string $url): bool
    {
        if (!$url) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    /**
     * Alihkan secara penuh AI aktif ke model pilihan (sekaligus driver, endpoint server, timeout optimal,
     * serta otomatis menyalakan/memuat LM Studio lokal bila dipilih).
     *
     * @return array{ok:bool,warning:?string,message:string,profile:array}
     */
    public function activateModel(AiModel $model, ?User $by, AuditLogger $audit, string $reason): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(240);
        }
        $model->loadMissing('server');
        $server = $model->server;
        if (!$server || $server->driver === 'mock') {
            throw new AiUnavailableException('Model simulasi tidak dapat diaktifkan sebagai engine operasional.', 'ERROR');
        }

        $hw = $this->telemetry->snapshot(true);
        $resolved = $this->profiles->resolve($model->name, [
            'display_name' => $model->display_name,
            'family' => $model->family,
            'parameters' => $model->parameter_size,
            'size' => $model->size,
            'quantization' => $model->quantization,
            'context_length' => $model->context_length,
            'vision' => $model->is_vision,
            'type' => $model->is_embedding ? 'embedding' : 'llm',
        ], $hw);

        // Jika model bertipe EMBEDDING, pasangkan sebagai embedding model tanpa memutus model chat aktif
        if ($resolved['is_embedding']) {
            $prevEmbed = $this->settings->get('ai.embedding_model');
            $this->settings->setInternal('ai.embedding_model', $model->name, $by);
            $this->resetDriver();
            $audit->log('AI_EMBEDDING_MODEL_SET', $model, [
                'previous' => $prevEmbed,
                'new' => $model->name,
                'reason' => $reason,
            ], $by?->id);
            return [
                'ok' => true,
                'warning' => null,
                'message' => "Model embedding '{$model->name}' disetel sebagai AI_EMBEDDING_MODEL (model analisis utama tetap terjaga).",
                'profile' => $resolved,
            ];
        }

        $prevDriver = $this->resolvedConfig()['driver'] ?? null;
        $prevBaseUrl = $this->resolvedConfig()['base_url'] ?? null;
        $prevActive = AiModel::where('is_active', true)
            ->whereHas('server', fn ($q) => $q->where('is_active', true))
            ->first();

        // Terapkan driver, endpoint server tujuan, model tujuan, dan timeout optimal otomatis
        $this->settings->setInternal('ai.driver', $server->driver, $by);
        $this->settings->setInternal('ai.base_url', (string) ($server->base_url ?? ''), $by);
        $this->settings->setInternal('ai.model', $model->name, $by);
        $this->settings->setInternal('ai.timeout', (string) $resolved['recommended_timeout'], $by);

        AiServer::where('id', '!=', $server->id)->update(['is_active' => false]);
        $server->update(['is_active' => true]);

        AiModel::where('server_id', $server->id)->update(['is_active' => false]);
        $model->update(['is_active' => true]);

        // Bebaskan RAM dari engine lokal sebelumnya jika berpindah lintas-engine
        if ($prevDriver === 'openai_compatible' && $this->isLocalLmStudioUrl($prevBaseUrl)
            && ($server->driver !== 'openai_compatible' || !$this->isLocalLmStudioUrl($server->base_url))) {
            $prevPort = (int) (parse_url((string) $prevBaseUrl, PHP_URL_PORT) ?: 1234);
            $this->telemetry->unloadAllLocalLmStudioModels($prevPort);
        } elseif ($prevDriver === 'ollama' && $prevBaseUrl && $prevActive && ($server->driver !== 'ollama' || rtrim((string) $prevBaseUrl, '/') !== rtrim((string) ($server->base_url ?? ''), '/'))) {
            $this->telemetry->unloadOllamaModel($prevBaseUrl, $prevActive->name);
        }

        $warning = null;
        $runtimeMessage = null;

        // Jika server target adalah LM Studio lokal, otomatis nyalakan server & muat model baru cleanly
        if ($server->driver === 'openai_compatible' && $this->isLocalLmStudioUrl($server->base_url)) {
            $port = (int) (parse_url((string) $server->base_url, PHP_URL_PORT) ?: 1234);
            $switchRes = $this->telemetry->switchLmStudioLoadedModel($model->name, $port, $resolved['estimated_ram_gb']);
            $warning = $switchRes['warning'];
            $runtimeMessage = $switchRes['message'];
        }

        $this->resetDriver();
        $freshServer = $this->syncServerRecord();

        // Perbarui profil & status loaded semua model di server tujuan bila online
        if ($freshServer->status === 'ONLINE') {
            $this->syncModelsFromServer($freshServer, $model->name);
        } elseif (!$warning) {
            $warning = "Server {$freshServer->name} berstatus OFFLINE. Jalankan engine AI pada {$freshServer->base_url} lalu klik Uji Koneksi.";
        }

        $changedById = $by?->id ?? User::value('id');
        if ($changedById) {
            AiModelChange::create([
                'model_id' => $model->id,
                'previous_model' => $prevActive?->name,
                'changed_by' => $changedById,
                'reason' => $reason,
            ]);
        }

        $audit->log('AI_MODEL_SWITCHED', $model, [
            'previous_driver' => $prevDriver,
            'previous_base_url' => $prevBaseUrl,
            'previous' => $prevActive?->name,
            'new_driver' => $server->driver,
            'new_base_url' => $server->base_url,
            'new' => $model->name,
            'capability_type' => $resolved['capability_type'],
            'adapted_timeout' => $resolved['recommended_timeout'],
            'server_status' => $freshServer->status,
            'reason' => $reason,
        ], $by?->id);

        $baseMsg = "AI aktif dialihkan ke '{$model->name}' pada {$server->driver} ({$server->base_url}) • Profil: {$resolved['capability_type']} • Timeout disesuaikan otomatis ke {$resolved['recommended_timeout']}s.";
        if ($runtimeMessage) {
            $baseMsg .= " {$runtimeMessage}";
        }

        return [
            'ok' => $freshServer->status === 'ONLINE',
            'warning' => $warning,
            'message' => $baseMsg,
            'profile' => $resolved,
        ];
    }

    private function syncLocalLmStudioCatalog(AiServer $server, array $hw, ?string $preferredModel = null, bool $markOffline = true): array
    {
        $catalog = $this->telemetry->scanLmStudioModelsDir($hw);
        $keys = [];
        foreach ($catalog as $item) {
            $key = (string) ($item['suggested_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $keys[] = $key;
            $existing = AiModel::where('server_id', $server->id)->where('name', $key)->first();
            $status = ($existing && $existing->status === 'LOADED' && !$markOffline)
                ? 'LOADED'
                : ($markOffline ? 'OFFLINE' : 'READY');
            $this->upsertProfiledModel($server, $key, $item, $status);
        }
        if ($preferredModel && ($keys === [] || in_array($preferredModel, $keys, true))) {
            AiModel::where('server_id', $server->id)->update(['is_active' => false]);
            AiModel::where('server_id', $server->id)->where('name', $preferredModel)->update(['is_active' => true]);
        }
        return $keys;
    }

    private function upsertProfiledModel(AiServer $server, string $name, array $resolved, string $status = 'READY'): AiModel
    {
        return AiModel::updateOrCreate(
            ['server_id' => $server->id, 'name' => $name],
            [
                'display_name' => $resolved['display_name'] ?? $name,
                'family' => $resolved['family'] ?? null,
                'parameter_size' => $resolved['parameter_size'] ?? null,
                'profile' => $resolved['profile'] ?? $this->inferProfile($name),
                'capability_type' => $resolved['capability_type'] ?? 'GENERAL_INSTRUCT',
                'is_reasoning' => !empty($resolved['is_reasoning']),
                'is_vision' => !empty($resolved['is_vision']),
                'is_embedding' => !empty($resolved['is_embedding']),
                'estimated_ram_gb' => $resolved['estimated_ram_gb'] ?? null,
                'recommended_timeout' => $resolved['recommended_timeout'] ?? 180,
                'recommended_temperature' => $resolved['recommended_temperature'] ?? 0.2,
                'notes' => $resolved['notes'] ?? null,
                'size' => $resolved['size'] ?? null,
                'quantization' => $resolved['quantization'] ?? null,
                'context_length' => isset($resolved['context_length']) ? (int) $resolved['context_length'] : null,
                'status' => substr($status, 0, 14),
                'is_simulation' => false,
            ]
        );
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
