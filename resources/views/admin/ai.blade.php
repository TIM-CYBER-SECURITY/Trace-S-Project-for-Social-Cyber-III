@extends('layouts.app')
@php
  $isId = app()->getLocale() === 'id';
  $isMock = ($status['is_simulation'] ?? true) || strtolower($status['driver'] ?? 'mock') === 'mock';
  $isOnline = ($status['status'] ?? '') === 'ONLINE';
  $loadedList = $status['loaded_models'] ?? [];
@endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Manajemen AI Lokal' : 'Local AI Management'))

@section('content')
<div class="space-y-5">

  <!-- Header -->
  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
    <div>
      <h1 class="font-mono text-sm font-bold uppercase tracking-wider">
        {{ $isId ? 'Manajemen Server AI Lokal, Intranet & Telemetri Perangkat' : 'Local & Intranet AI Server Management & Hardware Telemetry' }}
      </h1>
      <p class="text-xs text-[var(--text-muted)] mt-0.5">
        {{ $isId
          ? 'Hubungkan Ollama, LM Studio, atau node workstation LAN PUSSIBERAD secara langsung tanpa mengedit kode.'
          : 'Connect Ollama, LM Studio, or PUSSIBERAD LAN workstation inference nodes dynamically.' }}
      </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <button type="button" id="detect-local-btn" class="btn-secondary">
        <i class="fa-solid fa-radar"></i><span>{{ $isId ? 'Deteksi AI Lokal' : 'Detect Local AI' }}</span>
      </button>
      <form method="POST" action="{{ route('admin.ai.sync') }}" class="inline">
        @csrf
        <button type="submit" class="btn-secondary">
          <i class="fa-solid fa-rotate"></i><span>{{ $isId ? 'Sinkron Model' : 'Sync Models' }}</span>
        </button>
      </form>
      <button type="button" id="test-connection-btn" class="btn-primary">
        <i class="fa-solid fa-plug-circle-check"></i><span>{{ $isId ? 'Uji Koneksi' : 'Test Connection' }}</span>
      </button>
    </div>
  </div>

  <!-- Status Banner -->
  @if($isMock)
    <div class="p-3.5 rounded border border-[#B47818]/60 bg-[#B47818]/10 text-xs space-y-1">
      <div class="font-mono font-bold uppercase text-[#8A580B] dark:text-[#E7C37B]">
        {{ $isId ? 'MODE SIMULASI AKTIF (MockDriver)' : 'SIMULATION MODE ACTIVE (MockDriver)' }}
      </div>
      <p class="text-[var(--text-secondary)]">
        {{ $isId
          ? 'Trace-S saat ini masih memakai MockDriver. Gunakan formulir Konfigurasi Server AI di bawah untuk beralih ke Ollama atau LM Studio nyata pada komputer ini / PUSSIBERAD.'
          : 'Trace-S is currently using MockDriver. Use the AI Server Configuration form below to connect Ollama or LM Studio on this workstation / PUSSIBERAD.' }}
      </p>
    </div>
  @elseif($isOnline)
    <div class="p-3.5 rounded border border-[#586B47]/60 bg-[#586B47]/10 text-xs flex flex-wrap items-center justify-between gap-2">
      <div class="space-y-0.5">
        <div class="font-mono font-bold uppercase text-[#3E4D31] dark:text-[#D5E2C8]">
          {{ $isId ? 'ENGINE AI NYATA TERHUBUNG (NON-SIMULASI)' : 'LIVE AI INFERENCE ENGINE CONNECTED' }}
        </div>
        <div class="text-[var(--text-secondary)]">
          {{ $status['runtime'] ?? strtoupper($status['driver']) }}
          @if(!empty($status['runtime_version'])) v{{ $status['runtime_version'] }} @endif
          &bull; Endpoint: <span class="font-mono">{{ $status['base_url'] ?? $server->base_url }}</span>
          &bull; Model: <span class="font-mono font-semibold">{{ $status['active_model'] ?? 'Belum dipilih' }}</span>
        </div>
      </div>
      <span class="badge badge-olive">LIVE INFERENCE</span>
    </div>
  @else
    <div class="p-3.5 rounded border border-[#991B1B]/60 bg-[#991B1B]/10 text-xs space-y-1">
      <div class="font-mono font-bold uppercase text-[#991B1B] dark:text-[#FCA5A5]">
        {{ $isId ? 'AI SERVER TIDAK TERJANGKAU / OFFLINE' : 'AI SERVER UNREACHABLE / OFFLINE' }}
      </div>
      <p class="text-[var(--text-secondary)]">
        {{ $status['error'] ?? ($isId ? 'Pastikan layanan Ollama atau LM Studio Server sedang aktif pada endpoint yang dikonfigurasi.' : 'Ensure Ollama or LM Studio server is running on configured endpoint.') }}
      </p>
    </div>
  @endif

  <!-- 4 Status & Real Hardware Telemetry Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 font-mono">
    <div class="panel p-4 space-y-1.5">
      <div class="label-meta">DRIVER STATUS</div>
      <div>
        @if($isMock)
          <span class="badge badge-warn">SIMULATION (MOCK)</span>
        @elseif($isOnline)
          <span class="badge badge-olive">ONLINE</span>
        @else
          <span class="badge badge-danger">{{ $status['status'] ?? 'OFFLINE' }}</span>
        @endif
      </div>
      <div class="text-[11px] text-[var(--text-muted)] truncate">{{ $status['base_url'] ?? 'internal://mock-driver' }}</div>
    </div>

    <div class="panel p-4 space-y-1.5">
      <div class="label-meta">RUNTIME &amp; LATENCY</div>
      <div class="text-sm font-bold truncate">
        {{ strtoupper($status['driver'] ?? 'MOCK') }}
        @if(!empty($status['runtime_version'])) &middot; v{{ $status['runtime_version'] }} @endif
      </div>
      <div class="text-[11px] text-[var(--text-muted)]" id="ping-latency-val">
        @if($isOnline && $status['latency_ms'] !== null)
          {{ $status['latency_ms'] }} ms
          @if(!empty($loadedList))
            &bull; {{ count($loadedList) }} loaded
          @endif
        @elseif($isMock)
          SIMULATION (0 ms)
        @else
          OFFLINE
        @endif
      </div>
    </div>

    <div class="panel p-4 space-y-1.5">
      <div class="label-meta">ACTIVE MODEL</div>
      <div class="text-xs font-bold break-all">
        {{ $status['active_model'] ?? ($models->firstWhere('is_active', true)?->name ?? 'NOT CONFIGURED') }}
      </div>
      <div class="text-[11px] text-[var(--text-muted)]">
        Timeout: {{ $cfg['timeout'] ?? 180 }}s &bull; Max Retry: {{ $cfg['max_retries'] ?? 2 }}
      </div>
    </div>

    <div class="panel p-4 space-y-1.5">
      <div class="label-meta">REAL HOST HARDWARE</div>
      <div class="text-[11px] font-bold truncate" title="{{ $hardware['cpu_name'] ?? '' }}">
        {{ $hardware['cpu_name'] ? Str::limit($hardware['cpu_name'], 30) : 'UNAVAILABLE' }}
        @if(!empty($hardware['cpu_cores'])) ({{ $hardware['cpu_cores'] }}T) @endif
      </div>
      <div class="text-[11px] text-[var(--text-muted)]" id="hardware-ram-gpu">
        @if($hardware['ram_total_gb'] !== null)
          RAM: {{ $hardware['ram_used_gb'] }}/{{ $hardware['ram_total_gb'] }} GB
        @else
          RAM: UNAVAILABLE
        @endif
        <br>
        GPU: {{ $hardware['gpu_name'] ?? 'UNAVAILABLE' }}
        @if(!empty($hardware['gpu_vram_total_gb']))
          ({{ $hardware['gpu_vram_used_gb'] ?? '?' }}/{{ $hardware['gpu_vram_total_gb'] }} GB VRAM)
        @elseif(($hardware['gpu_type'] ?? '') === 'INTEGRATED_SHARED')
          (Shared RAM)
        @endif
      </div>
      <div class="text-[10px] text-[var(--accent-olive)] dark:text-[#C5B89C] truncate" id="test-connection-msg">
        {{ $isOnline ? ($isId ? 'Siap inferensi nyata' : 'Ready for live inference') : ($isId ? 'Menunggu pengujian' : 'Ready for test') }}
      </div>
    </div>
  </div>

  <!-- Server Configuration + Live Discovery Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <!-- Configure Runtime AI Server -->
    <div class="lg:col-span-7 panel p-4 space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border-color)] pb-2.5">
        <span class="font-mono text-xs font-bold uppercase tracking-wider">
          {{ $isId ? 'Konfigurasi Endpoint & Model AI Server' : 'AI Server Endpoint & Model Configuration' }}
        </span>
        <span class="badge badge-neutral">LOCAL / INTRANET GUARD</span>
      </div>

      <!-- Quick Presets -->
      <div class="space-y-1.5">
        <div class="label-meta">{{ $isId ? 'Preset Cepat Perangkat:' : 'Quick Hardware Presets:' }}</div>
        <div class="flex flex-wrap gap-1.5 font-mono text-[11px]">
          <button type="button" onclick="applyAiPreset('ollama', 'http://127.0.0.1:11434', 'qwen2.5-coder:7b', 180)"
                  class="btn-secondary py-1 px-2.5 text-[11px]">
            <i class="fa-solid fa-terminal"></i> Ollama Lokal (:11434)
          </button>
          <button type="button" onclick="applyAiPreset('openai_compatible', 'http://127.0.0.1:1234', 'qwen3-14b-claude-4.5-opus-high-reasoning-distill', 240)"
                  class="btn-secondary py-1 px-2.5 text-[11px]">
            <i class="fa-solid fa-laptop-code"></i> LM Studio (:1234 • Qwen3-14B)
          </button>
          <button type="button" onclick="applyAiPreset('openai_compatible', 'http://192.168.1.100:1234', 'qwen3-27b-claude-5.5-opus-max-reasoning', 300)"
                  class="btn-secondary py-1 px-2.5 text-[11px]">
            <i class="fa-solid fa-server"></i> LAN PUSSIBERAD (Qwen3-27B Max)
          </button>
          <button type="button" onclick="applyAiPreset('mock', '', '', 30)"
                  class="btn-secondary py-1 px-2.5 text-[11px]">
            Mock (Simulasi)
          </button>
        </div>
      </div>

      <form method="POST" action="{{ route('admin.ai.configure') }}" id="ai-config-form" class="space-y-3 text-xs">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label for="cfg-driver" class="label-meta block mb-1">Driver Protocol</label>
            <select id="cfg-driver" name="driver" class="input-mil font-mono text-xs">
              @php $curDrv = old('driver', $cfg['driver'] ?? 'mock'); @endphp
              <option value="ollama" {{ $curDrv === 'ollama' ? 'selected' : '' }}>ollama (Ollama API)</option>
              <option value="openai_compatible" {{ $curDrv === 'openai_compatible' ? 'selected' : '' }}>openai_compatible (LM Studio / vLLM / llama.cpp)</option>
              <option value="mock" {{ $curDrv === 'mock' ? 'selected' : '' }}>mock (Simulation Testing Only)</option>
              @if(!empty($cfg['allow_external']))
                <option value="external" {{ $curDrv === 'external' ? 'selected' : '' }}>external (Cloud API - Restricted)</option>
              @endif
            </select>
          </div>

          <div>
            <label for="cfg-timeout" class="label-meta block mb-1">{{ $isId ? 'Timeout Inferensi (detik)' : 'Inference Timeout (sec)' }}</label>
            <input id="cfg-timeout" type="number" name="timeout" min="10" max="900"
                   value="{{ old('timeout', $cfg['timeout'] ?? 180) }}" class="input-mil font-mono text-xs">
          </div>
        </div>

        <div>
          <label for="cfg-base-url" class="label-meta block mb-1">Base URL Endpoint (Localhost / IP Intranet PUSSIBERAD)</label>
          <input id="cfg-base-url" type="url" name="base_url"
                 value="{{ old('base_url', $cfg['base_url'] ?? 'http://127.0.0.1:11434') }}"
                 placeholder="http://127.0.0.1:1234 atau http://192.168.x.x:1234"
                 class="input-mil font-mono text-xs">
          <p class="text-[11px] text-[var(--text-muted)] mt-1">
            {{ $isId
              ? 'LocalGuard aktif: hanya menerima loopback (127.0.0.1 / localhost) atau IP privat intranet (10.x / 172.16-31.x / 192.168.x).'
              : 'LocalGuard enforced: accepts localhost and RFC1918 intranet endpoints only.' }}
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label for="cfg-model" class="label-meta block mb-1">{{ $isId ? 'Nama / ID Model Aktif' : 'Active Model ID' }}</label>
            <input id="cfg-model" type="text" name="model"
                   value="{{ old('model', $status['active_model'] ?? $cfg['model'] ?? '') }}"
                   placeholder="qwen3-14b-claude-4.5-opus-high-reasoning-distill"
                   class="input-mil font-mono text-xs">
          </div>

          <div>
            <label for="cfg-embedding" class="label-meta block mb-1">{{ $isId ? 'Model Embedding (Opsional)' : 'Embedding Model (Optional)' }}</label>
            <input id="cfg-embedding" type="text" name="embedding_model"
                   value="{{ old('embedding_model', $cfg['embedding_model'] ?? '') }}"
                   placeholder="text-embedding-nomic-embed-text-v1.5"
                   class="input-mil font-mono text-xs">
          </div>
        </div>

        <div>
          <label for="cfg-api-key" class="label-meta block mb-1">{{ $isId ? 'Bearer API Key Opsional (Jika Node Intranet Memakai Token)' : 'Optional Bearer API Key' }}</label>
          <input id="cfg-api-key" type="password" name="api_key" autocomplete="new-password"
                 placeholder="{{ !empty($cfg['api_key']) ? '•••••••• (tersimpan terenkripsi, kosongkan untuk pertahankan)' : 'Kosongkan jika localhost/intranet tanpa token' }}"
                 class="input-mil font-mono text-xs">
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-[var(--border-color)]">
          <span class="text-[11px] text-[var(--text-muted)]">
            {{ $isId ? 'Perubahan langsung tersimpan & diaudit.' : 'Changes apply immediately & are audited.' }}
          </span>
          <button type="submit" class="btn-primary">
            <i class="fa-solid fa-floppy-disk"></i>
            <span>{{ $isId ? 'Simpan & Hubungkan AI Server' : 'Save & Connect AI Server' }}</span>
          </button>
        </div>
      </form>
    </div>

    <!-- Real Hardware & Local Discovery + PUSSIBERAD Setup Guide -->
    <div class="lg:col-span-5 space-y-4">
      <div class="panel p-4 space-y-3">
        <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-2">
          <span class="font-mono text-xs font-bold uppercase tracking-wider">
            {{ $isId ? 'Penemuan Engine & GGUF Lokal' : 'Local Engine & GGUF Discovery' }}
          </span>
          <span class="badge badge-olive">{{ count($ggufOnDisk) }} GGUF</span>
        </div>

        <div id="local-discovery-results" class="text-xs space-y-2">
          <div class="panel-subtle p-2.5 text-[11px] text-[var(--text-muted)]">
            {{ $isId
              ? 'Klik "Deteksi AI Lokal" di kanan atas untuk memindai port Ollama (:11434), LM Studio (:1234), dan llama.cpp (:8080) secara real-time.'
              : 'Click "Detect Local AI" above to probe Ollama (:11434), LM Studio (:1234), and llama.cpp (:8080) live.' }}
          </div>
        </div>

        @if(count($ggufOnDisk) > 0)
          <div class="space-y-1.5">
            <div class="label-meta">{{ $isId ? 'Model LM Studio Terdeteksi di Disk:' : 'LM Studio Models Detected on Disk:' }}</div>
            <div class="max-h-48 overflow-y-auto border border-[var(--border-color)] rounded divide-y divide-[var(--border-color)]">
              @foreach($ggufOnDisk as $g)
                <div class="p-2 flex items-center justify-between gap-2 text-[11px]">
                  <div class="min-w-0">
                    <div class="font-mono font-semibold truncate" title="{{ $g['filename'] }}">{{ $g['suggested_key'] }}</div>
                    <div class="text-[10px] text-[var(--text-muted)]">{{ $g['publisher'] }} &bull; {{ $g['size_gb'] }} GB</div>
                  </div>
                  @if(str_contains(strtolower($g['suggested_key']), 'embed'))
                    <button type="button" onclick="fillEmbeddingModel('{{ $g['suggested_key'] }}')"
                            class="btn-secondary py-0.5 px-2 text-[10px] shrink-0">Embedding</button>
                  @else
                    <button type="button" onclick="applyAiPreset('openai_compatible', 'http://127.0.0.1:1234', '{{ $g['suggested_key'] }}', 240)"
                            class="btn-secondary py-0.5 px-2 text-[10px] shrink-0">{{ $isId ? 'Pakai' : 'Use' }}</button>
                  @endif
                </div>
              @endforeach
            </div>
          </div>
        @endif
      </div>

      <div class="panel-subtle p-4 space-y-2 text-xs">
        <div class="font-mono text-[11px] font-bold uppercase text-[var(--accent-olive)] dark:text-[#C5B89C]">
          {{ $isId ? 'Panduan Koneksi Komputer Lokal & PUSSIBERAD' : 'Workstation & PUSSIBERAD LAN Setup Guide' }}
        </div>
        <ol class="list-decimal pl-4 space-y-1 text-[11px] text-[var(--text-secondary)]">
          <li>
            <b>Laptop Lokal (LM Studio — Qwen3-14B):</b> Jalankan server lokal di terminal/LM Studio:
            <code class="px-1 bg-[var(--bg-surface)] rounded">lms server start --port 1234</code> lalu load model <code class="px-1 bg-[var(--bg-surface)] rounded">qwen3-14b-claude-4.5-opus-high-reasoning-distill</code>.
          </li>
          <li>
            <b>Workstation PUSSIBERAD (Qwen3-27B Max Reasoning):</b> Aktifkan Local Server di LM Studio/vLLM/Ollama dengan bind LAN (<code class="px-1 bg-[var(--bg-surface)] rounded">--host 0.0.0.0</code> / Serve on Local Network) di IP internal kantor, lalu masukkan <code class="px-1 bg-[var(--bg-surface)] rounded">http://&lt;IP-Workstation&gt;:1234</code> &amp; Timeout <code class="px-1 bg-[var(--bg-surface)] rounded">240–300</code>s.
          </li>
          <li>
            <b>Catatan Memori Host:</b> Host saat ini memiliki total RAM <b>{{ $hardware['ram_total_gb'] ?? '?' }} GB</b>
            @if(($hardware['ram_free_gb'] ?? null) !== null) (tersedia <b>{{ $hardware['ram_free_gb'] }} GB</b>) @endif.
            Untuk 14B (~6.7 GB GGUF), tutup aplikasi berat terlebih dahulu sebelum load di LM Studio; untuk 7B (~4.7 GB) di Ollama dapat berjalan ringan.
          </li>
        </ol>
      </div>
    </div>

  </div>

  <!-- Registered AI Models -->
  <div class="panel overflow-hidden">
    <div class="panel-header">
      <span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Model Terdaftar pada Driver Aktif' : 'Registered AI Models (Active Server)' }}</span>
      <span class="badge badge-neutral">{{ strtoupper($server->driver) }}</span>
    </div>
    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead><tr><th>MODEL NAME</th><th>PROFILE / SIZE</th><th>QUANTIZATION</th><th>CONTEXT</th><th>STATUS</th><th>{{ $isId ? 'ALIHKAN MODEL (DENGAN ALASAN)' : 'SWITCH MODEL (AUDITED)' }}</th></tr></thead>
        <tbody>
          @forelse($models as $m)
            <tr>
              <td class="font-mono text-xs font-bold">{{ $m->name }}</td>
              <td class="font-mono text-xs">{{ $m->profile ?? '-' }} @if($m->size) &middot; {{ $m->size }} @endif</td>
              <td class="font-mono text-xs">{{ $m->quantization ?? '-' }}</td>
              <td class="font-mono text-xs">{{ $m->context_length ? number_format($m->context_length) : '-' }}</td>
              <td><span class="badge {{ $m->is_active ? 'badge-olive' : 'badge-neutral' }}">{{ $m->is_active ? 'ACTIVE' : 'STANDBY' }}</span></td>
              <td>
                @if(!$m->is_active)
                  <form method="POST" action="{{ route('admin.ai.switch-model') }}" class="flex items-center gap-2">
                    @csrf
                    <input type="hidden" name="model_id" value="{{ $m->id }}">
                    <input type="text" name="reason" required minlength="5" placeholder="{{ $isId ? 'Alasan perubahan...' : 'Switch reason...' }}" class="input-mil w-44 py-1 text-xs">
                    <button type="submit" class="btn-primary py-1 px-2 text-[10px]">{{ $isId ? 'Aktifkan' : 'Switch' }}</button>
                  </form>
                @else
                  <span class="text-xs font-mono text-[var(--accent-olive)] dark:text-[#C5B89C]">IN USE</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-xs text-[var(--text-muted)] py-5">{{ $isId ? 'Belum ada model tersinkron. Klik Sinkron Model atau hubungkan endpoint AI.' : 'No models synced yet.' }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Switch History -->
  <div class="panel overflow-hidden">
    <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Riwayat Pergantian Model' : 'Model Switch Audit History' }}</span></div>
    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead><tr><th>TIME</th><th>PREVIOUS</th><th>NEW</th><th>ADMIN</th><th>REASON</th></tr></thead>
        <tbody>
          @forelse($changes as $c)
            <tr>
              <td class="font-mono text-xs text-[var(--text-muted)]">{{ $c->created_at?->format('Y-m-d H:i') }}</td>
              <td class="font-mono text-xs">{{ $c->previous_model ?? '-' }}</td>
              <td class="font-mono text-xs font-bold">{{ $c->model?->name ?? '-' }}</td>
              <td class="font-mono text-xs">{{ $c->admin?->trace_id ?? '-' }}</td>
              <td class="text-xs">{{ $c->reason }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-xs text-[var(--text-muted)] py-4">-</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

@push('scripts')
<script>
function applyAiPreset(driver, url, model, timeoutSec) {
  const drv = document.getElementById('cfg-driver');
  const urlEl = document.getElementById('cfg-base-url');
  const modEl = document.getElementById('cfg-model');
  const tmoEl = document.getElementById('cfg-timeout');
  if (drv) drv.value = driver;
  if (urlEl) urlEl.value = url;
  if (modEl) modEl.value = model;
  if (tmoEl && timeoutSec) tmoEl.value = timeoutSec;
  document.getElementById('ai-config-form')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function fillEmbeddingModel(modelKey) {
  const el = document.getElementById('cfg-embedding');
  if (el) el.value = modelKey;
}

function escapeHtmlAi(s) {
  const d = document.createElement('div');
  d.textContent = s == null ? '' : String(s);
  return d.innerHTML;
}

document.getElementById('test-connection-btn')?.addEventListener('click', async () => {
  const msgEl = document.getElementById('test-connection-msg');
  const latEl = document.getElementById('ping-latency-val');
  msgEl.textContent = 'Testing connection...';
  try {
    const res = await fetch('{{ route("admin.ai.test") }}', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      }
    });
    const data = await res.json();
    msgEl.textContent = data.message || (data.ok ? 'OK' : 'FAILED');
    if (data.latency_ms !== undefined && data.latency_ms !== null && latEl) {
      latEl.textContent = data.latency_ms + ' ms';
    }
  } catch (e) {
    msgEl.textContent = 'Connection check failed';
  }
});

