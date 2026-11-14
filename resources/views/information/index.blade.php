@extends('layouts.app')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Direktori Informasi' : 'Information Directory'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
  <div>
    <h1 class="font-mono text-sm font-bold uppercase tracking-wider">
      {{ $isId ? 'Direktori & Alur Persetujuan Rekaman Informasi' : 'Information Records & Approval Workflow' }}
    </h1>
    <p class="text-xs text-[var(--text-muted)] mt-0.5">
      {{ $isId ? 'Isolasi lingkup organisasi dan riwayat versi permanen.' : 'Organization-scoped directory with immutable versioning and audited reviews.' }}
    </p>
  </div>
  @if(Auth::user()->hasPermission('info.submit'))
    <a href="{{ route('records.create') }}" class="btn-primary">
      <i class="fa-solid fa-plus"></i><span>{{ $isId ? 'Buat Rekaman Baru' : 'New Information Record' }}</span>
    </a>
  @endif
</div>

<form method="GET" action="{{ route('records.index') }}" class="panel p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5 items-end">
  <div class="lg:col-span-2">
    <label class="block label-meta mb-1">{{ $isId ? 'CARI KODE / JUDUL' : 'SEARCH CODE / TITLE' }}</label>
    <input type="text" name="q" value="{{ request('q') }}" class="input-mil font-mono text-xs">
  </div>
  <div>
    <label class="block label-meta mb-1">{{ $isId ? 'CAKUPAN' : 'SCOPE' }}</label>
    <select name="scope" class="input-mil font-mono text-xs">
      <option value="all" @selected(request('scope') !== 'mine')>{{ $isId ? 'Semua Diizinkan' : 'All Visible' }}</option>
      <option value="mine" @selected(request('scope') === 'mine')>{{ $isId ? 'Milik Saya' : 'My Submissions' }}</option>
    </select>
  </div>
  <div>
    <label class="block label-meta mb-1">STATUS</label>
    <select name="status" class="input-mil font-mono text-xs">
      <option value="">ALL</option>
      @foreach(\App\Models\InformationRecord::STATUSES as $st)
        <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="block label-meta mb-1">{{ $isId ? 'KATEGORI' : 'CATEGORY' }}</label>
    <select name="category" class="input-mil font-mono text-xs">
      <option value="">ALL</option>
      @foreach(config('trace.record_categories', []) as $ck => $cl)
        <option value="{{ $ck }}" @selected(request('category') === $ck)>{{ $cl }}</option>
      @endforeach
    </select>
  </div>
  <button type="submit" class="btn-primary justify-center">{{ $isId ? 'Saring' : 'Filter' }}</button>
</form>

<div class="panel overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table-mil">
      <thead>
        <tr>
          <th>CODE</th>
          <th>{{ $isId ? 'JUDUL' : 'TITLE' }}</th>
          <th>{{ $isId ? 'KATEGORI' : 'CATEGORY' }}</th>
          <th>{{ $isId ? 'PENGAJU' : 'CREATOR' }}</th>
          <th>STATUS</th>
          <th>VER</th>
          <th>{{ $isId ? 'DIPERBARUI' : 'UPDATED' }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($items as $r)
          <tr>
            <td class="font-mono text-xs"><a href="{{ route('records.show', $r->record_code) }}" class="font-bold text-[#586B47] hover:underline">{{ $r->record_code }}</a></td>
            <td class="text-xs">
              <strong>{{ $r->title }}</strong>
            </td>
            <td class="font-mono text-xs">{{ config('trace.record_categories.' . $r->category, $r->category) }}</td>
            <td class="font-mono text-xs">{{ $r->creator?->trace_id ?? '-' }}</td>
            <td><span class="badge {{ $r->status === 'APPROVED' ? 'badge-olive' : ($r->status === 'REJECTED' ? 'badge-danger' : 'badge-sand') }}">{{ $r->status }}</span></td>
            <td class="font-mono text-xs">v{{ $r->current_version }}</td>
            <td class="font-mono text-xs text-[var(--text-muted)]">{{ $r->updated_at?->format('Y-m-d H:i') }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-xs text-[var(--text-muted)] py-6">{{ $isId ? 'Tidak ada rekaman.' : 'No records found.' }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 border-t border-[var(--border-color)]">{{ $items->links() }}</div>
</div>
@endsection
