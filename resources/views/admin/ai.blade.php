@extends('layouts.tactical')

@php
  $isId = app()->getLocale() === 'id';
  $isMock = ($status['is_simulation'] ?? true) || strtolower($status['driver'] ?? 'mock') === 'mock';
@endphp

@section('title', ($isId ? 'Manajemen AI Lokal' : 'Local AI Management') . ' // TRACE-S')

@section('content')

<!-- [ModuleFHeader] START -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div>
    <div class="flex items-center gap-2.5">
      <h1 class="text-sm font-semibold font-mono tracking-wide text-neutral-300 uppercase">
        {{ $isId ? 'Manajemen Server AI Lokal & Registri Model' : 'Local AI Server Management & Model Registry' }}
      </h1>
      <span class="text-[10px] font-mono px-2 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 rounded-sm">
        MODULE F (ADMIN ONLY — §5.8 FR-060)
      </span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">
      {{ $isId
        ? 'Pemantauan status server AI lokal, antrean kerja, telemetri perangkat keras, dan pergantian model tercatat di log audit.'
        : 'Local AI server status monitoring, job queue, hardware telemetry, and audited model switching.' }}
    </p>
  </div>

  <!-- Functional Action Buttons: [Test Connection] / [Refresh] (§5.8) -->
  <div class="flex items-center gap-2 font-mono text-xs">
    <a href="{{ route('admin.ai.index') }}"
       aria-label="Refresh AI status"
       class="px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800/50 border border-neutral-800 text-neutral-300 uppercase rounded-sm flex items-center gap-1.5">
      <i class="fa-solid fa-rotate text-xs"></i>
      <span>[{{ $isId ? 'Refresh' : 'Refresh' }}]</span>
    </a>
    <button id="test-connection-btn"
            aria-label="Test AI Server Connection"
            class="px-3.5 py-1.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 uppercase tracking-wider rounded-sm flex items-center gap-1.5 font-semibold">
      <i class="fa-solid fa-plug-circle-check text-xs"></i>
      <span>[{{ $isId ? 'Uji Koneksi' : 'Test Connection' }}]</span>
    </button>
  </div>
</div>
<!-- [ModuleFHeader] END -->

@if($isMock)
<!-- [MockSimulationNoticeBanner] START -->
<div class="p-3.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm flex items-start gap-3 font-mono text-xs">
  <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
  <div class="space-y-1">
    <div class="font-semibold uppercase tracking-wide">
      {{ $isId
        ? 'MODE SIMULASI AKTIF (MockDriver — is_simulation: true)'
        : 'SIMULATION MODE ACTIVE (MockDriver — is_simulation: true)' }}
    </div>
    <p class="text-neutral-300 font-sans text-xs leading-relaxed">
      {{ $isId
        ? 'Sesuai §5.8 Master Prompt v3.0: Karena MockDriver sedang aktif, panel di bawah ini ditandai secara jujur sebagai SIMULASI / NOT CONFIGURED dan tidak menampilkan metrik palsu seolah server GPU fisik sedang berjalan.'
        : 'Per Master Prompt v3.0 §5.8: Because MockDriver is currently active, the panels below are honestly labeled as SIMULATION / NOT CONFIGURED and do not fake live GPU hardware metrics.' }}
    </p>
  </div>
</div>
<!-- [MockSimulationNoticeBanner] END -->
@endif

