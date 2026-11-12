@extends('layouts.app')
@php
  $isId = app()->getLocale() === 'id';
  $isMock = ($status['is_simulation'] ?? true) || strtolower($status['driver'] ?? 'mock') === 'mock';
@endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Manajemen AI Lokal' : 'Local AI Management'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
  <div>
    <h1 class="font-mono text-sm font-bold uppercase tracking-wider">
      {{ $isId ? 'Manajemen Server AI Lokal & Registri Model' : 'Local AI Server Management & Model Registry' }}
    </h1>
    <p class="text-xs text-[var(--text-muted)] mt-0.5">
      {{ $isId ? 'Konfigurasi driver AI, pengujian koneksi, dan audit perpindahan model aktif.' : 'AI driver diagnostics, connection testing, and audited model switching.' }}
    </p>
  </div>
  <div class="flex items-center gap-2">
    <a href="{{ route('admin.ai.index') }}" class="btn-secondary"><i class="fa-solid fa-rotate"></i><span>Refresh</span></a>
    <button type="button" id="test-connection-btn" class="btn-primary">
      <i class="fa-solid fa-plug-circle-check"></i><span>{{ $isId ? 'Uji Koneksi' : 'Test Connection' }}</span>
    </button>
  </div>
</div>

@if($isMock)
  <div class="p-3.5 rounded border border-[#B47818]/60 bg-[#B47818]/10 text-xs space-y-1">
    <div class="font-mono font-bold uppercase text-[#8A580B] dark:text-[#E7C37B]">
      {{ $isId ? 'MODE SIMULASI AKTIF (MockDriver)' : 'SIMULATION MODE ACTIVE (MockDriver)' }}
    </div>
    <p class="text-[var(--text-secondary)]">
      {{ $isId
        ? 'Tidak menampilkan metrik GPU/VRAM palsu saat MockDriver aktif. Konfigurasikan AI_DRIVER di lingkungan deployment untuk mengaktifkan runtime lokal nyata.'
        : 'No fake GPU/VRAM telemetry is displayed while MockDriver is active. Configure AI_DRIVER in deployment settings for live local inference.' }}
    </p>
  </div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 font-mono">
  <div class="panel p-4 space-y-1">
    <div class="label-meta">DRIVER STATUS</div>
    <div>
      @if($isMock)
        <span class="badge badge-warn">SIMULATION (MOCK)</span>
      @elseif(($status['status'] ?? '') === 'ONLINE')
        <span class="badge badge-olive">ONLINE</span>
      @else
        <span class="badge badge-danger">OFFLINE</span>
      @endif
    </div>
    <div class="text-[11px] text-[var(--text-muted)] truncate">{{ $server->base_url ?? 'internal://mock-driver' }}</div>
  </div>

  <div class="panel p-4 space-y-1">
    <div class="label-meta">RUNTIME & LATENCY</div>
    <div class="text-sm font-bold">{{ strtoupper($status['driver'] ?? 'MOCK') }} &middot; v{{ $server->runtime_version ?? '1.0' }}</div>
    <div class="text-[11px] text-[var(--text-muted)]" id="ping-latency-val">
      {{ $status['latency_ms'] ? $status['latency_ms'] . ' ms' : ($isMock ? 'SIMULATION (0 ms)' : 'OFFLINE') }}
    </div>
  </div>

  <div class="panel p-4 space-y-1">
    <div class="label-meta">ACTIVE MODEL</div>
    <div class="text-sm font-bold truncate">{{ $status['active_model'] ?? ($models->firstWhere('is_active', true)?->name ?? 'NOT CONFIGURED') }}</div>
    <div class="text-[11px] text-[var(--text-muted)]">{{ $isId ? 'Model-agnostic' : 'Runtime configurable' }}</div>
  </div>

  <div class="panel p-4 space-y-1">
    <div class="label-meta">HARDWARE TELEMETRY</div>
    <div class="text-xs font-bold">{{ $isMock ? 'NOT CONFIGURED' : 'AVAILABLE' }}</div>
    <div class="text-[11px] text-[var(--text-muted)]" id="test-connection-msg">{{ $isId ? 'Siap diuji' : 'Ready for test' }}</div>
  </div>
</div>

<div class="panel overflow-hidden">
  <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Model Terdaftar' : 'Registered AI Models' }}</span></div>
  <div class="overflow-x-auto">
    <table class="table-mil">
      <thead><tr><th>MODEL NAME</th><th>PARAMS</th><th>QUANTIZATION</th><th>CONTEXT</th><th>STATUS</th><th>{{ $isId ? 'ALIHKAN MODEL (DENGAN ALASAN)' : 'SWITCH MODEL (AUDITED)' }}</th></tr></thead>
      <tbody>
        @forelse($models as $m)
          <tr>
            <td class="font-mono text-xs font-bold">{{ $m->name }}</td>
            <td class="font-mono text-xs">{{ $m->parameters ?? '-' }}</td>
            <td class="font-mono text-xs">{{ $m->quantization ?? '-' }}</td>
            <td class="font-mono text-xs">{{ $m->context_length ?? '-' }}</td>
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
                <span class="text-xs font-mono text-[var(--text-muted)]">-</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-xs text-[var(--text-muted)] py-5">-</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

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

@push('scripts')
<script>
document.getElementById('test-connection-btn')?.addEventListener('click', async () => {
  const msgEl = document.getElementById('test-connection-msg');
  const latEl = document.getElementById('ping-latency-val');
  msgEl.textContent = 'Testing...';
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
    if (data.latency_ms !== undefined && latEl) {
      latEl.textContent = data.latency_ms + ' ms';
    }
  } catch (e) {
    msgEl.textContent = 'Connection check failed';
  }
});
</script>
@endpush
@endsection
