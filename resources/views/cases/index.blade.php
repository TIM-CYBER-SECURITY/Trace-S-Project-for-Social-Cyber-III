@extends('layouts.app')

@php
  $isId = app()->getLocale() === 'id';
  $canWriteCase = Auth::user()->hasPermission('case.write');
@endphp

@section('title', ($isId ? 'Arsip Kasus' : 'Case Archive') . ' // TRACE-S')

@section('content')
<div class="space-y-5">

  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
    <div>
      <div class="flex flex-wrap items-center gap-2">
        <h1 class="text-lg font-semibold tracking-tight">
          {{ $isId ? 'Arsip Kasus Analisis Siber Sosial' : 'Social Cyber Analysis Case Archive' }}
        </h1>
        <span class="badge badge-olive">CASE-CENTRIC WORKFLOW</span>
      </div>
      <p class="text-xs text-[var(--text-muted)] mt-0.5">
        {{ $isId
          ? 'Pengelolaan berkas kasus investigasi, repositori bukti SHA-256, dan pelacakan pemeriksaan klaim.'
          : 'Case dossier management, SHA-256 digital evidence repository, and linked claim inspections.' }}
      </p>
    </div>

    @if($canWriteCase)
    <button type="button" onclick="document.getElementById('new-case-modal').classList.remove('hidden')" class="btn-primary self-start sm:self-auto">
      <i class="fa-solid fa-folder-plus"></i>
      <span>{{ $isId ? 'Buat Kasus Baru' : 'Create New Case' }}</span>
    </button>
    @endif
  </div>

  <div class="panel">
    <div class="panel-header">
      <span class="font-mono text-xs font-semibold uppercase tracking-wider">
        {{ $isId ? 'Total Kasus Terdaftar:' : 'Registered Cases:' }} {{ $cases->total() }}
      </span>
      <span class="label-meta">{{ $isId ? 'Urutan: Terbaru' : 'Sort: Latest' }}</span>
    </div>

    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead>
          <tr>
            <th>Case ID</th>
            <th>{{ $isId ? 'Judul & Klaim Utama' : 'Title & Primary Claim' }}</th>
            <th>{{ $isId ? 'Kategori' : 'Category' }}</th>
            <th>{{ $isId ? 'Prioritas' : 'Priority' }}</th>
            <th>{{ $isId ? 'Bukti' : 'Evidence' }}</th>
            <th>Status</th>
            <th class="text-right">{{ $isId ? 'Aksi' : 'Action' }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($cases as $c)
          <tr>
            <td class="font-mono font-semibold whitespace-nowrap">
              <a href="{{ route('cases.show', $c->id) }}" class="text-[var(--accent-olive)] dark:text-[#C5B89C] hover:underline">{{ $c->case_code }}</a>
            </td>
            <td class="max-w-md">
              <a href="{{ route('cases.show', $c->id) }}" class="font-semibold hover:underline block truncate">{{ $c->title }}</a>
              <div class="text-xs text-[var(--text-muted)] truncate mt-0.5">"{{ $c->claim_text }}"</div>
            </td>
            <td class="text-xs text-[var(--text-muted)]">{{ $c->category }}</td>
            <td>
              <span class="badge {{ $c->priority === 'KRITIS' ? 'badge-danger' : ($c->priority === 'TINGGI' ? 'badge-warn' : 'badge-neutral') }}">
                {{ $c->priority }}
              </span>
            </td>
            <td class="font-mono text-xs whitespace-nowrap"><b>{{ $c->evidence_count }}</b> {{ $isId ? 'berkas' : 'items' }}</td>
            <td><span class="badge badge-neutral">{{ $c->status }}</span></td>
            <td class="text-right whitespace-nowrap">
              <a href="{{ route('cases.show', $c->id) }}" class="btn-secondary py-1 px-2.5 text-[11px]">
                {{ $isId ? 'Buka Kasus' : 'Open Case' }}
              </a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center text-[var(--text-muted)] py-6">
              {{ $isId ? 'Belum ada kasus terdaftar.' : 'No cases registered yet.' }}
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($cases->hasPages())
    <div class="p-3 border-t border-[var(--border-color)]">{{ $cases->links() }}</div>
    @endif
  </div>

</div>

@if($canWriteCase)
<div id="new-case-modal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
  <div class="panel w-full max-w-xl shadow-2xl">
    <div class="panel-header">
      <h2 class="font-mono text-xs font-semibold uppercase tracking-wider">
        {{ $isId ? 'Registrasi Kasus Analisis Baru' : 'Register New Analysis Case' }}
      </h2>
      <button type="button" onclick="document.getElementById('new-case-modal').classList.add('hidden')" class="btn-secondary py-1 px-2 text-xs" aria-label="Close modal">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('cases.store') }}" method="POST" class="p-4 space-y-3.5 text-xs">
      @csrf
      <div class="space-y-1">
        <label class="label-meta">{{ $isId ? 'Judul Kasus' : 'Case Title' }}</label>
        <input type="text" name="title" required placeholder="{{ $isId ? 'Contoh: Pemeriksaan Klaim Anggaran Pendidikan' : 'e.g. Education Budget Claim Verification' }}" class="input-mil">
      </div>

      <div class="space-y-1">
        <label class="label-meta">{{ $isId ? 'Klaim Utama yang Diperiksa' : 'Primary Claim Under Verification' }}</label>
        <textarea name="claim_text" rows="3" required placeholder="{{ $isId ? 'Tuliskan bunyi klaim yang beredar...' : 'Enter the circulating claim text...' }}" class="input-mil"></textarea>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="label-meta">{{ $isId ? 'Kategori' : 'Category' }}</label>
          <select name="category" required class="input-mil font-mono text-xs">
            <option value="DISINFORMASI STRATEGIS / KOGNITIF">{{ $isId ? 'Disinformasi Terbukti' : 'Disinformation' }}</option>
            <option value="MANIPULASI KONTEN DIGITAL">{{ $isId ? 'Alterasi Konten Digital' : 'Altered Digital Content' }}</option>
            <option value="MISINFORMASI VIRAL">{{ $isId ? 'Misinformasi Viral' : 'Viral Misinformation' }}</option>
          </select>
        </div>

        <div class="space-y-1">
          <label class="label-meta">{{ $isId ? 'Prioritas' : 'Priority' }}</label>
          <select name="priority" required class="input-mil font-mono text-xs">
            <option value="SEDANG">{{ $isId ? 'SEDANG' : 'MEDIUM' }}</option>
            <option value="TINGGI" selected>{{ $isId ? 'TINGGI' : 'HIGH' }}</option>
            <option value="KRITIS">{{ $isId ? 'KRITIS' : 'CRITICAL' }}</option>
          </select>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-[var(--border-color)]">
        <button type="button" onclick="document.getElementById('new-case-modal').classList.add('hidden')" class="btn-secondary">{{ $isId ? 'Batal' : 'Cancel' }}</button>
        <button type="submit" class="btn-primary">{{ $isId ? 'Simpan Kasus' : 'Save Case' }}</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection
