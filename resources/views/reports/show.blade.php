@extends('layouts.tactical')

@php
  $isId = app()->getLocale() === 'id';
  $c = $report->content;
  $id = $c['section_1_case_identity'] ?? [];
  $exec = $c['section_2_executive_summary'] ?? [];
  $scope = $c['section_3_data_scope'] ?? [];
  $claims = $c['section_4_main_claims'] ?? [];
  $sources = $c['section_5_sources_and_evidence'] ?? [];
  $cross = $c['section_6_cross_source_verification'] ?? [];
  $indep = $c['section_7_source_independence'] ?? [];
  $auth = $c['section_8_authenticity_comparison'] ?? [];
  $findings = $c['section_11_key_findings'] ?? [];
  $threat = $c['section_12_threat_indicators'] ?? [];
  $validation = $c['section_13_analyst_validation'] ?? [];
  $limitations = $c['section_14_limitations'] ?? [];
  $recomms = $c['section_15_recommendations'] ?? [];
  $hashes = $c['section_16_evidence_hashes'] ?? [];
@endphp

@section('title', $report->report_code . ' // Intel Brief')

@section('content')

<!-- [IntelBriefToolbar] START -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div class="flex items-center gap-2">
    <a href="{{ route('reports.index') }}" class="text-xs font-mono text-neutral-500 hover:text-cyan-500 flex items-center gap-1.5">
      <i class="fa-solid fa-arrow-left"></i>
      <span>{{ $isId ? 'Kembali ke Daftar Laporan' : 'Back to Reports' }}</span>
    </a>
    <span class="text-neutral-800">|</span>
    <span class="text-xs font-mono font-semibold text-cyan-500">{{ $report->report_code }}</span>
    @if($report->is_simulation)
      <span class="text-[10px] font-mono px-1.5 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm">
        {{ $isId ? 'SIMULASI' : 'SIMULATION' }}
      </span>
    @endif
  </div>

  <div class="flex items-center gap-2">
    <button onclick="window.print()" aria-label="Print report" class="px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800/50 text-neutral-300 text-xs font-mono uppercase rounded-sm border border-neutral-800 flex items-center gap-1.5">
      <i class="fa-solid fa-print"></i>
      <span>{{ $isId ? 'Cetak' : 'Print' }}</span>
    </button>
    <a href="{{ route('reports.pdf', $report->id) }}" class="px-3.5 py-1.5 text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 hover:bg-neutral-800/50 text-xs font-mono uppercase tracking-wider rounded-sm flex items-center gap-1.5 font-semibold">
      <i class="fa-solid fa-file-pdf"></i>
      <span>[EXPORT INTEL BRIEF (PDF)]</span>
    </a>
  </div>
</div>
<!-- [IntelBriefToolbar] END -->

