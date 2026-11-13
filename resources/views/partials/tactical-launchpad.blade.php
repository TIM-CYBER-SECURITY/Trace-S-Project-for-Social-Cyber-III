@php
  $isId = app()->getLocale() === 'id';
  $hw = app(\App\Services\Ai\HardwareTelemetry::class)->snapshot();
  $aiStat = app(\App\Services\Ai\AiManager::class)->status();
  $ownedLaunchModels = \App\Models\AiModel::with('server')
      ->where('is_simulation', false)
      ->where('is_embedding', false)
      ->whereHas('server', fn ($q) => $q->where('driver', '!=', 'mock'))
      ->orderByDesc('is_active')
      ->orderBy('name')
      ->get();
  $canRunScan = Auth::check() && Auth::user()->hasPermission('analysis.run');
  $gpus = $hw['gpus'] ?? [];
  $aiRam = $hw['ai_process_ram_gb'] ?? null;
@endphp

<div class="panel overflow-hidden">
  <!-- Host Hardware & Active AI Telemetry Strip -->
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-[var(--border-color)] border-b border-[var(--border-color)] font-mono text-[11px]">
    <div class="p-3 space-y-0.5">
      <div class="label-meta">HOST CPU</div>
      <div class="font-bold truncate" title="{{ $hw['cpu_name'] ?? '' }}">{{ $hw['cpu_name'] ?? 'UNAVAILABLE' }}</div>
      <div class="text-[10px] text-[var(--text-muted)] tabular-nums">
        {{ $hw['cpu_cores'] ?? '?' }}C / {{ $hw['cpu_threads'] ?? '?' }}T
        @if(!empty($hw['cpu_clock_mhz'])) &bull; {{ $hw['cpu_clock_mhz'] }} MHz @endif
        @if(isset($hw['cpu_load_pct']) && $hw['cpu_load_pct'] !== null) &bull; Load {{ $hw['cpu_load_pct'] }}% @endif
      </div>
    </div>

    <div class="p-3 space-y-0.5">
      <div class="label-meta">GPU &amp; iGPU</div>
      @if(count($gpus) > 0)
        @foreach($gpus as $g)
          <div class="flex items-center justify-between gap-1.5">
            <span class="font-bold truncate" title="{{ $g['name'] }}">{{ $g['name'] }}</span>
            <span class="badge {{ ($g['role'] ?? 'iGPU') === 'dGPU' ? 'badge-olive' : 'badge-sand' }} text-[9px] shrink-0">{{ $g['role'] }}</span>
          </div>
        @endforeach
      @else
        <div class="font-bold">{{ $hw['gpu_name'] ?? 'UNAVAILABLE' }}</div>
      @endif
    </div>

    <div class="p-3 space-y-0.5">
      <div class="label-meta">RAM MODEL &amp; CAPACITY</div>
      <div class="font-bold truncate" title="{{ $hw['ram_model'] ?? '' }}">{{ $hw['ram_model'] ?? ($hw['ram_total_gb'] ? $hw['ram_total_gb'] . ' GB' : 'UNAVAILABLE') }}</div>
      @if($hw['ram_total_gb'] !== null)
        <div class="text-[10px] text-[var(--text-muted)] tabular-nums">
          Used: <b>{{ $hw['ram_used_gb'] }}/{{ $hw['ram_total_gb'] }} GB</b> ({{ $hw['ram_used_pct'] }}%) &bull; Free: {{ $hw['ram_free_gb'] }} GB
        </div>
      @endif
    </div>

    <div class="p-3 space-y-0.5">
      <div class="label-meta">ACTIVE LOCAL AI &amp; AI RAM</div>
      <div class="font-bold truncate" title="{{ $aiStat['active_model'] ?? '' }}">
        {{ $aiStat['active_model'] ?? strtoupper($aiStat['driver'] ?? 'MOCK') }}
      </div>
      <div class="text-[10px] text-[var(--text-muted)] tabular-nums flex items-center justify-between gap-1">
        <span>AI RAM: <b class="text-[var(--text-primary)]">{{ ($aiRam !== null && $aiRam > 0) ? number_format((float) $aiRam, 2) . ' GB' : '0.00 GB' }}</b></span>
        <span class="badge {{ ($aiStat['status'] ?? '') === 'ONLINE' ? 'badge-olive' : 'badge-warn' }} text-[9px]">{{ $aiStat['status'] ?? 'SIMULASI' }}</span>
      </div>
    </div>
  </div>

  <!-- Quick Model Selection & Instant Investigation Form -->
  @if($canRunScan)
  <div class="p-3.5 bg-[var(--bg-subtle)] grid grid-cols-1 lg:grid-cols-12 gap-3 items-center">
    <form action="{{ route('scanner.select-model') }}" method="POST" class="lg:col-span-4 flex items-center gap-2">
      @csrf
      <label class="label-meta shrink-0">{{ $isId ? 'Model AI:' : 'AI Model:' }}</label>
      <select name="model_id" onchange="this.form.submit()" aria-label="Activate host local AI model" class="input-mil font-mono text-[11px] py-1.5">
        @forelse($ownedLaunchModels as $om)
          <option value="{{ $om->id }}" {{ ($aiStat['active_model'] ?? '') === $om->name ? 'selected' : '' }}>
            {{ $om->name }}{{ $om->estimated_ram_gb ? ' (~' . $om->estimated_ram_gb . 'GB)' : '' }}
          </option>
        @empty
          <option value="">{{ strtoupper($aiStat['driver'] ?? 'MOCK') }}</option>
        @endforelse
      </select>
    </form>

    <form action="{{ route('scanner.scan') }}" method="POST" class="lg:col-span-8 flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
      @csrf
      <input type="hidden" name="case_id" value="auto">
      <input type="text" name="text" required minlength="3"
             placeholder="{{ $isId ? 'Masukkan kata kunci, link berita, atau narasi konten untuk langsung diselidiki AI Lokal...' : 'Enter keyword, URL, or news content for instant Local AI investigation...' }}"
             class="input-mil font-mono text-xs py-1.5 flex-1">
      <button type="submit" class="btn-primary whitespace-nowrap py-1.5 px-3.5">
        <i class="fa-solid fa-magnifying-glass-chart"></i>
        <span>{{ $isId ? 'SELIDIKI SEKARANG' : 'INVESTIGATE NOW' }}</span>
      </button>
    </form>
  </div>
  @endif
</div>
