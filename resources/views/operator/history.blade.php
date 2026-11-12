@extends('layouts.app')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Riwayat Aktivitas Pribadi' : 'Personal Activity History'))

@section('content')
<div class="border-b border-[var(--border-color)] pb-3 flex items-center justify-between">
  <div>
    <h1 class="font-mono text-sm font-bold uppercase tracking-wider">{{ $isId ? 'Riwayat Pengajuan & Aktivitas Pribadi' : 'Personal Submissions & Activity History' }}</h1>
    <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ Auth::user()->trace_id }}</p>
  </div>
</div>

<div class="panel overflow-hidden">
  <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Pengajuan Informasi Saya' : 'My Information Submissions' }}</span></div>
  <div class="overflow-x-auto">
    <table class="table-mil">
      <thead><tr><th>CODE</th><th>TITLE</th><th>CATEGORY</th><th>STATUS</th><th>VERSIONS</th><th>UPDATED</th></tr></thead>
      <tbody>
        @forelse($records as $r)
          <tr>
            <td class="font-mono text-xs"><a href="{{ route('records.show', $r->record_code) }}" class="font-bold text-[#586B47] hover:underline">{{ $r->record_code }}</a></td>
            <td class="text-xs">{{ $r->title }}</td>
            <td class="font-mono text-xs">{{ $r->category }}</td>
            <td><span class="badge badge-sand">{{ $r->status }}</span></td>
            <td class="font-mono text-xs">{{ $r->versions_count }}</td>
            <td class="font-mono text-xs text-[var(--text-muted)]">{{ $r->updated_at?->format('Y-m-d H:i') }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-xs text-[var(--text-muted)] py-5">-</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 border-t border-[var(--border-color)]">{{ $records->links() }}</div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  <div class="panel overflow-hidden">
    <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Keputusan Review pada Pengajuan Saya' : 'Review Decisions on My Records' }}</span></div>
    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead><tr><th>DECISION</th><th>REVIEWER</th><th>COMMENT</th><th>DATE</th></tr></thead>
        <tbody>
          @forelse($reviews as $rv)
            <tr>
              <td><span class="badge badge-sand">{{ $rv->decision }}</span></td>
              <td class="font-mono text-xs">{{ $rv->reviewer?->trace_id ?? '-' }}</td>
              <td class="text-xs">{{ $rv->comment ?? '-' }}</td>
              <td class="font-mono text-xs text-[var(--text-muted)]">{{ $rv->created_at?->format('Y-m-d H:i') }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-xs text-[var(--text-muted)] py-4">-</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel overflow-hidden">
    <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Log Aktivitas Saya' : 'My Audit Activity' }}</span></div>
    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead><tr><th>TIME</th><th>ACTION</th><th>TARGET</th><th>RESULT</th></tr></thead>
        <tbody>
          @forelse($activity as $a)
            <tr>
              <td class="font-mono text-xs text-[var(--text-muted)]">{{ $a->created_at?->format('Y-m-d H:i') }}</td>
              <td class="font-mono text-xs">{{ $a->action }}</td>
              <td class="font-mono text-xs">{{ $a->target_type }}:{{ $a->target_id }}</td>
              <td><span class="badge {{ $a->result === 'SUCCESS' ? 'badge-olive' : 'badge-warn' }}">{{ $a->result }}</span></td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-xs text-[var(--text-muted)] py-4">-</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-3 border-t border-[var(--border-color)]">{{ $activity->links() }}</div>
  </div>
</div>
@endsection