<!-- [IntelBriefDocument] START -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-6 space-y-5 text-neutral-300 font-sans max-w-5xl mx-auto">

  <!-- Document Classification Header (§5.6) -->
  <div class="border-b border-neutral-800 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 bg-neutral-950 border border-cyan-500/50 rounded-sm flex items-center justify-center text-cyan-500 text-lg">
        <i class="fa-solid fa-shield-halved"></i>
      </div>
      <div>
        <div class="text-[10px] font-mono tracking-widest text-cyan-500 uppercase font-semibold">TRACE-S // SOCIAL CYBER INTELLIGENCE</div>
        <div class="text-sm font-semibold font-mono tracking-wide text-neutral-300 uppercase">
          {{ $isId ? 'LAPORAN RINGKAS INTELIJEN (INTEL BRIEF — §8.1 FR-040)' : 'SOCIAL CYBER INTELLIGENCE BRIEF (§8.1 FR-040)' }}
        </div>
        <div class="text-[10px] font-mono text-neutral-500 uppercase">
          {{ $isId ? 'VERIFIKASI FAKTA & ANALISIS NARASI BERBASIS BUKTI' : 'EVIDENCE-BASED NARRATIVE & FACT VERIFICATION' }}
        </div>
      </div>
    </div>

    <div class="text-right font-mono text-xs space-y-0.5">
      <div class="text-[10px] px-2 py-0.5 text-rose-600 bg-rose-500/10 border border-rose-600/50 uppercase font-semibold inline-block rounded-sm">
        {{ $report->classification_label }}
      </div>
      <div class="text-neutral-300 text-xs pt-1">REF: <b>{{ $report->report_code }}</b></div>
      <div class="text-neutral-500 text-[10px]">{{ $report->created_at->format('Y-m-d H:i') }} UTC</div>
    </div>
  </div>

  <!-- 1. Executive Summary (§5.6) -->
  <div class="space-y-2 border-b border-neutral-800 pb-4">
    <div class="flex items-center justify-between">
      <div class="text-xs font-mono uppercase tracking-widest text-cyan-500 font-semibold">
        1. {{ $isId ? 'RINGKASAN EKSEKUTIF' : 'EXECUTIVE SUMMARY' }}
      </div>
      <span class="text-[10px] font-mono px-1.5 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm">
        CASE: {{ $id['case_code'] ?? '-' }} &middot; {{ $id['priority'] ?? '-' }}
      </span>
    </div>
    <p class="text-xs leading-relaxed text-neutral-300 font-sans bg-neutral-950 p-3 border border-neutral-800 rounded-sm">
      {{ $exec['summary'] ?? '-' }}
    </p>
  </div>

  <!-- 2. Claims & Findings (§5.6) -->
  <div class="space-y-2.5 border-b border-neutral-800 pb-4">
    <div class="text-xs font-mono uppercase tracking-widest text-cyan-500 font-semibold">
      2. {{ $isId ? 'KLAIM UTAMA & TEMUAN BERBASIS BUKTI' : 'CLAIMS & EVIDENCE-BASED FINDINGS' }}
    </div>
    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-sm text-xs text-neutral-300 italic">
      "{{ $claims['primary_claim'] ?? '-' }}"
    </div>

    <div class="space-y-2 font-mono text-xs pt-1">
      @foreach($findings as $f)
      <div class="p-2.5 bg-neutral-950 border border-neutral-800 rounded-sm flex items-start gap-3">
        <span class="px-2 py-0.5 rounded-sm text-[10px] font-semibold uppercase shrink-0
          {{ $f['marker'] === 'FAKTA TERDOKUMENTASI' ? 'text-emerald-500 bg-emerald-500/10 border border-emerald-500/30' :
             ($f['marker'] === 'KLAIM SUMBER' ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50' :
             'text-amber-500 bg-amber-500/10 border border-amber-500/30') }}">
          [{{ $f['marker'] }}]
        </span>
        <span class="font-sans text-neutral-300 text-xs leading-relaxed">{{ $f['statement'] }}</span>
      </div>
      @endforeach
    </div>
  </div>

  <!-- 3. Evidence & Sources (§5.6) -->
  <div class="space-y-2 border-b border-neutral-800 pb-4">
    <div class="text-xs font-mono uppercase tracking-widest text-cyan-500 font-semibold">
      3. {{ $isId ? 'BUKTI & SUMBER RUJUKAN' : 'EVIDENCE & REFERENCE SOURCES' }}
    </div>
    <div class="overflow-x-auto text-xs font-mono bg-neutral-950 border border-neutral-800 rounded-sm">
      <table class="w-full text-left">
        <thead>
          <tr class="border-b border-neutral-800 text-[10px] text-neutral-500 uppercase tracking-widest">
            <th class="py-2 px-2.5">EVIDENCE ID</th>
            <th class="py-2 px-2.5">{{ $isId ? 'PERAN' : 'ROLE' }}</th>
            <th class="py-2 px-2.5">{{ $isId ? 'JUDUL MATERI' : 'TITLE' }}</th>
            <th class="py-2 px-2.5">{{ $isId ? 'PENERBIT / PLATFORM' : 'PUBLISHER / PLATFORM' }}</th>
            <th class="py-2 px-2.5">{{ $isId ? 'STATUS AKSES' : 'ACCESS' }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-800 leading-tight">
          @foreach($sources as $s)
          <tr>
            <td class="py-2 px-2.5 font-semibold text-cyan-500">{{ $s['evidence_code'] }}</td>
            <td class="py-2 px-2.5 text-[10px] text-neutral-500">{{ $s['role'] }}</td>
            <td class="py-2 px-2.5 font-sans truncate max-w-xs text-neutral-300">{{ $s['title'] }}</td>
            <td class="py-2 px-2.5 text-xs text-neutral-500">{{ $s['publisher'] }} ({{ $s['platform'] }})</td>
            <td class="py-2 px-2.5 text-[10px] text-emerald-500">{{ $s['access_status'] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-sm text-xs">
      <span class="font-mono text-[10px] uppercase tracking-widest text-amber-500 font-semibold">
        {{ $isId ? 'Kemandirian Sumber & Perbandingan Dokumen:' : 'Source Independence & Authenticity Comparison:' }}
      </span>
      <p class="mt-1 text-neutral-300 leading-relaxed">{{ $indep['assessment'] ?? '-' }} {{ $auth['assessment'] ?? '' }}</p>
    </div>
  </div>

  <!-- 4. Analyst Validation Result (§5.6) -->
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b border-neutral-800 pb-4 text-xs font-mono">
    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-sm space-y-1.5">
      <div class="text-[10px] uppercase tracking-widest text-cyan-500 font-semibold">
        4A. {{ $isId ? 'INDIKATOR KREDIBILITAS & ANCAMAN' : 'CREDIBILITY & THREAT INDICATORS' }}
      </div>
      <div>{{ $isId ? 'Level Ancaman:' : 'Threat Level:' }} <b class="text-rose-600">{{ $threat['threat_level'] ?? 'MODERATE' }}</b></div>
      <div>{{ $isId ? 'Indikator Kredibilitas:' : 'Credibility Indicator:' }} <b class="text-cyan-500">{{ $threat['credibility_score'] ?? 50 }}/100</b></div>
      <div>{{ $isId ? 'Status Verifikasi:' : 'Verification Status:' }} <b class="text-neutral-300">{{ $threat['verification_status'] ?? 'UNVERIFIED' }}</b></div>
    </div>

    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-sm space-y-1.5">
      <div class="text-[10px] uppercase tracking-widest text-emerald-500 font-semibold">
        4B. {{ $isId ? 'HASIL VALIDASI ANALIS (HUMAN-IN-THE-LOOP)' : 'ANALYST VALIDATION RESULT (HUMAN-IN-THE-LOOP)' }}
      </div>
      <div>{{ $isId ? 'Keputusan:' : 'Decision:' }} <b class="text-emerald-500">{{ $validation['status'] ?? 'PENDING REVIEW' }}</b></div>
      <div>{{ $isId ? 'Analis:' : 'Reviewer:' }} <b class="text-neutral-300">{{ $validation['analyst'] ?? '-' }}</b></div>
      <div class="text-xs text-neutral-500 font-sans italic">"{{ $validation['reason'] ?? '-' }}"</div>
    </div>
  </div>

  <!-- 5. Analysis Limitations & Recommendations (§5.6) -->
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b border-neutral-800 pb-4 text-xs font-mono">
    <div class="space-y-1.5">
      <div class="text-[10px] uppercase tracking-widest text-amber-500 font-semibold">
        5A. {{ $isId ? 'KETERBATASAN ANALISIS (WAJIB DICANTUMKAN)' : 'ANALYSIS LIMITATIONS (MANDATORY)' }}
      </div>
      <ul class="list-disc pl-4 space-y-1 text-neutral-300 font-sans text-xs">
        @foreach($limitations as $lim)
          <li>{{ $lim }}</li>
        @endforeach
      </ul>
    </div>

    <div class="space-y-1.5">
      <div class="text-[10px] uppercase tracking-widest text-cyan-500 font-semibold">
        5B. {{ $isId ? 'REKOMENDASI KOMUNIKASI PUBLIK' : 'PUBLIC COMMUNICATION RECOMMENDATIONS' }}
      </div>
      <ul class="list-disc pl-4 space-y-1 text-neutral-300 text-xs font-sans">
        @foreach($recomms as $rec)
          <li>{{ $rec }}</li>
        @endforeach
      </ul>
    </div>
  </div>

  <!-- 6. File Integrity Hashes (SHA-256) (§5.6) -->
  <div class="space-y-2">
    <div class="text-xs font-mono uppercase tracking-widest text-cyan-500 font-semibold">
      6. {{ $isId ? 'HASH INTEGRITAS BERKAS BUKTI (SHA-256)' : 'EVIDENCE FILE INTEGRITY HASHES (SHA-256)' }}
    </div>
    <div class="bg-neutral-950 border border-neutral-800 rounded-sm p-3 text-xs font-mono overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr class="border-b border-neutral-800 text-[10px] text-neutral-500 uppercase tracking-widest">
            <th class="py-1.5 px-2">EVIDENCE CODE</th>
            <th class="py-1.5 px-2">{{ $isId ? 'METODE AKUISISI' : 'ACQUISITION METHOD' }}</th>
            <th class="py-1.5 px-2">TIMESTAMP</th>
            <th class="py-1.5 px-2">SHA-256 CRYPTOGRAPHIC HASH</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-800 leading-tight">
          @foreach($hashes as $h)
          <tr>
            <td class="py-1.5 px-2 font-semibold text-cyan-500">{{ $h['code'] }}</td>
            <td class="py-1.5 px-2 text-neutral-500">{{ $h['method'] }}</td>
            <td class="py-1.5 px-2 text-neutral-500">{{ $h['retrieved_at'] ?? '-' }}</td>
            <td class="py-1.5 px-2 select-all break-all text-neutral-300">{{ $h['hash'] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

</div>
<!-- [IntelBriefDocument] END -->

@endsection
