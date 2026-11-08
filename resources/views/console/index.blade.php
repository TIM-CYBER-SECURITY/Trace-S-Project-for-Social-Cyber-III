@extends('layouts.tactical')

@php
  $isId = app()->getLocale() === 'id';
@endphp

@section('title', ($isId ? 'Konsol Analis AI' : 'AI Analyst Console') . ' // TRACE-S')

@section('content')

<!-- [ModuleEHeader] START -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div>
    <div class="flex items-center gap-2.5">
      <h1 class="text-sm font-semibold font-mono tracking-wide text-neutral-300 uppercase">
        {{ $isId ? 'Konsol Dialog Analis AI Terkunci Konteks Kasus' : 'Case-Scoped AI Analyst Console' }}
      </h1>
      <span class="text-[10px] font-mono px-2 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 rounded-sm">
        MODULE E (§5.7 FR-050)
      </span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">
      {{ $isId
        ? 'Terkunci hanya pada data kasus aktif (Case ID) menggunakan daftar fungsi resmi (allowlist) tanpa akses shell bebas.'
        : 'Strictly locked to active case data (Case ID) using backend allowlisted tools without free shell access.' }}
    </p>
  </div>

  <!-- Case Context Switcher -->
  <form action="{{ route('console.index') }}" method="GET" class="flex items-center gap-2 text-xs font-mono">
    <label class="text-[10px] uppercase tracking-widest text-neutral-500">{{ $isId ? 'KASUS TERKUNCI:' : 'LOCKED CASE:' }}</label>
    <select name="case_id" onchange="this.form.submit()" aria-label="Select case context" class="bg-neutral-900 border border-neutral-800 text-neutral-300 px-3 py-1.5 rounded-sm focus:outline-none focus:border-cyan-500 text-xs font-mono">
      @foreach($cases as $c)
        <option value="{{ $c->id }}" {{ $selectedCase && $selectedCase->id === $c->id ? 'selected' : '' }}>
          {{ $c->case_code }} — {{ Str::limit($c->title, 35) }}
        </option>
      @endforeach
    </select>
  </form>
</div>
<!-- [ModuleEHeader] END -->

<!-- [AiAnalystConsoleContainer] START -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm flex flex-col h-[620px] overflow-hidden">

  <!-- Console Status Bar -->
  <div class="px-4 py-2.5 bg-neutral-950 border-b border-neutral-800 flex flex-wrap items-center justify-between gap-3 font-mono text-xs">
    <div class="flex items-center gap-3">
      <div class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse-slow"></span>
        <span class="text-cyan-500 font-semibold uppercase text-[10px] tracking-widest">CASE-LOCKED SESSION</span>
      </div>
      <span class="text-neutral-800">|</span>
      <span class="text-neutral-500 text-xs">CASE ID: <b class="text-neutral-300">{{ $selectedCase?->case_code ?? 'UNBOUND' }}</b></span>
      <span class="text-neutral-800">|</span>
      <span class="text-neutral-500 text-xs">{{ $isId ? 'BUKTI:' : 'EVIDENCE:' }} <b class="text-cyan-500">{{ $selectedCase?->evidence->count() ?? 0 }}</b></span>
    </div>

    <div class="flex flex-wrap items-center gap-2 text-[10px] text-neutral-500">
      <span class="px-2 py-0.5 bg-neutral-900 border border-neutral-800 rounded-sm">
        ALLOWLIST: extract_claims, compare_numeric_entities, analyze_citations
      </span>
      <span class="px-2 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm" title="is_simulation: true">
        {{ $isId ? 'SIMULASI' : 'SIMULATION' }}
      </span>
    </div>
  </div>

  <!-- Messages Scroll Area -->
  <div id="console-messages" class="flex-1 p-4 overflow-y-auto space-y-3.5 font-mono text-xs bg-neutral-950">

    <!-- System Initialization Message -->
    <div class="p-3.5 bg-neutral-900 border border-neutral-800 rounded-sm space-y-1.5 max-w-3xl">
      <div class="flex items-center justify-between text-[10px] text-cyan-500 pb-1.5 border-b border-neutral-800 uppercase tracking-widest">
        <span class="font-semibold flex items-center gap-1.5">
          <i class="fa-solid fa-terminal"></i>
          <span>TRACE-S CASE ASSISTANT</span>
        </span>
        <span>CASE: {{ $selectedCase?->case_code }}</span>
      </div>
      <p class="font-sans text-neutral-300 text-xs leading-relaxed">
        {{ $isId
          ? 'Sesi ini terkunci pada berkas bukti kasus ' . ($selectedCase?->case_code ?? '-') . '. Anda dapat meminta ekstraksi klaim, perbandingan angka terhadap dokumen resmi, atau rujukan bukti. Setiap jawaban menyertakan kode bukti yang digunakan.'
          : 'This session is strictly locked to evidence files in case ' . ($selectedCase?->case_code ?? '-') . '. You can request claim extraction, numeric entity comparison against official documents, or citation checks. Every response cites the evidence IDs used.' }}
      </p>
    </div>

    @foreach($messages as $msg)
    @if($msg->role === 'user')
    <div class="flex justify-end">
      <div class="p-3 bg-neutral-900 border border-cyan-500/50 rounded-sm space-y-1 max-w-2xl text-right">
        <div class="text-[10px] uppercase tracking-widest text-cyan-500 font-semibold">
          {{ Auth::user()->name }} &middot; {{ $msg->created_at->format('H:i') }} UTC
        </div>
        <p class="font-sans text-neutral-300 text-xs text-left leading-relaxed whitespace-pre-wrap">{{ $msg->content }}</p>
      </div>
    </div>
    @else
    <div class="flex justify-start">
      <div class="p-3.5 bg-neutral-900 border border-neutral-800 rounded-sm space-y-2 max-w-2xl">
        <div class="flex items-center justify-between text-[10px] text-cyan-500 pb-1 border-b border-neutral-800 uppercase tracking-widest">
          <span class="font-semibold">AI ANALYST CONSOLE</span>
          <span class="text-neutral-500">{{ $msg->created_at->format('H:i') }} UTC</span>
        </div>
        <p class="font-sans text-neutral-300 text-xs leading-relaxed whitespace-pre-wrap">{{ $msg->content }}</p>

        @if(!empty($msg->sources))
        <div class="flex flex-wrap items-center gap-1.5 pt-1 text-[10px]">
          <span class="text-neutral-500 uppercase tracking-widest">{{ $isId ? 'RUJUKAN BUKTI:' : 'EVIDENCE CITATIONS:' }}</span>
          @foreach($msg->sources as $src)
            <span class="px-1.5 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 rounded-sm">{{ $src }}</span>
          @endforeach
        </div>
        @endif

        @if(!empty($msg->tool_calls))
        <div class="p-2 bg-neutral-950 border border-neutral-800 rounded-sm text-[10px] text-neutral-500 space-y-0.5">
          <div class="uppercase tracking-widest font-semibold text-neutral-300">ALLOWLISTED TOOL EXECUTION:</div>
          @foreach($msg->tool_calls as $tc)
            <div>&middot; Tool: <b class="text-cyan-500">{{ $tc['tool'] ?? 'extract_claims' }}</b></div>
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
               aria-label="Enter case question"
               placeholder="{{ $isId ? 'Tanyakan ekstraksi klaim, perbandingan angka, atau kutipan bukti pada kasus ini...' : 'Ask for claim extraction, numeric comparison, or evidence citations in this case...' }}"
               class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3.5 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500 placeholder-neutral-500">
      </div>
      <button type="submit" id="send-btn" aria-label="Send message" class="px-4 py-2 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 text-xs font-mono uppercase tracking-wider rounded-sm transition-opacity duration-150 flex items-center gap-2 font-semibold">
        <span>{{ $isId ? 'Kirim' : 'Send' }}</span>
        <i class="fa-solid fa-paper-plane text-xs"></i>
      </button>
    </form>
  </div>

