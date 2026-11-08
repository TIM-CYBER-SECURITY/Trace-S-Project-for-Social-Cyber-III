@extends('layouts.tactical')

@php
  $isId = app()->getLocale() === 'id';
@endphp

@section('title', $case->case_code . ' // TRACE-S')

@section('content')

<!-- [CaseDetailHeader] START -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
    <div class="flex items-center gap-2.5">
      <div class="px-2.5 py-1 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-mono font-semibold text-sm rounded-sm">
        {{ $case->case_code }}
      </div>
      <div>
        <h1 class="text-sm font-semibold font-sans text-neutral-300">{{ $case->title }}</h1>
        <div class="flex flex-wrap items-center gap-2 text-[10px] font-mono text-neutral-500 mt-0.5">
          <span>{{ $isId ? 'KATEGORI:' : 'CATEGORY:' }} <b class="text-neutral-300">{{ $case->category }}</b></span>
          <span>&middot;</span>
          <span>{{ $isId ? 'DIBUAT:' : 'CREATED:' }} <b class="text-neutral-300">{{ $case->created_at->format('Y-m-d H:i') }} UTC</b></span>
          <span>&middot;</span>
          <span>OPERATOR: <b class="text-neutral-300">{{ $case->creator?->name ?? 'System' }}</b></span>
        </div>
      </div>
    </div>

    <div class="flex items-center gap-2 font-mono">
      @if($case->priority === 'KRITIS')
        <span class="px-2 py-0.5 text-rose-600 bg-rose-500/10 border border-rose-600/50 text-[10px] uppercase font-semibold rounded-sm">CRITICAL</span>
      @elseif($case->priority === 'TINGGI')
        <span class="px-2 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 text-[10px] uppercase font-semibold rounded-sm">HIGH</span>
      @else
        <span class="px-2 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 text-[10px] uppercase rounded-sm">{{ $case->priority }}</span>
      @endif

      @if($case->is_simulation)
        <span class="px-2 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 text-[10px] rounded-sm">SIMULATION</span>
      @endif

      <form action="{{ route('reports.generate') }}" method="POST">
        @csrf
        <input type="hidden" name="case_id" value="{{ $case->id }}">
        <button type="submit" class="px-3 py-1.5 text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 hover:bg-neutral-800/50 text-xs font-mono uppercase tracking-wider rounded-sm flex items-center gap-1.5 font-semibold">
          <i class="fa-solid fa-file-pdf"></i>
          <span>{{ $isId ? 'Buat Intel Brief' : 'Generate Intel Brief' }}</span>
        </button>
      </form>
    </div>
  </div>

  <!-- Primary Claim -->
  <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-sm">
    <div class="text-[10px] uppercase font-mono tracking-widest text-cyan-500 mb-1">
      {{ $isId ? 'KLAIM UTAMA YANG DIPERIKSA' : 'PRIMARY CLAIM UNDER VERIFICATION' }}
    </div>
    <div class="text-xs font-sans text-neutral-300 leading-relaxed font-medium">
      "{{ $case->claim_text }}"
    </div>
  </div>
</div>
<!-- [CaseDetailHeader] END -->

<!-- [CaseModuleNavigationHub] START -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
  <a href="{{ route('scanner.index', ['case_id' => $case->id, 'tab' => 'scan']) }}" class="p-3 bg-neutral-900 border border-neutral-800 hover:bg-neutral-800/50 rounded-sm flex items-center gap-3 transition-opacity duration-150">
    <div class="w-8 h-8 rounded-sm text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 flex items-center justify-center">
      <i class="fa-solid fa-magnifying-glass"></i>
    </div>
    <div>
      <div class="text-xs font-semibold font-mono text-neutral-300">1. Credibility Scanner</div>
      <div class="text-[10px] text-neutral-500 font-mono">{{ $isId ? 'Ekstraksi klaim & skor indikator' : 'Claim extraction & indicator score' }}</div>
    </div>
  </a>

  <a href="{{ route('scanner.index', ['case_id' => $case->id, 'tab' => 'compare']) }}" class="p-3 bg-neutral-900 border border-neutral-800 hover:bg-neutral-800/50 rounded-sm flex items-center gap-3 transition-opacity duration-150">
    <div class="w-8 h-8 rounded-sm text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 flex items-center justify-center">
      <i class="fa-solid fa-scale-balanced"></i>
    </div>
    <div>
      <div class="text-xs font-semibold font-mono text-neutral-300">2. Authenticity Comparator</div>
      <div class="text-[10px] text-neutral-500 font-mono">{{ $isId ? 'Diff kata-per-kata vs dokumen resmi' : 'Word-by-word diff vs official doc' }}</div>
    </div>
  </a>

  <a href="{{ route('scanner.index', ['case_id' => $case->id, 'tab' => 'crosssource']) }}" class="p-3 bg-neutral-900 border border-neutral-800 hover:bg-neutral-800/50 rounded-sm flex items-center gap-3 transition-opacity duration-150">
    <div class="w-8 h-8 rounded-sm text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 flex items-center justify-center">
      <i class="fa-solid fa-code-compare"></i>
    </div>
    <div>
      <div class="text-xs font-semibold font-mono text-neutral-300">3. Cross-Source Workspace</div>
      <div class="text-[10px] text-neutral-500 font-mono">{{ $isId ? 'Kemiripan leksikal vs semantik' : 'Lexical vs semantic similarity' }}</div>
    </div>
  </a>
