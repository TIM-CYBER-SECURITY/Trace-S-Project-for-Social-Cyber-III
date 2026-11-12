@extends('layouts.app')

@php
  $isId = app()->getLocale() === 'id';
  $canGenerate = Auth::user()->hasPermission('report.generate');
  $canExport = Auth::user()->hasPermission('report.export');
@endphp

@section('title', ($isId ? 'Laporan Intelijen & Bukti' : 'Intelligence Reports & Evidence') . ' // TRACE-S')

@section('content')
<div class="space-y-5">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
    <div>
      <div class="flex flex-wrap items-center gap-2">
        <h1 class="text-lg font-semibold tracking-tight">
          {{ $isId ? 'Laporan Intelijen & Repositori Bukti' : 'Intelligence Reports & Raw Evidence Repository' }}
        </h1>
      </div>
      <p class="text-xs text-[var(--text-muted)] mt-0.5">
        {{ $isId
          ? 'Arsip Intel Brief terstruktur beserta hash integritas SHA-256 bukti mentah yang terpisah dari ringkasan AI.'
          : 'Archive of structured Intel Briefs with SHA-256 raw evidence hashes kept strictly separate from AI summaries.' }}
      </p>
    </div>

    @if($canGenerate)
    <button type="button" onclick="document.getElementById('new-report-modal').classList.remove('hidden')" class="btn-primary self-start sm:self-auto">
      <i class="fa-solid fa-file-circle-plus"></i>
      <span>{{ $isId ? 'Buat Intel Brief Baru' : 'Generate Intel Brief' }}</span>
    </button>
    @endif
  </div>

  <!-- Archive Table -->
  <div class="panel">
    <div class="panel-header flex-wrap">
      <div class="font-mono text-xs font-semibold uppercase tracking-wider">
        {{ $isId ? 'Arsip Laporan Intel Brief' : 'Intel Brief Archive' }} ({{ $reports->total() }})
      </div>

      <form method="GET" action="{{ route('reports.index') }}" class="flex items-center gap-2 text-xs font-mono">
        <label class="label-meta">{{ $isId ? 'Status Validasi:' : 'Validation Status:' }}</label>
        <select name="validation_status" onchange="this.form.submit()" aria-label="Filter by validation status" class="input-mil py-1 text-xs font-mono w-auto">
          <option value="">{{ $isId ? 'Semua Status' : 'All Statuses' }}</option>
          <option value="VALIDATED" {{ ($statusFilter ?? '') === 'VALIDATED' ? 'selected' : '' }}>VALIDATED</option>
          <option value="PENDING REVIEW" {{ ($statusFilter ?? '') === 'PENDING REVIEW' ? 'selected' : '' }}>PENDING REVIEW</option>
          <option value="REJECTED" {{ ($statusFilter ?? '') === 'REJECTED' ? 'selected' : '' }}>REJECTED</option>
          <option value="RECLASSIFIED" {{ ($statusFilter ?? '') === 'RECLASSIFIED' ? 'selected' : '' }}>RECLASSIFIED</option>
        </select>
      </form>
    </div>

    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead>
          <tr>
            <th>{{ $isId ? 'Kode Laporan' : 'Report Code' }}</th>
            <th>{{ $isId ? 'Kasus' : 'Case ID' }}</th>
            <th>{{ $isId ? 'Judul Intel Brief' : 'Intel Brief Title' }}</th>
            <th>{{ $isId ? 'Validasi' : 'Validation' }}</th>
            <th>{{ $isId ? 'Waktu Terbit' : 'Timestamp' }}</th>
            <th>{{ $isId ? 'Klasifikasi' : 'Classification' }}</th>
            <th class="text-right">{{ $isId ? 'Tindakan' : 'Action' }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($reports as $rep)
          @php $valStatus = $rep->case?->analyses?->first()?->validation_status ?? 'PENDING REVIEW'; @endphp
          <tr>
            <td class="font-mono font-semibold whitespace-nowrap">
              <a href="{{ route('reports.show', $rep->id) }}" class="text-[var(--accent-olive)] dark:text-[#C5B89C] hover:underline">{{ $rep->report_code }}</a>
              @if($rep->is_simulation)
                <span class="badge badge-warn block mt-1 w-fit">SIMULATION</span>
              @endif
            </td>
            <td class="font-mono whitespace-nowrap">
              <a href="{{ route('cases.show', $rep->case_id) }}" class="hover:underline">{{ $rep->case?->case_code ?? '-' }}</a>
            </td>
            <td class="max-w-sm font-medium">
              <a href="{{ route('reports.show', $rep->id) }}" class="hover:underline block truncate">{{ $rep->title }}</a>
            </td>
            <td>
              <span class="badge {{ $valStatus === 'VALIDATED' ? 'badge-olive' : ($valStatus === 'REJECTED' ? 'badge-danger' : 'badge-warn') }}">
                {{ $valStatus }}
              </span>
            </td>
            <td class="font-mono text-xs text-[var(--text-muted)] whitespace-nowrap">{{ $rep->created_at->format('Y-m-d H:i') }} UTC</td>
            <td><span class="badge badge-neutral">{{ $rep->classification_label }}</span></td>
            <td class="text-right whitespace-nowrap space-x-1.5">
              <a href="{{ route('reports.show', $rep->id) }}" class="btn-secondary py-1 px-2.5 text-[11px]">
                {{ $isId ? 'Buka' : 'View' }}
              </a>
              @if($canExport)
              <a href="{{ route('reports.pdf', $rep->id) }}" class="btn-primary py-1 px-2.5 text-[11px]">
                EXPORT INTEL BRIEF (PDF)
              </a>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center text-[var(--text-muted)] py-6">
              {{ $isId ? 'Belum ada laporan Intel Brief yang diterbitkan.' : 'No Intel Brief reports generated yet.' }}
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($reports->hasPages())
    <div class="p-3 border-t border-[var(--border-color)]">{{ $reports->links() }}</div>
    @endif
  </div>

  <!-- Raw Evidence Repository Panel -->
  <div class="panel">
    <div class="panel-header flex-wrap">
      <div>
        <h2 class="font-mono text-xs font-semibold uppercase tracking-wider">
          {{ $isId ? 'Repositori Bukti Mentah (Terpisah dari Ringkasan AI)' : 'Raw Evidence Repository (Separated from AI Summary)' }}
        </h2>
        <p class="text-[11px] text-[var(--text-muted)] mt-0.5">
          {{ $isId ? 'Bukti asli beserta hash kriptografis SHA-256 dan waktu akuisisi.' : 'Original evidence items with SHA-256 hashes and acquisition timestamps.' }}
        </p>
      </div>
      <span class="badge badge-olive">SHA-256 VERIFIED</span>
    </div>

    <div class="overflow-x-auto">
      <table class="table-mil font-mono text-xs">
        <thead>
          <tr>
            <th>Evidence ID</th>
            <th>{{ $isId ? 'Kasus' : 'Case' }}</th>
            <th>{{ $isId ? 'Peran' : 'Role' }}</th>
            <th>{{ $isId ? 'Judul Materi Mentah' : 'Raw Evidence Title' }}</th>
            <th>Timestamp</th>
            <th>SHA-256 Hash</th>
          </tr>
        </thead>
        <tbody>
          @foreach($cases->flatMap->evidence->take(10) as $ev)
          <tr>
            <td class="font-semibold">{{ $ev->evidence_code }}</td>
            <td>{{ $ev->case?->case_code ?? '-' }}</td>
            <td><span class="badge badge-neutral">{{ $ev->role }}</span></td>
            <td class="font-sans truncate max-w-xs">{{ $ev->title }}</td>
            <td class="text-[11px] text-[var(--text-muted)] whitespace-nowrap">{{ $ev->retrieved_at ? $ev->retrieved_at->format('Y-m-d H:i') : '-' }} UTC</td>
            <td class="text-[11px] select-all break-all max-w-xs">{{ $ev->content_hash ?? 'N/A' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

</div>

@if($canGenerate)
<div id="new-report-modal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
  <div class="panel w-full max-w-lg shadow-2xl">
    <div class="panel-header">
      <h2 class="font-mono text-xs font-semibold uppercase tracking-wider">
        {{ $isId ? 'Buat Laporan Intel Brief Baru' : 'Generate New Intel Brief' }}
      </h2>
      <button type="button" onclick="document.getElementById('new-report-modal').classList.add('hidden')" class="btn-secondary py-1 px-2 text-xs" aria-label="Close modal">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('reports.generate') }}" method="POST" class="p-4 space-y-4 text-xs">
      @csrf
      <div class="space-y-1">
        <label class="label-meta">{{ $isId ? 'Pilih Kasus' : 'Select Investigation Case' }}</label>
        <select name="case_id" required class="input-mil font-mono text-xs">
          @foreach($cases as $cs)
            <option value="{{ $cs->id }}">{{ $cs->case_code }} — {{ Str::limit($cs->title, 45) }} ({{ $cs->priority }})</option>
          @endforeach
        </select>
      </div>

      <div class="panel-subtle p-3 space-y-1 text-[var(--text-secondary)]">
        <div class="label-meta">{{ $isId ? 'Komponen Intel Brief' : 'Intel Brief Components' }}</div>
        <p class="leading-relaxed">
          {{ $isId
            ? 'Menyusun ringkasan eksekutif, klaim & temuan, bukti & sumber, hasil validasi analis, keterbatasan analisis, label klasifikasi, dan hash SHA-256.'
            : 'Builds executive summary, claims & findings, evidence & sources, analyst validation status, mandatory limitations, classification stamp, and SHA-256 hashes.' }}
        </p>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-[var(--border-color)]">
        <button type="button" onclick="document.getElementById('new-report-modal').classList.add('hidden')" class="btn-secondary">{{ $isId ? 'Batal' : 'Cancel' }}</button>
        <button type="submit" class="btn-primary">{{ $isId ? 'Terbitkan Intel Brief' : 'Generate Brief' }}</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection
