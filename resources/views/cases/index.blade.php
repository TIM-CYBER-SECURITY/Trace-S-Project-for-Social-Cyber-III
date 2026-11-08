@extends('layouts.tactical')

@php
  $isId = app()->getLocale() === 'id';
@endphp

@section('title', ($isId ? 'Arsip Kasus' : 'Case Archive') . ' // TRACE-S')

@section('content')

<!-- [CaseArchiveHeader] START -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div>
    <div class="flex items-center gap-2.5">
      <h1 class="text-sm font-semibold font-mono tracking-wide text-neutral-300 uppercase">
        {{ $isId ? 'Arsip Kasus Analisis Siber Sosial' : 'Social Cyber Analysis Case Archive' }}
      </h1>
      <span class="text-[10px] font-mono px-2 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 rounded-sm">
        CASE-CENTRIC WORKFLOW
      </span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">
      {{ $isId
        ? 'Pengelolaan berkas kasus, penyimpanan bukti digital dengan hash SHA-256, dan keterkaitan pemeriksaan klaim.'
        : 'Management of case files, SHA-256 digital evidence storage, and linked claim inspections.' }}
    </p>
  </div>

  <div>
    <button onclick="document.getElementById('new-case-modal').classList.remove('hidden')"
            aria-label="Create new case"
            class="px-4 py-2 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 text-xs font-mono uppercase tracking-wider rounded-sm transition-opacity duration-150 flex items-center gap-2 font-semibold">
      <i class="fa-solid fa-folder-plus"></i>
      <span>{{ $isId ? 'Buat Kasus Baru' : 'Create New Case' }}</span>
    </button>
  </div>
</div>
<!-- [CaseArchiveHeader] END -->

<!-- [CaseArchiveTable] START -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-neutral-800 pb-2.5">
    <div class="text-xs font-mono uppercase tracking-wide text-neutral-300 font-semibold">
      {{ $isId ? 'Total Kasus Terdaftar:' : 'Total Registered Cases:' }} <b class="text-cyan-500">{{ $cases->total() }}</b>
    </div>
    <div class="text-[10px] font-mono text-neutral-500 uppercase tracking-widest">
      {{ $isId ? 'URUTAN: TERBARU' : 'SORT: LATEST' }}
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-widest text-neutral-500 bg-neutral-950">
          <th class="py-2 px-3">CASE ID</th>
          <th class="py-2 px-3">{{ $isId ? 'JUDUL & KLAIM UTAMA' : 'TITLE & PRIMARY CLAIM' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'KATEGORI' : 'CATEGORY' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'PRIORITAS' : 'PRIORITY' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'BUKTI' : 'EVIDENCE' }}</th>
          <th class="py-2 px-3">STATUS</th>
          <th class="py-2 px-3 text-right">{{ $isId ? 'AKSI' : 'ACTION' }}</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800 leading-tight">
        @forelse($cases as $c)
        <tr class="hover:bg-neutral-800/50 transition-opacity duration-150">
          <td class="py-2.5 px-3 font-semibold text-cyan-500">
            <a href="{{ route('cases.show', $c->id) }}" class="hover:underline">{{ $c->case_code }}</a>
            @if($c->is_simulation)
            <span class="block text-[10px] font-normal text-amber-500">SIMULATION</span>
            @endif
          </td>
          <td class="py-2.5 px-3 max-w-md">
            <a href="{{ route('cases.show', $c->id) }}" class="font-sans text-neutral-300 hover:text-cyan-500 font-medium block truncate">
              {{ $c->title }}
            </a>
            <div class="text-xs text-neutral-500 font-sans truncate mt-0.5">"{{ $c->claim_text }}"</div>
          </td>
          <td class="py-2.5 px-3 text-xs text-neutral-500">{{ $c->category }}</td>
          <td class="py-2.5 px-3">
            @if($c->priority === 'KRITIS')
              <span class="px-2 py-0.5 text-rose-600 bg-rose-500/10 border border-rose-600/50 text-[10px] uppercase font-semibold rounded-sm">CRITICAL</span>
            @elseif($c->priority === 'TINGGI')
              <span class="px-2 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 text-[10px] uppercase font-semibold rounded-sm">HIGH</span>
            @else
              <span class="px-2 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 text-[10px] uppercase rounded-sm">{{ $c->priority }}</span>
            @endif
          </td>
          <td class="py-2.5 px-3 text-neutral-300">
            <b>{{ $c->evidence_count }}</b> {{ $isId ? 'berkas' : 'items' }}
          </td>
          <td class="py-2.5 px-3">
            <span class="px-2 py-0.5 bg-neutral-950 border border-neutral-800 text-neutral-300 text-[10px] uppercase rounded-sm">
              {{ $c->status }}
            </span>
          </td>
          <td class="py-2.5 px-3 text-right">
            <a href="{{ route('cases.show', $c->id) }}" class="px-2.5 py-1 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 text-[10px] rounded-sm">
              {{ $isId ? 'Buka Kasus' : 'Open Case' }}
            </a>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="py-6 text-center text-neutral-500">
            {{ $isId ? 'Belum ada kasus terdaftar.' : 'No cases registered yet.' }}
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pt-2">
    {{ $cases->links() }}
  </div>