</div>
<!-- [CaseModuleNavigationHub] END -->

<!-- [CaseEvidenceVaultTable] START -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-2.5">
    <div>
      <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-neutral-300 flex items-center gap-2">
        <i class="fa-solid fa-box-archive text-cyan-500 text-xs"></i>
        <span>{{ $isId ? 'Repositori Bukti Kasus (SHA-256 Terverifikasi)' : 'Case Evidence Repository (SHA-256 Verified)' }}</span>
      </h2>
      <p class="text-xs text-neutral-500 mt-0.5">
        {{ $isId ? 'Setiap berkas bukti disimpan bersama hash SHA-256 dan stempel waktu pengambilan.' : 'Every evidence item is stored with its SHA-256 hash and retrieval timestamp.' }}
      </p>
    </div>

    <div class="flex items-center gap-2 font-mono text-xs">
      <button onclick="document.getElementById('fetch-url-modal').classList.remove('hidden')" class="px-3 py-1.5 bg-neutral-950 hover:bg-neutral-800/50 text-neutral-300 uppercase rounded-sm border border-neutral-800 flex items-center gap-1.5">
        <i class="fa-solid fa-globe text-cyan-500"></i>
        <span>{{ $isId ? 'Ambil via URL' : 'Fetch URL' }}</span>
      </button>
      <button onclick="document.getElementById('import-evidence-modal').classList.remove('hidden')" class="px-3 py-1.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 uppercase rounded-sm flex items-center gap-1.5 font-semibold">
        <i class="fa-solid fa-file-import"></i>
        <span>{{ $isId ? 'Tambah Bukti' : 'Add Evidence' }}</span>
      </button>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-widest text-neutral-500 bg-neutral-950">
          <th class="py-2 px-3">EVIDENCE ID</th>
          <th class="py-2 px-3">{{ $isId ? 'PERAN' : 'ROLE' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'JUDUL & KUTIPAN' : 'TITLE & EXCERPT' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'PENERBIT / PLATFORM' : 'PUBLISHER / PLATFORM' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'AKSES' : 'ACCESS' }}</th>
          <th class="py-2 px-3">SHA-256 HASH</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800 leading-tight">
        @forelse($case->evidence as $e)
        <tr class="hover:bg-neutral-800/50 transition-opacity duration-150">
          <td class="py-2.5 px-3 font-semibold text-cyan-500 whitespace-nowrap">
            {{ $e->evidence_code }}
            @if($e->is_simulation)
            <span class="block text-[10px] text-amber-500 font-normal">SIMULATION</span>
            @endif
          </td>
          <td class="py-2.5 px-3 whitespace-nowrap">
            @if($e->role === 'PRIMARY')
              <span class="px-2 py-0.5 text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 text-[10px] uppercase font-semibold rounded-sm">PRIMARY</span>
            @elseif($e->role === 'REFERENCE')
              <span class="px-2 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 text-[10px] uppercase rounded-sm">REFERENCE</span>
            @else
              <span class="px-2 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 text-[10px] uppercase rounded-sm">{{ $e->role }}</span>
            @endif
          </td>
          <td class="py-2.5 px-3 max-w-sm">
            <div class="font-sans text-neutral-300 font-medium truncate">{{ $e->title }}</div>
            <div class="text-xs text-neutral-500 font-sans truncate mt-0.5">{{ $e->extracted_text }}</div>
          </td>
          <td class="py-2.5 px-3 whitespace-nowrap">
            <div class="text-neutral-300">{{ $e->publisher ?? '-' }}</div>
            <div class="text-[10px] text-neutral-500 uppercase">{{ $e->platform }}</div>
          </td>
          <td class="py-2.5 px-3 whitespace-nowrap text-emerald-500 text-[10px]">
            {{ $e->access_status }}
          </td>
          <td class="py-2.5 px-3 font-mono text-[10px] text-neutral-500 select-all">
            {{ $e->content_hash ? substr($e->content_hash, 0, 14) . '...' : 'N/A' }}
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="6" class="py-6 text-center text-neutral-500">—</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<!-- [CaseEvidenceVaultTable] END -->

