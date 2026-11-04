@extends('layouts.tactical')

@section('title', 'Credibility Scanner & Verification // TRACE-S')

@section('content')

@php
  $currentTab = request()->query('tab', 'scan');
@endphp

<!-- Header Bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div>
    <div class="flex items-center gap-2">
      <h1 class="text-lg font-bold font-mono tracking-wide text-neutral-100 uppercase">Credibility Scanner &amp; Verification Suite</h1>
      <span class="text-[10px] font-mono px-2 py-0.5 border border-cyan-500/40 bg-cyan-950/30 text-cyan-400 rounded-sm">MODUL B &amp; H</span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">Analisis linguistik terstruktur, komparasi autentisitas editan konten, dan verifikasi lintas sumber independen.</p>
  </div>

  <!-- Case Selector Filter -->
  <form action="{{ route('scanner.index') }}" method="GET" class="flex items-center gap-2 text-xs font-mono">
    <input type="hidden" name="tab" value="{{ $currentTab }}">
    <label class="text-[10px] uppercase text-neutral-400">Kasus Terpilih:</label>
    <select name="case_id" onchange="this.form.submit()" class="bg-neutral-900 border border-neutral-700 text-neutral-200 px-3 py-1.5 rounded-sm focus:outline-none focus:border-cyan-500 text-xs font-mono">
      @foreach($cases as $c)
        <option value="{{ $c->id }}" {{ $selectedCase && $selectedCase->id === $c->id ? 'selected' : '' }}>
          {{ $c->case_code }} - {{ Str::limit($c->title, 35) }}
        </option>
      @endforeach
    </select>
  </form>
</div>

<!-- START: TABS NAVIGATION -->
<div class="flex items-center gap-1 border-b border-neutral-800 font-mono text-xs">
  <a href="{{ route('scanner.index', ['case_id' => $selectedCase?->id, 'tab' => 'scan']) }}"
     class="px-4 py-2.5 uppercase tracking-wider border-b-2 transition-all flex items-center gap-2 {{ $currentTab === 'scan' ? 'border-cyan-500 text-cyan-400 font-bold bg-neutral-900/60' : 'border-transparent text-neutral-400 hover:text-neutral-200' }}">
    <i class="fa-solid fa-radar text-xs"></i>
    <span>1. Credibility Scan</span>
  </a>

  <a href="{{ route('scanner.index', ['case_id' => $selectedCase?->id, 'tab' => 'compare']) }}"
     class="px-4 py-2.5 uppercase tracking-wider border-b-2 transition-all flex items-center gap-2 {{ $currentTab === 'compare' ? 'border-cyan-500 text-cyan-400 font-bold bg-neutral-900/60' : 'border-transparent text-neutral-400 hover:text-neutral-200' }}">
    <i class="fa-solid fa-scale-balanced text-xs"></i>
    <span>2. Authenticity Comparator</span>
    <span class="text-[9px] px-1 bg-neutral-800 text-cyan-400">Diff</span>
  </a>

  <a href="{{ route('scanner.index', ['case_id' => $selectedCase?->id, 'tab' => 'crosssource']) }}"
     class="px-4 py-2.5 uppercase tracking-wider border-b-2 transition-all flex items-center gap-2 {{ $currentTab === 'crosssource' ? 'border-cyan-500 text-cyan-400 font-bold bg-neutral-900/60' : 'border-transparent text-neutral-400 hover:text-neutral-200' }}">
    <i class="fa-solid fa-code-compare text-xs"></i>
    <span>3. Cross-Source Verification</span>
    <span class="text-[9px] px-1 bg-neutral-800 text-emerald-400">5-Sim</span>
  </a>
</div>
<!-- END: TABS NAVIGATION -->