</div>
<!-- [AiAnalystConsoleContainer] END -->

@endsection

@push('scripts')
<!-- [ModuleEScripts] START -->
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

    const userDiv = document.createElement('div');
    userDiv.className = 'flex justify-end';
    userDiv.innerHTML = `
      <div class="p-3 bg-neutral-900 border border-cyan-500/50 rounded-sm space-y-1 max-w-2xl text-right">
        <div class="text-[10px] uppercase tracking-widest text-cyan-500 font-semibold">
          OPERATOR &middot; NOW
        </div>
        <p class="font-sans text-neutral-300 text-xs text-left leading-relaxed">${escapeHtml(text)}</p>
      </div>
    `;
    msgContainer.appendChild(userDiv);
    input.value = '';
    sendBtn.disabled = true;
    scrollToBottom();

    // Monochrome skeleton loading bar (§4.6)
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'flex justify-start';
    loadingDiv.id = 'loading-placeholder';
    loadingDiv.innerHTML = `
      <div class="p-3 bg-neutral-900 border border-neutral-800 rounded-sm w-64 space-y-2">
        <div class="h-2.5 bg-neutral-800 animate-pulse rounded-none w-3/4"></div>
        <div class="h-2.5 bg-neutral-800 animate-pulse rounded-none w-1/2"></div>
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
            <div class="flex flex-wrap items-center gap-1.5 pt-1 text-[10px]">
              <span class="text-neutral-500 uppercase tracking-widest">EVIDENCE CITATIONS:</span>
              ${msg.sources.map(s => `<span class="px-1.5 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 rounded-sm">${escapeHtml(s)}</span>`).join('')}
            </div>
          `;
        }

        assistDiv.innerHTML = `
          <div class="p-3.5 bg-neutral-900 border border-neutral-800 rounded-sm space-y-2 max-w-2xl">
            <div class="flex items-center justify-between text-[10px] text-cyan-500 pb-1 border-b border-neutral-800 uppercase tracking-widest">
              <span class="font-semibold">AI ANALYST CONSOLE</span>
              <span class="text-neutral-500">${escapeHtml(msg.created_at || '')} UTC</span>
            </div>
            <p class="font-sans text-neutral-300 text-xs leading-relaxed whitespace-pre-wrap">${escapeHtml(msg.content)}</p>
            ${sourcesHtml}
          </div>
        `;
        msgContainer.appendChild(assistDiv);
      }
    } catch (err) {
      document.getElementById('loading-placeholder')?.remove();
    } finally {
      sendBtn.disabled = false;
      scrollToBottom();
    }
  });

  function escapeHtml(string) {
    const p = document.createElement("p");
    p.textContent = string;
    return p.innerHTML;
  }
</script>
<!-- [ModuleEScripts] END -->
@endpush
