@extends('layouts.tactical')

@section('title', 'AI Analyst Console // TRACE-S')

@section('content')

<!-- Header Bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div>
    <div class="flex items-center gap-2">
      <h1 class="text-lg font-bold font-mono tracking-wide text-neutral-100 uppercase">AI Analyst Console (Context-Aware)</h1>
      <span class="text-[10px] font-mono px-2 py-0.5 border border-cyan-500/40 bg-cyan-950/30 text-cyan-400 rounded-sm">MODUL E</span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">Asisten analitis berbasis konteks berkas kasus dan eksekusi tool berizin (Tool Allowlist) tanpa akses jaringan luar.</p>
  </div>

  <!-- Case Context Switcher -->
  <form action="{{ route('console.index') }}" method="GET" class="flex items-center gap-2 text-xs font-mono">
    <label class="text-[10px] uppercase text-neutral-400">Konteks Kasus:</label>
    <select name="case_id" onchange="this.form.submit()" class="bg-neutral-900 border border-neutral-700 text-neutral-200 px-3 py-1.5 rounded-sm focus:outline-none focus:border-cyan-500 text-xs font-mono">
      @foreach($cases as $c)
        <option value="{{ $c->id }}" {{ $selectedCase && $selectedCase->id === $c->id ? 'selected' : '' }}>
          {{ $c->case_code }} - {{ Str::limit($c->title, 35) }}
        </option>
      @endforeach
    </select>
  </form>
</div>