@if($currentTab === 'scan')
<!-- ========================================================================= -->
<!-- TAB 1: CREDIBILITY SCAN (Master Prompt §7.3)                              -->
<!-- ========================================================================= -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

  <!-- Left Column: Scan Input Form (5 Cols) -->
  <div class="lg:col-span-5 bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-4">
    <div class="border-b border-neutral-800 pb-2 flex items-center justify-between">
      <div class="text-xs font-mono uppercase tracking-wider text-neutral-300 font-semibold flex items-center gap-2">
        <i class="fa-solid fa-terminal text-cyan-400"></i>
        <span>Terminal Input Pindaian</span>
      </div>
      <span class="text-[9px] font-mono text-neutral-500">ANTI-INJECTION SECURED</span>
    </div>

    <form action="{{ route('scanner.scan') }}" method="POST" class="space-y-4">
      @csrf
      <input type="hidden" name="case_id" value="{{ $selectedCase?->id }}">

      <div class="space-y-1">
        <label class="text-[10px] uppercase font-mono tracking-wider text-neutral-400">Kasus Rujukan Terkait:</label>
        <div class="px-3 py-2 bg-neutral-950 border border-neutral-800 rounded-sm text-xs font-mono text-cyan-400 truncate">
          {{ $selectedCase?->case_code }} — {{ $selectedCase?->title }}
        </div>
      </div>

      <div class="space-y-1">
        <div class="flex items-center justify-between">
          <label class="text-[10px] uppercase font-mono tracking-wider text-neutral-400">Teks Materi yang Diperiksa</label>
          <button type="button" onclick="document.getElementById('scan-textarea').value = '{{ addslashes($selectedCase?->claim_text ?? '') }}'" class="text-[9px] font-mono text-cyan-400 hover:underline">
            Salin Klaim Kasus
          </button>
        </div>
        <textarea id="scan-textarea" name="text" rows="8" required placeholder="Tempel teks artikel berita, salinan takarir unggahan media sosial, atau klaim narasi..."
                  class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500 placeholder-neutral-600 leading-relaxed">{{ old('text', $selectedCase?->claim_text) }}</textarea>
      </div>

      <div class="p-2.5 bg-neutral-950 border border-neutral-850 rounded-sm text-[10px] font-mono text-neutral-500 space-y-1">
        <div class="flex items-center justify-between">
          <span>Engine AI Aktif:</span>
          <b class="text-cyan-400">{{ app(App\Services\Ai\AiManager::class)->driverName() }} (Local-First)</b>
        </div>
        <div class="flex items-center justify-between">
          <span>Bukti Pendukung Terkait:</span>
          <b class="text-neutral-300">{{ $selectedCase?->evidence->count() ?? 0 }} Dokumen</b>
        </div>
      </div>

      <button type="submit" class="w-full py-2.5 bg-cyan-600/20 text-cyan-400 border border-cyan-500/50 hover:bg-cyan-600/40 hover:text-cyan-300 font-mono text-xs uppercase tracking-wider rounded-sm transition-all flex items-center justify-center gap-2 shadow-[0_0_12px_rgba(6,182,212,0.2)]">
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
        $score = $latestAnalysis->credibility_score ?? 50;
        $scoreColor = $score < 40 ? 'text-rose-500' : ($score < 80 ? 'text-amber-400' : 'text-emerald-400');
        $ringColor = $score < 40 ? '#f43f5e' : ($score < 80 ? '#fbbf24' : '#10b981');
      @endphp

      <!-- Result Header & Radial Score -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-neutral-800 pb-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="text-xs font-mono font-bold tracking-wider uppercase text-neutral-200">Hasil Penilaian Kognitif</span>
            @if($latestAnalysis->is_simulation)
            <span class="text-[9px] font-mono px-1.5 py-0.2 border border-amber-500/40 text-amber-500 bg-amber-950/20">SIMULASI</span>
            @endif
          </div>
          <div class="text-[10px] font-mono text-neutral-500 mt-0.5">
            Model: {{ $latestAnalysis->model_name }} • Analisis ID: {{ substr($latestAnalysis->analysis_uuid, 0, 8) }}
          </div>
        </div>

        <!-- Badges -->
        <div class="flex items-center gap-2">
          @if($latestAnalysis->classification === 'DISINFORMATION')
            <span class="px-2 py-0.5 bg-rose-950/60 border border-rose-600/50 text-rose-400 text-xs font-mono font-bold uppercase tracking-wider shadow-[0_0_10px_rgba(225,29,72,0.2)]">
              DISINFORMATION
            </span>
          @elseif($latestAnalysis->classification === 'MISLEADING')
            <span class="px-2 py-0.5 bg-amber-950/60 border border-amber-500/50 text-amber-400 text-xs font-mono font-bold uppercase tracking-wider">
              MISLEADING
            </span>
          @else
            <span class="px-2 py-0.5 bg-cyan-950/60 border border-cyan-500/50 text-cyan-400 text-xs font-mono uppercase tracking-wider">
              {{ $latestAnalysis->classification }}
            </span>
          @endif

          <span class="px-2 py-0.5 bg-neutral-800 border border-neutral-700 text-neutral-300 text-xs font-mono uppercase">
            {{ $latestAnalysis->verification_status }}
          </span>
        </div>
      </div>

      <!-- Tactical Radial Metric & Summary Box -->
      <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center bg-neutral-950 p-4 border border-neutral-850 rounded-sm">
        <!-- SVG Radial Gauge (4 Cols) -->
        <div class="sm:col-span-4 flex flex-col items-center justify-center">
          <div class="relative w-28 h-28 flex items-center justify-center">
            <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
              <circle cx="50" cy="50" r="40" stroke="#262626" stroke-width="8" fill="transparent"/>
              <circle cx="50" cy="50" r="40" stroke="{{ $ringColor }}" stroke-width="8" fill="transparent"
                      stroke-dasharray="251.2" stroke-dashoffset="{{ 251.2 - (251.2 * $score / 100) }}"
                      stroke-linecap="round" class="transition-all duration-1000"/>
            </svg>
            <div class="absolute flex flex-col items-center">
              <span class="text-2xl font-bold font-mono {{ $scoreColor }}">{{ $score }}</span>
              <span class="text-[8px] font-mono text-neutral-500 uppercase tracking-widest">SKOR / 100</span>
            </div>
          </div>
          <div class="text-[9px] font-mono text-neutral-500 mt-1 uppercase tracking-wider text-center">
            Indikator Kredibilitas
          </div>
        </div>

        <!-- Tactical Executive Summary (8 Cols) -->
        <div class="sm:col-span-8 space-y-2 border-t sm:border-t-0 sm:border-l border-neutral-800 sm:pl-4">
          <div class="text-[10px] uppercase font-mono tracking-widest text-cyan-400 flex items-center gap-1.5">
            <i class="fa-solid fa-file-contract"></i>
            <span>Executive Tactical Summary</span>
          </div>
          <p class="text-xs font-sans text-neutral-200 leading-relaxed font-medium">
            {{ $res['tactical_summary'] ?? $res['summary'] ?? 'Ringkasan analisis selesai dieksekusi.' }}
          </p>
          <div class="flex items-center gap-4 text-[10px] font-mono text-neutral-400 pt-1">
            <span>Tingkat Ancaman: <b class="{{ $latestAnalysis->threat_level === 'KRITIS' ? 'text-rose-400' : 'text-amber-400' }}">{{ $latestAnalysis->threat_level }}</b></span>
            <span>•</span>
            <span>Estimasi Model: <b class="text-cyan-400">{{ round(($latestAnalysis->confidence ?? 0.5) * 100) }}%</b></span>
          </div>
        </div>
      </div>

      <!-- Linguistic Breakdown Table -->
      <div class="space-y-1.5">
        <div class="text-[10px] uppercase font-mono tracking-wider text-neutral-400">Linguistic &amp; Content Breakdown</div>
        <div class="bg-neutral-950 border border-neutral-850 rounded-sm overflow-hidden text-xs font-mono">
          <table class="w-full text-left">
            <tbody class="divide-y divide-neutral-850">
              <tr>
                <td class="py-2 px-3 text-[10px] text-neutral-500 uppercase w-36">Muatan Emotif</td>
                <td class="py-2 px-3 text-neutral-200">{{ $res['linguistic_analysis']['emotion'] ?? '-' }}</td>
              </tr>
              <tr>
                <td class="py-2 px-3 text-[10px] text-neutral-500 uppercase">Logical Fallacy</td>
                <td class="py-2 px-3 text-neutral-200">{{ $res['linguistic_analysis']['fallacy'] ?? '-' }}</td>
              </tr>
              <tr>
                <td class="py-2 px-3 text-[10px] text-neutral-500 uppercase">Verifikasi Sumber</td>
                <td class="py-2 px-3 text-neutral-200">{{ $res['linguistic_analysis']['source_verification'] ?? '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Claims Extracted -->
      @if(!empty($res['claims']))
      <div class="space-y-1.5">
        <div class="text-[10px] uppercase font-mono tracking-wider text-neutral-400">Klaim Teridentifikasi (Claim-Level)</div>
        <div class="space-y-1.5">
          @foreach($res['claims'] as $cl)
          <div class="p-2.5 bg-neutral-950 border border-neutral-850 rounded-sm flex items-start justify-between gap-3 text-xs font-mono">
            <div>
              <span class="text-cyan-400 font-bold mr-1">[{{ $cl['id'] }}]</span>
              <span class="text-neutral-200 font-sans">{{ $cl['text'] }}</span>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
              <span class="text-[9px] px-1.5 py-0.2 bg-neutral-800 text-neutral-400 border border-neutral-700 uppercase">{{ $cl['type'] }}</span>
              <span class="text-[9px] px-1.5 py-0.2 uppercase {{ $cl['check_status'] === 'TERBANTAHKAN' ? 'bg-rose-950 text-rose-400 border border-rose-600/40' : 'bg-neutral-800 text-neutral-400' }}">
                {{ $cl['check_status'] }}
              </span>
            </div>
          </div>
          @endforeach
        </div>
      </div>
      @endif

      <!-- Limitations Box -->
      @if(!empty($res['limitations']))
      <div class="p-3 bg-neutral-950/60 border border-neutral-850 rounded-sm text-[10px] font-mono text-neutral-500 space-y-1">
        <div class="uppercase tracking-widest text-neutral-400 font-bold">Keterbatasan Pemeriksaan:</div>
        <ul class="list-disc pl-4 space-y-0.5">
          @foreach($res['limitations'] as $lim)
            <li>{{ $lim }}</li>
          @endforeach
        </ul>
      </div>
      @endif

      <!-- START: ANALYST HUMAN-IN-THE-LOOP VALIDATION PANEL (Supplemental §13) -->
      <div class="p-3 bg-neutral-950 border border-cyan-500/30 rounded-sm space-y-3">
        <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
          <div class="text-xs font-mono uppercase tracking-wider text-cyan-400 font-semibold flex items-center gap-1.5">
            <i class="fa-solid fa-user-check"></i>
            <span>Validasi Analis Manusia (Human Validation)</span>
          </div>
          <span class="text-[10px] font-mono uppercase px-2 py-0.5 bg-neutral-800 border border-neutral-700 text-neutral-300">
            Status: <b>{{ $latestAnalysis->validation_status }}</b>
          </span>
        </div>

        @if(Auth::check() && Auth::user()->hasRole('analis'))
        <form action="{{ route('scanner.review') }}" method="POST" class="space-y-3 text-xs font-mono">
          @csrf
          <input type="hidden" name="analysis_id" value="{{ $latestAnalysis->id }}">

          <div class="flex items-center gap-3">
            <label class="flex items-center gap-1.5 text-neutral-300 cursor-pointer">
              <input type="radio" name="decision" value="VALIDATED" checked class="text-cyan-500 focus:ring-0">
              <span class="text-emerald-400 font-bold">VALIDATE</span> (Setujui Hasil AI)
            </label>
            <label class="flex items-center gap-1.5 text-neutral-300 cursor-pointer">
              <input type="radio" name="decision" value="REJECTED" class="text-rose-500 focus:ring-0">
              <span class="text-rose-400 font-bold">REJECT</span> (Tolak Hasil)
            </label>
            <label class="flex items-center gap-1.5 text-neutral-300 cursor-pointer">
              <input type="radio" name="decision" value="RECLASSIFIED" class="text-amber-500 focus:ring-0">
              <span class="text-amber-400 font-bold">RECLASSIFY</span> (Koreksi)
            </label>
          </div>

          <div class="space-y-1">
            <label class="text-[10px] uppercase text-neutral-400">Alasan Analisis / Bukti Penolakan / Reklasifikasi (Wajib jika Tolak):</label>
            <textarea name="reason" rows="2" placeholder="Tuliskan argumen analitis dan bukti yang mendasari keputusan validasi..."
                      class="w-full bg-neutral-900 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500 placeholder-neutral-600"></textarea>
          </div>

          <div class="flex justify-end">
            <button type="submit" class="px-4 py-1.5 bg-cyan-600/20 text-cyan-400 border border-cyan-500/50 hover:bg-cyan-600/40 text-xs font-mono uppercase tracking-wider rounded-sm transition-all">
              Catat Keputusan Analis
            </button>
          </div>
        </form>
        @else
        <div class="text-[11px] font-mono text-neutral-500 italic">
          * Modul persetujuan validasi hanya dapat diisi oleh personel dengan peran <b>ANALIS</b>. (Silakan ganti peran di pojok kanan atas untuk menguji).
        </div>
        @endif
      </div>
      <!-- END: ANALYST HUMAN VALIDATION PANEL -->

    @else
      <div class="h-full flex flex-col items-center justify-center py-20 text-center text-neutral-500 font-mono space-y-2">
        <i class="fa-solid fa-radar text-4xl text-neutral-700"></i>
        <div class="text-xs uppercase tracking-wider text-neutral-400 font-semibold">Belum Ada Hasil Pindaian Aktif</div>
        <div class="text-[11px] max-w-sm">Pilih kasus dan klik tombol [INITIATE COGNITIVE SCAN] pada panel kiri untuk menjalankan analisis.</div>
      </div>
    @endif
  </div>

</div>

@elseif($currentTab === 'compare')
<!-- ========================================================================= -->
<!-- TAB 2: AUTHENTICITY COMPARATOR (Supplemental §6)                          -->
<!-- ========================================================================= -->
<div class="space-y-4">

  <!-- Comparison Selection Form -->
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
    <div class="border-b border-neutral-800 pb-2 flex items-center justify-between">
      <div class="text-xs font-mono uppercase tracking-wider text-neutral-300 font-semibold flex items-center gap-2">
        <i class="fa-solid fa-scale-balanced text-cyan-400"></i>
        <span>Pilih Dua Materi untuk Komparasi Autentisitas</span>
      </div>
      <span class="text-[10px] font-mono text-cyan-400">Word-Level Diff &amp; Meaning Impact</span>
    </div>

    <form action="{{ route('scanner.compare') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
      @csrf
      <input type="hidden" name="case_id" value="{{ $selectedCase?->id }}">

      <div class="sm:col-span-5 space-y-1">
        <label class="text-[10px] uppercase font-mono tracking-wider text-emerald-400">Materi Referensi Asli (Original Reference):</label>
        <select name="reference_id" required class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
          @foreach($selectedCase?->evidence ?? [] as $ev)
            <option value="{{ $ev->id }}" {{ $ev->role === 'PRIMARY' || $ev->role === 'REFERENCE' ? 'selected' : '' }}>
              [{{ $ev->evidence_code }}] {{ Str::limit($ev->title, 40) }} ({{ $ev->role }})
            </option>
          @endforeach
        </select>
      </div>

      <div class="sm:col-span-5 space-y-1">
        <label class="text-[10px] uppercase font-mono tracking-wider text-rose-400">Konten Pembanding (Suspect / Compared):</label>
        <select name="compared_id" required class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
          @foreach($selectedCase?->evidence ?? [] as $ev)
            <option value="{{ $ev->id }}" {{ $ev->role === 'COMPARED' || $ev->role === 'POST' ? 'selected' : '' }}>
              [{{ $ev->evidence_code }}] {{ Str::limit($ev->title, 40) }} ({{ $ev->role }})
            </option>
          @endforeach
        </select>
      </div>

      <div class="sm:col-span-2">
        <button type="submit" class="w-full py-2 bg-cyan-600/20 text-cyan-400 border border-cyan-500/50 hover:bg-cyan-600/40 text-xs font-mono uppercase tracking-wider rounded-sm transition-all">
          Bandingkan
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

    <!-- Comparative Diff Canvas (2 Columns) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Original Reference Pane -->
      <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-2">
        <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
          <div class="text-xs font-mono uppercase tracking-wider text-emerald-400 font-semibold flex items-center gap-1.5">
            <i class="fa-solid fa-file-shield"></i>
            <span>Materi Referensi Asli [{{ $diffJson['reference']['id'] ?? 'REF' }}]</span>
          </div>
          <span class="text-[10px] font-mono text-neutral-500">{{ $diffJson['reference']['publisher'] ?? '-' }}</span>
        </div>

        <div class="p-3 bg-neutral-950 border border-neutral-850 rounded-sm font-sans text-xs leading-relaxed min-h-[140px] max-h-60 overflow-y-auto">
          @foreach($refSegs as $seg)
            @if($seg['s'] === 'removed')
              <span class="bg-yellow-500/20 text-yellow-300 px-1 py-0.5 rounded-xs border-b border-yellow-500/50 font-semibold" title="Bagian ini dihapus/kehilangan konteks">{{ $seg['t'] }}</span>
            @else
              <span class="text-neutral-300">{{ $seg['t'] }}</span>
            @endif
          @endforeach
        </div>
        <div class="text-[9px] font-mono text-neutral-500 select-all">
          Hash SHA-256: {{ $diffJson['reference']['hash'] ?? 'N/A' }}
        </div>
      </div>

      <!-- Compared Suspect Content Pane -->
      <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-2">
        <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
          <div class="text-xs font-mono uppercase tracking-wider text-rose-400 font-semibold flex items-center gap-1.5">
            <i class="fa-solid fa-file-circle-exclamation"></i>
            <span>Konten Pembanding [{{ $diffJson['compared']['id'] ?? 'CMP' }}]</span>
          </div>
          <span class="text-[10px] font-mono text-neutral-500">{{ $diffJson['compared']['publisher'] ?? '-' }}</span>
        </div>

        <div class="p-3 bg-neutral-950 border border-neutral-850 rounded-sm font-sans text-xs leading-relaxed min-h-[140px] max-h-60 overflow-y-auto">
          @foreach($cmpSegs as $seg)
            @if($seg['s'] === 'added')
              <span class="bg-rose-500/20 text-rose-300 px-1 py-0.5 rounded-xs border-b border-rose-600/50 font-bold" title="Bagian ini ditambahkan / berubah">{{ $seg['t'] }}</span>
            @else
              <span class="text-neutral-300">{{ $seg['t'] }}</span>
            @endif
          @endforeach
        </div>
        <div class="text-[9px] font-mono text-neutral-500 select-all">
          Hash SHA-256: {{ $diffJson['compared']['hash'] ?? 'N/A' }}
        </div>
      </div>
    </div>

    <!-- Visual Legend Bar -->
    <div class="flex flex-wrap items-center gap-4 px-3 py-2 bg-neutral-900 border border-neutral-800 rounded-sm text-[11px] font-mono">
      <span class="text-neutral-400 font-semibold text-[10px] uppercase">Penanda Perubahan:</span>
      <span class="flex items-center gap-1.5 text-neutral-300"><span class="w-3 h-3 bg-neutral-800 border border-neutral-700 inline-block"></span> Teks Sama</span>
      <span class="flex items-center gap-1.5 text-rose-400"><span class="w-3 h-3 bg-rose-500/30 border border-rose-600 inline-block"></span> Berubah / Ditambahkan</span>
      <span class="flex items-center gap-1.5 text-yellow-400"><span class="w-3 h-3 bg-yellow-500/30 border border-yellow-500 inline-block"></span> Dihapus / Hilang Konteks</span>
      <span class="flex items-center gap-1.5 text-neutral-500"><span class="w-3 h-3 bg-neutral-700/50 inline-block"></span> Belum Dapat Dibandingkan</span>
    </div>

    <!-- Meaning Impact & Numeric Entity Breakdown -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
      <div class="text-xs font-mono uppercase tracking-wider text-cyan-400 font-semibold flex items-center gap-2">
        <i class="fa-solid fa-list-check"></i>
        <span>Dampak Perubahan Terhadap Makna (Semantic &amp; Entity Diff)</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left font-mono text-xs">
          <thead>
            <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-wider text-neutral-500 bg-neutral-950/40">
              <th class="py-2 px-3">Elemen</th>
              <th class="py-2 px-3">Materi Asli</th>
              <th class="py-2 px-3">Pembanding</th>
              <th class="py-2 px-3">Perubahan</th>
              <th class="py-2 px-3">Dampak Terhadap Makna</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-800/60 font-sans">
            @forelse($changes as $ch)
            <tr class="hover:bg-neutral-850/50">
              <td class="py-2 px-3 font-mono font-semibold text-neutral-300 uppercase text-[11px]">{{ $ch['element'] }}</td>
              <td class="py-2 px-3 text-emerald-400 text-xs">{{ $ch['original'] }}</td>
              <td class="py-2 px-3 text-rose-400 text-xs font-semibold">{{ $ch['compared'] }}</td>
              <td class="py-2 px-3 font-mono text-[10px]">
                <span class="px-1.5 py-0.2 bg-rose-950/60 border border-rose-600/40 text-rose-400 uppercase">{{ $ch['change_type'] }}</span>
              </td>
              <td class="py-2 px-3 text-neutral-200 text-xs leading-relaxed max-w-md">{{ $ch['meaning_impact'] }}</td>
            </tr>
            @empty
            <tr>
              <td colspan="5" class="py-4 text-center text-neutral-500 font-mono">Tidak terdeteksi perubahan substansial pada teks materi.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <!-- AI Comparative Assessment Note -->
      <div class="p-3 bg-neutral-950 border border-cyan-500/30 rounded-sm space-y-1">
        <div class="text-[10px] uppercase font-mono tracking-widest text-cyan-400 font-bold">Interpretasi AI (Bukan Vonis Mutlak):</div>
        <p class="text-xs font-sans text-neutral-200 leading-relaxed">
          {{ $diffJson['assessment'] }}
        </p>
      </div>
    </div>

  @else
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-12 text-center text-neutral-500 font-mono space-y-2">
      <i class="fa-solid fa-scale-balanced text-4xl text-neutral-700"></i>
      <div class="text-xs uppercase tracking-wider text-neutral-400 font-semibold">Pilih Dua Berkas Bukti untuk Komparasi</div>
      <div class="text-[11px] max-w-md mx-auto">Sistem akan melakukan ekstraksi diff tingkat kata, penandaan entitas angka, dan penilaian dampak pergeseran makna secara otomatis.</div>
    </div>
  @endif

</div>

@elseif($currentTab === 'crosssource')
<!-- ========================================================================= -->
<!-- TAB 3: CROSS-SOURCE VERIFICATION (Supplemental §5)                        -->
<!-- ========================================================================= -->
<div class="space-y-4">

  <!-- Header Banner & Action Button -->
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
      <div class="flex items-center gap-2">
        <h2 class="text-xs font-mono font-bold uppercase tracking-wider text-neutral-200">Mesin Verifikasi Lintas Sumber &amp; Independensi</h2>
        <span class="text-[9px] font-mono px-1.5 py-0.2 border border-emerald-500/40 text-emerald-400 bg-emerald-950/20">5 METODE SIMILARITY</span>
      </div>
      <p class="text-[11px] text-neutral-500 mt-0.5">Membandingkan konsistensi klaim antar-sumber dan mendeteksi apakah media mengutip satu rujukan primer yang sama.</p>
    </div>

    <form action="{{ route('scanner.crosssource') }}" method="POST">
      @csrf
      <input type="hidden" name="case_id" value="{{ $selectedCase?->id }}">
      <button type="submit" class="px-4 py-2 bg-emerald-600/20 text-emerald-400 border border-emerald-500/50 hover:bg-emerald-600/40 text-xs font-mono uppercase tracking-wider rounded-sm transition-all flex items-center gap-2 shadow-[0_0_10px_rgba(16,185,129,0.15)]">
        <i class="fa-solid fa-rotate text-xs"></i>
        <span>Jalankan Verifikasi Lintas Sumber</span>
      </button>
    </form>
  </div>

  @if($crossResult)
    <!-- Source Independence Alert Banner (Supplemental §5.4) -->
    <div class="p-3.5 bg-neutral-900 border border-amber-500/40 rounded-sm flex items-start gap-3 text-xs font-mono">
      <div class="w-6 h-6 rounded-full bg-amber-950 border border-amber-500/60 flex items-center justify-center text-amber-400 shrink-0 mt-0.5">
        <i class="fa-solid fa-link text-[10px]"></i>
      </div>
      <div class="space-y-1">
        <div class="text-amber-400 font-bold uppercase text-[11px] tracking-wide">Analisis Hubungan &amp; Independensi Antarsumber:</div>
        <p class="text-neutral-200 font-sans leading-relaxed">
          {{ $crossResult['independence_assessment'] }}
        </p>
        <div class="text-[10px] text-neutral-500 pt-1">
          * Jumlah pemberitaan tidak otomatis diperlakukan sebagai jumlah bukti independen jika seluruhnya bersumber dari siaran pers atau kutipan yang sama.
        </div>
      </div>
    </div>

    <!-- Overall Cross-Source Status -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 font-mono text-xs">
      <div class="p-3 bg-neutral-900 border border-neutral-800 rounded-sm space-y-1">
        <div class="text-[10px] text-neutral-500 uppercase">Status Dukungan Informasi</div>
        <div class="text-sm font-bold text-cyan-400">{{ $crossResult['status'] }}</div>
        <div class="text-[9px] text-neutral-500">Penilaian dukungan, bukan label kebenaran otomatis</div>
      </div>

      <div class="p-3 bg-neutral-900 border border-neutral-800 rounded-sm space-y-1">
        <div class="text-[10px] text-neutral-500 uppercase">Kelompok Rujukan Independen</div>
        <div class="text-sm font-bold text-emerald-400">{{ $crossResult['independent_units'] ?? 1 }} Klaster Rujukan</div>
        <div class="text-[9px] text-neutral-500">Dari total {{ count($crossResult['sources']) }} sumber yang dibandingkan</div>
      </div>

      <div class="p-3 bg-neutral-900 border border-neutral-800 rounded-sm space-y-1">
        <div class="text-[10px] text-neutral-500 uppercase">Metode Pengukuran</div>
        <div class="text-[11px] text-neutral-300 font-semibold">{{ $crossResult['methods']['lexical'] }}</div>
        <div class="text-[9px] text-neutral-500">{{ $crossResult['methods']['claim'] }}</div>
      </div>
    </div>

    <!-- 5-Similarity Cross Comparison Table -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
      <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
        <div class="text-xs font-mono uppercase tracking-wider text-neutral-300 font-semibold flex items-center gap-2">
          <i class="fa-solid fa-table text-cyan-400"></i>
          <span>Matriks Perbandingan Komprehensif Antar-Sumber</span>
        </div>
        <span class="text-[10px] font-mono text-neutral-500">Dilarang menyebut skor % sebagai probabilitas kebenaran</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left font-mono text-xs">
          <thead>
            <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-wider text-neutral-500 bg-neutral-950/40">
              <th class="py-2.5 px-3">Sumber A vs B</th>
              <th class="py-2.5 px-3">Hubungan Sumber</th>
              <th class="py-2.5 px-3">Lexical Sim</th>
              <th class="py-2.5 px-3">Semantic Sim</th>
              <th class="py-2.5 px-3">Claim Matching</th>
              <th class="py-2.5 px-3">Numeric &amp; Context</th>
              <th class="py-2.5 px-3">Penjelasan AI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-800/60">
            @forelse($crossResult['comparisons_detail'] as $cmp)
            <tr class="hover:bg-neutral-850/50">
              <td class="py-2.5 px-3 font-semibold text-neutral-200">
                <span class="text-cyan-400">{{ $cmp['evidence_a'] }}</span> ({{ $cmp['publisher_a'] }})<br>
                <span class="text-cyan-400">{{ $cmp['evidence_b'] }}</span> ({{ $cmp['publisher_b'] }})
              </td>
              <td class="py-2.5 px-3 text-[10px]">
                <span class="px-1.5 py-0.5 bg-neutral-800 border border-neutral-700 text-neutral-300 uppercase">
                  {{ $cmp['relationship'] }}
                </span>
              </td>
              <td class="py-2.5 px-3 font-bold text-neutral-300">
                {{ $cmp['lexical_similarity'] }}%
              </td>
              <td class="py-2.5 px-3 font-bold text-neutral-300">
                {{ $cmp['semantic_similarity'] ? $cmp['semantic_similarity'] . '%' : 'N/A' }}
              </td>
              <td class="py-2.5 px-3">
                @if($cmp['claim_match_status'] === 'MATCH')
                  <span class="px-1.5 py-0.2 bg-emerald-950/60 border border-emerald-500/40 text-emerald-400 text-[9px] uppercase">MATCH</span>
                @elseif($cmp['claim_match_status'] === 'CONFLICT')
                  <span class="px-1.5 py-0.2 bg-rose-950/60 border border-rose-600/40 text-rose-400 text-[9px] uppercase">CONFLICT</span>
                @else
                  <span class="px-1.5 py-0.2 bg-amber-950/60 border border-amber-500/40 text-amber-400 text-[9px] uppercase">{{ $cmp['claim_match_status'] }}</span>
                @endif
              </td>
              <td class="py-2.5 px-3 text-[10px]">
                <div>Angka: <b class="{{ $cmp['numeric_consistency'] === 'INCONSISTENT' ? 'text-rose-400' : 'text-neutral-400' }}">{{ $cmp['numeric_consistency'] }}</b></div>
                <div>Konteks: <b class="text-neutral-400">{{ $cmp['context_consistency'] }}</b></div>
              </td>
              <td class="py-2.5 px-3 font-sans text-neutral-300 text-xs leading-relaxed max-w-xs">
                {{ $cmp['explanation'] }}
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="py-4 text-center text-neutral-500">Belum ada pasangan sumber yang memenuhi syarat perbandingan.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  @else
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-12 text-center text-neutral-500 font-mono space-y-2">
      <i class="fa-solid fa-code-compare text-4xl text-neutral-700"></i>
      <div class="text-xs uppercase tracking-wider text-neutral-400 font-semibold">Verifikasi Lintas Sumber Belum Dijalankan</div>
      <div class="text-[11px] max-w-md mx-auto">Klik tombol "Jalankan Verifikasi Lintas Sumber" di atas untuk membandingkan konsistensi sumber referensi pada kasus ini.</div>
    </div>
  @endif

</div>

@endif

@endsection
