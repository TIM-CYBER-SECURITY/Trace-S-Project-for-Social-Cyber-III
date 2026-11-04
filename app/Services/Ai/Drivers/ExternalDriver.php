<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Exceptions\AiUnavailableException;

/**
 * ExternalDriver (opsional): penyedia cloud yang kompatibel OpenAI API.
 * NONAKTIF secara default; hanya aktif bila AI_ALLOW_EXTERNAL=true diatur
 * administrator. Data klasifikasi/terbatas tidak boleh dikirim tanpa persetujuan
 * dan kebijakan institusi. TIDAK ADA fallback otomatis dari lokal ke cloud.
 */
class ExternalDriver extends OpenAICompatibleDriver
{
    public function name(): string { return 'external'; }

    protected function http(?int $timeout = null): \Illuminate\Http\Client\PendingRequest
    {
        if (!config('trace.ai.allow_external')) {
            throw new AiUnavailableException('ExternalDriver nonaktif (AI_ALLOW_EXTERNAL=false). Aktivasi hanya oleh administrator berwenang.', 'ERROR');
        }
        $key = config('trace.ai.external_api_key');
        if (!$key) {
            throw new AiUnavailableException('AI_EXTERNAL_API_KEY belum diisi (NOT CONFIGURED).', 'OFFLINE');
        }
        if (!$this->baseUrl) {
            throw new AiUnavailableException('AI_BASE_URL belum dikonfigurasi (NOT CONFIGURED).', 'OFFLINE');
        }
        return \Illuminate\Support\Facades\Http::timeout($timeout ?? $this->timeout)->acceptJson()->withToken($key);
    }
}