document.getElementById('detect-local-btn')?.addEventListener('click', async () => {
  const box = document.getElementById('local-discovery-results');
  if (!box) return;
  box.innerHTML = '<div class="panel-subtle p-2.5 font-mono text-[11px]">Memindai Ollama (:11434), LM Studio (:1234), llama.cpp (:8080)...</div>';
  try {
    const res = await fetch('{{ route("admin.ai.detect") }}', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      }
    });
    const data = await res.json();
    const eps = data.discovery?.endpoints || [];
    box.replaceChildren();
    eps.forEach((ep) => {
      const card = document.createElement('div');
      card.className = 'panel-subtle p-2.5 space-y-1.5';
      const statusBadge = ep.online
        ? `<span class="badge badge-olive">ONLINE (${ep.latency_ms} ms)</span>`
        : `<span class="badge badge-neutral">OFFLINE</span>`;
      let modelsHtml = '';
      if (ep.online && Array.isArray(ep.models) && ep.models.length) {
        modelsHtml = '<div class="flex flex-wrap gap-1 pt-1">' + ep.models.map((m) =>
          `<button type="button" onclick="applyAiPreset('${escapeHtmlAi(ep.driver)}', '${escapeHtmlAi(ep.base_url)}', '${escapeHtmlAi(m.id)}', 180)" class="btn-secondary py-0.5 px-2 text-[10px]">Pakai: ${escapeHtmlAi(m.id)}${m.params ? ' (' + escapeHtmlAi(m.params) + ')' : ''}</button>`
        ).join('') + '</div>';
      } else if (!ep.online) {
        modelsHtml = `<div class="text-[10px] text-[var(--text-muted)]">${escapeHtmlAi(ep.error || 'Tidak berjalan')}</div>`;
      }
      card.innerHTML = `
        <div class="flex items-center justify-between gap-2 font-mono text-[11px]">
          <span><b>${escapeHtmlAi(ep.engine)}</b> &middot; ${escapeHtmlAi(ep.base_url)}</span>
          ${statusBadge}
        </div>
        ${modelsHtml}
      `;
      box.appendChild(card);
    });
  } catch (e) {
    box.innerHTML = '<div class="panel-subtle p-2.5 text-[11px] text-[#991B1B]">Gagal memindai port lokal.</div>';
  }
});
</script>
@endpush
@endsection