<!-- [ImportEvidenceModal] START -->
<div id="import-evidence-modal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-md z-50 flex items-center justify-center p-4">
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm w-full max-w-xl p-5 space-y-4">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
      <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-neutral-300">
        {{ $isId ? 'Tambah Bukti Digital Manual' : 'Add Digital Evidence Manually' }}
      </h2>
      <button onclick="document.getElementById('import-evidence-modal').classList.add('hidden')" aria-label="Close modal" class="text-neutral-500 hover:text-neutral-300">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('cases.import-evidence', $case->id) }}" method="POST" enctype="multipart/form-data" class="space-y-3 text-xs font-mono">
      @csrf
      <div class="space-y-1">
        <label class="text-[10px] uppercase tracking-widest text-neutral-500">{{ $isId ? 'JUDUL BUKTI' : 'EVIDENCE TITLE' }}</label>
        <input type="text" name="title" required class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="text-[10px] uppercase tracking-widest text-neutral-500">{{ $isId ? 'PERAN BUKTI' : 'ROLE' }}</label>
          <select name="role" required class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm">
            <option value="REFERENCE">REFERENCE</option>
            <option value="PRIMARY">PRIMARY</option>
            <option value="COMPARED">COMPARED</option>
            <option value="POST">POST</option>
          </select>
        </div>
        <div class="space-y-1">
          <label class="text-[10px] uppercase tracking-widest text-neutral-500">{{ $isId ? 'JENIS' : 'TYPE' }}</label>
          <select name="type" required class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm">
            <option value="TEXT">TEXT</option>
            <option value="DOCUMENT">DOCUMENT</option>
            <option value="SCREENSHOT">SCREENSHOT</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="text-[10px] uppercase tracking-widest text-neutral-500">{{ $isId ? 'PENERBIT / SUMBER' : 'PUBLISHER / SOURCE' }}</label>
          <input type="text" name="publisher" class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm">
        </div>
        <div class="space-y-1">
          <label class="text-[10px] uppercase tracking-widest text-neutral-500">PLATFORM</label>
          <input type="text" name="platform" value="WEB" class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm">
        </div>
      </div>
      <div class="space-y-1">
        <label class="text-[10px] uppercase tracking-widest text-neutral-500">{{ $isId ? 'ISI TEKS ASLI' : 'EXTRACTED VERBATIM TEXT' }}</label>
        <textarea name="extracted_text" rows="4" class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-2 border-t border-neutral-800">
        <button type="button" onclick="document.getElementById('import-evidence-modal').classList.add('hidden')" class="px-3 py-1.5 bg-neutral-950 border border-neutral-800 text-neutral-300 uppercase rounded-sm">
          {{ $isId ? 'Batal' : 'Cancel' }}
        </button>
        <button type="submit" class="px-4 py-1.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 uppercase rounded-sm font-semibold">
          {{ $isId ? 'Simpan Bukti' : 'Save Evidence' }}
        </button>
      </div>
    </form>
  </div>
</div>
<!-- [ImportEvidenceModal] END -->

<!-- [FetchUrlEvidenceModal] START -->
<div id="fetch-url-modal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-md z-50 flex items-center justify-center p-4">
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm w-full max-w-lg p-5 space-y-4">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
      <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-neutral-300">
        {{ $isId ? 'Ambil Bukti dari URL Publik' : 'Fetch Evidence from Public URL' }}
      </h2>
      <button onclick="document.getElementById('fetch-url-modal').classList.add('hidden')" aria-label="Close modal" class="text-neutral-500 hover:text-neutral-300">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('cases.fetch-url', $case->id) }}" method="POST" class="space-y-3 text-xs font-mono">
      @csrf
      <div class="space-y-1">
        <label class="text-[10px] uppercase tracking-widest text-neutral-500">PUBLIC URL</label>
        <input type="url" name="url" required placeholder="https://..." class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="text-[10px] uppercase tracking-widest text-neutral-500">{{ $isId ? 'PENERBIT' : 'PUBLISHER' }}</label>
          <input type="text" name="publisher" class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm">
        </div>
        <div class="space-y-1">
          <label class="text-[10px] uppercase tracking-widest text-neutral-500">{{ $isId ? 'PERAN' : 'ROLE' }}</label>
          <select name="role" required class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm">
            <option value="REFERENCE">REFERENCE</option>
            <option value="PRIMARY">PRIMARY</option>
            <option value="COMPARED">COMPARED</option>
          </select>
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-2 border-t border-neutral-800">
        <button type="button" onclick="document.getElementById('fetch-url-modal').classList.add('hidden')" class="px-3 py-1.5 bg-neutral-950 border border-neutral-800 text-neutral-300 uppercase rounded-sm">
          {{ $isId ? 'Batal' : 'Cancel' }}
        </button>
        <button type="submit" class="px-4 py-1.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 uppercase rounded-sm font-semibold">
          {{ $isId ? 'Ambil' : 'Fetch' }}
        </button>
      </div>
    </form>
  </div>
</div>
<!-- [FetchUrlEvidenceModal] END -->

@endsection
