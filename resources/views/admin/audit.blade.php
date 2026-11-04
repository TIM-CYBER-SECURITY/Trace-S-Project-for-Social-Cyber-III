@extends('layouts.tactical')

@section('title', 'Immutable Audit Log // TRACE-S')

@section('content')

<!-- Header Bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div>
    <div class="flex items-center gap-2">
      <h1 class="text-lg font-bold font-mono tracking-wide text-neutral-100 uppercase">Immutable Audit Trail</h1>
      <span class="text-[10px] font-mono px-2 py-0.5 border border-purple-500/40 bg-purple-950/30 text-purple-400 rounded-sm">KEAMANAN &amp; KEPATUHAN MILITER</span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">Catatan log append-only tidak dapat diubah atau dihapus. Mengaudit seluruh interaksi operator, eksekusi tool AI, otentikasi, dan perubahan investigasi.</p>
  </div>

  <div class="flex items-center gap-2">
    <div class="px-3 py-1 bg-neutral-900 border border-neutral-800 rounded-sm font-mono text-[11px] text-neutral-400 flex items-center gap-2">
      <i class="fa-solid fa-shield-halved text-emerald-400"></i>
      <span>Status: <b>INTEGRITY ENFORCED</b></span>
    </div>
  </div>
</div>

<!-- Info Alert Immutability -->
<div class="bg-neutral-900/80 border-l-2 border-purple-500 border-r border-y border-neutral-800 p-3.5 rounded-sm flex items-start gap-3 text-xs">
  <i class="fa-solid fa-lock text-purple-400 text-sm mt-0.5"></i>
  <div class="space-y-1">
    <div class="font-bold font-mono text-neutral-200 uppercase tracking-wide">Kebijakan Integritas Log PUSSIBERAD</div>
    <p class="text-neutral-400 text-[11px] leading-relaxed">
      Sesuai Standar Operasional Siber TNI AD, model <code class="text-neutral-300 bg-neutral-950 px-1 py-0.5 rounded">SystemLog</code> memproteksi event <code class="text-neutral-300">updating</code> dan <code class="text-neutral-300">deleting</code> di tingkat ORM Eloquent. Setiap record dilindungi oleh hashing kriptografis dan stempel waktu terverifikasi.
    </p>
  </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="{{ route('admin.audit.index') }}" class="bg-neutral-900/60 border border-neutral-800 p-3 rounded-sm flex flex-wrap items-center gap-3 text-xs font-mono">
  <div class="flex-1 min-w-[200px]">
    <label class="block text-[10px] text-neutral-500 uppercase mb-1">Filter Berdasarkan Action / Event</label>
    <div class="relative">
      <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2.5 text-neutral-500 text-xs"></i>
      <input type="text" name="action" value="{{ request('action') }}" placeholder="Contoh: auth.login, report.generate, case.create..." class="w-full bg-neutral-950 border border-neutral-800 pl-8 pr-3 py-1.5 text-neutral-200 placeholder-neutral-600 focus:outline-none focus:border-cyan-500 rounded-sm text-xs">
    </div>
  </div>

  <div class="w-48">
    <label class="block text-[10px] text-neutral-500 uppercase mb-1">Filter Alamat IP</label>
    <input type="text" name="ip" value="{{ request('ip') }}" placeholder="127.0.0.1" class="w-full bg-neutral-950 border border-neutral-800 px-3 py-1.5 text-neutral-200 placeholder-neutral-600 focus:outline-none focus:border-cyan-500 rounded-sm text-xs">
  </div>

  <div class="flex items-end gap-2 pt-4">
    <button type="submit" class="px-3.5 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-neutral-200 font-mono text-xs uppercase tracking-wider rounded-sm transition-all flex items-center gap-1.5">
      <i class="fa-solid fa-filter text-xs"></i>
      <span>Terapkan</span>
    </button>
    @if(request('action') || request('ip'))
      <a href="{{ route('admin.audit.index') }}" class="px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800 text-neutral-400 font-mono text-xs uppercase tracking-wider rounded-sm transition-all">
        Reset
      </a>
    @endif
  </div>
</form>