<!-- Main Console Container -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm flex flex-col h-[650px] shadow-2xl overflow-hidden">

  <!-- Console Top Bar / Status -->
  <div class="px-4 py-2.5 bg-neutral-950 border-b border-neutral-800 flex flex-wrap items-center justify-between gap-3 font-mono text-xs">
    <div class="flex items-center gap-3">
      <div class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
        <span class="text-cyan-400 font-bold uppercase text-[11px]">KONSOL ANALIS AKTIF</span>
      </div>
      <span class="text-neutral-600">|</span>
      <span class="text-neutral-400 text-[11px]">Kasus: <b class="text-neutral-200">{{ $selectedCase?->case_code ?? 'TIDAK TERIKAT' }}</b></span>
      <span class="text-neutral-600">|</span>
      <span class="text-neutral-400 text-[11px]">Bukti Tersedia: <b class="text-cyan-400">{{ $selectedCase?->evidence->count() ?? 0 }} Dokumen</b></span>
    </div>

    <div class="flex items-center gap-2 text-[10px] text-neutral-500">
      <span class="px-2 py-0.5 bg-neutral-900 border border-neutral-800 rounded-sm">TOOL ALLOWLIST: 10 FUNGSI</span>
      <span class="px-2 py-0.5 bg-neutral-900 border border-neutral-800 rounded-sm text-cyan-400">ANTI-INJECTION: AKTIF</span>
      <span class="px-2 py-0.5 bg-amber-950/30 border border-amber-500/30 text-amber-500">SIMULASI</span>
    </div>
  </div>

  <!-- Messages Scroll Area -->
  <div id="console-messages" class="flex-1 p-4 overflow-y-auto space-y-4 font-mono text-xs bg-neutral-950/40">

    <!-- Initial Welcome Message -->
    <div class="p-3 bg-neutral-900 border border-cyan-500/30 rounded-sm space-y-1.5 max-w-3xl">
      <div class="flex items-center justify-between text-[10px] text-cyan-400 pb-1 border-b border-neutral-800">
        <span class="font-bold uppercase flex items-center gap-1.5">
          <i class="fa-solid fa-microchip"></i>
          <span>TRACE-S COGNITIVE ASSISTANT (SYSTEM)</span>
        </span>
        <span>SIAP OPERASI</span>
      </div>
      <p class="font-sans text-neutral-200 text-xs leading-relaxed">
        Selamat bertugas, Analis. Saya terhubung dengan basis bukti digital kasus <b>{{ $selectedCase?->case_code }}</b>.
        Anda dapat menanyakan ekstraksi klaim, ringkasan perbandingan materi referensi vs editan, atau meminta penghitungan nilai kemiripan teks. Seluruh jawaban berlandaskan bukti yang tersimpan dan tidak mengarang fakta.
      </p>
      <div class="text-[10px] text-neutral-500 pt-1 font-mono">
        Saran perintah: <i>"Buat ringkasan kasus"</i>, <i>"Tampilkan daftar klaim utama"</i>, atau tanyakan detail bukti.
      </div>
    </div>

    @foreach($messages as $msg)
    @if($msg->role === 'user')
    <!-- User Message -->
    <div class="flex justify-end">
      <div class="p-3 bg-cyan-950/40 border border-cyan-500/50 rounded-sm space-y-1 max-w-2xl text-right">
        <div class="text-[9px] uppercase tracking-wider text-cyan-400 font-bold">
          {{ Auth::user()->name }} [OPERATOR] • {{ $msg->created_at->format('H:i') }}
        </div>
        <p class="font-sans text-neutral-100 text-xs text-left leading-relaxed whitespace-pre-wrap">{{ $msg->content }}</p>
        @if(!empty($msg->tool_calls['prompt_injection_warning']))
        <div class="text-[9px] text-rose-400 font-mono text-left pt-1">
          [PERINGATAN]: Input mengandung pola instruksi eksternal yang dinetralisir.
        </div>
        @endif
      </div>
    </div>
    @else
    <!-- Assistant Message -->
    <div class="flex justify-start">
      <div class="p-3 bg-neutral-900 border border-neutral-800 rounded-sm space-y-2 max-w-2xl">
        <div class="flex items-center justify-between text-[10px] text-cyan-400 pb-1 border-b border-neutral-800">
          <span class="font-bold uppercase flex items-center gap-1">
            <i class="fa-solid fa-terminal"></i>
            <span>AI ANALYST ASSISTANT</span>
          </span>
          <span class="text-neutral-500">{{ $msg->created_at->format('H:i') }} WIB</span>
        </div>
        <p class="font-sans text-neutral-200 text-xs leading-relaxed whitespace-pre-wrap">{{ $msg->content }}</p>

        @if(!empty($msg->sources))
        <div class="flex items-center gap-1.5 pt-1 text-[10px]">
          <span class="text-neutral-500 uppercase">Rujukan Bukti:</span>
          @foreach($msg->sources as $src)
            <span class="px-1.5 py-0.2 bg-neutral-950 border border-cyan-500/40 text-cyan-400 rounded-xs">{{ $src }}</span>
          @endforeach
        </div>
        @endif

        @if(!empty($msg->tool_calls))
        <div class="p-2 bg-neutral-950 border border-neutral-850 rounded-sm text-[9px] text-neutral-400 space-y-1">
          <div class="text-neutral-500 uppercase font-bold">Eksekusi Backend Tool:</div>
          @foreach($msg->tool_calls as $tc)
            <div>• Tool: <b class="text-cyan-400">{{ $tc['tool'] ?? 'generic' }}</b> (Parameter tervalidasi allowlist)</div>
          @endforeach
        </div>
        @endif
      </div>
    </div>
    @endif
    @endforeach

  </div>

  <!-- Input Bar Form -->
  <div class="p-3 bg-neutral-900 border-t border-neutral-800">
    <form id="console-form" class="flex items-center gap-2">
      <input type="hidden" id="session-id" value="{{ $session->id }}">
      <div class="relative flex-1">
        <input type="text" id="console-input" required autocomplete="off"
               placeholder="Ketik pertanyaan investigasi atau instruksi analisis terkait kasus..."
               class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3.5 py-2.5 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500 placeholder-neutral-600">
      </div>
      <button type="submit" id="send-btn" class="px-4 py-2.5 bg-cyan-600/20 text-cyan-400 border border-cyan-500/50 hover:bg-cyan-600/40 text-xs font-mono uppercase tracking-wider rounded-sm transition-all flex items-center gap-2">
        <span>Kirim</span>
        <i class="fa-solid fa-paper-plane text-xs"></i>
      </button>
    </form>
  </div>

</div>

@endsection

