@extends('layouts.app')

@php
  $isId = app()->getLocale() === 'id';
  $currentTab = request()->query('tab', 'scan');
  $prefillText = request()->query('prefill', '');
  $aiStatus = app(\App\Services\Ai\AiManager::class)->status();
  $driverUpper = strtoupper($aiStatus['driver'] ?? 'MOCK');
  $isAiOffline = ($aiStatus['status'] ?? 'OFFLINE') === 'ERROR' || ($aiStatus['status'] ?? 'OFFLINE') === 'OFFLINE';
  $canReview = Auth::check() && Auth::user()->hasPermission('analysis.review');
@endphp

@section('title', ($isId ? 'Pemeriksa Kredibilitas & Verifikasi' : 'Credibility Scanner & Verification') . ' // TRACE-S')

@section('content')
<div class="space-y-5">

  <!-- Header & Case Switcher -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
    <div>
      <div class="flex flex-wrap items-center gap-2">
        <h1 class="text-lg font-semibold tracking-tight">
          {{ $isId ? 'Pemeriksa Kredibilitas, Komparator & Lintas Sumber' : 'Credibility Scanner, Comparator & Cross-Source Suite' }}
        </h1>
      </div>
      <p class="text-xs text-[var(--text-muted)] mt-0.5">
        {{ $isId
          ? 'Verifikasi klaim berbasis bukti primer, diff kata-per-kata dokumen, dan evaluasi kemandirian sumber.'
          : 'Primary-evidence claim verification, word-by-word document diff, and source independence evaluation.' }}
      </p>
    </div>

    <form action="{{ route('scanner.index') }}" method="GET" class="flex items-center gap-2 text-xs font-mono">
      <input type="hidden" name="tab" value="{{ $currentTab }}">
      <label class="label-meta">{{ $isId ? 'Kasus:' : 'Case:' }}</label>
      <select name="case_id" onchange="this.form.submit()" aria-label="Select active case" class="input-mil py-1.5 font-mono text-xs w-auto max-w-xs">
        @foreach($cases as $c)
          <option value="{{ $c->id }}" {{ $selectedCase && $selectedCase->id === $c->id ? 'selected' : '' }}>
            {{ $c->case_code }} — {{ Str::limit($c->title, 36) }}
          </option>
        @endforeach
      </select>
    </form>
  </div>

  @if($isAiOffline && !$aiStatus['is_simulation'])
  <div class="panel p-3 border-l-4 border-l-[#991B1B] flex flex-wrap items-center justify-between gap-2 text-xs font-mono">
    <div class="flex items-center gap-2 text-[#991B1B] dark:text-[#FCA5A5]">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <span>
        <b>AI SERVER OFFLINE:</b>
        {{ $isId
          ? 'Server AI lokal tidak terjangkau. Sistem tidak memalsukan hasil analisis saat server offline.'
          : 'Local AI server is unreachable. Results are never fabricated when offline.' }}
      </span>
    </div>
    <span class="badge badge-danger">DRIVER: {{ $driverUpper }}</span>
  </div>
  @endif

  <!-- Tabs Navigation -->
  <div class="flex flex-wrap items-center gap-1 border-b border-[var(--border-color)] font-mono text-xs">
    <a href="{{ route('scanner.index', ['case_id' => $selectedCase?->id, 'tab' => 'scan']) }}"
       class="px-4 py-2.5 uppercase tracking-wider border-b-2 flex items-center gap-2 {{ $currentTab === 'scan' ? 'border-[#586B47] text-[var(--accent-olive)] dark:text-[#C5B89C] font-semibold bg-[var(--bg-subtle)]' : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-primary)]' }}">
      <i class="fa-solid fa-magnifying-glass text-xs"></i>
      <span>1. {{ $isId ? 'Pemeriksa Kredibilitas' : 'Credibility Scanner' }}</span>
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
  <!-- Tab 1: Credibility Scanner -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <div class="lg:col-span-5 panel p-4 space-y-4">
      <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-2.5">
        <span class="font-mono text-xs font-semibold uppercase tracking-wider">
          {{ $isId ? 'Input Klaim / Materi Pemeriksaan' : 'Claim / Content Inspection Input' }}
        </span>
        <span class="badge badge-neutral">UNTRUSTED GUARD</span>
      </div>

      <form action="{{ route('scanner.scan') }}" method="POST" class="space-y-4">
        @csrf
        <input type="hidden" name="case_id" value="{{ $selectedCase?->id }}">

        <div class="space-y-1">
          <label class="label-meta">{{ $isId ? 'Kasus Rujukan' : 'Linked Case' }}</label>
          <div class="panel-subtle px-3 py-2 font-mono text-xs truncate">
            {{ $selectedCase?->case_code ?? '-' }} — {{ $selectedCase?->title ?? '-' }}
          </div>
        </div>

        <div class="space-y-1">
          <div class="flex items-center justify-between">
            <label for="scan-textarea" class="label-meta">{{ $isId ? 'Teks yang Diperiksa' : 'Text to Inspect' }}</label>
            <button type="button" id="load-case-claim-btn"
                    data-claim="{{ $selectedCase?->claim_text ?? '' }}"
                    onclick="document.getElementById('scan-textarea').value = this.getAttribute('data-claim') || ''"
                    class="text-[11px] font-mono text-[var(--accent-olive)] dark:text-[#C5B89C] hover:underline">
              {{ $isId ? 'Gunakan Klaim Kasus' : 'Load Case Claim' }}
            </button>
          </div>
          <textarea id="scan-textarea" name="text" rows="7" required
                    placeholder="{{ $isId ? 'Tempel kutipan narasi atau klaim yang ingin diverifikasi...' : 'Paste narrative excerpt or claim to verify...' }}"
                    class="input-mil font-mono text-xs leading-relaxed">{{ old('text', $prefillText ?: $selectedCase?->claim_text) }}</textarea>
        </div>

        <div class="panel-subtle p-3 text-[11px] font-mono space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-[var(--text-muted)]">{{ $isId ? 'Driver AI Aktif:' : 'Active AI Driver:' }}</span>
            <span class="font-semibold">{{ $driverUpper }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-[var(--text-muted)]">{{ $isId ? 'Bukti Tersedia:' : 'Case Evidence:' }}</span>
            <span class="font-semibold">{{ $selectedCase?->evidence->count() ?? 0 }} {{ $isId ? 'Berkas' : 'Items' }}</span>
          </div>
        </div>

        <button type="submit" class="btn-primary w-full justify-center">
          <i class="fa-solid fa-magnifying-glass"></i>
          <span>{{ $isId ? 'Jalankan Pemeriksaan Kredibilitas' : 'Run Credibility Scan' }}</span>
        </button>
      </form>
    </div>

    <div class="lg:col-span-7 panel p-4 space-y-4">
      @if($latestAnalysis)
        @php
          $res = $latestAnalysis->result;
          $score = (int) ($latestAnalysis->credibility_score ?? 50);
          $ringColor = $score < 40 ? '#991B1B' : ($score < 80 ? '#B47818' : '#586B47');
          $scoreClass = $score < 40 ? 'text-[#991B1B] dark:text-[#FCA5A5]' : ($score < 80 ? 'text-[#8A580B] dark:text-[#E7C37B]' : 'text-[#3E4D31] dark:text-[#D5E2C8]');
        @endphp

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[var(--border-color)] pb-3">
          <div>
            <div class="flex items-center gap-2">
              <span class="font-mono text-xs font-semibold uppercase tracking-wider">
                {{ $isId ? 'Hasil Pemeriksaan Kredibilitas' : 'Credibility Assessment Result' }}
              </span>
              @if($latestAnalysis->is_simulation)
                <span class="badge badge-warn">{{ $isId ? 'SIMULASI' : 'SIMULATION' }}</span>
              @endif
            </div>
            <div class="text-[11px] font-mono text-[var(--text-muted)] mt-0.5">
              DRIVER: {{ strtoupper($latestAnalysis->driver ?? 'MOCK') }} &middot; ID: {{ substr($latestAnalysis->analysis_uuid, 0, 8) }}
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-1.5">
            <span class="badge {{ $latestAnalysis->classification === 'DISINFORMATION' ? 'badge-danger' : ($latestAnalysis->classification === 'MISLEADING' ? 'badge-warn' : 'badge-olive') }}">
              {{ $latestAnalysis->classification }}
            </span>
            <span class="badge badge-neutral">{{ $latestAnalysis->verification_status }}</span>
          </div>
        </div>

        <!-- Indicator Ring & Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center panel-subtle p-4">
          <div class="sm:col-span-4 flex flex-col items-center justify-center">
            <div class="relative w-24 h-24 flex items-center justify-center">
              <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100" aria-label="Credibility Indicator Score {{ $score }} out of 100">
                <circle cx="50" cy="50" r="40" stroke="currentColor" stroke-opacity="0.15" stroke-width="8" fill="transparent"/>
                <circle cx="50" cy="50" r="40" stroke="{{ $ringColor }}" stroke-width="8" fill="transparent"
                        stroke-dasharray="251.2" stroke-dashoffset="{{ 251.2 - (251.2 * $score / 100) }}" stroke-linecap="butt"/>
              </svg>
              <div class="absolute flex flex-col items-center">
                <span class="text-xl font-bold font-mono {{ $scoreClass }}">{{ $score }}</span>
                <span class="text-[10px] font-mono text-[var(--text-muted)]">/ 100</span>
              </div>
            </div>
            <div class="label-meta mt-1.5 text-center">{{ $isId ? 'Indikator Kredibilitas' : 'Credibility Indicator' }}</div>
          </div>

          <div class="sm:col-span-8 space-y-2 border-t sm:border-t-0 sm:border-l border-[var(--border-color)] pt-3 sm:pt-0 sm:pl-4">
            <div class="label-meta">{{ $isId ? 'Ringkasan Analisis Berbasis Bukti' : 'Evidence-Based Summary' }}</div>
            <p class="text-xs leading-relaxed">{{ $res['tactical_summary'] ?? $res['summary'] ?? '-' }}</p>
            <div class="flex flex-wrap items-center gap-3 text-[11px] font-mono text-[var(--text-muted)] pt-1">
              <span>{{ $isId ? 'Level:' : 'Threat:' }} <b>{{ $latestAnalysis->threat_level }}</b></span>
              <span>&middot;</span>
              <span>{{ $isId ? 'Keyakinan Model:' : 'Confidence:' }} <b>{{ round(($latestAnalysis->confidence ?? 0.5) * 100) }}%</b></span>
            </div>
          </div>
        </div>

        <!-- Linguistic & Fallacy Breakdown -->
        <div class="space-y-1.5">
          <div class="label-meta">{{ $isId ? 'Pola Bahasa & Kesesuaian Rujukan' : 'Linguistic Patterns & Reference Alignment' }}</div>
          <div class="overflow-x-auto border border-[var(--border-color)] rounded">
            <table class="table-mil">
              <tbody>
                <tr>
                  <td class="label-meta w-44">{{ $isId ? 'Pola Nada Bahasa' : 'Emotional Tone Pattern' }}</td>
                  <td class="text-xs">{{ $res['linguistic_analysis']['emotion'] ?? '-' }}</td>
                </tr>
                <tr>
                  <td class="label-meta">{{ $isId ? 'Indikasi Kesalahan Logika' : 'Logical Fallacy' }}</td>
                  <td class="text-xs">{{ $res['linguistic_analysis']['fallacy'] ?? '-' }}</td>
                </tr>
                <tr>
                  <td class="label-meta">{{ $isId ? 'Kesesuaian Bukti Primer' : 'Primary Evidence Match' }}</td>
                  <td class="text-xs">{{ $res['linguistic_analysis']['source_verification'] ?? '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Atomic Claims Table -->
        <div class="space-y-1.5">
          <div class="label-meta">{{ $isId ? 'Ekstraksi Klaim Atomik' : 'Extracted Atomic Claims' }}</div>
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
                    <span class="badge {{ $cs === 'TERBANTAHKAN' || $cs === 'REFUTED' ? 'badge-danger' : 'badge-warn' }}">{{ $cs }}</span>
                  </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-[var(--text-muted)] py-3">—</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        <!-- Mandatory Analysis Limitations -->
        <div class="panel-subtle p-3 border-l-4 border-l-[#B47818] space-y-1 text-xs">
          <div class="label-meta text-[#8A580B] dark:text-[#E7C37B]">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
            {{ $isId ? 'Keterbatasan Analisis (Wajib Ditampilkan)' : 'Analysis Limitations (Mandatory Disclosure)' }}
          </div>
          <ul class="list-disc pl-4 space-y-1 text-[var(--text-secondary)]">
            @forelse($res['limitations'] ?? [] as $lim)
              <li>{{ $lim }}</li>
            @empty
              <li>{{ $isId ? 'Penilaian terbatas pada bukti tersimpan pada berkas kasus aktif.' : 'Assessment is bounded strictly to evidence stored in the active case file.' }}</li>
              <li>{{ $isId ? 'Indikator kredibilitas merupakan alat bantu analis, bukan penentu kebenaran mutlak.' : 'Credibility score is an assistive indicator, not a final truth determination.' }}</li>
            @endforelse
          </ul>
        </div>

        <!-- Human-in-the-Loop Validation Panel -->
        <div class="panel p-4 border-l-4 border-l-[#586B47] space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border-color)] pb-2">
            <span class="font-mono text-xs font-semibold uppercase tracking-wider">
              {{ $isId ? 'Validasi Analis (Human-in-the-Loop)' : 'Analyst Validation (Human-in-the-Loop)' }}
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

            <div class="space-y-1">
              <label class="label-meta">{{ $isId ? 'Alasan Keputusan (Wajib)' : 'Decision Rationale (Mandatory)' }}</label>
              <textarea name="reason" rows="2" required
                        placeholder="{{ $isId ? 'Jelaskan dasar bukti atas validasi, penolakan, atau reklasifikasi...' : 'State evidence-based rationale for validation, rejection, or reclassification...' }}"
                        class="input-mil font-sans text-xs"></textarea>
            </div>

            <div class="flex justify-end">
              <button type="submit" class="btn-primary">
                <i class="fa-solid fa-check"></i>
                <span>{{ $isId ? 'Simpan Keputusan Validasi' : 'Submit Validation Decision' }}</span>
              </button>
            </div>
          </form>
          @else
          <p class="text-xs text-[var(--text-muted)]">
            {{ $isId
              ? 'Validasi final [VALIDATE] / [REJECT] / [RECLASSIFY] hanya dapat ditetapkan oleh akun berizin `analysis.review` (Administrator).'
              : 'Final [VALIDATE] / [REJECT] / [RECLASSIFY] actions require the `analysis.review` permission (Administrator).' }}
          </p>
          @endif
        </div>
      @else
        <div class="py-12 text-center space-y-2 text-[var(--text-muted)]">
          <i class="fa-solid fa-magnifying-glass text-2xl"></i>
          <div class="font-mono text-xs uppercase font-semibold">{{ $isId ? 'Belum Ada Hasil Pemeriksaan' : 'No Scan Result Yet' }}</div>
          <p class="text-xs max-w-sm mx-auto">
            {{ $isId ? 'Masukkan klaim dan pilih kasus aktif di panel kiri untuk memulai pemeriksaan.' : 'Select a case and run a credibility scan from the left panel.' }}
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
                <span class="bg-[#B47818]/20 text-[#8A580B] dark:text-[#E7C37B] px-1 rounded font-medium">{{ $seg['t'] }}</span>
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
                <span class="bg-[#991B1B]/15 text-[#991B1B] dark:text-[#FCA5A5] px-1 rounded font-semibold">{{ $seg['t'] }}</span>
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

        <div class="panel-subtle p-3 space-y-1">
          <div class="label-meta">{{ $isId ? 'Ringkasan Dampak Semantik' : 'Semantic Impact Summary' }}</div>
          <p class="text-xs leading-relaxed">{{ $diffJson['assessment'] ?? '-' }}</p>
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
        <p class="text-xs text-[var(--text-muted)] mt-0.5">
          {{ $isId
            ? 'Memisahkan kemiripan leksikal vs semantik serta mengidentifikasi sindikasi satu dokumen primer.'
            : 'Separates lexical vs semantic similarity and detects syndication of a single primary source.' }}
        </p>
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
          <span class="label-meta">{{ $isId ? 'Mesin Kemandirian Sumber' : 'Source Independence Engine' }}</span>
          <span class="badge badge-warn">{{ $crossResult['independent_units'] ?? 1 }} {{ $isId ? 'KLASTER INDEPENDEN' : 'INDEPENDENT UNITS' }}</span>
        </div>
        <p class="text-xs leading-relaxed">{{ $crossResult['independence_assessment'] }}</p>
      </div>

      <div class="panel">
        <div class="panel-header">
          <span class="font-mono text-xs font-semibold uppercase tracking-wider">
            {{ $isId ? 'Perbandingan Kemiripan (Leksikal vs Semantik Terpisah)' : 'Similarity Matrix (Lexical vs Semantic Separated)' }}
          </span>
        </div>
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

      @if($latestAnalysis)
      <div class="panel p-4 border-l-4 border-l-[#586B47] space-y-3">
        <div class="font-mono text-xs font-semibold uppercase tracking-wider">
          {{ $isId ? 'Keputusan Validasi Analis' : 'Analyst Validation Decision' }}
        </div>
        @if($canReview)
        <form action="{{ route('scanner.review') }}" method="POST" class="space-y-3 text-xs font-mono">
          @csrf
          <input type="hidden" name="analysis_id" value="{{ $latestAnalysis->id }}">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <label class="panel-subtle p-2 flex items-center gap-2 cursor-pointer"><input type="radio" name="decision" value="VALIDATED" checked><span>[VALIDATE]</span></label>
            <label class="panel-subtle p-2 flex items-center gap-2 cursor-pointer"><input type="radio" name="decision" value="REJECTED"><span>[REJECT]</span></label>
            <label class="panel-subtle p-2 flex items-center gap-2 cursor-pointer"><input type="radio" name="decision" value="RECLASSIFIED"><span>[RECLASSIFY]</span></label>
          </div>
          <textarea name="reason" rows="2" required class="input-mil font-sans text-xs"
                    placeholder="{{ $isId ? 'Wajib isi alasan keputusan berdasarkan hasil verifikasi lintas sumber...' : 'Mandatory rationale based on cross-source verification...' }}"></textarea>
          <div class="flex justify-end">
            <button type="submit" class="btn-primary">{{ $isId ? 'Simpan Keputusan' : 'Submit Decision' }}</button>
          </div>
        </form>
        @else
        <p class="text-xs text-[var(--text-muted)]">
          {{ $isId
            ? 'Keputusan validasi memerlukan izin `analysis.review` (Administrator).'
            : 'Validation actions require `analysis.review` permission (Administrator).' }}
        </p>
        @endif
      </div>
      @endif
    @endif
  </div>
  @endif

</div>
@endsection
