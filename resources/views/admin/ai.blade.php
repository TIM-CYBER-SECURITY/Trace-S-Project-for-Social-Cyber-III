@extends('layouts.tactical')

@section('title', 'Local AI Server Management // TRACE-S')

@section('content')

<!-- Header Bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div>
    <div class="flex items-center gap-2">
      <h1 class="text-lg font-bold font-mono tracking-wide text-neutral-100 uppercase">Local AI Server Management &amp; Model Registry</h1>
      <span class="text-[10px] font-mono px-2 py-0.5 border border-cyan-500/40 bg-cyan-950/30 text-cyan-400 rounded-sm">MODUL F (ADMIN)</span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">Konfigurasi inferensi AI lokal, pemantauan status runtime intranet, dan pergantian model teregistrasi.</p>
  </div>

  <div class="flex items-center gap-2">
    <button id="test-connection-btn" class="px-3.5 py-1.5 bg-cyan-600/20 text-cyan-400 border border-cyan-500/50 hover:bg-cyan-600/40 text-xs font-mono uppercase tracking-wider rounded-sm transition-all flex items-center gap-2">
      <i class="fa-solid fa-satellite-dish text-xs"></i>
      <span>Uji Koneksi AI Host</span>
    </button>
  </div>
</div>

<!-- START: SERVER HEALTH STATUS CARD -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 font-mono text-xs">

  <div class="bg-neutral-900 border border-neutral-800 p-4 rounded-sm space-y-1">
    <div class="text-[10px] text-neutral-500 uppercase tracking-wider">Status Inferensi Host</div>
    <div class="text-base font-bold flex items-center gap-2">
      <span class="w-2.5 h-2.5 rounded-full {{ $status['status'] === 'ONLINE' ? 'bg-emerald-500' : ($status['status'] === 'SIMULASI' ? 'bg-amber-500' : 'bg-rose-500') }}"></span>
      <span class="{{ $status['status'] === 'ONLINE' ? 'text-emerald-400' : ($status['status'] === 'SIMULASI' ? 'text-amber-400' : 'text-rose-400') }}">
        {{ $status['status'] }}
      </span>
    </div>
    <div class="text-[10px] text-neutral-500">Driver: <b>{{ $status['driver'] }}</b></div>
  </div>

  <div class="bg-neutral-900 border border-neutral-800 p-4 rounded-sm space-y-1">
    <div class="text-[10px] text-neutral-500 uppercase tracking-wider">Latensi Response</div>
    <div class="text-base font-bold text-neutral-200">
      {{ $status['latency_ms'] ? $status['latency_ms'] . ' ms' : 'N/A' }}
    </div>
    <div class="text-[10px] text-neutral-500">Metrik ping internal server</div>
  </div>

  <div class="bg-neutral-900 border border-neutral-800 p-4 rounded-sm space-y-1">
    <div class="text-[10px] text-neutral-500 uppercase tracking-wider">Runtime &amp; Versi</div>
    <div class="text-base font-bold text-cyan-400 truncate">
      {{ $server->runtime ?? 'Ollama / vLLM' }}
    </div>
    <div class="text-[10px] text-neutral-500">Versi: {{ $server->runtime_version ?? '1.0' }}</div>
  </div>

  <div class="bg-neutral-900 border border-neutral-800 p-4 rounded-sm space-y-1">
    <div class="text-[10px] text-neutral-500 uppercase tracking-wider">Model Aktif</div>
    <div class="text-base font-bold text-neutral-100 truncate">
      {{ $status['model'] ?? 'TraceS-Mock' }}
    </div>
    <div class="text-[10px] text-neutral-500">Tipe: LLM Intranet C4ISR</div>
  </div>

</div>
<!-- END: SERVER HEALTH STATUS CARD -->

