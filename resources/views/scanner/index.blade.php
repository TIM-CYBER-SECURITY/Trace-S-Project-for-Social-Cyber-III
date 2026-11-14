@extends('layouts.app')

@php
  $isId = app()->getLocale() === 'id';
  $currentTab = request()->query('tab', 'scan');
  $prefillText = request()->query('prefill', '');
  $driverUpper = strtoupper($aiStatus['driver'] ?? 'MOCK');
  $isSim = (bool) ($aiStatus['is_simulation'] ?? true);
  $isAiOffline = ($aiStatus['status'] ?? 'OFFLINE') === 'ERROR' || ($aiStatus['status'] ?? 'OFFLINE') === 'OFFLINE';
  $canReview = Auth::check() && Auth::user()->hasPermission('analysis.review');
  $canExport = Auth::check() && Auth::user()->hasPermission('report.export');
  $canGenerateReport = Auth::check() && Auth::user()->hasPermission('report.generate');
  $aiRam = $hardware['ai_process_ram_gb'] ?? null;
@endphp

@section('title', ($isId ? 'Konsol Investigasi & Verifikasi AI' : 'AI Investigation & Credibility Scanner') . ' // TRACE-S')

@section('content')
<div class="space-y-4">

  <!-- Header & Active Local AI Selector Bar -->
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
    <div>
      <div class="label-meta">C4ISR // MULTI-CHANNEL FACT VERIFICATION</div>
      <h1 class="font-mono text-sm font-bold uppercase tracking-wider mt-0.5">
        {{ $isId ? 'Konsol Investigasi AI Lokal, Lintas Sumber & Deteksi Timpa Teks' : 'Local AI Investigation, Cross-Source & Manipulation Console' }}
      </h1>
    </div>

    <div class="flex flex-wrap items-center gap-2 font-mono text-xs">
      <!-- Quick Local AI Model Activator -->
      <form action="{{ route('scanner.select-model') }}" method="POST" class="flex items-center gap-1.5">
        @csrf
        <span class="label-meta">AI:</span>
        <select name="model_id" onchange="this.form.submit()" aria-label="Select owned local AI model" class="input-mil py-1 font-mono text-[11px] w-auto max-w-[230px]">
          @forelse($ownedModels as $om)
            <option value="{{ $om->id }}" {{ ($aiStatus['active_model'] ?? '') === $om->name ? 'selected' : '' }}>
              {{ $om->name }} ({{ $om->server?->driver === 'ollama' ? 'Ollama' : 'Local' }}{{ $om->estimated_ram_gb ? ' • ~' . $om->estimated_ram_gb . 'GB' : '' }})
            </option>
          @empty
            <option value="">{{ $driverUpper }} ({{ $aiStatus['active_model'] ?? 'NONE' }})</option>
          @endforelse
        </select>
        @if($aiRam !== null && $aiRam > 0)
          <span class="badge badge-olive tabular-nums" title="{{ $hardware['ai_active_process'] ?? '' }}">
            RAM AI: {{ number_format((float) $aiRam, 2) }} GB
          </span>
        @endif
      </form>

      <!-- Case Switcher -->
      <form action="{{ route('scanner.index') }}" method="GET" class="flex items-center gap-1.5">
        <input type="hidden" name="tab" value="{{ $currentTab }}">
        <select name="case_id" onchange="this.form.submit()" aria-label="Select active case" class="input-mil py-1 font-mono text-[11px] w-auto max-w-[220px]">
          <option value="auto" {{ request()->query('case_id') === 'auto' ? 'selected' : '' }}>+ {{ $isId ? 'KASUS BARU (OTOMATIS)' : 'AUTO-NEW CASE' }}</option>
          @foreach($cases as $c)
            <option value="{{ $c->id }}" {{ $selectedCase && $selectedCase->id === $c->id && request()->query('case_id') !== 'auto' ? 'selected' : '' }}>
              {{ $c->case_code }} — {{ Str::limit($c->title, 28) }}
            </option>
          @endforeach
        </select>
      </form>
    </div>
  </div>

  @if($isAiOffline && !$isSim)
  <div class="panel p-3 border-l-4 border-l-[#991B1B] flex flex-wrap items-center justify-between gap-2 text-xs font-mono">
    <div class="flex items-center gap-2 text-[#991B1B] dark:text-[#FCA5A5]">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <span><b>AI SERVER OFFLINE:</b> {{ $isId ? 'Aktifkan model AI lokal di atas atau nyalakan engine LM Studio / Bionic / Ollama.' : 'Local AI server is offline.' }}</span>
    </div>
    <span class="badge badge-danger">{{ $driverUpper }}</span>
  </div>
  @endif

  <!-- Agency Hybrid Cloud AI Configuration (Shared per Kode Instansi for Operators & Admins) -->
  @include('partials.cloud-ai-panel', [
    'formAction' => route('scanner.cloud-ai.configure'),
    'testUrl' => route('scanner.cloud-ai.test'),
    'compactCollapsible' => true,
  ])

  <!-- Tabs Navigation -->
  <div class="flex flex-wrap items-center gap-1 border-b border-[var(--border-color)] font-mono text-xs">
    <a href="{{ route('scanner.index', ['case_id' => $selectedCase?->id, 'tab' => 'scan']) }}"
       class="px-4 py-2.5 uppercase tracking-wider border-b-2 flex items-center gap-2 {{ $currentTab === 'scan' ? 'border-[#586B47] text-[var(--accent-olive)] dark:text-[#C5B89C] font-semibold bg-[var(--bg-subtle)]' : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-primary)]' }}">
      <i class="fa-solid fa-magnifying-glass text-xs"></i>
      <span>1. {{ $isId ? 'Investigasi & Scan Kredibilitas' : 'Investigation & Scanner' }}</span>
    </a>

    <a href="{{ route('scanner.index', ['case_id' => $selectedCase?->id, 'tab' => 'compare']) }}"
       class="px-4 py-2.5 uppercase tracking-wider border-b-2 flex items-center gap-2 {{ $currentTab === 'compare' ? 'border-[#586B47] text-[var(--accent-olive)] dark:text-[#C5B89C] font-semibold bg-[var(--bg-subtle)]' : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-primary)]' }}">
      <i class="fa-solid fa-scale-balanced text-xs"></i>
      <span>2. {{ $isId ? 'Komparator Keaslian' : 'Authenticity Comparator' }}</span>
    </a>

    <a href="{{ route('scanner.index', ['case_id' => $selectedCase?->id, 'tab' => 'crosssource']) }}"
       class="px-4 py-2.5 uppercase tracking-wider border-b-2 flex items-center gap-2 {{ $currentTab === 'crosssource' ? 'border-[#586B47] text-[var(--accent-olive)] dark:text-[#C5B89C] font-semibold bg-[var(--bg-subtle)]' : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-primary)]' }}">
      <i class="fa-solid fa-code-compare text-xs"></i>
      <span>3. {{ $isId ? 'Verifikasi Lintas Sumber' : 'Cross-Source Verification' }}</span>
    </a>
  </div>

  @if($currentTab === 'scan')
  <!-- 4-Stage Operational Architecture Bar -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 font-mono text-[11px]">
    <div class="panel-subtle p-2.5 border-l-2 border-l-[#586B47]">
      <div class="text-[9px] text-[var(--text-muted)] uppercase">TAHAP 1 // INGESTION</div>
      <div class="font-bold mt-0.5">{{ $isId ? 'Akuisisi & URL Fetcher' : 'Acquisition & URL Fetcher' }}</div>
    </div>
    <div class="panel-subtle p-2.5 border-l-2 border-l-[#586B47]">
      <div class="text-[9px] text-[var(--text-muted)] uppercase">TAHAP 2 // ENGINE</div>
      <div class="font-bold mt-0.5">{{ $isId ? 'Local AI & Cross-Corpus' : 'Local AI & Cross-Corpus' }}</div>
    </div>
    <div class="panel-subtle p-2.5 border-l-2 border-l-[#B47818]">
      <div class="text-[9px] text-[var(--text-muted)] uppercase">TAHAP 3 // ANALYSIS</div>
      <div class="font-bold mt-0.5">{{ $isId ? 'Timpa Teks & Independensi' : 'Text-Overlay & Independence' }}</div>
    </div>
    <div class="panel-subtle p-2.5 border-l-2 border-l-[#17251F] dark:border-l-[#C5B89C]">
      <div class="text-[9px] text-[var(--text-muted)] uppercase">TAHAP 4 // OUTPUT</div>
      <div class="font-bold mt-0.5">{{ $isId ? 'Laporan PDF & Spreadsheet' : 'PDF & Spreadsheet Export' }}</div>
    </div>
  </div>

  <!-- Tab 1: End-to-End Investigation Pipeline -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <!-- Left Input Panel -->
    <div class="lg:col-span-5 space-y-4">
      <div class="panel p-4 space-y-3.5">
        <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-2.5">
          <span class="font-mono text-xs font-semibold uppercase tracking-wider">
            {{ $isId ? 'Input Kata Kunci / Tautan / Konten' : 'Keyword / Link / Content Input' }}
          </span>
          <span class="badge {{ $isSim ? 'badge-warn' : 'badge-olive' }}">{{ $aiStatus['active_model'] ? Str::limit($aiStatus['active_model'], 18) : $driverUpper }}</span>
        </div>

        <form id="investigation-scan-form" action="{{ route('scanner.scan') }}" method="POST" class="space-y-3.5">
          @csrf
          <div>
            <label for="scan-case-id" class="label-meta block mb-1">{{ $isId ? 'Target Kasus' : 'Target Case' }}</label>
            <select id="scan-case-id" name="case_id" class="input-mil font-mono text-xs">
              <option value="auto" {{ !$selectedCase || request()->query('case_id') === 'auto' ? 'selected' : '' }}>
                {{ $isId ? '[OTOMATIS] Buat Kasus Investigasi Baru' : '[AUTO] Create New Investigation Case' }}
              </option>
              @foreach($cases as $c)
                <option value="{{ $c->id }}" {{ $selectedCase && $selectedCase->id === $c->id && request()->query('case_id') !== 'auto' ? 'selected' : '' }}>
                  {{ $c->case_code }} — {{ Str::limit($c->title, 34) }}
                </option>
              @endforeach
            </select>
          </div>

          <div>
            <label for="scan-url" class="label-meta block mb-1">{{ $isId ? 'Tautan / Link Berita atau Sosmed (Opsional)' : 'News / Social Media Link (Optional)' }}</label>
            <input id="scan-url" type="url" name="source_url" value="{{ old('source_url') }}"
                   placeholder="https://..." class="input-mil font-mono text-xs">
          </div>

          <div class="space-y-1">
            <div class="flex items-center justify-between">
              <label for="scan-textarea" class="label-meta">{{ $isId ? 'Kata Kunci, Link, atau Narasi Konten' : 'Keyword, Link, or Content Excerpt' }}</label>
              @if($selectedCase)
                <button type="button" id="load-case-claim-btn"
                        data-claim="{{ $selectedCase->claim_text }}"
                        onclick="document.getElementById('scan-textarea').value = this.getAttribute('data-claim') || ''"
                        class="text-[11px] font-mono text-[var(--accent-olive)] dark:text-[#C5B89C] hover:underline">
                  {{ $isId ? 'Muat Klaim Kasus' : 'Load Case Claim' }}
                </button>
              @endif
            </div>
            <textarea id="scan-textarea" name="text" rows="7" required
                      placeholder="{{ $isId ? 'Masukkan kata kunci isu, link berita, atau tempel konten/berita yang ingin diselidiki...' : 'Enter keyword, URL, or paste news/social content to investigate...' }}"
                      class="input-mil font-mono text-xs leading-relaxed">{{ old('text', $prefillText ?: ($selectedCase?->claim_text ?? '')) }}</textarea>
          </div>

          <button type="submit" id="run-investigation-btn" class="btn-primary w-full justify-center">
            <i class="fa-solid fa-magnifying-glass-chart"></i>
            <span>{{ $isId ? 'Jalankan Investigasi AI Lokal' : 'Run Local AI Investigation' }}</span>
          </button>
        </form>
      </div>

      <!-- Real-Time Live Investigation Progress Box (shown immediately on submit) -->
      <div id="live-investigation-progress" class="hidden panel p-4 border-l-4 border-l-[#586B47] space-y-2.5 font-mono text-xs">
        <div class="flex items-center justify-between">
          <span class="font-bold uppercase tracking-wider text-[var(--accent-olive)] dark:text-[#C5B89C]">
            <i class="fa-solid fa-circle-notch fa-spin mr-1.5"></i>
            {{ $isId ? 'PROSES INVESTIGASI REAL-TIME AI LOKAL' : 'REAL-TIME LOCAL AI INVESTIGATION' }}
          </span>
          <span class="badge badge-olive">WORKING</span>
        </div>
        <div id="live-progress-steps" class="space-y-1.5 text-[11px]"></div>
      </div>
    </div>

    <!-- Right Result Panel -->
    <div class="lg:col-span-7 panel p-4 space-y-4">
      @if($latestAnalysis)
        @php
          $res = $latestAnalysis->result ?? [];
          $score = (int) ($latestAnalysis->credibility_score ?? 50);
          $ringColor = $score < 40 ? '#991B1B' : ($score < 70 ? '#B47818' : '#586B47');
          $scoreClass = $score < 40 ? 'text-[#991B1B] dark:text-[#FCA5A5]' : ($score < 70 ? 'text-[#8A580B] dark:text-[#E7C37B]' : 'text-[#3E4D31] dark:text-[#D5E2C8]');
          $highlights = $res['manipulation_highlights'] ?? [];
          $extractedKeywords = $res['extracted_keywords'] ?? [];
          $crossMatches = $res['cross_channel_matches'] ?? [];
          $traceSteps = $res['investigation_trace'] ?? [];
          $indepAssess = $res['independence_assessment'] ?? null;
          $searchQueryUsed = $res['search_query'] ?? null;
        @endphp

        <!-- Result Header + Direct PDF & Spreadsheet Export -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[var(--border-color)] pb-3">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-mono text-xs font-semibold uppercase tracking-wider">
                {{ $isId ? 'Hasil Investigasi Berita, Medsos & AI Lokal' : 'Real-Time Investigation & AI Verdict' }}
              </span>
              <span class="badge {{ in_array($latestAnalysis->classification, ['DISINFORMATION', 'MANIPULATED_CONTENT'], true) ? 'badge-danger' : ($latestAnalysis->classification === 'MISLEADING' ? 'badge-warn' : 'badge-olive') }}">
                {{ $latestAnalysis->classification }}
              </span>
              <span class="badge {{ $latestAnalysis->verification_status === 'TERVERIFIKASI' ? 'badge-olive' : ($latestAnalysis->verification_status === 'TERBANTAHKAN' ? 'badge-danger' : 'badge-warn') }}">
                {{ $latestAnalysis->verification_status }}
              </span>
            </div>
            <div class="text-[11px] font-mono text-[var(--text-muted)] mt-0.5 tabular-nums">
              MODEL: {{ $latestAnalysis->model_name ?? strtoupper($latestAnalysis->driver ?? 'MOCK') }}
              &middot; ID: {{ substr($latestAnalysis->analysis_uuid, 0, 8) }}
              @if($searchQueryUsed) &middot; QUERY: "{{ Str::limit($searchQueryUsed, 42) }}" @endif
            </div>
          </div>

          <!-- Direct Export Controls -->
          <div class="flex flex-wrap items-center gap-1.5 shrink-0">
            @if($canExport)
              <a href="{{ route('scanner.export.spreadsheet', $latestAnalysis->id) }}" class="btn-secondary py-1 px-2.5 text-[10px]">
                <i class="fa-solid fa-file-excel"></i>
                <span>SPREADSHEET</span>
              </a>
              <a href="{{ route('scanner.export.pdf', $latestAnalysis->id) }}" class="btn-primary py-1 px-2.5 text-[10px]">
                <i class="fa-solid fa-file-pdf"></i>
                <span>EXPORT PDF</span>
              </a>
            @elseif($canGenerateReport && $selectedCase)
              <form action="{{ route('reports.generate') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="case_id" value="{{ $selectedCase->id }}">
                <button type="submit" class="btn-primary py-1 px-2.5 text-[10px]">
                  <i class="fa-regular fa-file-lines"></i>
                  <span>{{ $isId ? 'Buat Intel Brief' : 'Intel Brief' }}</span>
                </button>
              </form>
            @endif
          </div>
        </div>

        <!-- BAGIAN 1: Highlight Kata Kunci & Deteksi Anomali Konten -->
        <div class="space-y-2.5">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="label-meta">
              1. {{ $isId ? 'Highlight Kata Kunci, Entitas & Deteksi Timpa Teks' : 'Keyword Focus & Text-Overlay Highlights' }}
            </span>
            <span class="badge {{ collect($highlights)->contains(fn ($h) => ($h['severity'] ?? '') === 'HIGH') ? 'badge-danger' : 'badge-olive' }}">
              {{ count($highlights) }} {{ $isId ? 'HIGHLIGHT AKTIF' : 'HIGHLIGHTS' }}
            </span>
          </div>

          @if(count($extractedKeywords) > 0)
            <div class="flex flex-wrap items-center gap-1.5 font-mono text-[10px]">
              <span class="text-[var(--text-muted)] uppercase mr-1">{{ $isId ? 'Kata Kunci:' : 'Keywords:' }}</span>
              @foreach($extractedKeywords as $kw)
                <span class="badge {{ ($kw['category'] ?? '') === 'ENTITAS' ? 'badge-olive' : (($kw['category'] ?? '') === 'WAKTU_ANGKA' ? 'badge-warn' : 'badge-sand') }}">
                  [{{ $kw['category'] ?? 'KEY' }}] {{ $kw['term'] ?? '' }}
                </span>
              @endforeach
            </div>
          @endif

          <div class="panel-subtle p-3.5 text-xs font-mono leading-relaxed max-h-48 overflow-y-auto">
            {!! $highlightedHtml ?? nl2br(e($latestAnalysis->source_text)) !!}
          </div>

          @if(count($highlights) > 0)
            <div class="overflow-x-auto border border-[var(--border-color)] rounded">
              <table class="table-mil">
                <thead>
                  <tr>
                    <th>TYPE</th>
                    <th>{{ $isId ? 'KATA KUNCI / SEGMEN TERSOROT' : 'HIGHLIGHTED SEGMENT' }}</th>
                    <th>{{ $isId ? 'KETERANGAN / DIAGNOSIS' : 'DIAGNOSTIC REASON' }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($highlights as $hl)
                    @php $sev = $hl['severity'] ?? 'MEDIUM'; @endphp
                    <tr>
                      <td class="font-mono whitespace-nowrap">
                        <span class="badge {{ $sev === 'HIGH' ? 'badge-danger' : ($sev === 'KEYWORD' ? 'badge-olive' : 'badge-warn') }}">{{ $hl['type'] ?? 'ANOMALY' }}</span>
                      </td>
                      <td class="font-mono text-xs font-bold {{ $sev === 'HIGH' ? 'text-[#991B1B] dark:text-[#FCA5A5]' : 'text-[var(--accent-olive)] dark:text-[#D5E2C8]' }}">"{{ $hl['segment'] ?? '-' }}"</td>
                      <td class="text-xs">{{ $hl['reason'] ?? '-' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>

        <!-- BAGIAN 2: Daftar Lengkap Berita Publik & Konten Sosial Media Real-Time -->
        <div class="space-y-2 pt-2 border-t border-[var(--border-color)]">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="label-meta">
              2. {{ $isId ? 'Daftar Berita Publik & Konten Sosial Media Real-Time' : 'Real-Time Public News & Social Media Sources' }}
            </span>
            <span class="badge badge-olive font-mono">{{ count($crossMatches) }} {{ $isId ? 'SUMBER DITEMUKAN' : 'SOURCES FOUND' }}</span>
          </div>

          @if(count($crossMatches) > 0)
            <div class="overflow-x-auto border border-[var(--border-color)] rounded">
              <table class="table-mil">
                <thead>
                  <tr>
                    <th>{{ $isId ? 'KANAL & TANGGAL' : 'CHANNEL & DATE' }}</th>
                    <th>{{ $isId ? 'JUDUL BERITA / KONTEN & TAUTAN SUMBER' : 'ARTICLE TITLE & SOURCE URL' }}</th>
                    <th>{{ $isId ? 'KESAMAAN' : 'MATCH' }}</th>
                    <th>{{ $isId ? 'RINGKASAN ISI' : 'EXCERPT' }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($crossMatches as $cm)
                    <tr>
                      <td class="font-mono whitespace-nowrap">
                        <span class="badge {{ !empty($cm['is_reference']) ? 'badge-olive' : 'badge-neutral' }}">{{ $cm['channel'] ?? '-' }}</span>
                        <div class="text-[10px] font-semibold mt-0.5">{{ $cm['publisher'] ?? '-' }}</div>
                        @if(!empty($cm['published_at']))
                          <div class="text-[9px] text-[var(--text-muted)]">{{ $cm['published_at'] }}</div>
                        @endif
                      </td>
                      <td class="text-xs max-w-[230px]">
                        @if(!empty($cm['url']))
                          <a href="{{ $cm['url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-[var(--accent-olive)] dark:text-[#CFE2BF] hover:underline">
                            {{ $cm['title'] ?? '-' }} <i class="fa-solid fa-arrow-up-right-from-square text-[9px] ml-0.5"></i>
                          </a>
                          <div class="text-[10px] font-mono text-[var(--text-muted)] truncate">{{ $cm['url'] }}</div>
                        @else
                          <div class="font-semibold">{{ $cm['title'] ?? '-' }}</div>
                        @endif
                      </td>
                      <td class="font-mono font-bold tabular-nums whitespace-nowrap">{{ $cm['similarity_pct'] ?? 0 }}%</td>
                      <td class="text-[11px] text-[var(--text-secondary)] max-w-xs">{{ Str::limit($cm['excerpt'] ?? '-', 170) }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="panel-subtle p-3 text-xs font-mono text-[var(--text-muted)]">
              {{ $isId ? 'Belum ditemukan artikel berita atau konten media sosial yang memenuhi kecocokan entitas kata kunci.' : 'No matching real-time news or social media items found for the keywords.' }}
            </div>
          @endif

          @if(!empty($indepAssess))
          <div class="panel-subtle p-3 border-l-4 border-l-[#586B47] space-y-1 text-xs">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <span class="label-meta">{{ $isId ? 'Evaluasi Kemandirian Sumber & Anti-Sindikasi' : 'Source Independence & Anti-Syndication Assessment' }}</span>
              <span class="badge badge-olive font-mono">{{ $indepAssess['independent_units'] ?? 0 }} {{ $isId ? 'UNIT INDEPENDEN' : 'INDEPENDENT UNITS' }}</span>
            </div>
            <p class="text-xs leading-relaxed text-[var(--text-secondary)]">{{ $indepAssess['statement'] ?? '-' }}</p>
          </div>
          @endif
        </div>

        <!-- BAGIAN 3: Analisis & Penentuan Status Kredibilitas Oleh AI Lokal / Hybrid Engine -->
        <div class="space-y-3 pt-2 border-t border-[var(--border-color)]">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="label-meta">
              3. {{ $isId ? 'Analisis & Penentuan Status Kredibilitas' : 'AI Analysis & Final Credibility Status' }}
            </div>
            @php $tier = $res['execution_tier'] ?? 'LOCAL_DIRECT'; @endphp
            @if($tier === 'CLOUD_PRIMARY')
              <span class="badge badge-olive font-mono">ENGINE: CLOUD AI ({{ strtoupper((string) ($res['cloud_provider'] ?? 'CLOUD')) }})</span>
            @elseif($tier === 'LOCAL_FAILOVER' || !empty($res['failover_triggered']))
              <span class="badge badge-warn font-mono">AUTO-FAILOVER -> AI LOKAL ({{ $latestAnalysis->model_name }})</span>
            @else
              <span class="badge badge-neutral font-mono">ENGINE: AI LOKAL ({{ $latestAnalysis->model_name ?? $latestAnalysis->driver }})</span>
            @endif
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center panel-subtle p-4">
            <div class="sm:col-span-4 flex flex-col items-center justify-center">
              <div class="relative w-24 h-24 flex items-center justify-center">
                <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                  <circle cx="50" cy="50" r="40" stroke="currentColor" stroke-opacity="0.15" stroke-width="8" fill="transparent"/>
                  <circle cx="50" cy="50" r="40" stroke="{{ $ringColor }}" stroke-width="8" fill="transparent"
                          stroke-dasharray="251.2" stroke-dashoffset="{{ 251.2 - (251.2 * $score / 100) }}" stroke-linecap="butt"/>
                </svg>
                <div class="absolute flex flex-col items-center tabular-nums">
                  <span class="text-xl font-bold font-mono {{ $scoreClass }}">{{ $score }}</span>
                  <span class="text-[10px] font-mono text-[var(--text-muted)]">/ 100</span>
                </div>
              </div>
              <div class="label-meta mt-1.5 text-center">{{ $isId ? 'Skor Kredibilitas' : 'Credibility Score' }}</div>
            </div>

            <div class="sm:col-span-8 space-y-2 border-t sm:border-t-0 sm:border-l border-[var(--border-color)] pt-3 sm:pt-0 sm:pl-4">
              <div class="flex flex-wrap items-center gap-2">
                <span class="label-meta">{{ $isId ? 'Kesimpulan Fakta & Status Akhir:' : 'Fact-Based Conclusion:' }}</span>
                <span class="badge {{ $latestAnalysis->verification_status === 'TERVERIFIKASI' ? 'badge-olive' : ($latestAnalysis->verification_status === 'TERBANTAHKAN' ? 'badge-danger' : 'badge-warn') }}">
                  {{ $latestAnalysis->verification_status }}
                </span>
              </div>
              <p class="text-xs leading-relaxed">{{ $res['tactical_summary'] ?? $res['summary'] ?? '-' }}</p>
              <div class="flex flex-wrap items-center gap-3 text-[11px] font-mono text-[var(--text-muted)] pt-1 tabular-nums">
                <span>{{ $isId ? 'Klasifikasi:' : 'Classification:' }} <b>{{ $latestAnalysis->classification }}</b></span>
                <span>&middot;</span>
                <span>{{ $isId ? 'Ancaman:' : 'Threat:' }} <b>{{ $latestAnalysis->threat_level }}</b></span>
                <span>&middot;</span>
                <span>{{ $isId ? 'Keyakinan:' : 'Confidence:' }} <b>{{ round(($latestAnalysis->confidence ?? 0.5) * 100) }}%</b></span>
              </div>
            </div>
          </div>

          @if(count($traceSteps) > 0)
            <div class="grid grid-cols-1 gap-1 font-mono text-[11px]">
              @foreach($traceSteps as $st)
                <div class="panel-subtle px-3 py-1.5 flex items-center justify-between gap-2">
                  <div class="min-w-0">
                    <span class="font-bold text-[var(--accent-olive)] dark:text-[#C5B89C]">[{{ $st['step'] }}]</span>
                    <span>{{ $st['label'] }}</span>
                    <div class="text-[10px] text-[var(--text-muted)] truncate">{{ $st['detail'] ?? '' }}</div>
                  </div>
                  <span class="badge badge-olive shrink-0">{{ $st['status'] ?? 'OK' }}</span>
                </div>
              @endforeach
            </div>
          @endif
        </div>

        <!-- Atomic Claims Table -->
        <div class="space-y-1.5">
          <div class="label-meta">{{ $isId ? 'Rincian Klaim & Status Verifikasi' : 'Extracted Claims Breakdown' }}</div>
          <div class="overflow-x-auto border border-[var(--border-color)] rounded">
            <table class="table-mil">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>{{ $isId ? 'Pernyataan Klaim' : 'Claim Statement' }}</th>
                  <th>{{ $isId ? 'Jenis' : 'Type' }}</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($res['claims'] ?? [] as $cl)
                <tr>
                  <td class="font-mono font-semibold">[{{ $cl['id'] }}]</td>
                  <td class="text-xs">{{ $cl['text'] }}</td>
                  <td><span class="badge badge-neutral">{{ $cl['type'] }}</span></td>
                  <td>
                    @php $cs = $cl['check_status'] ?? ''; @endphp
                    <span class="badge {{ $cs === 'TERBANTAHKAN' || $cs === 'REFUTED' ? 'badge-danger' : ($cs === 'TERVERIFIKASI' ? 'badge-olive' : 'badge-warn') }}">{{ $cs }}</span>
                  </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-[var(--text-muted)] py-3">—</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        <!-- Mandatory Limitations & Analyst Validation -->
        <div class="panel-subtle p-3 border-l-4 border-l-[#B47818] space-y-1 text-xs">
          <div class="label-meta text-[#8A580B] dark:text-[#E7C37B]">
            {{ $isId ? 'Keterbatasan Analisis' : 'Analysis Limitations' }}
          </div>
          <ul class="list-disc pl-4 space-y-0.5 text-[var(--text-secondary)]">
            @forelse($res['limitations'] ?? [] as $lim)
              <li>{{ $lim }}</li>
            @empty
              <li>{{ $isId ? 'Penilaian didasarkan pada dataset bukti dan korpus lokal aktif.' : 'Assessment bounded to active case evidence and local corpus.' }}</li>
            @endforelse
          </ul>
        </div>

        <!-- Human-in-the-Loop Validation Panel -->
        <div class="panel p-4 border-l-4 border-l-[#586B47] space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border-color)] pb-2">
            <span class="font-mono text-xs font-semibold uppercase tracking-wider">
              {{ $isId ? 'Validasi Analis' : 'Analyst Validation' }}
            </span>
            <span class="badge badge-neutral">STATUS: {{ $latestAnalysis->validation_status }}</span>
          </div>

          @if($canReview)
          <form action="{{ route('scanner.review') }}" method="POST" class="space-y-3 text-xs font-mono">
            @csrf
            <input type="hidden" name="analysis_id" value="{{ $latestAnalysis->id }}">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
              <label class="panel-subtle p-2 flex items-center gap-2 cursor-pointer">
                <input type="radio" name="decision" value="VALIDATED" checked>
                <span class="font-semibold text-[#3E4D31] dark:text-[#D5E2C8]">[VALIDATE]</span>
              </label>
              <label class="panel-subtle p-2 flex items-center gap-2 cursor-pointer">
                <input type="radio" name="decision" value="REJECTED">
                <span class="font-semibold text-[#991B1B] dark:text-[#FCA5A5]">[REJECT]</span>
              </label>
              <label class="panel-subtle p-2 flex items-center gap-2 cursor-pointer">
                <input type="radio" name="decision" value="RECLASSIFIED">
                <span class="font-semibold text-[#8A580B] dark:text-[#E7C37B]">[RECLASSIFY]</span>
              </label>
            </div>
            <textarea name="reason" rows="2" required
                      placeholder="{{ $isId ? 'Alasan keputusan validasi berbasis bukti...' : 'Evidence-based validation rationale...' }}"
                      class="input-mil font-sans text-xs"></textarea>
            <div class="flex justify-end">
              <button type="submit" class="btn-primary">
                <i class="fa-solid fa-check"></i>
                <span>{{ $isId ? 'Simpan Keputusan' : 'Submit Decision' }}</span>
              </button>
            </div>
          </form>
          @else
          <p class="text-xs text-[var(--text-muted)] font-mono">
            {{ $isId ? 'Keputusan final memerlukan otoritas Administrator.' : 'Final validation requires Administrator authority.' }}
          </p>
          @endif
        </div>
      @else
        <div class="py-16 text-center space-y-2 text-[var(--text-muted)]">
          <i class="fa-solid fa-radar text-2xl"></i>
          <div class="font-mono text-xs uppercase font-semibold">{{ $isId ? 'Siap Menjalankan Investigasi AI Lokal' : 'Ready for Local AI Investigation' }}</div>
          <p class="text-xs max-w-sm mx-auto">
            {{ $isId ? 'Masukkan kata kunci, link berita, atau narasi pada panel kiri lalu tekan Jalankan Investigasi AI Lokal.' : 'Enter keyword, URL, or content on the left to start.' }}
          </p>
        </div>
      @endif
    </div>

  </div>

  @elseif($currentTab === 'compare')
  <!-- Tab 2: Authenticity Comparator -->
  <div class="space-y-4">
    <div class="panel p-4 space-y-3">
      <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-2.5">
        <span class="font-mono text-xs font-semibold uppercase tracking-wider">
          {{ $isId ? 'Komparator Keaslian: Konten Beredar vs Referensi Resmi' : 'Authenticity Comparator: Circulating vs Official Reference' }}
        </span>
        <span class="badge badge-olive">WORD-BY-WORD DIFF</span>
      </div>

      <form action="{{ route('scanner.compare') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
        @csrf
        <input type="hidden" name="case_id" value="{{ $selectedCase?->id }}">
        <div class="sm:col-span-5 space-y-1">
          <label class="label-meta">{{ $isId ? 'Dokumen Referensi Resmi' : 'Official Reference Document' }}</label>
          <select name="reference_id" required class="input-mil font-mono text-xs">
            @foreach($selectedCase?->evidence ?? [] as $ev)
              <option value="{{ $ev->id }}" {{ $ev->role === 'PRIMARY' || $ev->role === 'REFERENCE' ? 'selected' : '' }}>
                [{{ $ev->evidence_code }}] {{ Str::limit($ev->title, 38) }} ({{ $ev->role }})
              </option>
            @endforeach
          </select>
        </div>
        <div class="sm:col-span-5 space-y-1">
          <label class="label-meta">{{ $isId ? 'Konten Beredar Pembanding' : 'Compared Circulating Content' }}</label>
          <select name="compared_id" required class="input-mil font-mono text-xs">
            @foreach($selectedCase?->evidence ?? [] as $ev)
              <option value="{{ $ev->id }}" {{ $ev->role === 'COMPARED' || $ev->role === 'POST' ? 'selected' : '' }}>
                [{{ $ev->evidence_code }}] {{ Str::limit($ev->title, 38) }} ({{ $ev->role }})
              </option>
            @endforeach
          </select>
        </div>
        <div class="sm:col-span-2">
          <button type="submit" class="btn-primary w-full justify-center">{{ $isId ? 'Bandingkan' : 'Compare' }}</button>
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

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="panel p-4 space-y-2.5">
          <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-2">
            <span class="font-mono text-xs font-semibold text-[#3E4D31] dark:text-[#D5E2C8]">
              {{ $isId ? 'Dokumen Referensi' : 'Reference Document' }} [{{ $diffJson['reference']['id'] ?? 'REF' }}]
            </span>
            <span class="text-[11px] font-mono text-[var(--text-muted)]">{{ $diffJson['reference']['publisher'] ?? '-' }}</span>
          </div>
          <div class="panel-subtle p-3 text-xs leading-relaxed min-h-[120px] max-h-60 overflow-y-auto">
            @foreach($refSegs as $seg)
              @if($seg['s'] === 'removed')
                <span class="hl-overlay">{{ $seg['t'] }}</span>
              @else
                <span>{{ $seg['t'] }}</span>
              @endif
            @endforeach
          </div>
          <div class="font-mono text-[10px] text-[var(--text-muted)] break-all">SHA-256: {{ $diffJson['reference']['hash'] ?? 'N/A' }}</div>
        </div>

        <div class="panel p-4 space-y-2.5">
          <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-2">
            <span class="font-mono text-xs font-semibold text-[#991B1B] dark:text-[#FCA5A5]">
              {{ $isId ? 'Konten Beredar' : 'Circulating Content' }} [{{ $diffJson['compared']['id'] ?? 'CMP' }}]
            </span>
            <span class="text-[11px] font-mono text-[var(--text-muted)]">{{ $diffJson['compared']['publisher'] ?? '-' }}</span>
          </div>
          <div class="panel-subtle p-3 text-xs leading-relaxed min-h-[120px] max-h-60 overflow-y-auto">
            @foreach($cmpSegs as $seg)
              @if($seg['s'] === 'added')
                <span class="hl-tamper">{{ $seg['t'] }}</span>
              @else
                <span>{{ $seg['t'] }}</span>
              @endif
            @endforeach
          </div>
          <div class="font-mono text-[10px] text-[var(--text-muted)] break-all">SHA-256: {{ $diffJson['compared']['hash'] ?? 'N/A' }}</div>
        </div>
      </div>

      <div class="panel p-4 space-y-3">
        <div class="font-mono text-xs font-semibold uppercase tracking-wider">
          {{ $isId ? 'Matriks Disparitas Angka & Dampak Makna' : 'Numeric Disparity Matrix & Semantic Impact' }}
        </div>
        <div class="overflow-x-auto">
          <table class="table-mil">
            <thead>
              <tr>
                <th>{{ $isId ? 'Elemen' : 'Element' }}</th>
                <th>{{ $isId ? 'Dokumen Primer' : 'Primary' }}</th>
                <th>{{ $isId ? 'Konten Beredar' : 'Circulating' }}</th>
                <th>{{ $isId ? 'Jenis Perubahan' : 'Change Type' }}</th>
                <th>{{ $isId ? 'Dampak Makna' : 'Semantic Impact' }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($changes as $ch)
              <tr>
                <td class="font-mono font-semibold text-xs">{{ $ch['element'] }}</td>
                <td class="font-mono text-xs text-[#3E4D31] dark:text-[#D5E2C8]">{{ $ch['original'] }}</td>
                <td class="font-mono text-xs text-[#991B1B] dark:text-[#FCA5A5] font-semibold">{{ $ch['compared'] }}</td>
                <td><span class="badge badge-danger">{{ $ch['change_type'] }}</span></td>
                <td class="text-xs">{{ $ch['meaning_impact'] }}</td>
              </tr>
              @empty
              <tr><td colspan="5" class="text-center text-[var(--text-muted)] py-4">{{ $isId ? 'Tidak ada disparitas tercatat.' : 'No disparity detected.' }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    @else
      <div class="panel p-10 text-center text-[var(--text-muted)] text-xs font-mono">
        {{ $isId ? 'Pilih dua bukti kasus lalu tekan Bandingkan.' : 'Select two evidence items above and click Compare.' }}
      </div>
    @endif
  </div>

  @elseif($currentTab === 'crosssource')
  <!-- Tab 3: Cross-Source Verification -->
  <div class="space-y-4">
    <div class="panel p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="font-mono text-xs font-semibold uppercase tracking-wider">
            {{ $isId ? 'Verifikasi Lintas Sumber' : 'Cross-Source Verification Workspace' }}
          </h2>
          <span class="badge badge-olive">LEXICAL &ne; SEMANTIC</span>
        </div>
      </div>

      <form action="{{ route('scanner.crosssource') }}" method="POST">
        @csrf
        <input type="hidden" name="case_id" value="{{ $selectedCase?->id }}">
        <button type="submit" class="btn-primary">
          <i class="fa-solid fa-rotate"></i>
          <span>{{ $isId ? 'Hitung Ulang Lintas Sumber' : 'Run Cross-Source Verification' }}</span>
        </button>
      </form>
    </div>

    @if($crossResult)
      <div class="panel-subtle p-4 border-l-4 border-l-[#B47818] space-y-1.5">
        <div class="flex items-center justify-between">
          <span class="label-meta">{{ $isId ? 'Kemandirian Sumber' : 'Source Independence' }}</span>
          <span class="badge badge-warn">{{ $crossResult['independent_units'] ?? 1 }} {{ $isId ? 'KLASTER INDEPENDEN' : 'INDEPENDENT UNITS' }}</span>
        </div>
        <p class="text-xs leading-relaxed">{{ $crossResult['independence_assessment'] }}</p>
      </div>

      <div class="panel">
        <div class="overflow-x-auto">
          <table class="table-mil">
            <thead>
              <tr>
                <th>{{ $isId ? 'Pasangan Bukti' : 'Source Pair' }}</th>
                <th>{{ $isId ? 'Relasi' : 'Relationship' }}</th>
                <th>Lexical</th>
                <th>Semantic</th>
                <th>Claim Match</th>
                <th>{{ $isId ? 'Temuan' : 'Findings' }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($crossResult['comparisons_detail'] as $cmp)
              <tr>
                <td class="font-mono text-xs">
                  <b>{{ $cmp['evidence_a'] }}</b> ({{ $cmp['publisher_a'] }})<br>
                  <b>{{ $cmp['evidence_b'] }}</b> ({{ $cmp['publisher_b'] }})
                </td>
                <td><span class="badge badge-neutral">{{ $cmp['relationship'] }}</span></td>
                <td class="font-mono font-semibold">{{ $cmp['lexical_similarity'] }}%</td>
                <td class="font-mono font-semibold">{{ $cmp['semantic_similarity'] ? $cmp['semantic_similarity'] . '%' : 'N/A' }}</td>
                <td>
                  <span class="badge {{ $cmp['claim_match_status'] === 'MATCH' ? 'badge-olive' : ($cmp['claim_match_status'] === 'CONFLICT' ? 'badge-danger' : 'badge-warn') }}">
                    {{ $cmp['claim_match_status'] }}
                  </span>
                </td>
                <td class="text-xs max-w-xs">{{ $cmp['explanation'] }}</td>
              </tr>
              @empty
              <tr><td colspan="6" class="text-center text-[var(--text-muted)] py-4">—</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    @endif
  </div>
  @endif

</div>

@push('scripts')
<script>
document.getElementById('investigation-scan-form')?.addEventListener('submit', function () {
  const box = document.getElementById('live-investigation-progress');
  const stepsEl = document.getElementById('live-progress-steps');
  const btn = document.getElementById('run-investigation-btn');
  if (!box || !stepsEl) return;

  box.classList.remove('hidden');
  if (btn) {
    btn.disabled = true;
    btn.classList.add('opacity-70');
  }

  const phases = [
    '[1/5] Ekstraksi & highlight kata kunci pencarian...',
    '[2/5] Pencarian real-time berita publik (Google News, Bing News) & sosial media...',
    '[3/5] Pemindaian arsip & verifikasi kesamaan lintas kanal...',
    '[4/5] Deteksi manipulasi angka, tanggal & timpa teks...',
    '[5/5] Analisis & penentuan status kredibilitas AI Lokal...'
  ];

  stepsEl.innerHTML = '';
  phases.forEach((msg, idx) => {
    setTimeout(() => {
      const row = document.createElement('div');
      row.className = 'flex items-center gap-2 text-[var(--text-secondary)]';
      row.innerHTML = '<i class="fa-solid fa-angles-right text-[#586B47]"></i><span>' + msg + '</span>';
      stepsEl.appendChild(row);
    }, idx * 450);
  });
});
</script>
@endpush
@endsection
