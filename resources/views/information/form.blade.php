@extends('layouts.app')
@php
  $isId = app()->getLocale() === 'id';
  $editing = $record !== null;
@endphp

@section('title', 'TRACE-S // ' . ($editing ? ($isId ? 'Ubah Rekaman ' : 'Edit Record ') . $record->record_code : ($isId ? 'Buat Rekaman Informasi' : 'Create Information Record')))

@section('content')
<div class="flex items-center justify-between border-b border-[var(--border-color)] pb-3">
  <div>
    <h1 class="font-mono text-sm font-bold uppercase tracking-wider">
      {{ $editing ? ($isId ? 'Perbarui Rekaman (Versi Baru Immutable)' : 'Update Record (Creates New Immutable Version)') : ($isId ? 'Pengajuan Rekaman Informasi Baru' : 'New Information Record') }}
    </h1>
    @if($editing)
      <div class="font-mono text-xs text-[var(--text-muted)]">{{ $record->record_code }} &middot; Current v{{ $record->current_version }}</div>
    @endif
  </div>
  <a href="{{ $editing ? route('records.show', $record->record_code) : route('records.index') }}" class="btn-secondary">{{ $isId ? 'Batal' : 'Cancel' }}</a>
</div>

<form method="POST" action="{{ $editing ? route('records.update', $record->record_code) : route('records.store') }}" class="panel p-5 space-y-4">
  @csrf
  @if($editing)
    @method('PUT')
  @endif

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="md:col-span-2">
      <label class="block label-meta mb-1">{{ $isId ? 'Judul Informasi' : 'Title' }} *</label>
      <input type="text" name="title" value="{{ old('title', $version?->title ?? $record?->title) }}" required class="input-mil">
    </div>
    <div>
      <label class="block label-meta mb-1">{{ $isId ? 'Kategori' : 'Category' }} *</label>
      <select name="category" required class="input-mil font-mono text-xs">
        @foreach(config('trace.record_categories', []) as $ck => $cl)
          <option value="{{ $ck }}" @selected(old('category', $record?->category) === $ck)>{{ $cl }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <div>
    <label class="block label-meta mb-1">{{ $isId ? 'Ringkasan / Deskripsi Singkat' : 'Summary / Short Description' }}</label>
    <input type="text" name="description" value="{{ old('description', $version?->description ?? $record?->description) }}" class="input-mil">
  </div>

  <div>
    <label class="block label-meta mb-1">{{ $isId ? 'Isi / Uraian Lengkap (min. 10 karakter)' : 'Full Record Content (min. 10 chars)' }} *</label>
    <textarea name="content" rows="8" required class="input-mil font-mono text-xs">{{ old('content', $version?->content) }}</textarea>
  </div>

  @if($editing)
    <div>
      <label class="block label-meta mb-1">{{ $isId ? 'Alasan Perubahan Versi' : 'Version Change Reason' }}</label>
      <input type="text" name="change_reason" value="{{ old('change_reason') }}" placeholder="{{ $isId ? 'Catatan perbaikan...' : 'Summary of changes...' }}" class="input-mil">
    </div>
  @endif

  <div class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-[var(--border-color)]">
    <button type="submit" class="btn-secondary">
      <i class="fa-solid fa-floppy-disk"></i>
      <span>{{ $editing ? ($isId ? 'Simpan Versi Baru' : 'Save New Version') : ($isId ? 'Simpan sebagai Draft' : 'Save as Draft') }}</span>
    </button>
    @if(!$editing)
      <button type="submit" name="submit_now" value="1" class="btn-primary">
        <i class="fa-solid fa-paper-plane"></i>
        <span>{{ $isId ? 'Simpan & Ajukan Review' : 'Save & Submit for Review' }}</span>
      </button>
    @endif
  </div>
</form>
@endsection
