<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\AiModelChange;
use App\Models\AiServer;
use App\Services\Ai\AiManager;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiManagementController extends Controller
{
    public function __construct(
        private AiManager $ai,
        private AuditLogger $audit
    ) {
    }

    public function index()
    {
        $server = $this->ai->syncServerRecord();
        $status = $this->ai->status();

        $models = AiModel::where('server_id', $server->id)->get();
        $changes = AiModelChange::with(['model', 'admin'])->latest()->take(10)->get();

        return view('admin.ai', compact('server', 'status', 'models', 'changes'));
    }

    public function testConnection()
    {
        try {
            $driver = $this->ai->driver();
            $res = $driver->validateConnection();

            return response()->json($res);
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
