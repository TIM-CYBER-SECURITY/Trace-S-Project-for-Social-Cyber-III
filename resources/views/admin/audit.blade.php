@extends('layouts.app')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Log Audit Permanen' : 'Immutable Audit Log'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
  <div>
    <h1 class="font-mono text-sm font-bold uppercase tracking-wider">
      {{ $isId ? 'Log Audit Permanen (Append-Only Hash Chain)' : 'Immutable Audit Log (Append-Only Hash Chain)' }}
    </h1>
    <p class="text-xs text-[var(--text-muted)] mt-0.5">
      {{ $isId
        ? 'Catatan waktu, aktor, aksi, target, IP, hasil, dan hash berantai SHA-256. Tanpa fitur edit/hapus di UI, model, maupun trigger DB.'
        : 'Append-only SHA-256 chained audit trail. No edit or deletion controls exist in the UI, application models, or DB triggers.' }}
    </p>
  </div>
  <span class="badge badge-olive"><i class="fa-solid fa-lock"></i> APPEND-ONLY CHAINED</span>
</div>

<form method="GET" action="{{ route('admin.audit.index') }}" class="panel p-3 flex flex-wrap items-end gap-3">
  <div class="flex-1 min-w-[180px]">
    <label class="block label-meta mb-1">{{ $isId ? 'KODE AKSI' : 'ACTION' }}</label>
    <input type="text" name="action" value="{{ request('action') }}" placeholder="LOGIN_SUCCESS, RECORD_APPROVED..." class="input-mil font-mono text-xs">
  </div>
  <div class="w-40">
    <label class="block label-meta mb-1">ACTOR</label>
    <input type="text" name="actor" value="{{ request('actor') }}" placeholder="TRS-..." class="input-mil font-mono text-xs">
  </div>
  <div class="w-36">
    <label class="block label-meta mb-1">RESULT</label>
    <select name="result" class="input-mil font-mono text-xs">
      <option value="">ALL</option>
      @foreach(['SUCCESS', 'FAILURE', 'DENIED', 'ERROR'] as $r)
        <option value="{{ $r }}" @selected(request('result') === $r)>{{ $r }}</option>
      @endforeach
    </select>
  </div>
  <div class="w-36">
    <label class="block label-meta mb-1">IP</label>
    <input type="text" name="ip" value="{{ request('ip') }}" placeholder="127.0.0.1" class="input-mil font-mono text-xs">
  </div>
  <div class="flex items-center gap-2">
    <button type="submit" class="btn-primary">{{ $isId ? 'Saring' : 'Filter' }}</button>
    @if(request()->anyFilled(['action', 'actor', 'result', 'ip']))
      <a href="{{ route('admin.audit.index') }}" class="btn-secondary">Reset</a>
    @endif
  </div>
</form>

<div class="panel overflow-hidden">
  <div class="panel-header">
    <span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Rekaman Audit' : 'Audit Trail' }} ({{ $logs->total() }})</span>
    <span class="label-meta">CLI VERIFY: php artisan trace:audit-verify</span>
  </div>
  <div class="overflow-x-auto">
    <table class="table-mil">
      <thead>
        <tr>
          <th>#</th>
          <th>TIMESTAMP (UTC)</th>
          <th>ACTOR</th>
          <th>ACTION</th>
          <th>TARGET</th>
          <th>IP</th>
          <th>CHAIN HASH</th>
          <th>RESULT</th>
        </tr>
      </thead>
      <tbody>
        @forelse($logs as $log)
          <tr>
            <td class="font-mono text-xs text-[var(--text-muted)]">{{ $log->id }}</td>
            <td class="font-mono text-xs text-[var(--text-muted)] whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
            <td class="font-mono text-xs">
              <strong>{{ $log->actor_label ?? ($log->user?->trace_id ?? 'SYSTEM') }}</strong>
              <span class="text-[10px] text-[var(--text-muted)]">[{{ $log->actor_type ?? 'SYSTEM' }}]</span>
            </td>
            <td><span class="badge badge-neutral">{{ $log->action }}</span></td>
            <td class="font-mono text-xs">{{ $log->target_type }}:{{ $log->target_id }}</td>
            <td class="font-mono text-xs text-[var(--text-muted)]">{{ $log->ip ?? '-' }}</td>
            <td class="font-mono text-[11px] text-[var(--text-muted)] select-all">{{ $log->entry_hash ? substr($log->entry_hash, 0, 16) . '...' : '-' }}</td>
            <td>
              <span class="badge {{ $log->result === 'SUCCESS' ? 'badge-olive' : ($log->result === 'DENIED' ? 'badge-warn' : 'badge-danger') }}">{{ $log->result ?? 'SUCCESS' }}</span>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-xs text-[var(--text-muted)] py-6">-</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 border-t border-[var(--border-color)]">{{ $logs->links() }}</div>
</div>
@endsection
