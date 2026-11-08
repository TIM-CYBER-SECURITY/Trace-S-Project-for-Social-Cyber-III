@extends('layouts.tactical')

@php
  $isId = app()->getLocale() === 'id';
  $currentTab = request()->query('tab', 'scan');
  $prefillText = request()->query('prefill', '');
  $aiStatus = app(\App\Services\Ai\AiManager::class)->status();
  $driverUpper = strtoupper($aiStatus['driver'] ?? 'MOCK');
  $isAiOffline = ($aiStatus['status'] ?? 'OFFLINE') === 'ERROR' || ($aiStatus['status'] ?? 'OFFLINE') === 'OFFLINE';
@endphp

@section('title', ($isId ? 'Pemeriksa Kredibilitas & Verifikasi' : 'Credibility Scanner & Verification') . ' // TRACE-S')

@section('content')

<!-- [ModuleBHeader] START -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div>
    <div class="flex items-center gap-2.5">
      <h1 class="text-sm font-semibold font-mono tracking-wide text-neutral-300 uppercase">
        {{ $isId ? 'Pemeriksa Kredibilitas, Komparator Keaslian & Lintas Sumber' : 'Credibility Scanner, Authenticity Comparator & Cross-Source Suite' }}
      </h1>
      <span class="text-[10px] font-mono px-2 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 rounded-sm">
        MODULE B (§5.3 &amp; §5.4)
      </span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">
      {{ $isId
        ? 'Memeriksa klaim berdasarkan bukti, membandingkan teks yang beredar dengan dokumen asli, dan menguji kemandirian sumber.'
        : 'Evidence-based claim inspection, word-by-word comparison against official documents, and source independence verification.' }}
    </p>
  </div>

  <!-- Case Selector Filter -->
  <form action="{{ route('scanner.index') }}" method="GET" class="flex items-center gap-2 text-xs font-mono">
    <input type="hidden" name="tab" value="{{ $currentTab }}">
    <label class="text-[10px] uppercase tracking-widest text-neutral-500">
      {{ $isId ? 'KASUS AKTIF:' : 'ACTIVE CASE:' }}
    </label>
    <select name="case_id" onchange="this.form.submit()" aria-label="Select active case" class="bg-neutral-900 border border-neutral-800 text-neutral-300 px-3 py-1.5 rounded-sm focus:outline-none focus:border-cyan-500 text-xs font-mono">
      @foreach($cases as $c)
        <option value="{{ $c->id }}" {{ $selectedCase && $selectedCase->id === $c->id ? 'selected' : '' }}>
          {{ $c->case_code }} — {{ Str::limit($c->title, 35) }}
        </option>
      @endforeach
    </select>
  </form>
</div>
<!-- [ModuleBHeader] END -->

@if($isAiOffline && !$aiStatus['is_simulation'])
<!-- [AiOfflineDegradationBanner] START -->
<div class="p-3 text-rose-600 bg-rose-500/10 border border-rose-600/50 rounded-sm flex items-center justify-between font-mono text-xs">
  <div class="flex items-center gap-2">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span>
      <b>AI SERVER OFFLINE (FR-022):</b>
      {{ $isId
        ? 'Server AI lokal sedang tidak terjangkau. Sistem tidak pernah memalsukan hasil analisis saat server offline.'
        : 'Local AI server is currently unreachable. The system never fabricates analysis results when the server is offline.' }}
    </span>
  </div>
  <span class="text-[10px] uppercase tracking-widest">DRIVER: {{ $driverUpper }}</span>
</div>
<!-- [AiOfflineDegradationBanner] END -->
@endif

<!-- [ModuleBTabsNavigation] START -->
<div class="flex flex-wrap items-center gap-1 border-b border-neutral-800 font-mono text-xs">
  <a href="{{ route('scanner.index', ['case_id' => $selectedCase?->id, 'tab' => 'scan']) }}"
     class="px-4 py-2.5 uppercase tracking-wider border-b-2 transition-opacity duration-150 flex items-center gap-2 {{ $currentTab === 'scan' ? 'border-cyan-500 text-cyan-500 bg-cyan-500/10 font-semibold' : 'border-transparent text-neutral-500 hover:text-neutral-300' }}">
    <i class="fa-solid fa-magnifying-glass text-xs"></i>
    <span>1. {{ $isId ? 'Pemeriksa Kredibilitas' : 'Credibility Scanner' }}</span>
  </a>

  <a href="{{ route('scanner.index', ['case_id' => $selectedCase?->id, 'tab' => 'compare']) }}"
     class="px-4 py-2.5 uppercase tracking-wider border-b-2 transition-opacity duration-150 flex items-center gap-2 {{ $currentTab === 'compare' ? 'border-cyan-500 text-cyan-500 bg-cyan-500/10 font-semibold' : 'border-transparent text-neutral-500 hover:text-neutral-300' }}">
    <i class="fa-solid fa-scale-balanced text-xs"></i>
    <span>2. {{ $isId ? 'Komparator Keaslian Dokumen' : 'Authenticity Comparator' }}</span>
  </a>

  <a href="{{ route('scanner.index', ['case_id' => $selectedCase?->id, 'tab' => 'crosssource']) }}"
     class="px-4 py-2.5 uppercase tracking-wider border-b-2 transition-opacity duration-150 flex items-center gap-2 {{ $currentTab === 'crosssource' ? 'border-cyan-500 text-cyan-500 bg-cyan-500/10 font-semibold' : 'border-transparent text-neutral-500 hover:text-neutral-300' }}">
    <i class="fa-solid fa-code-compare text-xs"></i>
    <span>3. {{ $isId ? 'Verifikasi Lintas Sumber (§5.4)' : 'Cross-Source Verification (§5.4)' }}</span>
  </a>
</div>
<!-- [ModuleBTabsNavigation] END -->

