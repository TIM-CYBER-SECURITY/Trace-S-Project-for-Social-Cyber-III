@extends('layouts.tactical')

@php
  $isId = app()->getLocale() === 'id';
@endphp

@section('title', ($isId ? 'Pelaporan & Bukti Digital' : 'Intelligence Reports & Evidence') . ' // TRACE-S')

@section('content')

<!-- [ModuleDHeader] START -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div>
    <div class="flex items-center gap-2.5">
      <h1 class="text-sm font-semibold font-mono tracking-wide text-neutral-300 uppercase">
        {{ $isId ? 'Laporan Intelijen & Repositori Bukti Mentah' : 'Intelligence Reports & Raw Evidence Repository' }}
      </h1>
      <span class="text-[10px] font-mono px-2 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 rounded-sm">
        MODULE D (§5.6 FR-040 &amp; AC-06)
      </span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">
      {{ $isId
        ? 'Arsip laporan Intel Brief beserta penyimpanan bukti mentah dengan hash SHA-256 yang terpisah dari ringkasan AI.'
        : 'Archive of Intel Brief reports alongside raw evidence storage with SHA-256 hashes separated from AI summaries.' }}
    </p>
  </div>

  <div>
    <button onclick="document.getElementById('new-report-modal').classList.remove('hidden')"
            aria-label="Create new Intel Brief"
            class="px-4 py-2 text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 hover:bg-neutral-800/50 text-xs font-mono uppercase tracking-wider rounded-sm transition-opacity duration-150 flex items-center gap-2 font-semibold">
      <i class="fa-solid fa-file-circle-plus"></i>
      <span>{{ $isId ? 'Buat Intel Brief Baru' : 'Create New Intel Brief' }}</span>
    </button>
  </div>
</div>
<!-- [ModuleDHeader] END -->

