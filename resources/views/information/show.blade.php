@extends('layouts.app')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // ' . $record->record_code)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
  <div>
    <div class="flex items-center gap-2">
      <span class="font-mono text-xs font-bold">{{ $record->record_code }}</span>
      <span class="badge {{ $record->status === 'APPROVED' ? 'badge-olive' : ($record->status === 'REJECTED' ? 'badge-danger' : 'badge-sand') }}">{{ $record->status }}</span>
    </div>
    <h1 class="font-mono text-base font-bold mt-1">{{ $shown?->title ?? $record->title }}</h1>
  </div>

  <div class="flex flex-wrap items-center gap-2">
    @if(Auth::user()->hasPermission('analysis.run'))
      @php
        $recTitleScan = trim((string) ($shown?->title ?? $record->title));
        $recBodyScan = trim((string) ($shown?->content ?? $record->description ?? ''));
        $recScanPayload = ($recBodyScan === '' || mb_strtolower($recTitleScan) === mb_strtolower($recBodyScan) || str_starts_with(mb_strtolower($recBodyScan), mb_strtolower($recTitleScan)))
            ? ($recBodyScan !== '' ? $recBodyScan : $recTitleScan)
            : ($recTitleScan . "\n\n" . $recBodyScan);
      @endphp
      <form method="POST" action="{{ route('scanner.scan') }}" class="inline">
        @csrf
        <input type="hidden" name="case_id" value="auto">
        <input type="hidden" name="text" value="{{ $recScanPayload }}">
        <button type="submit" class="btn-primary">
          <i class="fa-solid fa-magnifying-glass-chart"></i>
          <span>{{ $isId ? 'Investigasi Konten Ini dengan AI Lokal' : 'Investigate with Local AI' }}</span>
        </button>
      </form>
    @endif
    @if($canEdit)
      <a href="{{ route('records.edit', $record->record_code) }}" class="btn-secondary">{{ $isId ? 'Ubah (Versi Baru)' : 'Edit (New Version)' }}</a>
      @if(in_array($record->status, ['DRAFT', 'REVISION REQUIRED'], true) && $record->created_by === Auth::id())
        <form method="POST" action="{{ route('records.submit', $record->record_code) }}" class="inline">
          @csrf
          <button type="submit" class="btn-primary">{{ $isId ? 'Ajukan Review' : 'Submit for Review' }}</button>
        </form>
      @endif
    @endif
    <a href="{{ route('records.index') }}" class="btn-secondary">{{ $isId ? 'Kembali' : 'Back' }}</a>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
  <div class="lg:col-span-2 space-y-4">
    <div class="panel p-5 space-y-3">
      <div class="flex flex-wrap items-center justify-between gap-2 text-xs font-mono text-[var(--text-muted)] border-b border-[var(--border-color)] pb-2">
        <span>CATEGORY: {{ config('trace.record_categories.' . $record->category, $record->category) }}</span>
        <span>VERSION: v{{ $shown?->version_number ?? $record->current_version }} &middot; AUTHOR: {{ $record->creator?->trace_id ?? '-' }}</span>
      </div>
      @if($shown?->description ?? $record->description)
        <div class="text-xs italic text-[var(--text-secondary)]">{{ $shown?->description ?? $record->description }}</div>
      @endif
      <div class="text-sm whitespace-pre-wrap leading-relaxed">{{ $shown?->content }}</div>
    </div>

    @if($versions->isNotEmpty())
      <div class="panel overflow-hidden">
        <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Riwayat Versi Immutable' : 'Immutable Version History' }}</span></div>
        <div class="overflow-x-auto">
          <table class="table-mil">
            <thead><tr><th>VER</th><th>AUTHOR</th><th>REASON</th><th>DATE</th></tr></thead>
            <tbody>
              @foreach($versions as $v)
                <tr>
                  <td class="font-mono text-xs font-bold">v{{ $v->version_number }}</td>
                  <td class="font-mono text-xs">{{ $v->author?->trace_id ?? '-' }}</td>
                  <td class="text-xs">{{ $v->change_reason ?? '-' }}</td>
                  <td class="font-mono text-xs text-[var(--text-muted)]">{{ $v->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif

    @if($reviews->isNotEmpty())
      <div class="panel overflow-hidden">
        <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Riwayat Keputusan Review' : 'Review Decisions' }}</span></div>
        <div class="overflow-x-auto">
          <table class="table-mil">
            <thead><tr><th>DECISION</th><th>REVIEWER</th><th>VER</th><th>COMMENT</th><th>DATE</th></tr></thead>
            <tbody>
              @foreach($reviews as $rv)
                <tr>
                  <td><span class="badge badge-sand">{{ $rv->decision }}</span></td>
                  <td class="font-mono text-xs">{{ $rv->reviewer?->trace_id ?? '-' }}</td>
                  <td class="font-mono text-xs">v{{ $rv->version_number }}</td>
                  <td class="text-xs">{{ $rv->comment ?? '-' }}</td>
                  <td class="font-mono text-xs text-[var(--text-muted)]">{{ $rv->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
  </div>

  <div class="space-y-4">
    @if($canReview && in_array($record->status, ['SUBMITTED', 'UNDER REVIEW'], true))
      <form method="POST" action="{{ route('records.review', $record->record_code) }}" class="panel p-4 space-y-3 border-l-4 border-l-[#586B47]">
        @csrf
        <div class="label-meta">{{ $isId ? 'PANEL TINJAUAN ADMINISTRATOR' : 'ADMINISTRATOR REVIEW PANEL' }}</div>
        <div>
          <label class="block text-xs font-mono mb-1">{{ $isId ? 'Keputusan' : 'Decision' }}</label>
          <select name="decision" required class="input-mil font-mono text-xs">
            @if($record->status === 'SUBMITTED')
              <option value="UNDER REVIEW">UNDER REVIEW</option>
            @endif
            <option value="APPROVED">APPROVED</option>
            <option value="REVISION REQUIRED">REVISION REQUIRED</option>
            <option value="REJECTED">REJECTED</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-mono mb-1">{{ $isId ? 'Komentar / Alasan' : 'Review Comment / Reason' }}</label>
          <textarea name="comment" rows="3" placeholder="{{ $isId ? 'Wajib diisi untuk penolakan/revisi...' : 'Required for revision/rejection...' }}" class="input-mil text-xs"></textarea>
        </div>
        <button type="submit" class="w-full btn-primary justify-center">{{ $isId ? 'Kirim Keputusan Review' : 'Submit Review Decision' }}</button>
      </form>
    @endif

    @if(Auth::user()->hasPermission('info.delete'))
      @if($record->status !== 'ARCHIVED')
        <form method="POST" action="{{ route('records.archive', $record->record_code) }}" class="panel p-4 space-y-2.5">
          @csrf
          <div class="label-meta">{{ $isId ? 'ARSIPKAN REKAMAN' : 'ARCHIVE RECORD' }}</div>
          <input type="text" name="reason" required minlength="5" placeholder="{{ $isId ? 'Alasan pengarsipan (min. 5 karakter)...' : 'Archive reason (min. 5 chars)...' }}" class="input-mil text-xs">
          <button type="submit" class="w-full btn-secondary justify-center">{{ $isId ? 'Arsipkan' : 'Archive Record' }}</button>
        </form>
      @endif

      <form method="POST" action="{{ route('records.destroy', $record->record_code) }}" class="panel p-4 space-y-2.5 border-l-4 border-l-[#991B1B]">
        @csrf
        @method('DELETE')
        <div class="label-meta">{{ $isId ? 'HAPUS LUNAK (SOFT DELETE)' : 'SOFT DELETE RECORD' }}</div>
        <input type="text" name="reason" required minlength="5" placeholder="{{ $isId ? 'Alasan penghapusan wajib...' : 'Deletion reason...' }}" class="input-mil text-xs">
        <button type="submit" class="w-full btn-danger justify-center">{{ $isId ? 'Hapus (Kebijakan Retensi)' : 'Soft Delete (Retention Enforced)' }}</button>
      </form>
    @endif
  </div>
</div>
@endsection