<!-- START: MODEL REGISTRY TABLE -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
    <div>
      <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-neutral-200 flex items-center gap-2">
        <i class="fa-solid fa-layer-group text-cyan-400"></i>
        <span>Model Registry (Local Repository)</span>
      </h2>
      <p class="text-[11px] text-neutral-500 mt-0.5">Daftar model LLM lokal yang terpasang pada infrastruktur komputasi PUSSIBERAD.</p>
    </div>
    <span class="text-[10px] font-mono text-neutral-500">Prinsip: Local-First &amp; Model-Agnostic</span>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-wider text-neutral-500 bg-neutral-950/40">
          <th class="py-2.5 px-3">Nama Model</th>
          <th class="py-2.5 px-3">Profil Perangkat</th>
          <th class="py-2.5 px-3">Ukuran Disk</th>
          <th class="py-2.5 px-3">Kuantisasi</th>
          <th class="py-2.5 px-3">Panjang Konteks</th>
          <th class="py-2.5 px-3">Status Kesiapan</th>
          <th class="py-2.5 px-3 text-right">Tindakan</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800/60">
        @foreach($models as $m)
        <tr class="{{ $m->is_active ? 'bg-cyan-950/20' : 'hover:bg-neutral-850/50' }} transition-colors">
          <td class="py-3 px-3 font-semibold {{ $m->is_active ? 'text-cyan-400' : 'text-neutral-200' }}">
            {{ $m->name }}
            @if($m->is_active)
              <span class="ml-1 text-[9px] px-1.5 py-0.2 bg-cyan-950 border border-cyan-500/50 text-cyan-300 uppercase">AKTIF</span>
            @endif
          </td>
          <td class="py-3 px-3">
            <span class="px-1.5 py-0.5 bg-neutral-800 border border-neutral-700 text-neutral-300 text-[10px] uppercase">{{ $m->profile }}</span>
          </td>
          <td class="py-3 px-3 text-neutral-400">{{ $m->size ?? 'N/A' }}</td>
          <td class="py-3 px-3 text-neutral-400">{{ $m->quantization ?? 'N/A' }}</td>
          <td class="py-3 px-3 text-neutral-400">{{ $m->context_length ? number_format($m->context_length) . ' tokens' : 'N/A' }}</td>
          <td class="py-3 px-3">
            @if($m->status === 'READY')
              <span class="text-emerald-400 text-[10px] uppercase font-bold flex items-center gap-1">
                <i class="fa-solid fa-check"></i> READY
              </span>
            @else
              <span class="text-neutral-500 text-[10px] uppercase">{{ $m->status }}</span>
            @endif
          </td>
          <td class="py-3 px-3 text-right">
            @if($m->is_active)
              <span class="text-[10px] text-cyan-400 font-bold uppercase">SEDANG DIGUNAKAN</span>
            @else
              <button onclick="openSwitchModal({{ $m->id }}, '{{ $m->name }}')" class="px-2.5 py-1 bg-neutral-800 hover:bg-cyan-950 border border-neutral-700 hover:border-cyan-500 text-neutral-300 hover:text-cyan-300 text-[10px] uppercase tracking-wider rounded-sm transition-all">
                Pilih Model Ini
              </button>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
<!-- END: MODEL REGISTRY TABLE -->