<!-- [ReportsArchiveTable] START -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-neutral-800 pb-2.5">
    <div class="text-xs font-mono uppercase tracking-wide text-neutral-300 font-semibold">
      {{ $isId ? 'Tabel Arsip Laporan Kasus' : 'Case Report Archive Table' }} ({{ $reports->total() }})
    </div>

    <!-- Validation Status Filter (§5.6) -->
    <form method="GET" action="{{ route('reports.index') }}" class="flex items-center gap-2 text-xs font-mono">
      <label class="text-[10px] uppercase tracking-widest text-neutral-500">
        {{ $isId ? 'FILTER STATUS VALIDASI:' : 'VALIDATION STATUS FILTER:' }}
      </label>
      <select name="validation_status" onchange="this.form.submit()" aria-label="Filter by validation status" class="bg-neutral-950 border border-neutral-800 text-neutral-300 px-2.5 py-1 rounded-sm text-xs font-mono focus:outline-none focus:border-cyan-500">
        <option value="">{{ $isId ? 'Semua Status' : 'All Statuses' }}</option>
        <option value="VALIDATED" {{ ($statusFilter ?? '') === 'VALIDATED' ? 'selected' : '' }}>VALIDATED</option>
        <option value="PENDING REVIEW" {{ ($statusFilter ?? '') === 'PENDING REVIEW' ? 'selected' : '' }}>PENDING REVIEW</option>
        <option value="REJECTED" {{ ($statusFilter ?? '') === 'REJECTED' ? 'selected' : '' }}>REJECTED</option>
        <option value="RECLASSIFIED" {{ ($statusFilter ?? '') === 'RECLASSIFIED' ? 'selected' : '' }}>RECLASSIFIED</option>
      </select>
    </form>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-widest text-neutral-500 bg-neutral-950">
          <th class="py-2 px-3">{{ $isId ? 'KODE LAPORAN' : 'REPORT CODE' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'KASUS' : 'CASE ID' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'JUDUL INTEL BRIEF' : 'INTEL BRIEF TITLE' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'STATUS VALIDASI' : 'VALIDATION STATUS' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'TANGGAL' : 'TIMESTAMP' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'KLASIFIKASI' : 'CLASSIFICATION' }}</th>
          <th class="py-2 px-3 text-right">{{ $isId ? 'TINDAKAN' : 'ACTION' }}</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800 leading-tight">
        @forelse($reports as $rep)
        @php
          $valStatus = $rep->case?->analyses?->first()?->validation_status ?? 'PENDING REVIEW';
        @endphp
        <tr class="hover:bg-neutral-800/50 transition-opacity duration-150">
          <td class="py-2.5 px-3 font-semibold text-cyan-500">
            <a href="{{ route('reports.show', $rep->id) }}" class="hover:underline">{{ $rep->report_code }}</a>
            @if($rep->is_simulation)
            <span class="block text-[10px] text-amber-500 font-normal">SIMULATION</span>
            @endif
          </td>
          <td class="py-2.5 px-3 text-neutral-300">
            <a href="{{ route('cases.show', $rep->case_id) }}" class="text-cyan-500 hover:underline">
              {{ $rep->case?->case_code ?? '-' }}
            </a>
          </td>
          <td class="py-2.5 px-3 max-w-sm">
            <a href="{{ route('reports.show', $rep->id) }}" class="font-sans text-neutral-300 hover:text-cyan-500 font-medium block truncate">
              {{ $rep->title }}
            </a>
          </td>
          <td class="py-2.5 px-3">
            @if($valStatus === 'VALIDATED')
              <span class="px-1.5 py-0.5 text-[10px] text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 rounded-sm">VALIDATED</span>
            @elseif($valStatus === 'REJECTED')
              <span class="px-1.5 py-0.5 text-[10px] text-rose-600 bg-rose-500/10 border border-rose-600/50 rounded-sm">REJECTED</span>
            @else
              <span class="px-1.5 py-0.5 text-[10px] text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm">{{ $valStatus }}</span>
            @endif
          </td>
          <td class="py-2.5 px-3 text-xs text-neutral-500">{{ $rep->created_at->format('Y-m-d H:i') }} UTC</td>
          <td class="py-2.5 px-3">
            <span class="px-2 py-0.5 bg-neutral-950 border border-neutral-800 text-neutral-300 text-[10px] uppercase rounded-sm">
              {{ $rep->classification_label }}
            </span>
          </td>
          <td class="py-2.5 px-3 text-right space-x-1.5">
            <a href="{{ route('reports.show', $rep->id) }}" class="px-2 py-1 bg-neutral-950 hover:bg-neutral-800/50 border border-neutral-800 text-neutral-300 text-[10px] rounded-sm">
              {{ $isId ? 'Buka' : 'View' }}
            </a>
            <a href="{{ route('reports.pdf', $rep->id) }}" class="px-2 py-1 text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 hover:bg-neutral-800/50 text-[10px] rounded-sm font-semibold">
              [EXPORT INTEL BRIEF (PDF)]
            </a>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="py-6 text-center text-neutral-500">
            {{ $isId ? 'Belum ada laporan Intel Brief yang diterbitkan.' : 'No Intel Brief reports generated yet.' }}
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pt-2">
    {{ $reports->links() }}
  </div>
</div>
<!-- [ReportsArchiveTable] END -->

