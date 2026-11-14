<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\AiModelChange;
use App\Models\AiServer;
use App\Services\Ai\AiManager;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\HardwareTelemetry;
use App\Services\Support\AppSettings;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiManagementController extends Controller
{
    public function __construct(
        private AiManager $ai,
        private HardwareTelemetry $telemetry,
        private AppSettings $settings,
        private AuditLogger $audit,
    ) {
    }

    public function index()
    {
        $server = $this->ai->syncServerRecord();

        // Pindai & sinkronkan semua node AI lokal agar seluruh model yang berbeda langsung terlihat & siap dipilih
        $discovered = $this->ai->discoverAndRegisterAllLocalNodes(Auth::user());
        $server = $server->fresh();
        $status = $this->ai->status();
        $activeProfile = $this->ai->activeModelProfile();

        if ($status['is_simulation']) {
            $models = AiModel::with('server')->where('server_id', $server->id)->orderByDesc('is_active')->orderBy('name')->get();
        } else {
            $models = AiModel::with('server')
                ->where('is_simulation', false)
                ->whereHas('server', fn ($q) => $q->where('driver', '!=', 'mock'))
                ->orderByDesc('is_active')
                ->orderBy('server_id')
                ->orderBy('name')
                ->get();
        }

        $allServers = AiServer::withCount('models')
            ->where('driver', '!=', 'mock')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();

        $changes = AiModelChange::with(['model.server', 'admin'])->latest()->take(12)->get();
        $hardware = $discovered['telemetry'];
        $ggufOnDisk = $discovered['discovery']['lmstudio_local_models'] ?? [];
        $lmsAvailable = !empty($discovered['discovery']['lms_cli_available']);
        $cfg = $this->ai->resolvedConfig();

        return view('admin.ai', compact(
            'server',
            'status',
            'activeProfile',
            'allServers',
            'models',
            'changes',
            'hardware',
            'ggufOnDisk',
            'lmsAvailable',
            'cfg'
        ));
    }

    public function configure(Request $request)
    {
        $validated = $request->validate([
            'driver' => 'required|in:mock,ollama,openai_compatible,external',
            'base_url' => 'nullable|url|max:255',
            'model' => 'nullable|string|max:200',
            'embedding_model' => 'nullable|string|max:200',
            'timeout' => 'nullable|integer|min:10|max:900',
            'auto_adapt_timeout' => 'nullable|boolean',
            'api_key' => 'nullable|string|max:500',
            'clear_api_key' => 'nullable|boolean',
        ]);

        try {
            $result = $this->ai->configureRuntime($validated, Auth::user(), $this->audit);
            $server = $result['server'];
            $sync = $result['sync'];
            $runtimeAction = $result['runtime_action'] ?? null;
            $profile = $result['profile'] ?? null;

            $msg = 'Konfigurasi AI Server diperbarui: DRIVER ' . strtoupper($server->driver)
                . ($server->base_url ? " ({$server->base_url})" : '')
                . ($this->ai->activeModelName() ? " • Model aktif: {$this->ai->activeModelName()}" : '')
                . ($profile ? " • Profil: {$profile['capability_type']}" : '') . '.';

            if (!empty($runtimeAction['warning'])) {
                return back()->with('warning', $msg . ' ' . $runtimeAction['warning']);
            }

            if ($server->status !== 'ONLINE' && !$sync['ok'] && $server->driver !== 'mock') {
                return back()->with('warning', $msg . ' Catatan: server belum dapat dihubungi saat ini (' . ($sync['error'] ?? $server->last_error ?? 'offline') . '). Pastikan Ollama/LM Studio sudah berjalan lalu klik Uji Koneksi.');
            }

            if (!empty($runtimeAction['message'])) {
                $msg .= ' ' . $runtimeAction['message'];
            }

            return back()->with('success', $msg . ($sync['count'] > 0 ? " {$sync['count']} model disinkronkan dari server." : ''));
        } catch (AiUnavailableException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function syncModels()
    {
        $server = $this->ai->syncServerRecord();
        $sync = $this->ai->syncModelsFromServer($server);
        $this->ai->discoverAndRegisterAllLocalNodes(Auth::user());

        $this->audit->log('AI_MODELS_SYNCED', $server, [
            'driver' => $server->driver,
            'base_url' => $server->base_url,
            'ok' => $sync['ok'],
            'count' => $sync['count'],
        ], Auth::id(), $sync['ok'] ? 'SUCCESS' : 'FAILED');

        if (!$sync['ok']) {
            return back()->with('warning', 'Katalog AI lokal diperbarui, tetapi server aktif saat ini sedang offline: ' . ($sync['error'] ?? 'Server offline'));
        }

        return back()->with('success', "Berhasil menyinkronkan {$sync['count']} model nyata dari {$server->driver} ({$server->base_url}).");
    }

    public function detectLocal()
    {
        $res = $this->ai->discoverAndRegisterAllLocalNodes(Auth::user());

        return response()->json([
            'ok' => true,
            'telemetry' => $res['telemetry'],
            'discovery' => $res['discovery'],
            'servers' => $res['servers'],
        ]);
    }

    public function startLocalServer(Request $request)
    {
        $port = (int) ($request->input('port', 1234));
        $res = $this->telemetry->ensureLmStudioServerRunning($port);

        $this->ai->discoverAndRegisterAllLocalNodes(Auth::user());

        if (!$res['ok']) {
            return back()->with('error', 'Gagal menyalakan server LM Studio lokal: ' . ($res['error'] ?? 'Tidak tersedia.'));
        }

        return back()->with('success', $res['already_running']
            ? "Server LM Studio lokal di port {$port} sudah berjalan ONLINE."
            : "Server LM Studio lokal di port {$port} berhasil dinyalakan (ONLINE) dan katalog model telah diperbarui.");
    }

    public function testConnection()
    {
        try {
            $server = $this->ai->syncServerRecord();
            $res = $this->ai->driver()->validateConnection();
            $telemetry = $this->telemetry->snapshot(true);

            return response()->json($res + [
                'server_status' => $server->status,
                'telemetry' => $telemetry,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Tes koneksi gagal: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()],
            ]);
        }
    }

    public function switchModel(Request $request)
    {
        $validated = $request->validate([
            'model_id' => 'required|exists:ai_models,id',
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $model = AiModel::with('server')->findOrFail($validated['model_id']);

        try {
            $res = $this->ai->activateModel($model, Auth::user(), $this->audit, $validated['reason']);

            if (!empty($res['warning'])) {
                return back()->with('warning', $res['message'] . ' Perhatian: ' . $res['warning']);
            }

            return back()->with('success', $res['message']);
        } catch (AiUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function configureCloud(Request $request)
    {
        $validated = $request->validate([
            'engine_mode' => 'required|in:HYBRID_AUTO,FORCE_LOCAL,FORCE_CLOUD',
            'cloud_provider' => 'required|in:google_ai_studio,exa_ai',
            'cloud_timeout' => 'required|integer|min:3|max:60',
            'google_model' => 'nullable|string|max:120',
            'google_api_key' => 'nullable|string|max:500',
            'clear_google_key' => 'nullable|boolean',
            'exa_model' => 'nullable|string|max:120',
            'exa_api_key' => 'nullable|string|max:500',
            'clear_exa_key' => 'nullable|boolean',
            'exa_search_enabled' => 'nullable|boolean',
        ]);

        $cfg = $this->ai->configureCloudRuntime($validated, Auth::user(), $this->audit);
        $providerLabel = $cfg['cloud_provider'] === 'exa_ai' ? 'Exa.ai Neural API' : 'Google AI Studio (Gemini)';

        return back()->with(
            'success',
            "Konfigurasi Hybrid Cloud AI diperbarui: Mode {$cfg['engine_mode']} • Primary Cloud: {$providerLabel} • Failover Timeout: {$cfg['cloud_timeout']}s -> Auto-Fallback ke AI Lokal."
        );
    }

    public function testCloud()
    {
        try {
            return response()->json($this->ai->testCloudConnection());
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Pengujian Cloud gagal: ' . $e->getMessage(),
            ]);
        }
    }
}