<!-- START: AUDIT HISTORY OF MODEL CHANGES -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="text-xs font-mono uppercase tracking-wider text-cyan-400 font-semibold flex items-center justify-between border-b border-neutral-800 pb-2">
    <span>Riwayat Pergantian Model Aktif (Audit Trail)</span>
    <i class="fa-solid fa-clock-rotate-left"></i>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-wider text-neutral-500">
          <th class="py-2 px-3">Waktu Perubahan</th>
          <th class="py-2 px-3">Model Baru</th>
          <th class="py-2 px-3">Model Sebelumnya</th>
          <th class="py-2 px-3">Diubah Oleh</th>
          <th class="py-2 px-3">Alasan Teknis/Kebijakan</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800/60 font-sans">
        @forelse($changes as $ch)
        <tr class="hover:bg-neutral-850/50">
          <td class="py-2 px-3 font-mono text-[11px] text-neutral-400">{{ $ch->created_at->format('d/m/Y H:i') }} WIB</td>
          <td class="py-2 px-3 font-mono font-bold text-cyan-400">{{ $ch->model?->name }}</td>
          <td class="py-2 px-3 font-mono text-neutral-500">{{ $ch->previous_model ?? 'Inisialisasi' }}</td>
          <td class="py-2 px-3 font-mono text-neutral-300">{{ $ch->admin?->name }}</td>
          <td class="py-2 px-3 text-neutral-300 text-xs">{{ $ch->reason }}</td>
        </tr>
        @empty
        <tr>
          <td colspan="5" class="py-4 text-center text-neutral-500 font-mono">Belum ada riwayat pergantian model.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<!-- END: AUDIT HISTORY -->

<!-- Modal Alihkan Model Aktif -->
<div id="switch-model-modal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm w-full max-w-lg p-5 shadow-2xl space-y-4">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
      <div class="flex items-center gap-2">
        <i class="fa-solid fa-arrow-right-arrow-left text-cyan-400"></i>
        <h2 class="text-sm font-bold font-mono uppercase tracking-wider text-neutral-200">Konfirmasi Pergantian Model Aktif</h2>
      </div>
      <button onclick="document.getElementById('switch-model-modal').classList.add('hidden')" class="text-neutral-500 hover:text-neutral-300">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('admin.ai.switch-model') }}" method="POST" class="space-y-3.5 text-xs font-mono">
      @csrf
      <input type="hidden" name="model_id" id="modal-model-id">

      <div class="p-3 bg-neutral-950 border border-neutral-850 rounded-sm text-neutral-300">
        Anda akan mengalihkan mesin inferensi ke: <b id="modal-model-name" class="text-cyan-400 block text-sm mt-1"></b>
      </div>

      <div class="space-y-1">
        <label class="text-[10px] uppercase text-neutral-400 font-bold">Alasan Pergantian Model (Wajib Diisi):</label>
        <textarea name="reason" rows="3" required placeholder="Jelaskan kebutuhan operasional atau optimasi beban kerja..."
                  class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500"></textarea>
      </div>

      <div class="text-[10px] text-neutral-500 leading-relaxed">
        * Master Prompt §8 menegaskan pergantian model aktif tidak boleh terjadi secara otomatis tanpa kebijakan tertulis dan selalu dicatat pada audit log.
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-neutral-800">
        <button type="button" onclick="document.getElementById('switch-model-modal').classList.add('hidden')" class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 uppercase rounded-sm">
          Batal
        </button>
        <button type="submit" class="px-4 py-1.5 bg-cyan-600/20 text-cyan-400 border border-cyan-500/50 hover:bg-cyan-600/40 uppercase rounded-sm">
          Konfirmasi Pergantian
        </button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
  function openSwitchModal(id, name) {
    document.getElementById('modal-model-id').value = id;
    document.getElementById('modal-model-name').textContent = name;
    document.getElementById('switch-model-modal').classList.remove('hidden');
  }

  // Ping AI Connection Test
  const testBtn = document.getElementById('test-connection-btn');
  if (testBtn) {
    testBtn.addEventListener('click', async () => {
      testBtn.disabled = true;
      testBtn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> Menguji Host...';

      try {
        const res = await fetch("{{ route('admin.ai.test') }}", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": "{{ csrf_token() }}"
          }
        });
        const json = await res.json();
        alert(json.message || "Hasil uji koneksi selesai.");
      } catch (e) {
        alert("Gagal menghubungi server aplikasi.");
      } finally {
        testBtn.disabled = false;
        testBtn.innerHTML = '<i class="fa-solid fa-satellite-dish text-xs"></i> <span>Uji Koneksi AI Host</span>';
      }
    });
  }
</script>
@endpush