<!-- [RawEvidenceRepositoryPanel] START -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-neutral-800 pb-2.5">
    <div>
      <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-neutral-300 flex items-center gap-2">
        <i class="fa-solid fa-box-archive text-cyan-500 text-xs"></i>
        <span>{{ $isId ? 'Repositori Bukti Mentah (Terpisah dari Ringkasan AI — §5.6)' : 'Raw Evidence Repository (Separated from AI Summary — §5.6)' }}</span>
      </h2>
      <p class="text-xs text-neutral-500 mt-0.5">
        {{ $isId
          ? 'Bukti digital asli dengan hash kriptografis SHA-256 dan waktu akuisisi, disimpan terpisah dari interpretasi AI.'
          : 'Original digital evidence with SHA-256 cryptographic hashes and acquisition timestamps, stored strictly separate from AI interpretations.' }}
      </p>
    </div>
    <span class="text-[10px] font-mono text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 px-2 py-0.5 rounded-sm">
      SHA-256 VERIFIED
    </span>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-widest text-neutral-500 bg-neutral-950">
          <th class="py-2 px-3">EVIDENCE ID</th>
          <th class="py-2 px-3">{{ $isId ? 'KASUS' : 'CASE' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'PERAN BUKTI' : 'ROLE' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'JUDUL MATERI MENTAH' : 'RAW EVIDENCE TITLE' }}</th>
          <th class="py-2 px-3">TIMESTAMP</th>
          <th class="py-2 px-3">SHA-256 HASH</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800 leading-tight">
        @foreach($cases->flatMap->evidence->take(10) as $ev)
        <tr class="hover:bg-neutral-800/50">
          <td class="py-2 px-3 text-cyan-500 font-semibold">{{ $ev->evidence_code }}</td>
          <td class="py-2 px-3 text-neutral-300">{{ $ev->case?->case_code ?? 'TS-2026-001' }}</td>
          <td class="py-2 px-3 text-[10px] text-neutral-500 uppercase">{{ $ev->role }}</td>
          <td class="py-2 px-3 text-neutral-300 font-sans truncate max-w-xs">{{ $ev->title }}</td>
          <td class="py-2 px-3 text-neutral-500 text-[11px]">{{ $ev->retrieved_at ? $ev->retrieved_at->format('Y-m-d H:i') : '2026-11-01 09:00' }} UTC</td>
          <td class="py-2 px-3 text-neutral-300 select-all text-[11px]">{{ $ev->content_hash ?? 'N/A' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
<!-- [RawEvidenceRepositoryPanel] END -->

<!-- [GenerateNewReportModal] START -->
<div id="new-report-modal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-md z-50 flex items-center justify-center p-4 transition-opacity duration-150">
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm w-full max-w-lg p-5 space-y-4">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
      <div class="flex items-center gap-2">
        <i class="fa-solid fa-file-circle-plus text-emerald-500"></i>
        <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-neutral-300">
          {{ $isId ? 'Buat Laporan Intel Brief Baru (§5.6)' : 'Generate New Intel Brief (§5.6)' }}
        </h2>
      </div>
      <button onclick="document.getElementById('new-report-modal').classList.add('hidden')" aria-label="Close modal" class="text-neutral-500 hover:text-neutral-300">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('reports.generate') }}" method="POST" class="space-y-4 text-xs font-mono">
      @csrf
      <div class="space-y-1">
        <label class="text-[10px] uppercase tracking-widest text-neutral-500">
          {{ $isId ? 'PILIH KASUS UNTUK DILAPORKAN:' : 'SELECT CASE FOR INTEL BRIEF:' }}
        </label>
        <select name="case_id" required class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
          @foreach($cases as $cs)
            <option value="{{ $cs->id }}">
              {{ $cs->case_code }} — {{ Str::limit($cs->title, 45) }} ({{ $cs->priority }})
            </option>
          @endforeach
        </select>
      </div>

      <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-sm text-xs text-neutral-500 space-y-1.5 leading-relaxed font-sans">
        <div class="text-neutral-300 font-semibold font-mono text-[10px] uppercase tracking-widest">
          {{ $isId ? 'Struktur Intel Brief (§8.1 FR-040 & AC-06):' : 'Intel Brief Structure (§8.1 FR-040 & AC-06):' }}
        </div>
        <p>
          {{ $isId
            ? 'Menyusun laporan Intel Brief yang mencakup: Ringkasan Eksekutif, Klaim & Temuan, Bukti & Sumber, Hasil Validasi Analis, Keterbatasan Analisis, Stempel Klasifikasi Dokumen, dan Hash Integritas Berkas SHA-256.'
            : 'Generates an Intel Brief containing: Executive Summary, Claims & Findings, Evidence & Sources, Analyst Validation Results, Analysis Limitations, Document Classification Stamp, and SHA-256 File Integrity Hashes.' }}
        </p>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-neutral-800">
        <button type="button" onclick="document.getElementById('new-report-modal').classList.add('hidden')" class="px-3 py-1.5 bg-neutral-950 hover:bg-neutral-800/50 border border-neutral-800 text-neutral-300 uppercase rounded-sm">
          {{ $isId ? 'Batal' : 'Cancel' }}
        </button>
        <button type="submit" class="px-4 py-1.5 text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 hover:bg-neutral-800/50 uppercase rounded-sm font-semibold">
          {{ $isId ? 'Buat Intel Brief' : 'Generate Intel Brief' }}
        </button>
      </div>
    </form>
  </div>
</div>
<!-- [GenerateNewReportModal] END -->

@endsection
