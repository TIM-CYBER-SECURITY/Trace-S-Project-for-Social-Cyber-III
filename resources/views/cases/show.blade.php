@extends('layouts.app')

@php
  $isId = app()->getLocale() === 'id';
  $canImportEvidence = Auth::user()->hasPermission('evidence.import');
  $canGenerateReport = Auth::user()->hasPermission('report.generate');
  $canRunAnalysis = Auth::user()->hasPermission('analysis.run');
  $canWriteCase = Auth::user()->hasPermission('case.write');
  $canSpreadView = Auth::user()->hasPermission('spread.view');
  $canConsoleUse = Auth::user()->hasPermission('console.use');
@endphp

@section('title', $case->case_code . ' // TRACE-S')

@section('content')
<div class="space-y-5">

  <!-- Case Header -->
  <div class="panel p-4 space-y-3">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
      <div class="flex items-start gap-3">
        <span class="badge badge-olive text-xs px-2.5 py-1">{{ $case->case_code }}</span>
        <div>
          <h1 class="text-base font-semibold">{{ $case->title }}</h1>
          <div class="flex flex-wrap items-center gap-2 text-[11px] font-mono text-[var(--text-muted)] mt-0.5">
            <span>{{ $isId ? 'KATEGORI:' : 'CATEGORY:' }} <b>{{ $case->category }}</b></span>
            <span>&middot;</span>
            <span>{{ $isId ? 'DIBUAT:' : 'CREATED:' }} <b>{{ $case->created_at->format('Y-m-d H:i') }} UTC</b></span>
            <span>&middot;</span>
            <span>BY: <b>{{ $case->creator?->trace_id ?? $case->creator?->name ?? 'System' }}</b></span>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <span class="badge {{ $case->priority === 'KRITIS' ? 'badge-danger' : ($case->priority === 'TINGGI' ? 'badge-warn' : 'badge-neutral') }}">
          {{ $case->priority }}
        </span>
        <span class="badge badge-neutral">{{ $case->status }}</span>
        @if($case->is_simulation)
          <span class="badge badge-warn">SIMULATION</span>
        @endif

        @if($canWriteCase)
        <form action="{{ route('cases.update-status', $case->id) }}" method="POST" class="flex items-center gap-1.5">
          @csrf
          <select name="status" aria-label="Case status" class="input-mil py-1 px-2 text-[11px] font-mono w-auto">
            @foreach(['OPEN', 'IN_REVIEW', 'VALIDATED', 'CLOSED'] as $stOpt)
              <option value="{{ $stOpt }}" {{ $case->status === $stOpt ? 'selected' : '' }}>{{ $stOpt }}</option>
            @endforeach
          </select>
          <button type="submit" class="btn-secondary py-1 px-2 text-[11px]">
            {{ $isId ? 'Ubah Status' : 'Set Status' }}
          </button>
        </form>
        @endif

        @if($canGenerateReport)
        <form action="{{ route('reports.generate') }}" method="POST">
          @csrf
          <input type="hidden" name="case_id" value="{{ $case->id }}">
          <button type="submit" class="btn-primary text-xs">
            <i class="fa-solid fa-file-pdf"></i>
            <span>{{ $isId ? 'Buat Intel Brief' : 'Generate Intel Brief' }}</span>
          </button>
        </form>
        @endif
      </div>
    </div>

    <div class="panel-subtle p-3">
      <div class="label-meta mb-1">{{ $isId ? 'Klaim Utama yang Diperiksa' : 'Primary Claim Under Verification' }}</div>
      <div class="text-xs font-medium leading-relaxed">"{{ $case->claim_text }}"</div>
    </div>
  </div>

  <!-- Module Links -->
  @if($canRunAnalysis || $canSpreadView || $canConsoleUse)
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
    @if($canRunAnalysis)
    <a href="{{ route('scanner.index', ['case_id' => $case->id, 'tab' => 'scan']) }}" class="panel p-3 flex items-center gap-3 hover:border-[#586B47]">
      <div class="w-9 h-9 rounded bg-[var(--bg-subtle)] border border-[var(--border-color)] flex items-center justify-center text-[var(--accent-olive)] shrink-0">
        <i class="fa-solid fa-magnifying-glass"></i>
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold font-mono truncate">1. Credibility Scanner</div>
        <div class="text-[11px] text-[var(--text-muted)] truncate">{{ $isId ? 'Ekstraksi klaim & indikator' : 'Claim extraction & scoring' }}</div>
      </div>
    </a>

    <a href="{{ route('scanner.index', ['case_id' => $case->id, 'tab' => 'compare']) }}" class="panel p-3 flex items-center gap-3 hover:border-[#586B47]">
      <div class="w-9 h-9 rounded bg-[var(--bg-subtle)] border border-[var(--border-color)] flex items-center justify-center text-[var(--accent-olive)] shrink-0">
        <i class="fa-solid fa-scale-balanced"></i>
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold font-mono truncate">2. Authenticity Compare</div>
        <div class="text-[11px] text-[var(--text-muted)] truncate">{{ $isId ? 'Diff kata-per-kata dokumen' : 'Word-by-word doc diff' }}</div>
      </div>
    </a>

    <a href="{{ route('scanner.index', ['case_id' => $case->id, 'tab' => 'crosssource']) }}" class="panel p-3 flex items-center gap-3 hover:border-[#586B47]">
      <div class="w-9 h-9 rounded bg-[var(--bg-subtle)] border border-[var(--border-color)] flex items-center justify-center text-[var(--accent-olive)] shrink-0">
        <i class="fa-solid fa-code-compare"></i>
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold font-mono truncate">3. Cross-Source Check</div>
        <div class="text-[11px] text-[var(--text-muted)] truncate">{{ $isId ? 'Leksikal vs semantik terpisah' : 'Lexical vs semantic check' }}</div>
      </div>
    </a>
    @endif

    @if($canSpreadView)
    <a href="{{ route('network.index', ['case_id' => $case->id]) }}" class="panel p-3 flex items-center gap-3 hover:border-[#586B47]">
      <div class="w-9 h-9 rounded bg-[var(--bg-subtle)] border border-[var(--border-color)] flex items-center justify-center text-[var(--accent-olive)] shrink-0">
        <i class="fa-solid fa-share-nodes"></i>
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold font-mono truncate">4. Spread Network</div>
        <div class="text-[11px] text-[var(--text-muted)] truncate">{{ $isId ? 'Klasterisasi & pola akun' : 'Clusters & account patterns' }}</div>
      </div>
    </a>
    @endif

    @if($canConsoleUse)
    <a href="{{ route('console.index', ['case_id' => $case->id]) }}" class="panel p-3 flex items-center gap-3 hover:border-[#586B47]">
      <div class="w-9 h-9 rounded bg-[var(--bg-subtle)] border border-[var(--border-color)] flex items-center justify-center text-[var(--accent-olive)] shrink-0">
        <i class="fa-solid fa-terminal"></i>
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold font-mono truncate">5. AI Analyst Console</div>
        <div class="text-[11px] text-[var(--text-muted)] truncate">{{ $isId ? 'Dialog bukti terkunci kasus' : 'Case-locked analyst Q&A' }}</div>
      </div>
    </a>
    @endif
  </div>
  @endif

  <!-- 12-Step Investigation Progress -->
  @if(!empty($steps))
  <div class="panel p-4 space-y-3">
    <div class="font-mono text-xs font-semibold uppercase tracking-wider border-b border-[var(--border-color)] pb-2">
      {{ $isId ? 'Tahapan Alur Kerja Investigasi (12 Langkah)' : '12-Step Investigation Pipeline Progress' }}
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
      @foreach($steps as $idx => $st)
      <div class="panel-subtle p-2.5 flex items-start gap-2 text-xs">
        <span class="badge shrink-0 {{ $st['done'] ? 'badge-olive' : 'badge-neutral' }}">{{ $idx + 1 }}</span>
        <div class="min-w-0">
          <div class="font-semibold truncate">{{ $st['name'] }}</div>
          <div class="text-[11px] text-[var(--text-muted)] truncate">{{ $st['desc'] }}</div>
        </div>
      </div>
      @endforeach
    </div>
  </div>
  @endif

  <!-- Evidence Vault -->
  <div class="panel">
    <div class="panel-header flex-wrap">
      <div>
        <h2 class="font-mono text-xs font-semibold uppercase tracking-wider">
          {{ $isId ? 'Repositori Bukti Kasus (SHA-256 Terverifikasi)' : 'Case Evidence Repository (SHA-256 Verified)' }}
        </h2>
        <p class="text-[11px] text-[var(--text-muted)] mt-0.5">
          {{ $isId ? 'Bukti digital disimpan dengan hash SHA-256 dan stempel waktu akuisisi.' : 'Digital evidence is hashed (SHA-256) with acquisition timestamps.' }}
        </p>
      </div>

      @if($canImportEvidence)
      <div class="flex items-center gap-2">
        <button type="button" onclick="document.getElementById('fetch-url-modal').classList.remove('hidden')" class="btn-secondary text-xs">
          <i class="fa-solid fa-globe"></i>
          <span>{{ $isId ? 'Ambil via URL' : 'Fetch URL' }}</span>
        </button>
        <button type="button" onclick="document.getElementById('import-evidence-modal').classList.remove('hidden')" class="btn-primary text-xs">
          <i class="fa-solid fa-file-import"></i>
          <span>{{ $isId ? 'Tambah Bukti' : 'Add Evidence' }}</span>
        </button>
      </div>
      @endif
    </div>

    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead>
          <tr>
            <th>Evidence ID</th>
            <th>{{ $isId ? 'Peran' : 'Role' }}</th>
            <th>{{ $isId ? 'Judul & Kutipan' : 'Title & Excerpt' }}</th>
            <th>{{ $isId ? 'Penerbit / Platform' : 'Publisher / Platform' }}</th>
            <th>{{ $isId ? 'Akses' : 'Access' }}</th>
            <th>SHA-256 Hash</th>
          </tr>
        </thead>
        <tbody>
          @forelse($case->evidence as $e)
          <tr>
            <td class="font-mono font-semibold whitespace-nowrap">
              {{ $e->evidence_code }}
              @if($e->is_simulation)
                <span class="badge badge-warn block mt-1 w-fit">SIMULATION</span>
              @endif
            </td>
            <td>
              <span class="badge {{ $e->role === 'PRIMARY' ? 'badge-olive' : ($e->role === 'REFERENCE' ? 'badge-sand' : 'badge-warn') }}">
                {{ $e->role }}
              </span>
            </td>
            <td class="max-w-sm">
              <div class="font-semibold truncate">{{ $e->title }}</div>
              <div class="text-xs text-[var(--text-muted)] truncate mt-0.5">{{ $e->extracted_text }}</div>
            </td>
            <td class="text-xs whitespace-nowrap">
              <div class="font-medium">{{ $e->publisher ?? '-' }}</div>
              <div class="font-mono text-[10px] text-[var(--text-muted)] uppercase">{{ $e->platform }}</div>
            </td>
            <td><span class="badge {{ $e->access_status === 'OK' ? 'badge-olive' : 'badge-danger' }}">{{ $e->access_status }}</span></td>
            <td class="font-mono text-[11px] select-all text-[var(--text-muted)]">
              {{ $e->content_hash ? substr($e->content_hash, 0, 16) . '...' : 'N/A' }}
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center text-[var(--text-muted)] py-6">—</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Linked Threat Analyses & Intel Briefs -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="panel p-4 space-y-2.5">
      <div class="font-mono text-xs font-semibold uppercase tracking-wider border-b border-[var(--border-color)] pb-2">
        {{ $isId ? 'Riwayat Analisis Kredibilitas (' . $case->analyses->count() . ')' : 'Credibility Analyses (' . $case->analyses->count() . ')' }}
      </div>
      @forelse($case->analyses->take(5) as $an)
      <div class="panel-subtle p-2.5 flex items-center justify-between gap-2 text-xs">
        <div class="min-w-0">
          <div class="font-mono font-semibold">{{ $an->classification }} ({{ $an->credibility_score }}/100)</div>
          <div class="text-[11px] text-[var(--text-muted)] font-mono truncate">{{ $an->driver }} &middot; {{ $an->model_name ?? '-' }}</div>
        </div>
        <span class="badge {{ $an->validation_status === 'VALIDATED' ? 'badge-olive' : 'badge-warn' }} shrink-0">{{ $an->validation_status }}</span>
      </div>
      @empty
      <p class="text-xs text-[var(--text-muted)]">{{ $isId ? 'Belum ada pemindaian kredibilitas.' : 'No credibility scans yet.' }}</p>
      @endforelse
    </div>

    <div class="panel p-4 space-y-2.5">
      <div class="font-mono text-xs font-semibold uppercase tracking-wider border-b border-[var(--border-color)] pb-2">
        {{ $isId ? 'Intel Brief Terbit (' . $case->reports->count() . ')' : 'Issued Intel Briefs (' . $case->reports->count() . ')' }}
      </div>
      @forelse($case->reports->take(5) as $rp)
      <div class="panel-subtle p-2.5 flex items-center justify-between gap-2 text-xs">
        <div class="min-w-0">
          <a href="{{ route('reports.show', $rp->id) }}" class="font-mono font-semibold text-[var(--accent-olive)] dark:text-[#C5B89C] hover:underline">{{ $rp->report_code }}</a>
          <div class="text-[11px] text-[var(--text-muted)] truncate">{{ $rp->created_at->format('Y-m-d H:i') }} UTC</div>
        </div>
        <div class="flex items-center gap-1.5 shrink-0">
          <a href="{{ route('reports.show', $rp->id) }}" class="btn-secondary py-1 px-2 text-[11px]">{{ $isId ? 'Buka' : 'View' }}</a>
          @if(Auth::user()->hasPermission('report.export'))
          <a href="{{ route('reports.pdf', $rp->id) }}" class="btn-primary py-1 px-2 text-[11px]">PDF</a>
          @endif
        </div>
      </div>
      @empty
      <p class="text-xs text-[var(--text-muted)]">{{ $isId ? 'Belum ada Intel Brief diterbitkan.' : 'No Intel Briefs issued yet.' }}</p>
      @endforelse
    </div>
  </div>

