@extends('layouts.app')

@php
  $isId = app()->getLocale() === 'id';
  $aiStatus = app(\App\Services\Ai\AiManager::class)->status();
  $aiDriver = strtoupper($aiStatus['driver'] ?? 'MOCK');
@endphp

@section('title', ($isId ? 'Konsol Analis AI' : 'AI Analyst Console') . ' // TRACE-S')

@section('content')
<div class="space-y-5">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
    <div>
      <div class="flex flex-wrap items-center gap-2">
        <h1 class="text-lg font-semibold tracking-tight">
          {{ $isId ? 'Konsol Dialog Analis AI Terkunci Kasus' : 'Case-Scoped AI Analyst Console' }}
        </h1>
      </div>
      <p class="text-xs text-[var(--text-muted)] mt-0.5">
        {{ $isId
          ? 'Terkunci hanya pada bukti kasus aktif (Case ID) menggunakan daftar alat backend resmi (allowlist) tanpa akses shell bebas.'
          : 'Locked strictly to active case evidence using backend allowlisted tools without shell execution.' }}
      </p>
    </div>

    <form action="{{ route('console.index') }}" method="GET" class="flex items-center gap-2 text-xs font-mono">
      <label class="label-meta">{{ $isId ? 'Kasus:' : 'Case:' }}</label>
      <select name="case_id" onchange="this.form.submit()" aria-label="Select case context" class="input-mil py-1.5 text-xs font-mono w-auto max-w-xs">
        @foreach($cases as $c)
          <option value="{{ $c->id }}" {{ $selectedCase && $selectedCase->id === $c->id ? 'selected' : '' }}>
            {{ $c->case_code }} — {{ Str::limit($c->title, 35) }}
          </option>
        @endforeach
      </select>
    </form>
  </div>

  <!-- Console Container -->
  <div class="panel flex flex-col h-[600px] overflow-hidden">

    <!-- Status Bar -->
    <div class="px-4 py-2.5 bg-[var(--bg-subtle)] border-b border-[var(--border-color)] flex flex-wrap items-center justify-between gap-2 font-mono text-xs">
      <div class="flex flex-wrap items-center gap-3">
        <span class="badge badge-olive">CASE-LOCKED SESSION</span>
        <span>CASE: <b>{{ $selectedCase?->case_code ?? 'UNBOUND' }}</b></span>
        <span>{{ $isId ? 'BUKTI:' : 'EVIDENCE:' }} <b>{{ $selectedCase?->evidence->count() ?? 0 }}</b></span>
      </div>

      <div class="flex flex-wrap items-center gap-2 text-[11px]">
        <span class="badge badge-neutral">ALLOWLIST: extract_claims, compare_numeric_entities, calculate_text_similarity, analyze_spread, analyze_source_relationships, cluster_narratives, generate_case_summary</span>
        <span class="badge badge-neutral">DRIVER: {{ $aiDriver }}</span>
        @if(($aiStatus['status'] ?? 'OFFLINE') === 'ONLINE' || !empty($aiStatus['cloud_configured']))
          <span class="badge badge-olive">ONLINE</span>
        @else
          <span class="badge badge-danger">{{ $aiStatus['status'] ?? 'OFFLINE' }}</span>
        @endif
      </div>
    </div>

    <!-- Scroll Area -->
    <div id="console-messages" class="flex-1 p-4 overflow-y-auto space-y-3.5 text-xs">

      <div class="panel-subtle p-3.5 max-w-3xl space-y-1.5">
        <div class="flex items-center justify-between text-[11px] font-mono border-b border-[var(--border-color)] pb-1">
          <span class="font-semibold"><i class="fa-solid fa-terminal mr-1.5 text-[var(--accent-olive)]"></i>TRACE-S CASE ASSISTANT</span>
          <span class="text-[var(--text-muted)]">{{ $selectedCase?->case_code ?? '-' }}</span>
        </div>
        <p class="leading-relaxed">
          {{ $isId
            ? 'Sesi ini terikat pada berkas bukti kasus ' . ($selectedCase?->case_code ?? '-') . '. Anda dapat meminta ekstraksi klaim, ringkasan bukti, atau rujukan sumber pada kasus ini.'
            : 'This session is scoped to evidence in case ' . ($selectedCase?->case_code ?? '-') . '. You may request claim extraction, summaries, or evidence citations.' }}
        </p>
      </div>

      @foreach($messages as $msg)
        @if($msg->role === 'user')
        <div class="flex justify-end">
          <div class="panel p-3 max-w-2xl border-r-4 border-r-[#586B47] space-y-1">
            <div class="label-meta text-right">{{ Auth::user()->trace_id ?? Auth::user()->name }} &middot; {{ $msg->created_at->format('H:i') }} UTC</div>
            <p class="leading-relaxed whitespace-pre-wrap">{{ $msg->content }}</p>
          </div>
        </div>
        @else
        <div class="flex justify-start">
          <div class="panel p-3.5 max-w-2xl border-l-4 border-l-[#46514C] space-y-2">
            <div class="flex items-center justify-between text-[11px] font-mono border-b border-[var(--border-color)] pb-1">
              <span class="font-semibold">AI ANALYST CONSOLE</span>
              <span class="text-[var(--text-muted)]">{{ $msg->created_at->format('H:i') }} UTC</span>
            </div>
            <p class="leading-relaxed whitespace-pre-wrap">{{ $msg->content }}</p>

            @if(!empty($msg->sources))
            <div class="flex flex-wrap items-center gap-1.5 pt-1">
              <span class="label-meta">{{ $isId ? 'Rujukan Bukti:' : 'Citations:' }}</span>
              @foreach($msg->sources as $src)
                <span class="badge badge-olive">{{ $src }}</span>
              @endforeach
            </div>
            @endif

            @if(!empty($msg->tool_calls))
            <div class="panel-subtle p-2 text-[11px] font-mono space-y-0.5">
              <div class="label-meta">ALLOWLISTED TOOL EXECUTION:</div>
              @foreach($msg->tool_calls as $tc)
                <div>&middot; Tool: <b>{{ is_array($tc) ? ($tc['tool'] ?? 'tool') : $tc }}</b></div>
              @endforeach
            </div>
            @endif
          </div>
        </div>
        @endif
      @endforeach

    </div>

    <!-- Input Bar -->
    <div class="p-3 bg-[var(--bg-subtle)] border-t border-[var(--border-color)]">
      <form id="console-form" class="flex items-center gap-2">
        <input type="hidden" id="session-id" value="{{ $session->id }}">
        <input type="text" id="console-input" required autocomplete="off"
               aria-label="Enter case question"
               placeholder="{{ $isId ? 'Tanyakan ekstraksi klaim, ringkasan, atau rujukan bukti pada kasus ini...' : 'Ask for claim extraction, case summary, or evidence citations...' }}"
               class="input-mil font-mono text-xs flex-1">
        <button type="submit" id="send-btn" class="btn-primary shrink-0">
          <span>{{ $isId ? 'Kirim' : 'Send' }}</span>
          <i class="fa-solid fa-paper-plane text-xs"></i>
        </button>
      </form>
    </div>

  </div>

</div>
@endsection

@push('scripts')
<script>
  (function () {
    const form = document.getElementById('console-form');
    const input = document.getElementById('console-input');
    const sendBtn = document.getElementById('send-btn');
    const msgContainer = document.getElementById('console-messages');
    const sessionId = document.getElementById('session-id').value;
    const actorLabel = @json(Auth::user()->trace_id ?? Auth::user()->name);

    function scrollToBottom() {
      msgContainer.scrollTop = msgContainer.scrollHeight;
    }
    scrollToBottom();

    function escapeHtml(str) {
      const p = document.createElement('p');
      p.textContent = str == null ? '' : String(str);
      return p.innerHTML;
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const text = input.value.trim();
      if (!text) return;

      const userDiv = document.createElement('div');
      userDiv.className = 'flex justify-end';
      userDiv.innerHTML = `
        <div class="panel p-3 max-w-2xl border-r-4 border-r-[#586B47] space-y-1">
          <div class="label-meta text-right">${escapeHtml(actorLabel)} &middot; NOW</div>
          <p class="leading-relaxed whitespace-pre-wrap">${escapeHtml(text)}</p>
        </div>
      `;
      msgContainer.appendChild(userDiv);
      input.value = '';
      sendBtn.disabled = true;
      scrollToBottom();

      const loadingDiv = document.createElement('div');
      loadingDiv.id = 'loading-placeholder';
      loadingDiv.className = 'flex justify-start';
      loadingDiv.innerHTML = `<div class="panel p-3 text-xs font-mono text-[var(--text-muted)]">Processing case-scoped query...</div>`;
      msgContainer.appendChild(loadingDiv);
      scrollToBottom();

      try {
        const res = await fetch("{{ route('console.message') }}", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-CSRF-TOKEN": "{{ csrf_token() }}"
          },
          body: JSON.stringify({ session_id: sessionId, message: text })
        });
        loadingDiv.remove();
        const json = await res.json();

        if (json.status === 'OK' && json.message) {
          const msg = json.message;
          const assistDiv = document.createElement('div');
          assistDiv.className = 'flex justify-start';
          let sourcesHtml = '';
          if (msg.sources && msg.sources.length) {
            sourcesHtml = `<div class="flex flex-wrap items-center gap-1.5 pt-1"><span class="label-meta">CITATIONS:</span>${msg.sources.map(s => `<span class="badge badge-olive">${escapeHtml(s)}</span>`).join('')}</div>`;
          }
          let toolsHtml = '';
          if (msg.tool_calls && msg.tool_calls.length) {
            toolsHtml = `<div class="panel-subtle p-2 text-[11px] font-mono space-y-0.5"><div class="label-meta">ALLOWLISTED TOOL EXECUTION:</div>${msg.tool_calls.map(tc => `<div>&middot; Tool: <b>${escapeHtml(tc && tc.tool ? tc.tool : String(tc))}</b></div>`).join('')}</div>`;
          }
          assistDiv.innerHTML = `
            <div class="panel p-3.5 max-w-2xl border-l-4 border-l-[#46514C] space-y-2">
              <div class="flex items-center justify-between gap-2 text-[11px] font-mono border-b border-[var(--border-color)] pb-1">
                <span class="font-semibold">AI ANALYST CONSOLE</span>
                <span class="text-[var(--text-muted)]">${escapeHtml(msg.created_at || '')}</span>
              </div>
              <p class="leading-relaxed whitespace-pre-wrap">${escapeHtml(msg.content)}</p>
              ${sourcesHtml}
              ${toolsHtml}
            </div>
          `;
          msgContainer.appendChild(assistDiv);
        }
      } catch (_) {
        document.getElementById('loading-placeholder')?.remove();
      } finally {
        sendBtn.disabled = false;
        scrollToBottom();
      }
    });
  })();
</script>
@endpush