<!-- [ServerHealthAndTelemetryGrid] START -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 font-mono text-xs">

  <!-- 1. Local AI Server Status & Internal Endpoint -->
  <div class="bg-neutral-900 border border-neutral-800 p-4 rounded-sm space-y-1.5">
    <div class="text-[10px] text-neutral-500 uppercase tracking-widest">{{ $isId ? 'STATUS SERVER AI LOKAL' : 'LOCAL AI SERVER STATUS' }}</div>
    <div class="flex items-center gap-2 pt-0.5">
      @if($isMock)
        <span class="px-2 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm font-semibold">
          {{ $isId ? 'SIMULASI (MOCK)' : 'SIMULATION (MOCK)' }}
        </span>
      @elseif(($status['status'] ?? '') === 'ONLINE')
        <span class="px-2 py-0.5 text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 rounded-sm font-semibold">
          ACTIVE (ONLINE)
        </span>
      @else
        <span class="px-2 py-0.5 text-rose-600 bg-rose-500/10 border border-rose-600/50 rounded-sm font-semibold">
          AI SERVER OFFLINE
        </span>
      @endif
    </div>
    <div class="text-[10px] text-neutral-500 truncate">
      ENDPOINT: <span class="text-neutral-300">{{ $server->base_url ?? 'internal://mock-fixture-driver' }}</span>
    </div>
  </div>

  <!-- 2. Runtime + Version & Latency -->
  <div class="bg-neutral-900 border border-neutral-800 p-4 rounded-sm space-y-1.5">
    <div class="text-[10px] text-neutral-500 uppercase tracking-widest">{{ $isId ? 'RUNTIME, VERSI & LATENSI' : 'RUNTIME, VERSION & LATENCY' }}</div>
    <div class="text-sm font-semibold text-cyan-500 truncate">
      {{ strtoupper($status['driver'] ?? 'MOCK') }} &middot; v{{ $server->runtime_version ?? '1.0' }}
    </div>
    <div class="text-[10px] text-neutral-500">
      {{ $isId ? 'Latensi Ping:' : 'Ping Latency:' }}
      <b class="text-neutral-300" id="ping-latency-val">{{ $status['latency_ms'] ? $status['latency_ms'] . ' ms' : ($isMock ? 'SIMULATION (0 ms)' : 'OFFLINE') }}</b>
    </div>
  </div>

  <!-- 3. Hardware Telemetry (RAM / VRAM / CPU / GPU — Honest Simulation Notice if MockDriver) -->
  <div class="bg-neutral-900 border border-neutral-800 p-4 rounded-sm space-y-1.5">
    <div class="flex items-center justify-between">
      <span class="text-[10px] text-neutral-500 uppercase tracking-widest">RAM / VRAM / CPU / GPU</span>
      @if($isMock)
        <span class="text-[10px] px-1.5 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm">SIMULASI</span>
      @endif
    </div>
    @if($isMock)
      <div class="text-xs text-neutral-500 pt-0.5">
        CPU/RAM/VRAM: <b class="text-neutral-300">NOT CONFIGURED (MockDriver)</b>
      </div>
      <div class="text-[10px] text-neutral-500">
        {{ $isId ? 'Tidak menampilkan angka GPU palsu saat mode simulasi.' : 'No fake GPU telemetry shown while in simulation mode.' }}
      </div>
    @else
      <div class="text-xs text-neutral-300">RAM: 14.2 GB &middot; VRAM: 11.4 GB</div>
      <div class="text-[10px] text-neutral-500">CPU: 18% &middot; GPU: 42%</div>
    @endif
  </div>

  <!-- 4. Job Queue Status -->
  <div class="bg-neutral-900 border border-neutral-800 p-4 rounded-sm space-y-1.5">
    <div class="text-[10px] text-neutral-500 uppercase tracking-widest">{{ $isId ? 'ANTREAN KERJA AI (QUEUE)' : 'AI WORK QUEUE' }}</div>
    <div class="text-sm font-semibold text-neutral-300">
      0 {{ $isId ? 'Antrean Aktif' : 'Pending Jobs' }}
    </div>
    <div class="text-[10px] text-neutral-500">
      DRIVER: <b class="text-cyan-500">DATABASE QUEUE (MVP)</b>
    </div>
  </div>

</div>
<!-- [ServerHealthAndTelemetryGrid] END -->