@if($currentTab === 'scan')
<!-- [CredibilityScannerTab] START -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

  <!-- Left Column: Scan Input Form (5 Cols) -->
  <div class="lg:col-span-5 bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-4">
    <div class="border-b border-neutral-800 pb-2.5 flex items-center justify-between">
      <div class="text-xs font-mono uppercase tracking-wide text-neutral-300 font-semibold flex items-center gap-2">
        <i class="fa-solid fa-terminal text-cyan-500"></i>
        <span>{{ $isId ? 'Input Teks / Klaim yang Diperiksa' : 'Claim / Content Input' }}</span>
      </div>
      <span class="text-[10px] font-mono text-neutral-500">UNTRUSTED INPUT GUARD</span>
    </div>

    <form action="{{ route('scanner.scan') }}" method="POST" class="space-y-4">
      @csrf
      <input type="hidden" name="case_id" value="{{ $selectedCase?->id }}">

      <div class="space-y-1">
        <label class="text-[10px] uppercase font-mono tracking-widest text-neutral-500">
          {{ $isId ? 'KASUS RUJUKAN:' : 'LINKED CASE:' }}
        </label>
        <div class="px-3 py-2 bg-neutral-950 border border-neutral-800 rounded-sm text-xs font-mono text-cyan-500 truncate">
          {{ $selectedCase?->case_code }} — {{ $selectedCase?->title }}
        </div>
      </div>

      <div class="space-y-1">
        <div class="flex items-center justify-between">
          <label for="scan-textarea" class="text-[10px] uppercase font-mono tracking-widest text-neutral-500">
            {{ $isId ? 'TEKS ATAU URL YANG AKAN DIPERIKSA' : 'TEXT OR URL TO INSPECT' }}
          </label>
          <button type="button"
                  onclick="document.getElementById('scan-textarea').value = '{{ addslashes($selectedCase?->claim_text ?? '') }}'"
                  class="text-[10px] font-mono text-cyan-500 hover:underline">
            {{ $isId ? 'Gunakan Klaim Kasus' : 'Load Case Claim' }}
          </button>
        </div>
        <textarea id="scan-textarea" name="text" rows="8" required
                  placeholder="{{ $isId ? 'Tempel teks berita, kutipan unggahan media sosial, atau klaim yang ingin diverifikasi...' : 'Paste news text, social media post excerpt, or claim to verify...' }}"
                  class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500 placeholder-neutral-500 leading-relaxed">{{ old('text', $prefillText ?: $selectedCase?->claim_text) }}</textarea>
      </div>

      <div class="p-2.5 bg-neutral-950 border border-neutral-800 rounded-sm text-[10px] font-mono text-neutral-500 space-y-1">
        <div class="flex items-center justify-between">
          <span>{{ $isId ? 'STATUS DRIVER AI:' : 'ACTIVE AI DRIVER:' }}</span>
          <b class="text-cyan-500">{{ $driverUpper }}</b>
        </div>
        <div class="flex items-center justify-between">
          <span>{{ $isId ? 'BUKTI KASUS TERSEDIA:' : 'AVAILABLE CASE EVIDENCE:' }}</span>
          <b class="text-neutral-300">{{ $selectedCase?->evidence->count() ?? 0 }} {{ $isId ? 'Berkas' : 'Items' }}</b>
        </div>
        <div class="pt-1 border-t border-neutral-800 text-neutral-500">
          {{ $isId
            ? 'Prinsip §2A-B: Sistem memverifikasi fakta terhadap bukti primer, bukan membungkam berita benar.'
            : 'Principle §2A-B: Verifies facts against primary evidence; never suppresses accurate news.' }}
        </div>
      </div>

      <button type="submit"
              aria-label="Initiate Cognitive Scan"
              class="w-full py-2.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 font-mono text-xs uppercase tracking-wider rounded-sm transition-opacity duration-150 flex items-center justify-center gap-2 font-semibold">
        <i class="fa-solid fa-bolt text-xs"></i>
        <span>[ INITIATE COGNITIVE SCAN ]</span>
      </button>
    </form>
  </div>

  <!-- Right Column: Scan Result (7 Cols) -->
  <div class="lg:col-span-7 bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-4">
    @if($latestAnalysis)
      @php
        $res = $latestAnalysis->result;
        $score = (int) ($latestAnalysis->credibility_score ?? 50);
        // §5.3: Red <40, Amber 40-79, Green >=80
        $scoreColor = $score < 40 ? 'text-rose-600' : ($score < 80 ? 'text-amber-500' : 'text-emerald-500');
        $ringColor = $score < 40 ? '#e11d48' : ($score < 80 ? '#f59e0b' : '#10b981');
      @endphp

      <!-- Result Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
        <div>
          <div class="flex items-center gap-2">
            <span class="text-xs font-mono font-semibold tracking-wide uppercase text-neutral-300">
              {{ $isId ? 'Hasil Pemeriksaan Kredibilitas' : 'Credibility Assessment Result' }}
            </span>
            @if($latestAnalysis->is_simulation)
            <span class="text-[10px] font-mono px-1.5 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm" title="is_simulation: true">
              {{ $isId ? 'SIMULASI' : 'SIMULATION' }}
            </span>
            @endif
          </div>
          <div class="text-[10px] font-mono text-neutral-500 mt-0.5">
            DRIVER: {{ strtoupper($latestAnalysis->driver ?? 'MOCK') }} &middot; ID: {{ substr($latestAnalysis->analysis_uuid, 0, 8) }}
          </div>
        </div>

        <!-- Official Classification Badges (§4.2) -->
        <div class="flex items-center gap-2 font-mono">
          @if($latestAnalysis->classification === 'DISINFORMATION')
            <span class="px-2 py-0.5 text-rose-600 bg-rose-500/10 border border-rose-600/50 text-xs font-semibold uppercase tracking-wider rounded-sm">
              DISINFORMATION
            </span>
          @elseif($latestAnalysis->classification === 'MISLEADING')
            <span class="px-2 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 text-xs font-semibold uppercase tracking-wider rounded-sm">
              MISLEADING
            </span>
          @else
            <span class="px-2 py-0.5 text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 text-xs font-semibold uppercase tracking-wider rounded-sm">
              {{ $latestAnalysis->classification }}
            </span>
          @endif

          <span class="px-2 py-0.5 bg-neutral-950 border border-neutral-800 text-neutral-300 text-[10px] uppercase rounded-sm">
            {{ $latestAnalysis->verification_status }}
          </span>
        </div>
      </div>

      <!-- Custom SVG Radial Score & Summary Box (§5.3) -->
      <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center bg-neutral-950 p-4 border border-neutral-800 rounded-sm">
        <!-- Custom SVG Radial Score (4 Cols) -->
        <div class="sm:col-span-4 flex flex-col items-center justify-center">
          <div class="relative w-28 h-28 flex items-center justify-center">
            <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100" aria-label="Credibility Indicator Score {{ $score }} out of 100">
              <circle cx="50" cy="50" r="40" stroke="#262626" stroke-width="8" fill="transparent"/>
              <circle cx="50" cy="50" r="40" stroke="{{ $ringColor }}" stroke-width="8" fill="transparent"
                      stroke-dasharray="251.2" stroke-dashoffset="{{ 251.2 - (251.2 * $score / 100) }}"
                      stroke-linecap="butt"/>
            </svg>
            <div class="absolute flex flex-col items-center">
              <span class="text-2xl font-semibold font-mono {{ $scoreColor }}">{{ $score }}</span>
              <span class="text-[10px] font-mono text-neutral-500 uppercase tracking-widest">/ 100</span>
            </div>
          </div>
          <!-- §5.3 Mandatory Label: "Credibility Indicator" / "Indikator Kredibilitas" (never "Truth Score") -->
          <div class="text-[10px] font-mono text-neutral-500 mt-1.5 uppercase tracking-widest text-center">
            {{ $isId ? 'Indikator Kredibilitas' : 'Credibility Indicator' }}
          </div>
        </div>

        <!-- Summary Box (8 Cols) -->
        <div class="sm:col-span-8 space-y-2 border-t sm:border-t-0 sm:border-l border-neutral-800 sm:pl-4">
          <div class="text-[10px] uppercase font-mono tracking-widest text-cyan-500">
            {{ $isId ? 'Ringkasan Analisis Berbasis Bukti' : 'Evidence-Based Analysis Summary' }}
          </div>
          <p class="text-xs font-sans text-neutral-300 leading-relaxed">
            {{ $res['tactical_summary'] ?? $res['summary'] ?? '-' }}
          </p>
          <div class="flex flex-wrap items-center gap-4 text-[10px] font-mono text-neutral-500 pt-1">
            <span>
              {{ $isId ? 'Level Ancaman:' : 'Threat Level:' }}
              <b class="{{ $latestAnalysis->threat_level === 'KRITIS' ? 'text-rose-600' : 'text-amber-500' }}">{{ $latestAnalysis->threat_level }}</b>
            </span>
            <span>&middot;</span>
            <span>
              {{ $isId ? 'Keyakinan Model:' : 'Model Confidence:' }}
              <b class="text-cyan-500">{{ round(($latestAnalysis->confidence ?? 0.5) * 100) }}%</b>
            </span>
          </div>
        </div>
      </div>

      <!-- Emotion & Logical Fallacy Breakdown (§5.3) -->
      <div class="space-y-1.5">
        <div class="text-[10px] uppercase font-mono tracking-widest text-neutral-500">
          {{ $isId ? 'Rincian Pola Bahasa & Kesalahan Logika' : 'Linguistic Pattern & Logical Fallacy Breakdown' }}
        </div>
        <div class="bg-neutral-950 border border-neutral-800 rounded-sm overflow-hidden text-xs font-mono">
          <table class="w-full text-left">
            <tbody class="divide-y divide-neutral-800 leading-tight">
              <tr>
                <td class="py-2 px-3 text-[10px] text-neutral-500 uppercase tracking-widest w-44">
                  {{ $isId ? 'Nada Bahasa Emosional' : 'Emotional Tone Pattern' }}
                </td>
                <td class="py-2 px-3 text-neutral-300 font-sans">{{ $res['linguistic_analysis']['emotion'] ?? '-' }}</td>
              </tr>
              <tr>
                <td class="py-2 px-3 text-[10px] text-neutral-500 uppercase tracking-widest">
                  {{ $isId ? 'Kesalahan Logika (Fallacy)' : 'Logical Fallacy' }}
                </td>
                <td class="py-2 px-3 text-neutral-300 font-sans">{{ $res['linguistic_analysis']['fallacy'] ?? '-' }}</td>
              </tr>
              <tr>
                <td class="py-2 px-3 text-[10px] text-neutral-500 uppercase tracking-widest">
                  {{ $isId ? 'Kesesuaian Bukti Rujukan' : 'Reference Evidence Match' }}
                </td>
                <td class="py-2 px-3 text-neutral-300 font-sans">{{ $res['linguistic_analysis']['source_verification'] ?? '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Atomic Claims Extraction Table: FACT / OPINION / ALLEGATION / QUOTE (§5.3) -->
      <div class="space-y-1.5">
        <div class="text-[10px] uppercase font-mono tracking-widest text-neutral-500">
          {{ $isId ? 'Tabel Ekstraksi Klaim Atomik (FAKTA / OPINI / DUGAAN / KUTIPAN)' : 'Atomic Claim Extraction Table (FACT / OPINION / ALLEGATION / QUOTE)' }}
        </div>
        <div class="overflow-x-auto bg-neutral-950 border border-neutral-800 rounded-sm">
          <table class="w-full text-left font-mono text-xs">
            <thead>
              <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-widest text-neutral-500">
                <th class="py-2 px-3">ID</th>
                <th class="py-2 px-3">{{ $isId ? 'PERNYATAAN KLAIM' : 'CLAIM STATEMENT' }}</th>
                <th class="py-2 px-3">{{ $isId ? 'JENIS' : 'TYPE' }}</th>
                <th class="py-2 px-3">{{ $isId ? 'STATUS VERIFIKASI' : 'CHECK STATUS' }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-800 leading-tight">
              @forelse($res['claims'] ?? [] as $cl)
              <tr>
                <td class="py-2 px-3 text-cyan-500 font-semibold">[{{ $cl['id'] }}]</td>
                <td class="py-2 px-3 text-neutral-300 font-sans">{{ $cl['text'] }}</td>
                <td class="py-2 px-3">
                  <span class="px-1.5 py-0.5 text-[10px] bg-neutral-900 border border-neutral-800 text-neutral-300 uppercase rounded-sm">
                    {{ $cl['type'] }}
                  </span>
                </td>
                <td class="py-2 px-3">
                  @if(($cl['check_status'] ?? '') === 'TERBANTAHKAN' || ($cl['check_status'] ?? '') === 'REFUTED')
                    <span class="px-1.5 py-0.5 text-[10px] text-rose-600 bg-rose-500/10 border border-rose-600/50 uppercase rounded-sm">
                      {{ $cl['check_status'] }}
                    </span>
                  @else
                    <span class="px-1.5 py-0.5 text-[10px] text-amber-500 bg-amber-500/10 border border-amber-500/30 uppercase rounded-sm">
                      {{ $cl['check_status'] }}
                    </span>
                  @endif
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="4" class="py-3 text-center text-neutral-500">
                  {{ $isId ? 'Tidak ada klaim atomik yang diekstrak.' : 'No atomic claims extracted.' }}
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <!-- Reference Sources List with Access Status (§5.3) -->
      <div class="space-y-1.5">
        <div class="text-[10px] uppercase font-mono tracking-widest text-neutral-500">
          {{ $isId ? 'Daftar Sumber Rujukan & Status Akses' : 'Reference Sources & Access Status' }}
        </div>
        <div class="overflow-x-auto bg-neutral-950 border border-neutral-800 rounded-sm">
          <table class="w-full text-left font-mono text-xs">
            <thead>
              <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-widest text-neutral-500">
                <th class="py-2 px-3">CODE</th>
                <th class="py-2 px-3">{{ $isId ? 'PENERBIT / SUMBER' : 'PUBLISHER / SOURCE' }}</th>
                <th class="py-2 px-3">{{ $isId ? 'PERAN' : 'ROLE' }}</th>
                <th class="py-2 px-3">{{ $isId ? 'STATUS AKSES' : 'ACCESS STATUS' }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-800 leading-tight">
              @forelse($selectedCase?->evidence ?? [] as $evItem)
              <tr>
                <td class="py-2 px-3 text-cyan-500 font-semibold">{{ $evItem->evidence_code }}</td>
                <td class="py-2 px-3 text-neutral-300 font-sans">{{ $evItem->publisher }} — {{ Str::limit($evItem->title, 40) }}</td>
                <td class="py-2 px-3 text-[10px] text-neutral-500 uppercase">{{ $evItem->role }}</td>
                <td class="py-2 px-3">
                  @if($evItem->access_status === 'OK')
                    <span class="px-1.5 py-0.5 text-[10px] text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 rounded-sm">ACCESSIBLE (OK)</span>
                  @else
                    <span class="px-1.5 py-0.5 text-[10px] text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm">{{ $evItem->access_status }}</span>
                  @endif
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="4" class="py-3 text-center text-neutral-500">—</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <!-- Analysis Limitations Panel (ALWAYS VISIBLE, NOT OPTIONAL — §5.3) -->
      <div class="p-3 bg-neutral-950 border border-amber-500/30 rounded-sm text-xs font-mono space-y-1.5">
        <div class="text-[10px] uppercase tracking-widest text-amber-500 font-semibold flex items-center gap-1.5">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <span>{{ $isId ? 'Keterbatasan Analisis (Wajib Ditampilkan — §5.3):' : 'Analysis Limitations (Mandatory Disclosure — §5.3):' }}</span>
        </div>
        <ul class="list-disc pl-4 space-y-1 text-neutral-300 font-sans text-xs">
          @if(!empty($res['limitations']))
            @foreach($res['limitations'] as $lim)
              <li>{{ $lim }}</li>
            @endforeach
          @else
            <li>{{ $isId ? 'Penilaian terbatas pada dokumen bukti yang tersimpan di dalam berkas kasus aktif.' : 'Assessment is strictly bounded to evidence documents stored within the active case file.' }}</li>
            <li>{{ $isId ? 'Skor kredibilitas merupakan indikator bantu bagi analis, bukan penentu kebenaran mutlak.' : 'Credibility score is an assistive indicator for analysts, not an absolute truth verdict.' }}</li>
          @endif
        </ul>
      </div>

      <!-- [AnalystValidationPanel] START -->
      <div class="p-3.5 bg-neutral-950 border border-cyan-500/50 rounded-sm space-y-3">
        <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
          <div class="text-xs font-mono uppercase tracking-wide text-cyan-500 font-semibold flex items-center gap-1.5">
            <i class="fa-solid fa-user-check"></i>
            <span>{{ $isId ? 'Keputusan Validasi Analis (Human-in-the-Loop — §2.5 & §5.3)' : 'Analyst Validation Decision (Human-in-the-Loop — §2.5 & §5.3)' }}</span>
          </div>
          <span class="text-[10px] font-mono uppercase px-2 py-0.5 bg-neutral-900 border border-neutral-800 text-neutral-300 rounded-sm">
            STATUS: <b>{{ $latestAnalysis->validation_status }}</b>
          </span>
        </div>

        @if(Auth::check() && Auth::user()->hasRole('analis'))
        <form action="{{ route('scanner.review') }}" method="POST" class="space-y-3 text-xs font-mono">
          @csrf
          <input type="hidden" name="analysis_id" value="{{ $latestAnalysis->id }}">

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <label class="p-2 bg-neutral-900 border border-emerald-500/30 rounded-sm flex items-center gap-2 text-emerald-500 cursor-pointer">
              <input type="radio" name="decision" value="VALIDATED" checked class="text-emerald-500 focus:ring-0">
              <span class="font-semibold">[VALIDATE]</span>
            </label>
            <label class="p-2 bg-neutral-900 border border-rose-600/50 rounded-sm flex items-center gap-2 text-rose-600 cursor-pointer">
              <input type="radio" name="decision" value="REJECTED" class="text-rose-600 focus:ring-0">
              <span class="font-semibold">[REJECT]</span>
            </label>
            <label class="p-2 bg-neutral-900 border border-amber-500/30 rounded-sm flex items-center gap-2 text-amber-500 cursor-pointer">
              <input type="radio" name="decision" value="RECLASSIFIED" class="text-amber-500 focus:ring-0">
              <span class="font-semibold">[RECLASSIFY]</span>
            </label>
          </div>

          <div class="space-y-1">
            <label class="text-[10px] uppercase tracking-widest text-neutral-500">
              {{ $isId ? 'ALASAN KEPUTUSAN ANALIS (WAJIB DIISI SEBELUM SUBMIT — §5.3):' : 'ANALYST JUSTIFICATION REASON (MANDATORY BEFORE SUBMIT — §5.3):' }}
            </label>
            <textarea name="reason" rows="2" required
                      placeholder="{{ $isId ? 'Tuliskan alasan bukti yang mendasari keputusan validasi, penolakan, atau reklasifikasi...' : 'State the evidence-based rationale for validating, rejecting, or reclassifying...' }}"
                      class="w-full bg-neutral-900 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500 placeholder-neutral-500"></textarea>
          </div>

          <div class="flex justify-end">
            <button type="submit" aria-label="Submit analyst validation" class="px-4 py-1.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 text-xs font-mono uppercase tracking-wider rounded-sm font-semibold">
              {{ $isId ? 'Simpan Keputusan Analis' : 'Submit Analyst Decision' }}
            </button>
          </div>
        </form>
        @else
        <div class="text-xs font-mono text-neutral-500">
          {{ $isId
            ? '* Hanya personel dengan peran ANALIS yang dapat mengirimkan keputusan [VALIDATE] / [REJECT] / [RECLASSIFY]. Gunakan menu profil di kanan atas untuk beralih ke peran Analis.'
            : '* Only personnel with the ANALYST role can submit [VALIDATE] / [REJECT] / [RECLASSIFY] decisions. Use the top-right profile menu to switch to Analyst role.' }}
        </div>
        @endif
      </div>
      <!-- [AnalystValidationPanel] END -->

    @else
      <!-- Even when no scan is run, show structure & limitations disclosure -->
      <div class="h-full flex flex-col justify-between space-y-4">
        <div class="flex flex-col items-center justify-center py-16 text-center text-neutral-500 font-mono space-y-2">
          <i class="fa-solid fa-magnifying-glass text-3xl text-neutral-500"></i>
          <div class="text-xs uppercase tracking-wider text-neutral-300 font-semibold">
            {{ $isId ? 'Belum Ada Hasil Pindaian pada Kasus Ini' : 'No Scan Result Recorded for This Case Yet' }}
          </div>
          <div class="text-xs max-w-sm font-sans">
            {{ $isId
              ? 'Pilih kasus dan klik tombol [INITIATE COGNITIVE SCAN] di panel kiri untuk memulai pemeriksaan.'
              : 'Select a case and click [INITIATE COGNITIVE SCAN] on the left panel to begin inspection.' }}
          </div>
        </div>

        <div class="p-3 bg-neutral-950 border border-amber-500/30 rounded-sm text-xs font-mono space-y-1">
          <div class="text-[10px] uppercase tracking-widest text-amber-500 font-semibold">
            {{ $isId ? 'Keterbatasan Analisis (§5.3):' : 'Analysis Limitations (§5.3):' }}
          </div>
          <p class="text-neutral-500 font-sans text-xs">
            {{ $isId
              ? 'Sistem hanya memeriksa klaim terhadap bukti yang tersedia di dalam berkas kasus dan tidak menghasilkan vonis tanpa tinjauan manusia.'
              : 'The system only evaluates claims against evidence present in the case file and never issues a final verdict without human review.' }}
          </p>
        </div>
      </div>
    @endif
  </div>

</div>
<!-- [CredibilityScannerTab] END -->

@elseif($currentTab === 'compare')
<!-- [AuthenticityComparatorTab] START -->
<div class="space-y-4">

  <!-- Selection Form -->
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
    <div class="border-b border-neutral-800 pb-2.5 flex items-center justify-between">
      <div class="text-xs font-mono uppercase tracking-wide text-neutral-300 font-semibold flex items-center gap-2">
        <i class="fa-solid fa-scale-balanced text-cyan-500"></i>
        <span>{{ $isId ? 'Komparator Keaslian: Konten Beredar vs Dokumen Referensi Resmi (§5.3 Tab 2)' : 'Authenticity Comparator: Circulating Content vs Official Reference Document (§5.3 Tab 2)' }}</span>
      </div>
      <span class="text-[10px] font-mono text-cyan-500">WORD-BY-WORD DIFF</span>
    </div>

    <form action="{{ route('scanner.compare') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
      @csrf
      <input type="hidden" name="case_id" value="{{ $selectedCase?->id }}">

      <div class="sm:col-span-5 space-y-1">
        <label class="text-[10px] uppercase font-mono tracking-widest text-emerald-500">
          {{ $isId ? 'DOKUMEN REFERENSI RESMI (PRIMER):' : 'OFFICIAL REFERENCE DOCUMENT (PRIMARY):' }}
        </label>
        <select name="reference_id" required aria-label="Select official reference document" class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
          @foreach($selectedCase?->evidence ?? [] as $ev)
            <option value="{{ $ev->id }}" {{ $ev->role === 'PRIMARY' || $ev->role === 'REFERENCE' ? 'selected' : '' }}>
              [{{ $ev->evidence_code }}] {{ Str::limit($ev->title, 40) }} ({{ $ev->role }})
            </option>
          @endforeach
        </select>
      </div>

      <div class="sm:col-span-5 space-y-1">
        <label class="text-[10px] uppercase font-mono tracking-widest text-rose-600">
          {{ $isId ? 'KONTEN BEREDAR (PEMBANDING):' : 'CIRCULATING CONTENT (COMPARED):' }}
        </label>
        <select name="compared_id" required aria-label="Select circulating content" class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
          @foreach($selectedCase?->evidence ?? [] as $ev)
            <option value="{{ $ev->id }}" {{ $ev->role === 'COMPARED' || $ev->role === 'POST' ? 'selected' : '' }}>
              [{{ $ev->evidence_code }}] {{ Str::limit($ev->title, 40) }} ({{ $ev->role }})
            </option>
          @endforeach
        </select>
      </div>

      <div class="sm:col-span-2">
        <button type="submit" aria-label="Run authenticity comparison" class="w-full py-2 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 text-xs font-mono uppercase tracking-wider rounded-sm font-semibold">
          {{ $isId ? 'Bandingkan' : 'Compare' }}
        </button>
      </div>
    </form>
  </div>

  @if($latestComparison)
    @php
      $diffJson = $latestComparison->diff_json;
      $refSegs = $diffJson['diff']['ref_segments'] ?? [];
      $cmpSegs = $diffJson['diff']['cmp_segments'] ?? [];
      $changes = $diffJson['changes'] ?? [];
    @endphp

    <!-- Two Windows: Official Reference Document vs Circulating Content (§5.3 Tab 2) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Window 1: Official Reference Document -->
      <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-2.5">
        <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
          <div class="text-xs font-mono uppercase tracking-wide text-emerald-500 font-semibold flex items-center gap-1.5">
            <i class="fa-solid fa-file-shield"></i>
            <span>{{ $isId ? 'Dokumen Referensi Resmi' : 'Official Reference Document' }} [{{ $diffJson['reference']['id'] ?? 'REF' }}]</span>
          </div>
          <span class="text-[10px] font-mono text-neutral-500">{{ $diffJson['reference']['publisher'] ?? '-' }}</span>
        </div>

        <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-sm font-sans text-xs leading-relaxed min-h-[140px] max-h-60 overflow-y-auto">
          @foreach($refSegs as $seg)
            @if($seg['s'] === 'removed')
              <!-- Yellow: Removed / Lost Context (§5.3 Tab 2) -->
              <span class="text-amber-500 bg-amber-500/10 border border-amber-500/30 px-1 py-0.5 rounded-sm font-medium" title="Removed / Lost Context">{{ $seg['t'] }}</span>
            @else
              <!-- Green: Unchanged / Matched (§5.3 Tab 2) -->
              <span class="text-emerald-500">{{ $seg['t'] }}</span>
            @endif
          @endforeach
        </div>
        <div class="text-[10px] font-mono text-neutral-500 select-all">
          SHA-256: {{ $diffJson['reference']['hash'] ?? 'N/A' }}
        </div>
      </div>

      <!-- Window 2: Circulating Content -->
      <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-2.5">
        <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
          <div class="text-xs font-mono uppercase tracking-wide text-rose-600 font-semibold flex items-center gap-1.5">
            <i class="fa-solid fa-file-circle-exclamation"></i>
            <span>{{ $isId ? 'Konten Beredar' : 'Circulating Content' }} [{{ $diffJson['compared']['id'] ?? 'CMP' }}]</span>
          </div>
          <span class="text-[10px] font-mono text-neutral-500">{{ $diffJson['compared']['publisher'] ?? '-' }}</span>
        </div>

        <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-sm font-sans text-xs leading-relaxed min-h-[140px] max-h-60 overflow-y-auto">
          @foreach($cmpSegs as $seg)
            @if($seg['s'] === 'added')
              <!-- Red: Altered / Added (§5.3 Tab 2) -->
              <span class="text-rose-600 bg-rose-500/10 border border-rose-600/50 px-1 py-0.5 rounded-sm font-semibold" title="Altered / Added">{{ $seg['t'] }}</span>
            @else
              <!-- Green: Unchanged / Matched (§5.3 Tab 2) -->
              <span class="text-emerald-500">{{ $seg['t'] }}</span>
            @endif
          @endforeach
        </div>
        <div class="text-[10px] font-mono text-neutral-500 select-all">
          SHA-256: {{ $diffJson['compared']['hash'] ?? 'N/A' }}
        </div>
      </div>
    </div>

    <!-- Diff Legend Bar (§5.3 Tab 2: Green matched, Red changed/added, Amber/Yellow removed/lost context) -->
    <div class="flex flex-wrap items-center gap-4 px-3 py-2 bg-neutral-900 border border-neutral-800 rounded-sm text-xs font-mono">
      <span class="text-neutral-500 font-semibold text-[10px] uppercase tracking-widest">{{ $isId ? 'LEGENDA DIFF:' : 'DIFF LEGEND:' }}</span>
      <span class="flex items-center gap-1.5 text-emerald-500">
        <span class="w-3 h-3 bg-emerald-500/10 border border-emerald-500/30 inline-block"></span>
        {{ $isId ? 'Hijau: Sama dengan Dokumen Resmi' : 'Green: Matches Official Document' }}
      </span>
      <span class="flex items-center gap-1.5 text-rose-600">
        <span class="w-3 h-3 bg-rose-500/10 border border-rose-600/50 inline-block"></span>
        {{ $isId ? 'Merah: Berubah / Ditambahkan' : 'Red: Altered / Added' }}
      </span>
      <span class="flex items-center gap-1.5 text-amber-500">
        <span class="w-3 h-3 bg-amber-500/10 border border-amber-500/30 inline-block"></span>
        {{ $isId ? 'Kuning: Dihapus / Kehilangan Konteks' : 'Yellow/Amber: Removed / Lost Context' }}
      </span>
    </div>

    <!-- Numeric Disparity Matrix & Factual Semantic Impact (§5.3 Tab 2) -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
      <div class="text-xs font-mono uppercase tracking-wide text-cyan-500 font-semibold flex items-center gap-2">
        <i class="fa-solid fa-table"></i>
        <span>{{ $isId ? 'Matriks Disparitas Angka (Nominal, Persentase, Tanggal) & Dampak Makna' : 'Numeric Disparity Matrix (Nominal, Percentage, Date) & Semantic Impact' }}</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left font-mono text-xs">
          <thead>
            <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-widest text-neutral-500 bg-neutral-950">
              <th class="py-2 px-3">{{ $isId ? 'ELEMEN' : 'ELEMENT' }}</th>
              <th class="py-2 px-3">{{ $isId ? 'DOKUMEN PRIMER' : 'PRIMARY DOCUMENT' }}</th>
              <th class="py-2 px-3">{{ $isId ? 'KONTEN BEREDAR' : 'CIRCULATING CONTENT' }}</th>
              <th class="py-2 px-3">{{ $isId ? 'JENIS PERUBAHAN' : 'CHANGE TYPE' }}</th>
              <th class="py-2 px-3">{{ $isId ? 'DAMPAK MAKNA BAGI PEMBACA' : 'SEMANTIC IMPACT ON READER' }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-800 font-sans leading-tight">
            @forelse($changes as $ch)
            <tr class="hover:bg-neutral-800/50">
              <td class="py-2.5 px-3 font-mono font-semibold text-neutral-300 uppercase text-xs">{{ $ch['element'] }}</td>
              <td class="py-2.5 px-3 text-emerald-500 font-mono text-xs">{{ $ch['original'] }}</td>
              <td class="py-2.5 px-3 text-rose-600 font-mono text-xs font-semibold">{{ $ch['compared'] }}</td>
              <td class="py-2.5 px-3 font-mono text-[10px]">
                <span class="px-1.5 py-0.5 text-rose-600 bg-rose-500/10 border border-rose-600/50 uppercase rounded-sm">{{ $ch['change_type'] }}</span>
              </td>
              <td class="py-2.5 px-3 text-neutral-300 text-xs leading-relaxed max-w-md">{{ $ch['meaning_impact'] }}</td>
            </tr>
            @empty
            <tr>
              <td colspan="5" class="py-4 text-center text-neutral-500 font-mono">
                {{ $isId ? 'Tidak ditemukan disparitas angka atau teks.' : 'No numeric or textual disparity detected.' }}
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <!-- Factual Semantic Impact Summary (1-2 factual sentences — §5.3 Tab 2) -->
      <div class="p-3 bg-neutral-950 border border-cyan-500/50 rounded-sm space-y-1">
        <div class="text-[10px] uppercase font-mono tracking-widest text-cyan-500 font-semibold">
          {{ $isId ? 'Ringkasan Dampak Semantik (1–2 Kalimat Faktual — §5.3):' : 'Semantic Impact Summary (1–2 Factual Sentences — §5.3):' }}
        </div>
        <p class="text-xs font-sans text-neutral-300 leading-relaxed">
          {{ $diffJson['assessment'] }}
        </p>
      </div>
    </div>
  @else
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-12 text-center text-neutral-500 font-mono space-y-2">
      <i class="fa-solid fa-scale-balanced text-3xl text-neutral-500"></i>
      <div class="text-xs uppercase tracking-wider text-neutral-300 font-semibold">
        {{ $isId ? 'Pilih Dua Bukti untuk Memulai Komparasi' : 'Select Two Evidence Items to Compare' }}
      </div>
    </div>
  @endif

</div>
<!-- [AuthenticityComparatorTab] END -->

@elseif($currentTab === 'crosssource')
<!-- [CrossSourceVerificationWorkspace] START -->
<div class="space-y-4">

  <!-- Header & Run Button -->
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
      <div class="flex items-center gap-2">
        <h2 class="text-sm font-mono font-semibold uppercase tracking-wide text-neutral-300">
          {{ $isId ? 'Workspace Verifikasi Lintas Sumber (§5.4 FR-025 s.d. FR-027)' : 'Cross-Source Verification Workspace (§5.4 FR-025 to FR-027)' }}
        </h2>
        <span class="text-[10px] font-mono px-1.5 py-0.5 text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 rounded-sm">
          LEXICAL &ne; SEMANTIC
        </span>
      </div>
      <p class="text-xs text-neutral-500 mt-0.5">
        {{ $isId
          ? 'Memisahkan skor kemiripan leksikal dan semantik, serta membedakan banyak sumber yang mengutip 1 dokumen primer vs liputan independen.'
          : 'Separates lexical and semantic similarity scores, and distinguishes multiple outlets quoting a single primary document vs independent reporting.' }}
      </p>
    </div>

    <form action="{{ route('scanner.crosssource') }}" method="POST">
      @csrf
      <input type="hidden" name="case_id" value="{{ $selectedCase?->id }}">
      <button type="submit" aria-label="Run Cross-Source Verification" class="px-4 py-2 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 text-xs font-mono uppercase tracking-wider rounded-sm font-semibold flex items-center gap-2">
        <i class="fa-solid fa-rotate text-xs"></i>
        <span>{{ $isId ? 'Jalankan Verifikasi Lintas Sumber' : 'Run Cross-Source Verification' }}</span>
      </button>
    </form>
  </div>

  @if($crossResult)
    <!-- 1. Source Independence Engine Panel (§5.4) -->
    <div class="p-4 bg-neutral-900 border border-amber-500/30 rounded-sm space-y-2">
      <div class="flex items-center justify-between">
        <div class="text-xs font-mono uppercase tracking-wide text-amber-500 font-semibold flex items-center gap-2">
          <i class="fa-solid fa-sitemap"></i>
          <span>{{ $isId ? 'Source Independence Engine (Kemandirian Sumber — §5.4)' : 'Source Independence Engine (§5.4)' }}</span>
        </div>
        <span class="text-[10px] font-mono px-2 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm">
          {{ $crossResult['independent_units'] ?? 1 }} {{ $isId ? 'KLASTER INDEPENDEN' : 'INDEPENDENT CLUSTERS' }}
        </span>
      </div>
      <p class="text-xs text-neutral-300 font-sans leading-relaxed">
        {{ $crossResult['independence_assessment'] }}
      </p>
      <div class="text-[10px] font-mono text-neutral-500">
        {{ $isId
          ? '* Banyak artikel portal berita yang mengutip 1 rilis resmi yang sama dihitung sebagai 1 rujukan primer, bukan banyak bukti terpisah.'
          : '* Multiple news articles quoting the same single press release are counted as 1 primary reference, not multiple independent proofs.' }}
      </div>
    </div>

    <!-- 2. Source List, Source Authenticity & Primary Evidence (§5.4) -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
      <div class="text-xs font-mono uppercase tracking-wide text-neutral-300 font-semibold">
        {{ $isId ? 'Daftar Sumber, Keaslian Sumber & Bukti Primer (Source List & Primary Evidence)' : 'Source List, Source Authenticity & Primary Evidence' }}
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left font-mono text-xs">
          <thead>
            <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-widest text-neutral-500 bg-neutral-950">
              <th class="py-2 px-3">EVIDENCE ID</th>
              <th class="py-2 px-3">{{ $isId ? 'PENERBIT / SUMBER' : 'PUBLISHER / SOURCE' }}</th>
              <th class="py-2 px-3">{{ $isId ? 'KATEGORI BUKTI' : 'EVIDENCE ROLE' }}</th>
              <th class="py-2 px-3">{{ $isId ? 'STATUS AKSES' : 'AUTHENTICITY / ACCESS' }}</th>
              <th class="py-2 px-3">SHA-256 HASH</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-800 leading-tight">
            @foreach($selectedCase?->evidence ?? [] as $ev)
            <tr class="hover:bg-neutral-800/50">
              <td class="py-2.5 px-3 text-cyan-500 font-semibold">{{ $ev->evidence_code }}</td>
              <td class="py-2.5 px-3 text-neutral-300 font-sans">{{ $ev->publisher }} — {{ $ev->title }}</td>
              <td class="py-2.5 px-3">
                @if($ev->role === 'PRIMARY')
                  <span class="px-1.5 py-0.5 text-[10px] text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 rounded-sm">PRIMARY EVIDENCE</span>
                @elseif($ev->role === 'REFERENCE')
                  <span class="px-1.5 py-0.5 text-[10px] text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 rounded-sm">REFERENCE SOURCE</span>
                @else
                  <span class="px-1.5 py-0.5 text-[10px] text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm">{{ $ev->role }}</span>
                @endif
              </td>
              <td class="py-2.5 px-3 text-emerald-500 text-[10px]">{{ $ev->access_status }}</td>
              <td class="py-2.5 px-3 text-neutral-500 text-[10px]">{{ substr($ev->content_hash ?? 'N/A', 0, 16) }}...</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <!-- 3. Claim Matching & Similarity Comparison (Lexical vs Semantic SEPARATED — §5.4) -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-neutral-800 pb-2.5">
        <div class="text-xs font-mono uppercase tracking-wide text-neutral-300 font-semibold">
          {{ $isId ? 'Pencocokan Klaim & Perbandingan Kemiripan (Leksikal vs Semantik Terpisah)' : 'Claim Matching & Similarity Comparison (Lexical vs Semantic Separated)' }}
        </div>
        <span class="text-[10px] font-mono text-neutral-500">
          {{ $isId ? 'Skor kemiripan bukan persentase kebenaran' : 'Similarity score is not truth probability' }}
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left font-mono text-xs">
          <thead>
            <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-widest text-neutral-500 bg-neutral-950">
              <th class="py-2.5 px-3">{{ $isId ? 'PASANGAN SUMBER' : 'SOURCE PAIR' }}</th>
              <th class="py-2.5 px-3">{{ $isId ? 'HUBUNGAN SUMBER' : 'RELATIONSHIP' }}</th>
              <th class="py-2.5 px-3">LEXICAL SIMILARITY</th>
              <th class="py-2.5 px-3">SEMANTIC SIMILARITY</th>
              <th class="py-2.5 px-3">CLAIM MATCHING</th>
              <th class="py-2.5 px-3">{{ $isId ? 'TEMUAN VERIFIKASI' : 'VERIFICATION FINDINGS' }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-800 leading-tight">
            @forelse($crossResult['comparisons_detail'] as $cmp)
            <tr class="hover:bg-neutral-800/50">
              <td class="py-2.5 px-3 font-semibold text-neutral-300">
                <span class="text-cyan-500">{{ $cmp['evidence_a'] }}</span> ({{ $cmp['publisher_a'] }})<br>
                <span class="text-cyan-500">{{ $cmp['evidence_b'] }}</span> ({{ $cmp['publisher_b'] }})
              </td>
              <td class="py-2.5 px-3 text-[10px]">
                <span class="px-1.5 py-0.5 bg-neutral-950 border border-neutral-800 text-neutral-300 uppercase rounded-sm">
                  {{ $cmp['relationship'] }}
                </span>
              </td>
              <td class="py-2.5 px-3 font-semibold text-neutral-300">
                {{ $cmp['lexical_similarity'] }}%
                <span class="block text-[10px] text-neutral-500 font-normal">Jaccard / Shingle</span>
              </td>
              <td class="py-2.5 px-3 font-semibold text-cyan-500">
                {{ $cmp['semantic_similarity'] ? $cmp['semantic_similarity'] . '%' : 'N/A' }}
                <span class="block text-[10px] text-neutral-500 font-normal">Vector Cosine</span>
              </td>
              <td class="py-2.5 px-3">
                @if($cmp['claim_match_status'] === 'MATCH')
                  <span class="px-1.5 py-0.5 text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 text-[10px] uppercase rounded-sm">MATCH</span>
                @elseif($cmp['claim_match_status'] === 'CONFLICT')
                  <span class="px-1.5 py-0.5 text-rose-600 bg-rose-500/10 border border-rose-600/50 text-[10px] uppercase rounded-sm">CONFLICT</span>
                @else
                  <span class="px-1.5 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 text-[10px] uppercase rounded-sm">{{ $cmp['claim_match_status'] }}</span>
                @endif
              </td>
              <td class="py-2.5 px-3 font-sans text-neutral-300 text-xs leading-relaxed max-w-xs">
                {{ $cmp['explanation'] }}
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="6" class="py-4 text-center text-neutral-500">—</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <!-- 4. Analyst Action Buttons in Cross-Source Workspace (§5.4 same as §5.3) -->
    @if($latestAnalysis)
    <div class="p-3.5 bg-neutral-900 border border-cyan-500/50 rounded-sm space-y-3">
      <div class="text-xs font-mono uppercase tracking-wide text-cyan-500 font-semibold">
        {{ $isId ? 'Keputusan Validasi Analis atas Temuan Lintas Sumber (§5.4)' : 'Analyst Validation Decision on Cross-Source Findings (§5.4)' }}
      </div>
      @if(Auth::check() && Auth::user()->hasRole('analis'))
      <form action="{{ route('scanner.review') }}" method="POST" class="space-y-3 text-xs font-mono">
        @csrf
        <input type="hidden" name="analysis_id" value="{{ $latestAnalysis->id }}">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
          <label class="p-2 bg-neutral-950 border border-emerald-500/30 rounded-sm flex items-center gap-2 text-emerald-500 cursor-pointer">
            <input type="radio" name="decision" value="VALIDATED" checked>
            <span class="font-semibold">[VALIDATE]</span>
          </label>
          <label class="p-2 bg-neutral-950 border border-rose-600/50 rounded-sm flex items-center gap-2 text-rose-600 cursor-pointer">
            <input type="radio" name="decision" value="REJECTED">
            <span class="font-semibold">[REJECT]</span>
          </label>
          <label class="p-2 bg-neutral-950 border border-amber-500/30 rounded-sm flex items-center gap-2 text-amber-500 cursor-pointer">
            <input type="radio" name="decision" value="RECLASSIFIED">
            <span class="font-semibold">[RECLASSIFY]</span>
          </label>
        </div>
        <textarea name="reason" rows="2" required
                  placeholder="{{ $isId ? 'Wajib isi alasan keputusan berdasarkan hasil verifikasi lintas sumber...' : 'Mandatory reason based on cross-source verification findings...' }}"
                  class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500"></textarea>
        <div class="flex justify-end">
          <button type="submit" class="px-4 py-1.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 uppercase rounded-sm font-semibold">
            {{ $isId ? 'Simpan Keputusan Analis' : 'Submit Analyst Decision' }}
          </button>
        </div>
      </form>
      @else
      <div class="text-xs font-mono text-neutral-500">
        {{ $isId ? '* Beralih ke peran ANALIS untuk mengirim keputusan validasi.' : '* Switch to ANALYST role to submit validation decisions.' }}
      </div>
      @endif
    </div>
    @endif
  @endif

</div>
<!-- [CrossSourceVerificationWorkspace] END -->
@endif

@endsection