@push('scripts')
<script>
  const form = document.getElementById('console-form');
  const input = document.getElementById('console-input');
  const sendBtn = document.getElementById('send-btn');
  const msgContainer = document.getElementById('console-messages');
  const sessionId = document.getElementById('session-id').value;

  function scrollToBottom() {
    msgContainer.scrollTop = msgContainer.scrollHeight;
  }
  scrollToBottom();

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;

    // Render User message directly
    const userDiv = document.createElement('div');
    userDiv.className = 'flex justify-end';
    userDiv.innerHTML = `
      <div class="p-3 bg-cyan-950/40 border border-cyan-500/50 rounded-sm space-y-1 max-w-2xl text-right">
        <div class="text-[9px] uppercase tracking-wider text-cyan-400 font-bold">
          Anda [OPERATOR] • Baru Saja
        </div>
        <p class="font-sans text-neutral-100 text-xs text-left leading-relaxed">${escapeHtml(text)}</p>
      </div>
    `;
    msgContainer.appendChild(userDiv);
    input.value = '';
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fa-solid fa-circle-notch animate-spin"></i>';
    scrollToBottom();

    // Loading placeholder
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'flex justify-start';
    loadingDiv.id = 'loading-placeholder';
    loadingDiv.innerHTML = `
      <div class="p-3 bg-neutral-900 border border-neutral-800 rounded-sm text-neutral-400 text-xs font-mono flex items-center gap-2">
        <i class="fa-solid fa-microchip animate-pulse text-cyan-400"></i>
        <span>Mengeksekusi analisis konteks berkas kasus...</span>
      </div>
    `;
    msgContainer.appendChild(loadingDiv);
    scrollToBottom();

    try {
      const res = await fetch("{{ route('console.message') }}", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": "{{ csrf_token() }}"
        },
        body: JSON.stringify({
          session_id: sessionId,
          message: text
        })
      });

      loadingDiv.remove();
      const json = await res.json();

      if (json.status === 'OK' && json.message) {
        const msg = json.message;
        const assistDiv = document.createElement('div');
        assistDiv.className = 'flex justify-start';

        let sourcesHtml = '';
        if (msg.sources && msg.sources.length) {
          sourcesHtml = `
            <div class="flex items-center gap-1.5 pt-1 text-[10px]">
              <span class="text-neutral-500 uppercase">Rujukan Bukti:</span>
              ${msg.sources.map(s => `<span class="px-1.5 py-0.2 bg-neutral-950 border border-cyan-500/40 text-cyan-400 rounded-xs">${s}</span>`).join('')}
            </div>
          `;
        }

        let toolsHtml = '';
        if (msg.tool_calls && msg.tool_calls.length) {
          toolsHtml = `
            <div class="p-2 bg-neutral-950 border border-neutral-850 rounded-sm text-[9px] text-neutral-400 space-y-1">
              <div class="text-neutral-500 uppercase font-bold">Eksekusi Backend Tool:</div>
              ${msg.tool_calls.map(tc => `<div>• Tool: <b class="text-cyan-400">${tc.tool || 'generic'}</b> (Parameter tervalidasi allowlist)</div>`).join('')}
            </div>
          `;
        }

        assistDiv.innerHTML = `
          <div class="p-3 bg-neutral-900 border border-neutral-800 rounded-sm space-y-2 max-w-2xl">
            <div class="flex items-center justify-between text-[10px] text-cyan-400 pb-1 border-b border-neutral-800">
              <span class="font-bold uppercase flex items-center gap-1">
                <i class="fa-solid fa-terminal"></i>
                <span>AI ANALYST ASSISTANT</span>
              </span>
              <span class="text-neutral-500">${msg.created_at} WIB</span>
            </div>
            <p class="font-sans text-neutral-200 text-xs leading-relaxed whitespace-pre-wrap">${escapeHtml(msg.content)}</p>
            ${sourcesHtml}
            ${toolsHtml}
          </div>
        `;
        msgContainer.appendChild(assistDiv);
      } else {
        alert("Gagal merespons: " + (json.error || "Terjadi kesalahan internal."));
      }
    } catch (e) {
      if (document.getElementById('loading-placeholder')) {
        document.getElementById('loading-placeholder').remove();
      }
      alert("Koneksi ke server terputus.");
    } finally {
      sendBtn.disabled = false;
      sendBtn.innerHTML = '<span>Kirim</span> <i class="fa-solid fa-paper-plane text-xs"></i>';
      scrollToBottom();
    }
  });

  function escapeHtml(string) {
    const p = document.createElement("p");
    p.textContent = string;
    return p.innerHTML;
  }
</script>
@endpush