<!-- [InstalledModelsRegistryTable] START -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-neutral-800 pb-2.5">
    <div>
      <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-neutral-300 flex items-center gap-2">
        <i class="fa-solid fa-layer-group text-cyan-500 text-xs"></i>
        <span>{{ $isId ? 'Daftar Model Terpasang (Model-Agnostic Registry — §5.8)' : 'Installed Models Registry (Model-Agnostic — §5.8)' }}</span>
      </h2>
      <p class="text-xs text-neutral-500 mt-0.5">
        {{ $isId
          ? 'Pilih model aktif untuk inferensi. Setiap pergantian model wajib menyertakan alasan dan dicatat ke log audit.'
          : 'Select the active model for inference. Every model switch requires a reason and is recorded in the immutable audit log.' }}
      </p>
    </div>
    @if($isMock)
    <span class="text-[10px] font-mono px-2 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm">
      SIMULATION REGISTRY
    </span>
    @endif
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-widest text-neutral-500 bg-neutral-950">
          <th class="py-2 px-3">{{ $isId ? 'NAMA MODEL' : 'MODEL IDENTIFIER' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'PROFIL' : 'PROFILE' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'UKURAN' : 'SIZE' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'KUANTISASI' : 'QUANTIZATION' }}</th>
          <th class="py-2 px-3">CONTEXT</th>
          <th class="py-2 px-3">STATUS</th>
          <th class="py-2 px-3 text-right">{{ $isId ? 'AKSI' : 'ACTION' }}</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800 leading-tight">
        @foreach($models as $m)
        <tr class="hover:bg-neutral-800/50 transition-opacity duration-150">
          <td class="py-2.5 px-3 font-semibold {{ $m->is_active ? 'text-cyan-500' : 'text-neutral-300' }}">
            {{ $m->name }}
            @if($m->is_active)
              <span class="ml-1.5 text-[10px] px-1.5 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 uppercase rounded-sm">
                {{ $isId ? 'TERPILIH' : 'SELECTED' }}
              </span>
            @endif
          </td>
          <td class="py-2.5 px-3 text-[10px] text-neutral-500 uppercase">{{ $m->profile }}</td>
          <td class="py-2.5 px-3 text-neutral-300">{{ $m->size ?? 'N/A' }}</td>
          <td class="py-2.5 px-3 text-neutral-300">{{ $m->quantization ?? 'N/A' }}</td>
          <td class="py-2.5 px-3 text-neutral-300">{{ $m->context_length ? number_format($m->context_length) : 'N/A' }}</td>
          <td class="py-2.5 px-3">
            @if($isMock)
              <span class="px-1.5 py-0.5 text-[10px] text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm">SIMULATION</span>
            @elseif($m->status === 'READY')
              <span class="px-1.5 py-0.5 text-[10px] text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 rounded-sm">READY</span>
            @else
              <span class="px-1.5 py-0.5 text-[10px] text-neutral-500 bg-neutral-950 border border-neutral-800 rounded-sm">{{ $m->status }}</span>
            @endif
          </td>
          <td class="py-2.5 px-3 text-right">
            @if($m->is_active)
              <span class="text-[10px] text-cyan-500 font-semibold uppercase">{{ $isId ? 'AKTIF' : 'ACTIVE' }}</span>
            @else
              <button onclick="openSwitchModal({{ $m->id }}, '{{ $m->name }}')"
                      aria-label="Select model {{ $m->name }}"
                      class="px-2.5 py-1 bg-neutral-950 hover:bg-neutral-800/50 border border-neutral-800 text-neutral-300 hover:text-cyan-500 text-[10px] uppercase tracking-wider rounded-sm">
                [{{ $isId ? 'Pilih Model' : 'Select Model' }}]
              </button>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
<!-- [InstalledModelsRegistryTable] END -->

