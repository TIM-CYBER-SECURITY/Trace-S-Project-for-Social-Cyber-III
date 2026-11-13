@extends('layouts.app')

@php
  $isId = app()->getLocale() === 'id';
  $c = $report->content;
  $id = $c['section_1_case_identity'] ?? [];
  $exec = $c['section_2_executive_summary'] ?? [];
  $claims = $c['section_4_main_claims'] ?? [];
  $sources = $c['section_5_sources_and_evidence'] ?? [];
  $indep = $c['section_7_source_independence'] ?? [];
  $auth = $c['section_8_authenticity_comparison'] ?? [];
  $findings = $c['section_11_key_findings'] ?? [];
  $threat = $c['section_12_threat_indicators'] ?? [];
  $validation = $c['section_13_analyst_validation'] ?? [];
  $limitations = $c['section_14_limitations'] ?? [];
  $recomms = $c['section_15_recommendations'] ?? [];
  $hashes = $c['section_16_evidence_hashes'] ?? [];
  $canExport = Auth::user()->hasPermission('report.export');
@endphp

@section('title', $report->report_code . ' // Intel Brief')

@section('content')
<div class="space-y-5 max-w-5xl mx-auto">

  <!-- Toolbar -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3 no-print">
    <div class="flex items-center gap-2 text-xs font-mono">
      <a href="{{ route('reports.index') }}" class="text-[var(--text-muted)] hover:underline flex items-center gap-1.5">
        <i class="fa-solid fa-arrow-left"></i>
        <span>{{ $isId ? 'Kembali ke Laporan' : 'Back to Reports' }}</span>
      </a>
      <span class="text-[var(--border-strong)]">|</span>
      <span class="font-semibold">{{ $report->report_code }}</span>
      @if($report->is_simulation)
        <span class="badge badge-warn">{{ $isId ? 'SIMULASI' : 'SIMULATION' }}</span>
      @endif
    </div>

    <div class="flex items-center gap-2">
      <button type="button" onclick="window.print()" class="btn-secondary text-xs">
        <i class="fa-solid fa-print"></i>
        <span>{{ $isId ? 'Cetak' : 'Print' }}</span>
      </button>
      @if($canExport)
      <a href="{{ route('reports.spreadsheet', $report->id) }}" class="btn-secondary text-xs">
        <i class="fa-solid fa-file-excel"></i>
        <span>EXPORT SPREADSHEET (CSV)</span>
      </a>
      <a href="{{ route('reports.pdf', $report->id) }}" class="btn-primary text-xs">
        <i class="fa-solid fa-file-pdf"></i>
        <span>EXPORT INTEL BRIEF (PDF)</span>
      </a>
      @endif
    </div>
  </div>

  <!-- Document Surface -->
  <div class="panel p-6 space-y-5">

    <!-- Header -->
    <div class="border-b-2 border-[var(--border-strong)] pb-4 flex flex-col sm:flex-row sm:items-start justify-between gap-4">
      <div class="flex items-start gap-3">
        <img src="{{ asset('images/trace-s-logo.png') }}" alt="TRACE-S" class="h-10 w-auto bg-[#F2F2EC] border border-[var(--border-color)] rounded p-1">
        <div>
          <div class="label-meta">TRACE-S // SOCIAL CYBER INTELLIGENCE</div>
          <h1 class="text-base font-bold font-mono uppercase tracking-wide mt-0.5">
            {{ $isId ? 'LAPORAN RINGKAS INTELIJEN (INTEL BRIEF)' : 'SOCIAL CYBER INTELLIGENCE BRIEF' }}
          </h1>
          <div class="text-[11px] font-mono text-[var(--text-muted)]">
            {{ $isId ? 'VERIFIKASI FAKTA & ANALISIS NARASI BERBASIS BUKTI' : 'EVIDENCE-BASED NARRATIVE & FACT VERIFICATION' }}
          </div>
        </div>
      </div>

      <div class="text-left sm:text-right font-mono text-xs space-y-1">
        <span class="badge badge-danger">{{ $report->classification_label }}</span>
        <div>REF: <b>{{ $report->report_code }}</b></div>
        <div class="text-[11px] text-[var(--text-muted)]">{{ $report->created_at->format('Y-m-d H:i') }} UTC</div>
      </div>
    </div>

    <!-- 1. Executive Summary -->
    <div class="space-y-2 border-b border-[var(--border-color)] pb-4">
      <div class="flex items-center justify-between">
        <div class="font-mono text-xs font-semibold uppercase tracking-wider">1. EXECUTIVE SUMMARY</div>
        <span class="badge badge-neutral">CASE: {{ $id['case_code'] ?? '-' }} &middot; {{ $id['priority'] ?? '-' }}</span>
      </div>
      <p class="panel-subtle p-3.5 text-xs leading-relaxed">{{ $exec['summary'] ?? '-' }}</p>
    </div>

    <!-- 2. Claims & Findings -->
    <div class="space-y-2.5 border-b border-[var(--border-color)] pb-4">
      <div class="font-mono text-xs font-semibold uppercase tracking-wider">
        2. {{ $isId ? 'Klaim Utama & Temuan Berbasis Bukti' : 'Claims & Evidence-Based Findings' }}
      </div>
      <div class="panel-subtle p-3 text-xs italic">"{{ $claims['primary_claim'] ?? '-' }}"</div>

      <div class="space-y-2">
        @foreach($findings as $f)
        <div class="panel-subtle p-2.5 flex items-start gap-2.5 text-xs">
          <span class="badge shrink-0 {{ $f['marker'] === 'FAKTA TERDOKUMENTASI' ? 'badge-olive' : ($f['marker'] === 'KLAIM SUMBER' ? 'badge-sand' : 'badge-warn') }}">
            [{{ $f['marker'] }}]
          </span>
          <span class="leading-relaxed">{{ $f['statement'] }}</span>
        </div>
        @endforeach
      </div>
    </div>

    <!-- 3. Evidence & Sources -->
    <div class="space-y-2.5 border-b border-[var(--border-color)] pb-4">
      <div class="font-mono text-xs font-semibold uppercase tracking-wider">
        3. {{ $isId ? 'Bukti & Sumber Rujukan' : 'Evidence & Reference Sources' }}
      </div>
      <div class="overflow-x-auto border border-[var(--border-color)] rounded">
        <table class="table-mil">
          <thead>
            <tr>
              <th>Evidence ID</th>
              <th>Role</th>
              <th>{{ $isId ? 'Judul Materi' : 'Title' }}</th>
              <th>{{ $isId ? 'Penerbit / Platform' : 'Publisher / Platform' }}</th>
              <th>Access</th>
            </tr>
          </thead>
          <tbody>
            @foreach($sources as $s)
            <tr>
              <td class="font-mono font-semibold">{{ $s['evidence_code'] }}</td>
              <td><span class="badge badge-neutral">{{ $s['role'] }}</span></td>
              <td class="truncate max-w-xs">{{ $s['title'] }}</td>
              <td class="text-xs text-[var(--text-muted)]">{{ $s['publisher'] }} ({{ $s['platform'] }})</td>
              <td><span class="badge badge-olive">{{ $s['access_status'] }}</span></td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="panel-subtle p-3 text-xs space-y-1">
        <div class="label-meta">{{ $isId ? 'Kemandirian Sumber & Perbandingan Keaslian' : 'Source Independence & Authenticity Comparison' }}</div>
        <p class="leading-relaxed">{{ $indep['assessment'] ?? '-' }} {{ $auth['assessment'] ?? '' }}</p>
      </div>
    </div>

    <!-- 4. Threat & Analyst Validation -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b border-[var(--border-color)] pb-4 text-xs">
      <div class="panel-subtle p-3 space-y-1.5 font-mono">
        <div class="label-meta">4A. {{ $isId ? 'Indikator Kredibilitas & Ancaman' : 'Credibility & Threat Indicators' }}</div>
        <div>Threat Level: <b class="text-[#991B1B] dark:text-[#FCA5A5]">{{ $threat['threat_level'] ?? 'MODERATE' }}</b></div>
        <div>Credibility Indicator: <b>{{ $threat['credibility_score'] ?? 50 }}/100</b></div>
        <div>Verification Status: <b>{{ $threat['verification_status'] ?? 'UNVERIFIED' }}</b></div>
      </div>

      <div class="panel-subtle p-3 space-y-1.5 font-mono">
        <div class="label-meta">4B. {{ $isId ? 'Validasi Analis (Human-in-the-Loop)' : 'Analyst Validation Result' }}</div>
        <div>Decision: <b>{{ $validation['status'] ?? 'PENDING REVIEW' }}</b></div>
        <div>Reviewer: <b>{{ $validation['analyst'] ?? '-' }}</b></div>
        <div class="font-sans italic text-[var(--text-muted)]">"{{ $validation['reason'] ?? '-' }}"</div>
      </div>
    </div>

    <!-- 5. Limitations & Recommendations -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b border-[var(--border-color)] pb-4 text-xs">
      <div class="space-y-1.5">
        <div class="label-meta">5A. {{ $isId ? 'Keterbatasan Analisis (Wajib)' : 'Analysis Limitations (Mandatory)' }}</div>
        <ul class="list-disc pl-4 space-y-1 text-[var(--text-secondary)]">
          @foreach($limitations as $lim) <li>{{ $lim }}</li> @endforeach
        </ul>
      </div>

      <div class="space-y-1.5">
        <div class="label-meta">5B. {{ $isId ? 'Rekomendasi Komunikasi Publik' : 'Public Communication Recommendations' }}</div>
        <ul class="list-disc pl-4 space-y-1 text-[var(--text-secondary)]">
          @foreach($recomms as $rec) <li>{{ $rec }}</li> @endforeach
        </ul>
      </div>
    </div>

    <!-- 6. Integrity Hashes -->
    <div class="space-y-2">
      <div class="font-mono text-xs font-semibold uppercase tracking-wider">
        6. {{ $isId ? 'Hash Integritas Berkas Bukti (SHA-256)' : 'Evidence File Integrity Hashes (SHA-256)' }}
      </div>
      <div class="overflow-x-auto border border-[var(--border-color)] rounded">
        <table class="table-mil font-mono text-xs">
          <thead>
            <tr>
              <th>Evidence Code</th>
              <th>Method</th>
              <th>Timestamp</th>
              <th>SHA-256 Hash</th>
            </tr>
          </thead>
          <tbody>
            @foreach($hashes as $h)
            <tr>
              <td class="font-semibold">{{ $h['code'] }}</td>
              <td>{{ $h['method'] }}</td>
              <td class="text-[var(--text-muted)]">{{ $h['retrieved_at'] ?? '-' }}</td>
              <td class="select-all break-all text-[11px]">{{ $h['hash'] }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
@endsection
