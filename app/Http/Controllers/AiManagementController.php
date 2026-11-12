<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\AiModelChange;
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
        $status = $this->ai->status();

        // Jika server live ONLINE dan daftar model DB masih kosong, sinkronkan otomatis
        if (
            !$status['is_simulation']
            && ($status['status'] ?? '') === 'ONLINE'
            && AiModel::where('server_id', $server->id)->where('is_simulation', false)->count() === 0
        ) {
            $this->ai->syncModelsFromServer($server);
        }

        $models = AiModel::where('server_id', $server->id)->orderByDesc('is_active')->orderBy('name')->get();
        $changes = AiModelChange::with(['model', 'admin'])->latest()->take(10)->get();
        $hardware = $this->telemetry->snapshot();
        $ggufOnDisk = $this->telemetry->scanLmStudioModelsDir();
        $cfg = $this->ai->resolvedConfig();

        return view('admin.ai', compact('server', 'status', 'models', 'changes', 'hardware', 'ggufOnDisk', 'cfg'));
    }

    public function configure(Request $request)
    {
        $validated = $request->validate([
            'driver' => 'required|in:mock,ollama,openai_compatible,external',
            'base_url' => 'nullable|url|max:255',
            'model' => 'nullable|string|max:200',
            'embedding_model' => 'nullable|string|max:200',
            'timeout' => 'nullable|integer|min:10|max:900',
            'api_key' => 'nullable|string|max:500',
            'clear_api_key' => 'nullable|boolean',
        ]);

        try {
            $result = $this->ai->configureRuntime($validated, Auth::user(), $this->audit);
            $server = $result['server'];
            $sync = $result['sync'];

            $msg = "Konfigurasi AI Server diperbarui: DRIVER " . strtoupper($server->driver)
                . ($server->base_url ? " ({$server->base_url})" : '')
                . ($this->ai->activeModelName() ? " • Model aktif: {$this->ai->activeModelName()}" : '') . '.';

            if (!$sync['ok'] && $server->driver !== 'mock') {
                return back()->with('warning', $msg . ' Catatan: server belum dapat dihubungi saat ini (' . ($sync['error'] ?? 'offline') . '). Pastikan Ollama/LM Studio sudah berjalan lalu klik Uji Koneksi.');
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

        $this->audit->log('AI_MODELS_SYNCED', $server, [
            'driver' => $server->driver,
            'ok' => $sync['ok'],
            'count' => $sync['count'],
        ], Auth::id(), $sync['ok'] ? 'SUCCESS' : 'FAILED');

        if (!$sync['ok']) {
            return back()->with('error', 'Gagal menyinkronkan model dari AI Server: ' . ($sync['error'] ?? 'Server offline'));
        }

        return back()->with('success', "Berhasil menyinkronkan {$sync['count']} model nyata dari {$server->driver} ({$server->base_url}).");
    }

    public function detectLocal()
    {
        $telemetry = $this->telemetry->snapshot(true);
        $discovery = $this->telemetry->detectLocalEngines();

        return response()->json([
            'ok' => true,
            'telemetry' => $telemetry,
            'discovery' => $discovery,
        ]);
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
        $prevActive = AiModel::where('server_id', $model->server_id)->where('is_active', true)->first();

        AiModel::where('server_id', $model->server_id)->update(['is_active' => false]);
        $model->update(['is_active' => true]);

        // Terapkan langsung sebagai model aktif pada pengaturan runtime
        if ($model->server && $model->server->driver !== 'mock') {
            $this->settings->setInternal('ai.model', $model->name, Auth::user());
            $this->ai->resetDriver();
        }

        AiModelChange::create([
            'model_id' => $model->id,
            'previous_model' => $prevActive?->name,
            'changed_by' => Auth::id(),
            'reason' => $validated['reason'],
        ]);

        $this->audit->log('AI_MODEL_SWITCHED', $model, [
            'previous' => $prevActive?->name,
            'new' => $model->name,
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', "Model AI aktif berhasil dialihkan ke: {$model->name}. Alasan pergantian tercatat di audit log.");
    }
}