</div>
<!-- [CaseArchiveTable] END -->

<!-- [CreateNewCaseModal] START -->
<div id="new-case-modal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-md z-50 flex items-center justify-center p-4 transition-opacity duration-150">
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm w-full max-w-xl p-5 space-y-4">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
      <div class="flex items-center gap-2">
        <i class="fa-solid fa-folder-plus text-cyan-500"></i>
        <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-neutral-300">
          {{ $isId ? 'Registrasi Kasus Analisis Baru' : 'Register New Analysis Case' }}
        </h2>
      </div>
      <button onclick="document.getElementById('new-case-modal').classList.add('hidden')" aria-label="Close modal" class="text-neutral-500 hover:text-neutral-300">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('cases.store') }}" method="POST" class="space-y-3.5 text-xs font-mono">
      @csrf
      <div class="space-y-1">
        <label class="text-[10px] uppercase tracking-widest text-neutral-500">{{ $isId ? 'JUDUL KASUS' : 'CASE TITLE' }}</label>
        <input type="text" name="title" required placeholder="{{ $isId ? 'mis. Pemeriksaan Klaim Anggaran Pendidikan 2026' : 'e.g. Verification of 2026 Education Budget Claim' }}"
               class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
      </div>

      <div class="space-y-1">
        <label class="text-[10px] uppercase tracking-widest text-neutral-500">{{ $isId ? 'KLAIM UTAMA YANG DIPERIKSA' : 'PRIMARY CLAIM TO VERIFY' }}</label>
        <textarea name="claim_text" rows="3" required placeholder="{{ $isId ? 'Tuliskan bunyi klaim yang beredar...' : 'Enter the exact circulating claim text...' }}"
                  class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500"></textarea>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="text-[10px] uppercase tracking-widest text-neutral-500">{{ $isId ? 'KATEGORI' : 'CATEGORY' }}</label>
          <select name="category" required class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
            <option value="DISINFORMASI STRATEGIS / KOGNITIF">{{ $isId ? 'Disinformasi Terbukti' : 'Verified Disinformation' }}</option>
            <option value="MANIPULASI KONTEN DIGITAL">{{ $isId ? 'Perubahan Konten / Editan' : 'Altered Digital Content' }}</option>
            <option value="MISINFORMASI VIRAL">{{ $isId ? 'Misinformasi Viral' : 'Viral Misinformation' }}</option>
          </select>
        </div>

        <div class="space-y-1">
          <label class="text-[10px] uppercase tracking-widest text-neutral-500">{{ $isId ? 'PRIORITAS' : 'PRIORITY' }}</label>
          <select name="priority" required class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
            <option value="SEDANG">{{ $isId ? 'SEDANG' : 'MEDIUM' }}</option>
            <option value="TINGGI" selected>{{ $isId ? 'TINGGI' : 'HIGH' }}</option>
            <option value="KRITIS">{{ $isId ? 'KRITIS' : 'CRITICAL' }}</option>
          </select>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-neutral-800">
        <button type="button" onclick="document.getElementById('new-case-modal').classList.add('hidden')" class="px-3 py-1.5 bg-neutral-950 hover:bg-neutral-800/50 border border-neutral-800 text-neutral-300 uppercase rounded-sm">
          {{ $isId ? 'Batal' : 'Cancel' }}
        </button>
        <button type="submit" class="px-4 py-1.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 uppercase rounded-sm font-semibold">
          {{ $isId ? 'Simpan Kasus' : 'Save Case' }}
        </button>
      </div>
    </form>
  </div>
</div>
<!-- [CreateNewCaseModal] END -->

@endsection