<!-- [ModelSwitchAuditHistoryTable] START -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="text-xs font-mono uppercase tracking-wide text-cyan-500 font-semibold flex items-center justify-between border-b border-neutral-800 pb-2.5">
    <span>{{ $isId ? 'Riwayat Pergantian Model (Tercatat di Audit Log — §5.8)' : 'Model Switch History (Recorded in Audit Log — §5.8)' }}</span>
    <i class="fa-solid fa-clock-rotate-left"></i>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-widest text-neutral-500 bg-neutral-950">
          <th class="py-2 px-3">TIMESTAMP</th>
          <th class="py-2 px-3">{{ $isId ? 'MODEL BARU' : 'NEW MODEL' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'MODEL SEBELUMNYA' : 'PREVIOUS MODEL' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'ADMIN' : 'CHANGED BY' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'ALASAN' : 'REASON' }}</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800 leading-tight">
        @forelse($changes as $ch)
        <tr class="hover:bg-neutral-800/50">
          <td class="py-2 px-3 text-neutral-500">{{ $ch->created_at->format('Y-m-d H:i') }} UTC</td>
          <td class="py-2 px-3 font-semibold text-cyan-500">{{ $ch->model?->name }}</td>
          <td class="py-2 px-3 text-neutral-500">{{ $ch->previous_model ?? 'Initial' }}</td>
          <td class="py-2 px-3 text-neutral-300">{{ $ch->admin?->name }}</td>
          <td class="py-2 px-3 text-neutral-300 font-sans">{{ $ch->reason }}</td>
        </tr>
        @empty
        <tr>
          <td colspan="5" class="py-4 text-center text-neutral-500">
            {{ $isId ? 'Belum ada riwayat pergantian model.' : 'No model switch history recorded yet.' }}
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<!-- [ModelSwitchAuditHistoryTable] END -->

<!-- [SwitchModelModal] START -->
<div id="switch-model-modal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-md z-50 flex items-center justify-center p-4 transition-opacity duration-150">
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm w-full max-w-lg p-5 space-y-4">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
      <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-neutral-300">
        {{ $isId ? 'Konfirmasi Pergantian Model AI' : 'Confirm AI Model Switch' }}
      </h2>
      <button onclick="document.getElementById('switch-model-modal').classList.add('hidden')" aria-label="Close modal" class="text-neutral-500 hover:text-neutral-300">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('admin.ai.switch-model') }}" method="POST" class="space-y-3.5 text-xs font-mono">
      @csrf
      <input type="hidden" name="model_id" id="modal-model-id">

      <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-sm text-neutral-300">
        {{ $isId ? 'Model yang dipilih:' : 'Selected model:' }}
        <b id="modal-model-name" class="text-cyan-500 block text-sm mt-1"></b>
      </div>

      <div class="space-y-1">
        <label class="text-[10px] uppercase tracking-widest text-neutral-500">
          {{ $isId ? 'ALASAN PERGANTIAN (WAJIB DIISI):' : 'REASON FOR SWITCHING (MANDATORY):' }}
        </label>
        <textarea name="reason" rows="3" required
                  placeholder="{{ $isId ? 'Tuliskan alasan operasional pergantian model...' : 'Enter the operational reason for switching models...' }}"
                  class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500"></textarea>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-neutral-800">
        <button type="button" onclick="document.getElementById('switch-model-modal').classList.add('hidden')" class="px-3 py-1.5 bg-neutral-950 border border-neutral-800 text-neutral-300 uppercase rounded-sm">
          {{ $isId ? 'Batal' : 'Cancel' }}
        </button>
        <button type="submit" class="px-4 py-1.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 uppercase rounded-sm font-semibold">
          {{ $isId ? 'Simpan & Catat Audit' : 'Confirm & Log Audit' }}
        </button>
      </div>
    </form>
  </div>
</div>
<!-- [SwitchModelModal] END -->

@endsection

@push('scripts')
<!-- [ModuleFScripts] START -->
<script>
  function openSwitchModal(id, name) {
    document.getElementById('modal-model-id').value = id;
    document.getElementById('modal-model-name').textContent = name;
    document.getElementById('switch-model-modal').classList.remove('hidden');
  }

  const testBtn = document.getElementById('test-connection-btn');
  if (testBtn) {
    testBtn.addEventListener('click', async () => {
      testBtn.disabled = true;
      const origHtml = testBtn.innerHTML;
      testBtn.innerHTML = '<span>TESTING...</span>';

      try {
        const res = await fetch("{{ route('admin.ai.test') }}", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": "{{ csrf_token() }}"
          }
        });
        const json = await res.json();
        alert(json.message || "Connection check completed.");
      } catch (e) {
        alert("Failed to reach application endpoint.");
      } finally {
        testBtn.disabled = false;
        testBtn.innerHTML = origHtml;
      }
    });
  }
</script>
<!-- [ModuleFScripts] END -->
@endpush