<!-- Audit Log Table -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm overflow-hidden">
  <div class="px-4 py-2.5 border-b border-neutral-800 flex items-center justify-between">
    <div class="flex items-center gap-2">
      <i class="fa-solid fa-list-check text-neutral-500 text-xs"></i>
      <span class="font-mono text-xs text-neutral-300 font-bold uppercase tracking-wider">Daftar Rekam Jejak Audit (Total: {{ $logs->total() }})</span>
    </div>
    <span class="text-[10px] font-mono text-neutral-500">Menampilkan 25 entri per halaman</span>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead class="bg-neutral-950 text-neutral-400 text-[10px] uppercase tracking-wider border-b border-neutral-800">
        <tr>
          <th class="py-2.5 px-3">#ID</th>
          <th class="py-2.5 px-3">Waktu (WIB)</th>
          <th class="py-2.5 px-3">Personel / User</th>
          <th class="py-2.5 px-3">Aksi (Action Code)</th>
          <th class="py-2.5 px-3">Entitas Terkait</th>
          <th class="py-2.5 px-3">Alamat IP</th>
          <th class="py-2.5 px-3 text-right">Rincian Payload</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800/60">
        @forelse($logs as $log)
          <tr class="hover:bg-neutral-800/40 transition-colors">
            <td class="py-2.5 px-3 text-neutral-500 text-[11px]">
              #{{ str_pad($log->id, 5, '0', STR_PAD_LEFT) }}
            </td>
            <td class="py-2.5 px-3 text-neutral-300 text-[11px] whitespace-nowrap">
              <i class="fa-regular fa-clock text-neutral-600 mr-1 text-[10px]"></i>
              {{ $log->created_at->format('Y-m-d H:i:s') }}
            </td>
            <td class="py-2.5 px-3">
              @if($log->user)
                <div class="flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
                  <span class="font-bold text-neutral-200">{{ $log->user->name }}</span>
                  <span class="text-[10px] text-neutral-500">({{ strtoupper($log->user->role) }})</span>
                </div>
                <div class="text-[10px] text-neutral-500 font-mono">{{ $log->user->rank_title ?? 'NRP '.$log->user->nrp }}</div>
              @else
                <span class="text-neutral-500 italic">SYSTEM / ANONYMOUS</span>
              @endif
            </td>
            <td class="py-2.5 px-3">
              @php
                $badgeColor = 'bg-neutral-800 text-neutral-300 border-neutral-700';
                if (str_starts_with($log->action, 'auth.')) {
                    $badgeColor = 'bg-blue-950/40 text-blue-400 border-blue-500/40';
                } elseif (str_starts_with($log->action, 'case.') || str_starts_with($log->action, 'evidence.')) {
                    $badgeColor = 'bg-emerald-950/40 text-emerald-400 border-emerald-500/40';
                } elseif (str_starts_with($log->action, 'ai.') || str_starts_with($log->action, 'tool.')) {
                    $badgeColor = 'bg-purple-950/40 text-purple-400 border-purple-500/40';
                } elseif (str_starts_with($log->action, 'report.')) {
                    $badgeColor = 'bg-amber-950/40 text-amber-400 border-amber-500/40';
                } elseif (str_contains($log->action, 'violation') || str_contains($log->action, 'denied')) {
                    $badgeColor = 'bg-rose-950/40 text-rose-400 border-rose-500/40';
                }
              @endphp
              <span class="px-2 py-0.5 border text-[10px] rounded-sm uppercase tracking-wide {{ $badgeColor }}">
                {{ $log->action }}
              </span>
            </td>
            <td class="py-2.5 px-3 text-neutral-400 text-[11px]">
              @if($log->target_type)
                <span class="text-neutral-300">{{ class_basename($log->target_type) }}</span>
                <span class="text-neutral-600">#{{ $log->target_id }}</span>
              @else
                <span class="text-neutral-600">-</span>
              @endif
            </td>
            <td class="py-2.5 px-3 text-neutral-400 text-[11px] font-mono whitespace-nowrap">
              {{ $log->ip ?? '127.0.0.1' }}
            </td>
            <td class="py-2.5 px-3 text-right">
              @if(!empty($log->metadata))
                <details class="cursor-pointer group">
                  <summary class="list-none inline-flex items-center gap-1 text-[10px] text-cyan-400 hover:text-cyan-300 bg-neutral-950 border border-neutral-800 px-2 py-0.5 rounded-sm">
                    <i class="fa-solid fa-code text-[9px]"></i>
                    <span>Metadata</span>
                    <i class="fa-solid fa-chevron-down text-[8px] group-open:rotate-180 transition-transform"></i>
                  </summary>
                  <div class="mt-2 text-left bg-neutral-950 border border-neutral-800 p-2.5 rounded text-[10px] text-neutral-300 font-mono overflow-x-auto max-w-sm whitespace-pre">
{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}
                  </div>
                </details>
              @else
                <span class="text-neutral-600 text-[10px]">-</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="py-8 text-center text-neutral-500 font-mono text-xs">
              <i class="fa-solid fa-inbox text-neutral-600 text-2xl mb-2 block"></i>
              Tidak ada log audit yang sesuai dengan filter.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($logs->hasPages())
    <div class="px-4 py-3 border-t border-neutral-800 bg-neutral-950 flex items-center justify-between text-xs font-mono">
      <div class="text-neutral-500 text-[11px]">
        Halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }}
      </div>
      <div>
        {{ $logs->links() }}
      </div>
    </div>
  @endif
</div>

@endsection
