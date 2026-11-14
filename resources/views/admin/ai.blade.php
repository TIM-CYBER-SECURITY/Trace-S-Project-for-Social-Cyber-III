@extends('layouts.app')
@php
  $isId = app()->getLocale() === 'id';
  $isMock = ($status['is_simulation'] ?? true) || strtolower($status['driver'] ?? 'mock') === 'mock';
  $isOnline = ($status['status'] ?? '') === 'ONLINE';
  $loadedList = $status['loaded_models'] ?? [];
  $lmsServer = $allServers->first(fn ($s) => $s->driver === 'openai_compatible' && str_contains((string) $s->base_url, '127.0.0.1:1234'));
  $lmsOffline = $lmsAvailable && (!$lmsServer || $lmsServer->status !== 'ONLINE');
  $gpus = $hardware['gpus'] ?? [];
  $aiProcRam = $hardware['ai_process_ram_gb'] ?? null;
  $aiProcBreakdown = $hardware['ai_process_breakdown'] ?? [];
@endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Server AI Lokal & Telemetri' : 'Local AI Server & Telemetry'))

@section('content')
<div class="space-y-5">

  <!-- Header -->
  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
    <div>
      <div class="label-meta">LOCAL AI ENGINE &amp; HOST TELEMETRY</div>
      <h1 class="font-mono text-sm font-bold uppercase tracking-wider mt-0.5">
        {{ $isId ? 'Server AI Lokal, Deteksi Model Host & Spesifikasi Perangkat' : 'Local AI Server, Host Model Discovery & Hardware Telemetry' }}
      </h1>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      @if($lmsOffline)
        <form method="POST" action="{{ route('admin.ai.start-local') }}" class="inline">
          @csrf
          <input type="hidden" name="port" value="1234">
          <button type="submit" class="btn-secondary">
            <i class="fa-solid fa-power-off"></i><span>{{ $isId ? 'Nyalakan LM Studio' : 'Start LM Studio' }}</span>
          </button>
        </form>
      @endif
      <button type="button" id="detect-local-btn" class="btn-secondary">
        <i class="fa-solid fa-radar"></i><span>{{ $isId ? 'Pindai AI Host' : 'Scan Host AI' }}</span>
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

  <!-- Real-Time Host Hardware Specifications & Active AI Memory Allocation -->
  <div class="panel overflow-hidden">
    <div class="panel-header">
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full {{ $isOnline ? 'bg-[#586B47]' : 'bg-[#B47818]' }}"></span>
        <span class="font-mono text-xs font-bold uppercase tracking-wider">
          {{ $isId ? 'Spesifikasi Real-Time Komputer Host & Alokasi Memori AI' : 'Real-Time Host Specifications & Active AI Memory Allocation' }}
        </span>
      </div>
      <span class="font-mono text-[11px] text-[var(--text-muted)]">HOST: {{ $hardware['hostname'] ?? 'localhost' }} ({{ $hardware['os'] ?? PHP_OS_FAMILY }})</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-[var(--border-color)] font-mono">
      <!-- 1. CPU -->
      <div class="p-4 space-y-2">
        <div class="flex items-center justify-between">
          <span class="label-meta">CPU PROCESSOR</span>
          @if(isset($hardware['cpu_load_pct']) && $hardware['cpu_load_pct'] !== null)
            <span class="badge badge-neutral">LOAD {{ $hardware['cpu_load_pct'] }}%</span>
          @endif
        </div>
        <div class="text-xs font-bold leading-snug">
          {{ $hardware['cpu_name'] ?? 'UNAVAILABLE' }}
        </div>
        <div class="text-[11px] text-[var(--text-muted)] space-y-0.5 tabular-nums">
          <div>
            Cores / Threads:
            <b class="text-[var(--text-primary)]">{{ $hardware['cpu_cores'] ?? '?' }}C / {{ $hardware['cpu_threads'] ?? ($hardware['cpu_cores'] ?? '?') }}T</b>
          </div>
          @if(!empty($hardware['cpu_clock_mhz']))
            <div>Base Clock: <b class="text-[var(--text-primary)]">{{ $hardware['cpu_clock_mhz'] }} MHz</b></div>
          @endif
        </div>
      </div>

      <!-- 2. GPU & iGPU -->
      <div class="p-4 space-y-2">
        <div class="flex items-center justify-between">
          <span class="label-meta">GPU &amp; iGPU ADAPTERS</span>
          <span class="badge badge-neutral">{{ count($gpus) }} DETECTED</span>
        </div>
        @if(count($gpus) > 0)
          <div class="space-y-1.5">
            @foreach($gpus as $gpu)
              <div class="p-2 border border-[var(--border-color)] bg-[var(--bg-subtle)] text-[11px] space-y-0.5">
                <div class="flex items-center justify-between gap-1">
                  <span class="font-bold truncate" title="{{ $gpu['name'] }}">{{ $gpu['name'] }}</span>
                  <span class="badge {{ ($gpu['role'] ?? 'iGPU') === 'dGPU' ? 'badge-olive' : 'badge-sand' }} shrink-0">{{ $gpu['role'] ?? 'GPU' }}</span>
                </div>
                <div class="text-[10px] text-[var(--text-muted)] flex flex-wrap items-center gap-2 tabular-nums">
                  <span>VRAM: <b>{{ $gpu['vram_gb'] ?? '-' }} GB</b></span>
                  @if(isset($gpu['vram_used_gb']) && $gpu['vram_used_gb'] !== null)
                    <span>Used: <b>{{ $gpu['vram_used_gb'] }} GB</b></span>
                  @endif
                  @if(!empty($gpu['driver_version']))
                    <span>Drv: {{ $gpu['driver_version'] }}</span>
                  @endif
                </div>
              </div>
            @endforeach
          </div>
        @else
          <div class="text-xs font-bold">{{ $hardware['gpu_name'] ?? 'UNAVAILABLE' }}</div>
        @endif
      </div>

      <!-- 3. RAM Model & Capacity -->
      <div class="p-4 space-y-2">
        <div class="flex items-center justify-between">
          <span class="label-meta">PHYSICAL RAM</span>
          @if($hardware['ram_used_pct'] !== null)
            <span class="badge {{ $hardware['ram_used_pct'] > 85 ? 'badge-danger' : 'badge-olive' }}">{{ $hardware['ram_used_pct'] }}% USED</span>
          @endif
        </div>
        <div class="text-xs font-bold leading-snug">
          {{ $hardware['ram_model'] ?? ($hardware['ram_total_gb'] ? $hardware['ram_total_gb'] . ' GB System Memory' : 'UNAVAILABLE') }}
        </div>
        @if($hardware['ram_total_gb'] !== null)
          <div class="space-y-1 text-[11px] text-[var(--text-muted)] tabular-nums">
            <div class="flex justify-between">
              <span>Used / Total:</span>
              <b class="text-[var(--text-primary)]">{{ $hardware['ram_used_gb'] }} / {{ $hardware['ram_total_gb'] }} GB</b>
            </div>
            <div class="flex justify-between">
              <span>Available Free:</span>
              <b class="text-[var(--accent-olive)] dark:text-[#C5B89C]">{{ $hardware['ram_free_gb'] }} GB</b>
            </div>
            <div class="w-full h-1.5 bg-[var(--bg-subtle)] border border-[var(--border-color)] overflow-hidden">
              <div class="h-full {{ $hardware['ram_used_pct'] > 85 ? 'bg-[#991B1B]' : 'bg-[#586B47]' }}" style="width: {{ min(100, (float) $hardware['ram_used_pct']) }}%"></div>
            </div>
          </div>
        @endif
      </div>

      <!-- 4. Active Local AI & RAM Used -->
      <div class="p-4 space-y-2">
        <div class="flex items-center justify-between">
          <span class="label-meta">ACTIVE LOCAL AI &amp; RAM</span>
          @if($isMock)
            <span class="badge badge-warn">MOCK</span>
          @elseif($isOnline)
            <span class="badge badge-olive">ACTIVE</span>
          @else
            <span class="badge badge-danger">{{ $status['status'] ?? 'OFFLINE' }}</span>
          @endif
        </div>
        <div class="text-xs font-bold break-all">
          {{ $status['active_model'] ?? ($models->firstWhere('is_active', true)?->name ?? 'NOT CONFIGURED') }}
        </div>
        <div class="text-[11px] text-[var(--text-muted)] space-y-1 tabular-nums">
          <div class="flex justify-between">
            <span>{{ $isId ? 'RAM Dipakai AI:' : 'AI RAM Usage:' }}</span>
            <b class="text-[var(--text-primary)]">
              @if($aiProcRam !== null && $aiProcRam > 0)
                {{ number_format((float) $aiProcRam, 2) }} GB (Live)
              @elseif(!empty($activeProfile['estimated_ram_gb']))
                ~{{ $activeProfile['estimated_ram_gb'] }} GB (Est.)
              @else
                0.00 GB
              @endif
            </b>
          </div>
          <div class="flex justify-between">
            <span>Latency / TMO:</span>
            <b id="ping-latency-val" class="text-[var(--text-primary)]">
              {{ $isOnline && $status['latency_ms'] !== null ? $status['latency_ms'] . ' ms' : 'N/A' }} / {{ $cfg['timeout'] ?? 180 }}s
            </b>
          </div>
          @if(count($aiProcBreakdown) > 0)
            <div class="text-[10px] text-[var(--accent-olive)] dark:text-[#C5B89C] truncate" title="{{ $hardware['ai_active_process'] ?? '' }}">
              {{ $hardware['ai_active_process'] }}
            </div>
          @endif
          <div class="text-[10px] text-[var(--text-muted)] truncate" id="test-connection-msg">
            {{ $status['runtime'] ?? strtoupper($status['driver'] ?? 'MOCK') }} &bull; {{ $status['base_url'] ?? 'internal' }}
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Detected Host AI Engines -->
  @if($allServers->count() > 0)
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    @foreach($allServers as $srv)
      <div class="panel p-3 flex items-center justify-between gap-2 {{ $srv->is_active ? 'border-[#586B47]' : '' }}">
        <div class="min-w-0">
          <div class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full {{ $srv->status === 'ONLINE' ? 'bg-[#586B47]' : 'bg-[#991B1B]' }}"></span>
            <span class="font-mono text-xs font-bold truncate">{{ $srv->name }}</span>
          </div>
          <div class="font-mono text-[10px] text-[var(--text-muted)] truncate mt-0.5 tabular-nums">
            {{ strtoupper($srv->driver) }} &bull; {{ $srv->models_count }} {{ $isId ? 'model terpasang' : 'installed' }}
            @if($srv->latency_ms !== null) &bull; {{ $srv->latency_ms }} ms @endif
          </div>
        </div>
        <span class="badge {{ $srv->is_active ? 'badge-olive' : ($srv->status === 'ONLINE' ? 'badge-olive' : 'badge-neutral') }}">
          {{ $srv->is_active ? 'ACTIVE NODE' : $srv->status }}
        </span>
      </div>
    @endforeach
  </div>
  @endif

  <!-- Owned Local AI Model Catalog (Strictly Host-Installed Only) -->
  <div class="panel overflow-hidden">
    <div class="panel-header flex-wrap gap-2">
      <div>
        <span class="font-mono text-xs font-bold uppercase tracking-wider">
          {{ $isId ? 'Model AI Lokal Terpasang di Komputer Host (LM Studio / Bionic / Ollama)' : 'Host-Installed Local AI Models (LM Studio / Bionic / Ollama)' }}
        </span>
      </div>
      <span class="badge badge-olive">{{ $models->count() }} {{ $isId ? 'MODEL DIMILIKI' : 'OWNED MODELS' }}</span>
    </div>

    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead>
          <tr>
            <th>ENGINE</th>
            <th>MODEL ID</th>
            <th>CAPABILITY</th>
            <th>SIZE &amp; RAM EST.</th>
            <th>STATUS</th>
            <th>{{ $isId ? 'AKTIFKAN MODEL' : 'ACTIVATE MODEL' }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($models as $m)
            @php
              $srv = $m->server;
              $srvOnline = $srv && $srv->status === 'ONLINE';
              $isCurrentChat = $m->is_active && $srv && $srv->is_active;
              $isCurrentEmbed = !empty($cfg['embedding_model']) && $cfg['embedding_model'] === $m->name;
              $cap = $m->capability_type ?: ($m->profile ?: 'GENERAL');
              $ramTotalHost = $hardware['ram_total_gb'] ?? null;
              $ramFreeHost = $hardware['ram_free_gb'] ?? null;
              $estRam = $m->estimated_ram_gb;
              $fitBadge = 'badge-neutral';
              $fitText = $estRam ? "~{$estRam} GB RAM" : '-';
              if ($estRam !== null && $ramTotalHost !== null) {
                if ($estRam > ($ramTotalHost * 0.85)) {
                  $fitBadge = 'badge-danger';
                  $fitText = "Melebihi RAM (~{$estRam} GB)";
                } elseif ($ramFreeHost !== null && $estRam <= $ramFreeHost) {
                  $fitBadge = 'badge-olive';
                  $fitText = "Fit (~{$estRam} GB)";
                } else {
                  $fitBadge = 'badge-warn';
                  $fitText = "Ketat (~{$estRam} GB)";
                }
              }
            @endphp
            <tr class="{{ $isCurrentChat ? 'bg-[#586B47]/5' : '' }}">
              <td class="font-mono text-[11px] whitespace-nowrap">
                <div class="font-bold">{{ $srv?->runtime ?: strtoupper($srv?->driver ?? '-') }}</div>
                <div class="text-[10px] text-[var(--text-muted)]">{{ $srv?->base_url ?? '-' }}</div>
              </td>
              <td class="min-w-[200px]">
                <div class="font-mono text-xs font-bold break-all">{{ $m->name }}</div>
                <div class="text-[10px] text-[var(--text-muted)]">
                  {{ $m->display_name ?? $m->name }} @if($m->family) &bull; {{ strtoupper($m->family) }} @endif
                </div>
              </td>
              <td>
                <div class="flex flex-wrap gap-1">
                  <span class="badge {{ $m->is_reasoning ? 'badge-olive' : ($m->is_vision ? 'badge-warn' : 'badge-neutral') }}">{{ $cap }}</span>
                  @if($m->is_reasoning)<span class="badge badge-olive">&lt;think&gt;</span>@endif
                  @if($m->is_vision)<span class="badge badge-warn">VISION</span>@endif
                  @if($m->recommended_timeout)
                    <span class="badge badge-neutral">{{ $m->recommended_timeout }}s</span>
                  @endif
                </div>
              </td>
              <td class="font-mono text-[11px] whitespace-nowrap tabular-nums">
                <div>{{ $m->parameter_size ?? '-' }} @if($m->quantization) &middot; {{ $m->quantization }} @endif</div>
                <div class="text-[10px] text-[var(--text-muted)]">
                  @if($m->size) {{ $m->size }} @endif
                  @if($m->context_length) &bull; {{ number_format($m->context_length) }} ctx @endif
                </div>
                <span class="badge {{ $fitBadge }} text-[9px] mt-0.5">{{ $fitText }}</span>
              </td>
              <td class="font-mono text-[11px] whitespace-nowrap">
                @if($isCurrentChat)
                  <span class="badge badge-olive">ACTIVE LLM</span>
                @elseif($isCurrentEmbed)
                  <span class="badge badge-olive">ACTIVE EMBED</span>
                @else
                  <span class="badge {{ $m->status === 'LOADED' ? 'badge-olive' : 'badge-neutral' }}">{{ $m->status }}</span>
                @endif
              </td>
              <td class="min-w-[210px]">
                @if($isCurrentChat)
                  <span class="text-xs font-mono font-semibold text-[var(--accent-olive)] dark:text-[#C5B89C]">
                    <i class="fa-solid fa-check mr-1"></i>{{ $isId ? 'AKTIF DIPAKAI' : 'ACTIVE' }}
                  </span>
                @else
                  <form method="POST" action="{{ route('admin.ai.switch-model') }}" class="flex items-center gap-1.5">
                    @csrf
                    <input type="hidden" name="model_id" value="{{ $m->id }}">
                    <input type="hidden" name="reason" value="Aktivasi model lokal host: {{ $m->name }}">
                    <button type="submit" class="btn-primary py-1 px-3 text-[10px] whitespace-nowrap">
                      <i class="fa-solid fa-bolt"></i>
                      <span>{{ $m->is_embedding ? ($isId ? 'Setel Embedding' : 'Set Embed') : ($isId ? 'Aktifkan Model' : 'Activate Model') }}</span>
                    </button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-xs text-[var(--text-muted)] py-6 font-mono">
                {{ $isId ? 'Tidak ada model AI lokal terdeteksi di komputer ini. Jalankan LM Studio / Bionic / Ollama lalu klik "Pindai AI Host".' : 'No local AI models detected on this host.' }}
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @include('partials.cloud-ai-panel', [
    'formAction' => route('admin.ai.configure-cloud'),
    'testUrl' => route('admin.ai.test-cloud'),
    'compactCollapsible' => false,
  ])

  <!-- Endpoint Configuration (Manual Host / Intranet Only) + Live Port Probe -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
    <div class="lg:col-span-7 panel p-4 space-y-3">
      <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-2">
        <span class="font-mono text-xs font-bold uppercase tracking-wider">
          {{ $isId ? 'Konfigurasi Endpoint Server AI Lokal' : 'Local AI Server Endpoint Configuration' }}
        </span>
        <span class="badge badge-neutral">LOCALHOST / INTRANET</span>
      </div>

      <form method="POST" action="{{ route('admin.ai.configure') }}" id="ai-config-form" class="space-y-3 text-xs">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label for="cfg-driver" class="label-meta block mb-1">Driver</label>
            <select id="cfg-driver" name="driver" class="input-mil font-mono text-xs">
              @php $curDrv = old('driver', $cfg['driver'] ?? 'ollama'); @endphp
              <option value="ollama" {{ $curDrv === 'ollama' ? 'selected' : '' }}>ollama (Ollama Local)</option>
              <option value="openai_compatible" {{ $curDrv === 'openai_compatible' ? 'selected' : '' }}>openai_compatible (LM Studio / OpenAI API Local)</option>
            </select>
          </div>
          <div>
            <label for="cfg-timeout" class="label-meta block mb-1">{{ $isId ? 'Timeout (detik)' : 'Timeout (sec)' }}</label>
            <input id="cfg-timeout" type="number" name="timeout" min="10" max="900"
                   value="{{ old('timeout', $cfg['timeout'] ?? 180) }}" class="input-mil font-mono text-xs">
          </div>
        </div>

        <div>
          <label for="cfg-base-url" class="label-meta block mb-1">Base URL</label>
          <input id="cfg-base-url" type="url" name="base_url"
                 value="{{ old('base_url', $cfg['base_url'] ?? 'http://127.0.0.1:1234') }}"
                 placeholder="http://127.0.0.1:1234"
                 class="input-mil font-mono text-xs">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label for="cfg-model" class="label-meta block mb-1">{{ $isId ? 'Model Aktif (Dari Host)' : 'Active Model (From Host)' }}</label>
            <input id="cfg-model" type="text" name="model"
                   value="{{ old('model', $status['active_model'] ?? $cfg['model'] ?? '') }}"
                   class="input-mil font-mono text-xs">
          </div>
          <div>
            <label for="cfg-embedding" class="label-meta block mb-1">{{ $isId ? 'Model Embedding' : 'Embedding Model' }}</label>
            <input id="cfg-embedding" type="text" name="embedding_model"
                   value="{{ old('embedding_model', $cfg['embedding_model'] ?? '') }}"
                   class="input-mil font-mono text-xs">
          </div>
        </div>

        <div class="flex justify-end pt-2 border-t border-[var(--border-color)]">
          <button type="submit" class="btn-primary">
            <i class="fa-solid fa-floppy-disk"></i>
            <span>{{ $isId ? 'Simpan Konfigurasi' : 'Save Configuration' }}</span>
          </button>
        </div>
      </form>
    </div>

    <div class="lg:col-span-5 panel p-4 space-y-3">
      <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-2">
        <span class="font-mono text-xs font-bold uppercase tracking-wider">
          {{ $isId ? 'Pemindaian Port Engine Lokal' : 'Local Engine Port Scan' }}
        </span>
        <span class="badge badge-olive">{{ count($ggufOnDisk) }} GGUF</span>
      </div>
      <div id="local-discovery-results" class="text-xs space-y-2">
        <div class="panel-subtle p-3 text-[11px] font-mono text-[var(--text-muted)]">
          {{ $isId
            ? 'Klik "Pindai AI Host" untuk memeriksa port Ollama (:11434), LM Studio (:1234), Bionic (:8001/:8080) pada komputer ini.'
            : 'Click "Scan Host AI" to probe Ollama (:11434), LM Studio (:1234), and Bionic (:8001/:8080) on this machine.' }}
        </div>
      </div>
    </div>
  </div>

  <!-- Switch History -->
  <div class="panel overflow-hidden">
    <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Audit Trail Pergantian Model AI' : 'AI Model Switch Audit Log' }}</span></div>
    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead><tr><th>TIME</th><th>PREVIOUS</th><th>NEW MODEL &amp; SERVER</th><th>ACTOR</th><th>REASON</th></tr></thead>
        <tbody>
          @forelse($changes as $c)
            <tr>
              <td class="font-mono text-xs text-[var(--text-muted)]">{{ $c->created_at?->format('Y-m-d H:i') }}</td>
              <td class="font-mono text-xs">{{ $c->previous_model ?? '-' }}</td>
              <td class="font-mono text-xs font-bold">
                {{ $c->model?->name ?? '-' }}
                @if($c->model?->server)
                  <span class="font-normal text-[var(--text-muted)]">({{ $c->model->server->base_url ?? $c->model->server->driver }})</span>
                @endif
              </td>
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
  box.innerHTML = '<div class="panel-subtle p-2.5 font-mono text-[11px]">Memindai Ollama (:11434), LM Studio (:1234), Bionic (:8001/:8080)...</div>';
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
        modelsHtml = '<div class="flex flex-wrap gap-1 pt-1 font-mono text-[10px]">' + ep.models.map((m) =>
          `<span class="badge badge-olive">${escapeHtmlAi(m.id)}</span>`
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
