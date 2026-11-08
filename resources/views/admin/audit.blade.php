@extends('layouts.tactical')

@php
  $isId = app()->getLocale() === 'id';
@endphp

@section('title', ($isId ? 'Log Audit Permanen' : 'Immutable Audit Log') . ' // TRACE-S')

@section('content')

<!-- [AuditLogHeader] START -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div>
    <div class="flex items-center gap-2.5">
      <h1 class="text-sm font-semibold font-mono tracking-wide text-neutral-300 uppercase">
        {{ $isId ? 'Log Audit Permanen (Append-Only — §5.9)' : 'Immutable Audit Log (Append-Only — §5.9)' }}
      </h1>
      <span class="text-[10px] font-mono px-2 py-0.5 text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 rounded-sm">
        APPEND-ONLY CONTRACT
      </span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">
      {{ $isId
        ? 'Catatan waktu, pengguna, aksi, hash referensi, dan status. Tombol edit atau hapus tidak pernah ada di antarmuka maupun backend.'
        : 'Records timestamp, user, action, reference hash, and status. Edit or delete controls never exist in the UI or backend.' }}
    </p>
  </div>

  <div class="flex items-center gap-2">
    <div class="px-3 py-1 bg-neutral-900 border border-neutral-800 rounded-sm font-mono text-[10px] uppercase tracking-widest text-emerald-500 flex items-center gap-2">
      <i class="fa-solid fa-lock"></i>
      <span>IMMUTABLE LOG ACTIVE</span>
    </div>
  </div>
</div>
<!-- [AuditLogHeader] END -->

<!-- [AuditLogFilterBar] START -->
<form method="GET" action="{{ route('admin.audit.index') }}" class="bg-neutral-900 border border-neutral-800 p-3 rounded-sm flex flex-wrap items-end gap-3 text-xs font-mono">
  <div class="flex-1 min-w-[200px]">
    <label class="block text-[10px] text-neutral-500 uppercase tracking-widest mb-1">
      {{ $isId ? 'FILTER KODE AKSI' : 'FILTER BY ACTION CODE' }}
    </label>
    <input type="text" name="action" value="{{ request('action') }}"
           placeholder="e.g. AUTH_LOGIN_SUCCESS, CREDIBILITY_SCAN_RUN..."
           class="w-full bg-neutral-950 border border-neutral-800 px-3 py-1.5 text-neutral-300 placeholder-neutral-500 focus:outline-none focus:border-cyan-500 rounded-sm text-xs">
  </div>

  <div class="w-48">
    <label class="block text-[10px] text-neutral-500 uppercase tracking-widest mb-1">
      {{ $isId ? 'FILTER IP INTERNAL' : 'FILTER INTERNAL IP' }}
    </label>
    <input type="text" name="ip" value="{{ request('ip') }}" placeholder="127.0.0.1"
           class="w-full bg-neutral-950 border border-neutral-800 px-3 py-1.5 text-neutral-300 placeholder-neutral-500 focus:outline-none focus:border-cyan-500 rounded-sm text-xs">
  </div>

  <div class="flex items-center gap-2">
    <button type="submit" aria-label="Apply audit filter" class="px-3.5 py-1.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 text-xs font-mono uppercase tracking-wider rounded-sm font-semibold">
      {{ $isId ? 'Terapkan' : 'Filter' }}
    </button>
    @if(request('action') || request('ip'))
      <a href="{{ route('admin.audit.index') }}" class="px-3 py-1.5 bg-neutral-950 border border-neutral-800 text-neutral-500 hover:text-neutral-300 text-xs font-mono uppercase tracking-wider rounded-sm">
        Reset
      </a>
    @endif
  </div>
</form>
<!-- [AuditLogFilterBar] END -->

<!-- [AuditLogImmutableTable] START -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm overflow-hidden">
  <div class="px-4 py-2.5 border-b border-neutral-800 flex items-center justify-between">
    <span class="font-mono text-xs text-neutral-300 font-semibold uppercase tracking-wide">
      {{ $isId ? 'Tabel Catatan Audit' : 'Audit Trail Records' }} ({{ $logs->total() }})
    </span>
    <span class="text-[10px] font-mono text-neutral-500 uppercase tracking-widest">
      {{ $isId ? 'TANPA TOMBOL EDIT / HAPUS (§5.9)' : 'NO EDIT / DELETE CONTROLS (§5.9)' }}
    </span>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="bg-neutral-950 text-neutral-500 text-[10px] uppercase tracking-widest border-b border-neutral-800">
          <th class="py-2 px-3">{{ $isId ? 'WAKTU (UTC)' : 'TIMESTAMP (UTC)' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'PENGGUNA' : 'USER' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'AKSI' : 'ACTION' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'HASH REFERENSI' : 'REFERENCE HASH' }}</th>
          <th class="py-2 px-3">STATUS</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800 leading-tight">
        @forelse($logs as $log)
          @php
            $refHash = substr(hash('sha256', $log->id . '|' . $log->action . '|' . $log->created_at), 0, 16);
          @endphp
          <tr class="hover:bg-neutral-800/50 transition-opacity duration-150">
            <td class="py-2.5 px-3 text-neutral-500 whitespace-nowrap">
              {{ $log->created_at->format('Y-m-d H:i:s') }}
            </td>
            <td class="py-2.5 px-3">
              @if($log->user)
                <span class="font-semibold text-neutral-300">{{ $log->user->name }}</span>
                <span class="text-[10px] text-neutral-500 ml-1">({{ strtoupper($log->user->role) }})</span>
              @else
                <span class="text-neutral-500">SYSTEM</span>
              @endif
            </td>
            <td class="py-2.5 px-3">
              <span class="px-2 py-0.5 text-[10px] text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 rounded-sm uppercase">
                {{ $log->action }}
              </span>
            </td>
            <td class="py-2.5 px-3 text-neutral-500 select-all">
              sha256:{{ $refHash }}...
            </td>
            <td class="py-2.5 px-3">
              @if(str_contains($log->action, 'FAILED') || str_contains($log->action, 'DENIED'))
                <span class="px-2 py-0.5 text-[10px] text-rose-600 bg-rose-500/10 border border-rose-600/50 rounded-sm uppercase">DENIED</span>
              @else
                <span class="px-2 py-0.5 text-[10px] text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 rounded-sm uppercase">RECORDED</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="py-6 text-center text-neutral-500 font-mono text-xs">
              {{ $isId ? 'Tidak ada catatan audit.' : 'No audit records found.' }}
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($logs->hasPages())
    <div class="px-4 py-3 border-t border-neutral-800 bg-neutral-950 flex items-center justify-between text-xs font-mono">
      <div class="text-neutral-500 text-[10px] uppercase tracking-widest">
        PAGE {{ $logs->currentPage() }} OF {{ $logs->lastPage() }}
      </div>
      <div>
        {{ $logs->links() }}
      </div>
    </div>
  @endif
</div>
<!-- [AuditLogImmutableTable] END -->

@endsection