</div>

@if($canImportEvidence)
<!-- Import Manual Evidence Modal (including Multipart File Upload) -->
<div id="import-evidence-modal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
  <div class="panel w-full max-w-xl shadow-2xl max-h-[90vh] flex flex-col">
    <div class="panel-header shrink-0">
      <h2 class="font-mono text-xs font-semibold uppercase tracking-wider">
        {{ $isId ? 'Tambah Bukti Digital Manual / Unggah Berkas' : 'Add Manual Evidence / Upload File' }}
      </h2>
      <button type="button" onclick="document.getElementById('import-evidence-modal').classList.add('hidden')" class="btn-secondary py-1 px-2 text-xs" aria-label="Close modal">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('cases.import-evidence', $case->id) }}" method="POST" enctype="multipart/form-data" class="p-4 space-y-3 text-xs overflow-y-auto">
      @csrf
      <div class="space-y-1">
        <label class="label-meta">{{ $isId ? 'Judul Bukti' : 'Evidence Title' }}</label>
        <input type="text" name="title" required class="input-mil">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="label-meta">{{ $isId ? 'Peran Bukti' : 'Role' }}</label>
          <select name="role" required class="input-mil font-mono text-xs">
            <option value="REFERENCE">REFERENCE</option>
            <option value="PRIMARY">PRIMARY</option>
            <option value="COMPARED">COMPARED</option>
            <option value="POST">POST</option>
          </select>
        </div>
        <div class="space-y-1">
          <label class="label-meta">{{ $isId ? 'Jenis' : 'Type' }}</label>
          <select name="type" required class="input-mil font-mono text-xs">
            <option value="TEXT">TEXT</option>
            <option value="DOCUMENT">DOCUMENT</option>
            <option value="SCREENSHOT">SCREENSHOT</option>
            <option value="IMAGE">IMAGE</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="label-meta">{{ $isId ? 'Penerbit / Sumber' : 'Publisher / Source' }}</label>
          <input type="text" name="publisher" class="input-mil">
        </div>
        <div class="space-y-1">
          <label class="label-meta">Platform</label>
          <input type="text" name="platform" value="WEB" class="input-mil font-mono">
        </div>
      </div>
      <div class="space-y-1">
        <label class="label-meta">{{ $isId ? 'Berkas Bukti (Opsional — txt, md, html, pdf, png, jpg; maks 5MB)' : 'Evidence File (Optional — txt, md, html, pdf, png, jpg; max 5MB)' }}</label>
        <input type="file" name="evidence_file" accept=".txt,.md,.html,.pdf,.png,.jpg,.jpeg" class="input-mil text-xs">
      </div>
      <div class="space-y-1">
        <label class="label-meta">{{ $isId ? 'Isi Teks Asli (Verbatim)' : 'Extracted Verbatim Text' }}</label>
        <textarea name="extracted_text" rows="4" class="input-mil font-mono text-xs"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-2 border-t border-[var(--border-color)]">
        <button type="button" onclick="document.getElementById('import-evidence-modal').classList.add('hidden')" class="btn-secondary">{{ $isId ? 'Batal' : 'Cancel' }}</button>
        <button type="submit" class="btn-primary">{{ $isId ? 'Simpan Bukti' : 'Save Evidence' }}</button>
      </div>
    </form>
  </div>
