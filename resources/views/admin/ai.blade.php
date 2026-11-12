@extends('layouts.app')
@php
  $isId = app()->getLocale() === 'id';
  $isMock = ($status['is_simulation'] ?? true) || strtolower($status['driver'] ?? 'mock') === 'mock';
  $isOnline = ($status['status'] ?? '') === 'ONLINE';
  $loadedList = $status['loaded_models'] ?? [];
  $lmsServer = $allServers->first(fn ($s) => $s->driver === 'openai_compatible' && str_contains((string) $s->base_url, '127.0.0.1:1234'));
  $lmsOffline = $lmsAvailable && (!$lmsServer || $lmsServer->status !== 'ONLINE');
@endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Manajemen AI Lokal' : 'Local AI Management'))

@section('content')
<div class="space-y-5">

  <!-- Header -->
  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
    <div>
      <h1 class="font-mono text-sm font-bold uppercase tracking-wider">
        {{ $isId ? 'Manajemen Multi-AI Lokal, Intranet PUSSIBERAD & Telemetri Perangkat' : 'Multi-AI Local & Intranet Server Management & Hardware Telemetry' }}
      </h1>
      <p class="text-xs text-[var(--text-muted)] mt-0.5">
        {{ $isId
          ? 'Deteksi otomatis berbagai model AI lokal (Ollama, LM Studio, llama.cpp, PUSSIBERAD LAN) beserta perbedaan profil, RAM, timeout, dan pergantian otomatis.'
          : 'Auto-detects multiple local AI models with distinct capability profiles, RAM fit checks, auto-tuned parameters, and seamless model switching.' }}
      </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      @if($lmsOffline)
        <form method="POST" action="{{ route('admin.ai.start-local') }}" class="inline">
          @csrf
          <input type="hidden" name="port" value="1234">
          <button type="submit" class="btn-secondary border-[#586B47] text-[#3E4D31] dark:text-[#D5E2C8]">
            <i class="fa-solid fa-power-off"></i><span>{{ $isId ? 'Nyalakan Server LM Studio' : 'Start LM Studio Server' }}</span>
          </button>
        </form>
      @endif
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
          ? 'Trace-S saat ini memakai MockDriver. Pilih salah satu model AI lokal di tabel di bawah atau gunakan formulir Konfigurasi Server AI untuk beralih ke AI nyata.'
          : 'Trace-S is using MockDriver. Select any detected local AI model below or configure an endpoint.' }}
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
          &bull; Profil: <span class="badge badge-olive">{{ $activeProfile['capability_type'] ?? 'GENERAL' }}</span>
        </div>
      </div>
      <span class="badge badge-olive">LIVE INFERENCE</span>
    </div>
  @else
    <div class="p-3.5 rounded border border-[#991B1B]/60 bg-[#991B1B]/10 text-xs flex flex-wrap items-center justify-between gap-3">
      <div class="space-y-1 min-w-0">
        <div class="font-mono font-bold uppercase text-[#991B1B] dark:text-[#FCA5A5]">
          {{ $isId ? 'SERVER AI AKTIF SEDANG OFFLINE / BELUM MENYALA' : 'ACTIVE AI SERVER OFFLINE' }}
        </div>
        <p class="text-[var(--text-secondary)]">
          {{ $status['error'] ?? ($isId ? 'Layanan AI pada endpoint aktif belum merespons.' : 'Active AI endpoint unreachable.') }}
          @if($lmsOffline)
            <span class="font-semibold">{{ $isId ? 'Klik "Nyalakan Server LM Studio" di kanan atas atau pilih langsung model dari tabel Multi-AI di bawah — sistem akan otomatis menyalakan server & memuat model.' : '' }}</span>
          @endif
        </p>
      </div>
      @if($lmsOffline)
        <form method="POST" action="{{ route('admin.ai.start-local') }}" class="shrink-0">
          @csrf
          <input type="hidden" name="port" value="1234">
          <button type="submit" class="btn-primary text-xs">
            <i class="fa-solid fa-play"></i><span>{{ $isId ? 'Nyalakan LM Studio (:1234)' : 'Start LM Studio' }}</span>
          </button>
        </form>
      @endif
    </div>
  @endif

  <!-- 4 Status, Active Model Profile & Real Hardware Telemetry Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 font-mono">
    <div class="panel p-4 space-y-1.5">
      <div class="label-meta">ACTIVE SERVER STATUS</div>
      <div class="flex items-center gap-2">
        @if($isMock)
          <span class="badge badge-warn">SIMULATION (MOCK)</span>
        @elseif($isOnline)
          <span class="badge badge-olive">ONLINE</span>
        @else
          <span class="badge badge-danger">{{ $status['status'] ?? 'OFFLINE' }}</span>
        @endif
        <span class="text-[11px] font-bold">{{ strtoupper($status['driver'] ?? 'MOCK') }}</span>
      </div>
      <div class="text-[11px] text-[var(--text-muted)] truncate">{{ $status['base_url'] ?? 'internal://mock-driver' }}</div>
      <div class="text-[11px] text-[var(--text-muted)]" id="ping-latency-val">
        @if($isOnline && $status['latency_ms'] !== null)
          Latency: {{ $status['latency_ms'] }} ms @if(!empty($loadedList)) &bull; {{ count($loadedList) }} loaded @endif
        @elseif($isMock)
          SIMULATION (0 ms)
        @else
          OFFLINE
        @endif
      </div>
    </div>

    <div class="panel p-4 space-y-1.5">
      <div class="label-meta">ACTIVE MODEL &amp; PROFILE</div>
      <div class="text-xs font-bold break-all">
        {{ $status['active_model'] ?? ($models->firstWhere('is_active', true)?->name ?? 'NOT CONFIGURED') }}
      </div>
      <div class="flex flex-wrap items-center gap-1">
        <span class="badge {{ !empty($activeProfile['is_reasoning']) ? 'badge-olive' : 'badge-neutral' }}">
          {{ $activeProfile['capability_type'] ?? 'GENERAL' }}
        </span>
        @if(!empty($activeProfile['parameter_size']))
          <span class="badge badge-neutral">{{ $activeProfile['parameter_size'] }}</span>
        @endif
        @if(!empty($activeProfile['is_reasoning']))
          <span class="badge badge-olive">&lt;think&gt; ON</span>
        @endif
      </div>
    </div>

    <div class="panel p-4 space-y-1.5">
      <div class="label-meta">AUTO-ADAPTED RUNTIME</div>
      <div class="text-xs font-bold">
        Timeout: {{ $cfg['timeout'] ?? 180 }}s &bull; Temp: {{ $activeProfile['recommended_temperature'] ?? 0.2 }}
      </div>
      <div class="text-[11px] text-[var(--text-muted)] truncate" title="{{ $cfg['embedding_model'] ?? '' }}">
        Embedding: {{ !empty($cfg['embedding_model']) ? $cfg['embedding_model'] : 'Belum disetel' }}
      </div>
      <div class="text-[10px] text-[var(--accent-olive)] dark:text-[#C5B89C] line-clamp-2">
        {{ $activeProfile['notes'] ?? '-' }}
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
          RAM: {{ $hardware['ram_used_gb'] }}/{{ $hardware['ram_total_gb'] }} GB (Bebas {{ $hardware['ram_free_gb'] }} GB)
        @else
          RAM: UNAVAILABLE
        @endif
        <br>
        GPU: {{ $hardware['gpu_name'] ?? 'UNAVAILABLE' }}
      </div>
      <div class="text-[10px] text-[var(--accent-olive)] dark:text-[#C5B89C] truncate" id="test-connection-msg">
        {{ $isOnline ? ($isId ? 'Siap inferensi nyata' : 'Ready for live inference') : ($isId ? 'Menunggu pengujian / aktifkan model' : 'Ready for test') }}
      </div>
    </div>
  </div>

  <!-- Registered Multi-Node AI Server Strip -->
  @if($allServers->count() > 0)
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    @foreach($allServers as $srv)
      <div class="panel p-3 flex items-center justify-between gap-2 {{ $srv->is_active ? 'border-[#586B47]' : '' }}">
        <div class="min-w-0">
          <div class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full {{ $srv->status === 'ONLINE' ? 'bg-[#586B47]' : 'bg-[#991B1B]' }}"></span>
            <span class="font-mono text-xs font-bold truncate">{{ $srv->name }}</span>
          </div>
          <div class="font-mono text-[10px] text-[var(--text-muted)] truncate mt-0.5">
            {{ strtoupper($srv->driver) }} &bull; {{ $srv->models_count }} model
            @if($srv->latency_ms !== null) &bull; {{ $srv->latency_ms }} ms @endif
          </div>
        </div>
        <div class="shrink-0 flex items-center gap-1">
          @if($srv->is_active)
            <span class="badge badge-olive">ACTIVE NODE</span>
          @else
            <span class="badge {{ $srv->status === 'ONLINE' ? 'badge-olive' : 'badge-neutral' }}">{{ $srv->status }}</span>
          @endif
        </div>
      </div>
    @endforeach
  </div>
  @endif

  <!-- Unified Multi-Model Catalog & 1-Click Adaptive Switcher -->
  <div class="panel overflow-hidden">
    <div class="panel-header flex-wrap gap-2">
      <div>
        <span class="font-mono text-xs font-bold uppercase">
          {{ $isId ? 'Katalog Multi-AI Lokal & Penyesuaian Otomatis Per Model' : 'Multi-AI Local Model Catalog & Per-Model Auto-Adaptation' }}
        </span>
        <div class="text-[11px] text-[var(--text-muted)]">
          {{ $isId
            ? 'Semua model dari Ollama, LM Studio, dan Node LAN ditampilkan beserta perbedaan kemampuan, kebutuhan RAM, dan timeout otomatis.'
            : 'All detected local/network AI models with distinct capability profiles, RAM fit checks, and one-click server+model switching.' }}
        </div>
      </div>
      <span class="badge badge-olive">{{ $models->count() }} {{ $isId ? 'MODEL TERDETEKSI' : 'MODELS' }}</span>
    </div>

    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead>
          <tr>
            <th>ENGINE &amp; SERVER</th>
            <th>MODEL &amp; ARSITEKTUR</th>
            <th>{{ $isId ? 'PERBEDAAN KEMAMPUAN' : 'CAPABILITY PROFILE' }}</th>
            <th>SPEC / RAM FIT</th>
            <th>STATUS</th>
            <th>{{ $isId ? 'AKTIFKAN & SESUAIKAN OTOMATIS' : 'ONE-CLICK ADAPTIVE SWITCH' }}</th>
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
              $capBadge = match ($cap) {
                'REASONING' => 'badge-olive',
                'MULTIMODAL' => 'badge-warn',
                'CODER_JSON' => 'badge-olive',
                'EMBEDDING' => 'badge-neutral',
                default => 'badge-neutral',
              };
              $ramTotalHost = $hardware['ram_total_gb'] ?? null;
              $ramFreeHost = $hardware['ram_free_gb'] ?? null;
              $estRam = $m->estimated_ram_gb;
              $fitBadge = 'badge-neutral';
              $fitText = $estRam ? "~{$estRam} GB" : '-';
              if ($estRam !== null && $ramTotalHost !== null) {
                if ($estRam > ($ramTotalHost * 0.85)) {
                  $fitBadge = 'badge-danger';
                  $fitText = "Workstation (~{$estRam} GB)";
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
                <div class="font-bold">{{ $srv?->driver === 'ollama' ? 'Ollama' : ($srv?->runtime ?: strtoupper($srv?->driver ?? '-')) }}</div>
                <div class="text-[10px] text-[var(--text-muted)]">{{ $srv?->base_url ?? '-' }}</div>
                <span class="badge {{ $srvOnline ? 'badge-olive' : 'badge-danger' }} text-[9px] mt-0.5">{{ $srv?->status ?? 'OFFLINE' }}</span>
              </td>
              <td class="min-w-[190px]">
                <div class="font-mono text-xs font-bold break-all">{{ $m->name }}</div>
                <div class="text-[10px] text-[var(--text-muted)]">
                  {{ $m->display_name ?? $m->name }}
                  @if($m->family) &bull; {{ strtoupper($m->family) }} @endif
                </div>
              </td>
              <td class="max-w-[260px]">
                <div class="flex flex-wrap gap-1 mb-1">
                  <span class="badge {{ $capBadge }}">{{ $cap }}</span>
                  @if($m->is_reasoning)<span class="badge badge-olive">&lt;think&gt;</span>@endif
                  @if($m->is_vision)<span class="badge badge-warn">VISION</span>@endif
                  @if($m->recommended_timeout)
                    <span class="badge badge-neutral">{{ $m->recommended_timeout }}s</span>
                  @endif
                </div>
                <div class="text-[10px] text-[var(--text-muted)] leading-snug">
                  {{ $m->notes ?? ($m->profile ?? '-') }}
                </div>
              </td>
              <td class="font-mono text-[11px] whitespace-nowrap">
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
              <td class="min-w-[230px]">
                @if($isCurrentChat)
                  <span class="text-xs font-mono font-semibold text-[var(--accent-olive)] dark:text-[#C5B89C]">
                    <i class="fa-solid fa-check mr-1"></i>{{ $isId ? 'SEDANG DIPAKAI' : 'IN USE' }}
                  </span>
                @else
                  <form method="POST" action="{{ route('admin.ai.switch-model') }}" class="flex flex-wrap items-center gap-1.5">
                    @csrf
                    <input type="hidden" name="model_id" value="{{ $m->id }}">
                    <input type="text" name="reason" required minlength="5"
                           value="{{ $m->is_embedding ? 'Penetapan model embedding semantik lokal' : 'Penyesuaian ke model ' . $m->name }}"
                           placeholder="{{ $isId ? 'Alasan pergantian...' : 'Switch reason...' }}"
                           class="input-mil w-40 py-1 text-[11px]">
                    <button type="submit" class="btn-primary py-1 px-2.5 text-[10px] whitespace-nowrap">
                      <i class="fa-solid fa-bolt"></i>
                      <span>{{ $m->is_embedding ? ($isId ? 'Setel Embedding' : 'Set Embed') : ($isId ? 'Aktifkan' : 'Activate') }}</span>
                    </button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-xs text-[var(--text-muted)] py-5">
                {{ $isId ? 'Belum ada model terdeteksi. Klik "Deteksi AI Lokal" atau konfigurasikan server di bawah.' : 'No models detected yet.' }}
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Custom Endpoint Config (PUSSIBERAD LAN / Manual) + Live Probe Output -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <div class="lg:col-span-7 panel p-4 space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border-color)] pb-2.5">
        <span class="font-mono text-xs font-bold uppercase tracking-wider">
          {{ $isId ? 'Tambah / Konfigurasi Endpoint Manual & Workstation PUSSIBERAD' : 'Configure Custom / PUSSIBERAD LAN AI Endpoint' }}
        </span>
        <span class="badge badge-neutral">LOCAL / INTRANET GUARD</span>
      </div>

      <!-- Quick Presets -->
      <div class="space-y-1.5">
        <div class="label-meta">{{ $isId ? 'Preset Cepat Perangkat:' : 'Quick Hardware Presets:' }}</div>
        <div class="flex flex-wrap gap-1.5 font-mono text-[11px]">
          <button type="button" onclick="applyAiPreset('ollama', 'http://127.0.0.1:11434', 'qwen2.5-coder:7b', 150)"
                  class="btn-secondary py-1 px-2.5 text-[11px]">
            <i class="fa-solid fa-terminal"></i> Ollama (:11434 • 7B)
          </button>
          <button type="button" onclick="applyAiPreset('openai_compatible', 'http://127.0.0.1:1234', 'qwen2.5-0.5b-instruct', 60)"
                  class="btn-secondary py-1 px-2.5 text-[11px]">
            <i class="fa-solid fa-feather"></i> LM Studio Ringan (0.5B)
          </button>
          <button type="button" onclick="applyAiPreset('openai_compatible', 'http://127.0.0.1:1234', 'qwen3-14b-claude-4.5-opus-high-reasoning-distill', 240)"
                  class="btn-secondary py-1 px-2.5 text-[11px]">
            <i class="fa-solid fa-laptop-code"></i> LM Studio Qwen3-14B
          </button>
          <button type="button" onclick="applyAiPreset('openai_compatible', 'http://192.168.1.100:1234', 'qwen3-27b-claude-5.5-opus-max-reasoning', 300)"
                  class="btn-secondary py-1 px-2.5 text-[11px]">
            <i class="fa-solid fa-server"></i> LAN PUSSIBERAD (27B Max)
          </button>
        </div>
      </div>

      <form method="POST" action="{{ route('admin.ai.configure') }}" id="ai-config-form" class="space-y-3 text-xs">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label for="cfg-driver" class="label-meta block mb-1">Driver Protocol</label>
            <select id="cfg-driver" name="driver" class="input-mil font-mono text-xs">
              @php $curDrv = old('driver', $cfg['driver'] ?? 'ollama'); @endphp
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
              ? 'LocalGuard aktif: menerima loopback (127.0.0.1 / localhost) & IP privat intranet. Untuk 127.0.0.1:1234, LM Studio dinyalakan otomatis bila CLI lms terpasang.'
              : 'LocalGuard enforced: accepts localhost and RFC1918 intranet endpoints only.' }}
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label for="cfg-model" class="label-meta block mb-1">{{ $isId ? 'Nama / ID Model Aktif' : 'Active Model ID' }}</label>
            <input id="cfg-model" type="text" name="model"
                   value="{{ old('model', $status['active_model'] ?? $cfg['model'] ?? '') }}"
                   placeholder="qwen2.5-0.5b-instruct / qwen3-14b-..."
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
            {{ $isId ? 'Perubahan tersimpan permanen, mengaktifkan node target, dan diaudit.' : 'Changes apply immediately & are audited.' }}
          </span>
          <button type="submit" class="btn-primary">
            <i class="fa-solid fa-floppy-disk"></i>
            <span>{{ $isId ? 'Simpan & Hubungkan AI Server' : 'Save & Connect AI Server' }}</span>
          </button>
        </div>
      </form>
    </div>

    <!-- Live Port Probe + PUSSIBERAD Setup Guide -->
    <div class="lg:col-span-5 space-y-4">
      <div class="panel p-4 space-y-3">
        <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-2">
          <span class="font-mono text-xs font-bold uppercase tracking-wider">
            {{ $isId ? 'Hasil Pemindaian Port AI Real-Time' : 'Live Local AI Port Probe' }}
          </span>
          <span class="badge badge-olive">{{ count($ggufOnDisk) }} LM STUDIO</span>
        </div>

        <div id="local-discovery-results" class="text-xs space-y-2">
          <div class="panel-subtle p-2.5 text-[11px] text-[var(--text-muted)]">
            {{ $isId
              ? 'Klik "Deteksi AI Lokal" di kanan atas untuk memindai port Ollama (:11434), LM Studio (:1234), dan llama.cpp (:8080) secara real-time sekaligus menyegarkan tabel model di atas.'
              : 'Click "Detect Local AI" above to probe Ollama (:11434), LM Studio (:1234), and llama.cpp (:8080) live.' }}
          </div>
        </div>
      </div>

      <div class="panel-subtle p-4 space-y-2 text-xs">
        <div class="font-mono text-[11px] font-bold uppercase text-[var(--accent-olive)] dark:text-[#C5B89C]">
          {{ $isId ? 'Panduan Multi-Model & Workstation PUSSIBERAD' : 'Multi-Model & PUSSIBERAD LAN Setup Guide' }}
        </div>
        <ol class="list-decimal pl-4 space-y-1.5 text-[11px] text-[var(--text-secondary)]">
          <li>
            <b>Laptop Lokal (Multi-Model):</b> Anda bebas berpindah antara <b>Ollama</b> (<code class="px-1 bg-[var(--bg-surface)] rounded">qwen2.5-coder:7b</code>) maupun <b>LM Studio</b> (<code class="px-1 bg-[var(--bg-surface)] rounded">qwen2.5-0.5b-instruct</code>, <code class="px-1 bg-[var(--bg-surface)] rounded">llama-3.2-3b</code>, <code class="px-1 bg-[var(--bg-surface)] rounded">qwen3-vl-8b</code>, <code class="px-1 bg-[var(--bg-surface)] rounded">qwen3-14b-opus</code>) cukup dengan klik <b>Aktifkan</b> pada tabel di atas.
          </li>
          <li>
            <b>Penyesuaian Otomatis Tiap Model:</b> TRACE-S otomatis menyetel timeout (<code class="px-1 bg-[var(--bg-surface)] rounded">60s</code> untuk 0.5B s/d <code class="px-1 bg-[var(--bg-surface)] rounded">240–300s</code> untuk 14B/27B High Reasoning), ekstraksi tag <code class="px-1 bg-[var(--bg-surface)] rounded">&lt;think&gt;</code>, unload model lama, dan menyalakan server LM Studio localhost otomatis.
          </li>
          <li>
            <b>Workstation PUSSIBERAD (Qwen3-27B Max Reasoning):</b> Aktifkan <i>Serve on Local Network</i> (<code class="px-1 bg-[var(--bg-surface)] rounded">--host 0.0.0.0</code>), lalu masukkan IP LAN workstation pada form di samping (contoh <code class="px-1 bg-[var(--bg-surface)] rounded">http://192.168.x.x:1234</code>). Node LAN tersimpan tanpa menghapus daftar model laptop lokal.
          </li>
          <li>
            <b>Memori Host:</b> RAM <b>{{ $hardware['ram_total_gb'] ?? '?' }} GB</b>
            @if(($hardware['ram_free_gb'] ?? null) !== null) (tersedia <b>{{ $hardware['ram_free_gb'] }} GB</b>) @endif.
            Gunakan model 0.5B–7B untuk respons cepat, atau tutup aplikasi berat sebelum memuat <b>Qwen3-14B (6.2 GB)</b>.
          </li>
        </ol>
      </div>
    </div>

  </div>

  <!-- Switch History -->
  <div class="panel overflow-hidden">
    <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Riwayat Pergantian Model & Server (Audit Trail)' : 'Model Switch Audit History' }}</span></div>
    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead><tr><th>TIME</th><th>PREVIOUS MODEL</th><th>NEW MODEL &amp; SERVER</th><th>ADMIN</th><th>REASON</th></tr></thead>
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
      latEl.textContent = 'Latency: ' + data.latency_ms + ' ms';
    }
  } catch (e) {
    msgEl.textContent = 'Connection check failed';
  }
});

document.getElementById('detect-local-btn')?.addEventListener('click', async () => {
  const box = document.getElementById('local-discovery-results');
  if (!box) return;
  box.innerHTML = '<div class="panel-subtle p-2.5 font-mono text-[11px]">Memindai Ollama (:11434), LM Studio (:1234), llama.cpp (:8080) &amp; menyinkronkan profil model...</div>';
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
          `<button type="button" onclick="applyAiPreset('${escapeHtmlAi(ep.driver)}', '${escapeHtmlAi(ep.base_url)}', '${escapeHtmlAi(m.id)}', ${Number(m.recommended_timeout || 180)})" class="btn-secondary py-0.5 px-2 text-[10px]">${escapeHtmlAi(m.id)}${m.capability_type ? ' [' + escapeHtmlAi(m.capability_type) + ']' : ''}</button>`
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