</div>

<!-- Fetch URL Modal -->
<div id="fetch-url-modal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
  <div class="panel w-full max-w-lg shadow-2xl">
    <div class="panel-header">
      <h2 class="font-mono text-xs font-semibold uppercase tracking-wider">
        {{ $isId ? 'Ambil Bukti dari URL Publik (Anti-SSRF Guard)' : 'Fetch Public URL Evidence (Anti-SSRF Guard)' }}
      </h2>
      <button type="button" onclick="document.getElementById('fetch-url-modal').classList.add('hidden')" class="btn-secondary py-1 px-2 text-xs" aria-label="Close modal">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('cases.fetch-url', $case->id) }}" method="POST" class="p-4 space-y-3 text-xs">
      @csrf
      <div class="space-y-1">
        <label class="label-meta">Public URL (http / https)</label>
        <input type="url" name="url" required placeholder="https://..." class="input-mil font-mono">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="label-meta">{{ $isId ? 'Penerbit' : 'Publisher' }}</label>
          <input type="text" name="publisher" class="input-mil">
        </div>
        <div class="space-y-1">
          <label class="label-meta">{{ $isId ? 'Peran' : 'Role' }}</label>
          <select name="role" required class="input-mil font-mono text-xs">
            <option value="REFERENCE">REFERENCE</option>
            <option value="PRIMARY">PRIMARY</option>
            <option value="COMPARED">COMPARED</option>
          </select>
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-2 border-t border-[var(--border-color)]">
        <button type="button" onclick="document.getElementById('fetch-url-modal').classList.add('hidden')" class="btn-secondary">{{ $isId ? 'Batal' : 'Cancel' }}</button>
        <button type="submit" class="btn-primary">{{ $isId ? 'Ambil URL' : 'Fetch URL' }}</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection
