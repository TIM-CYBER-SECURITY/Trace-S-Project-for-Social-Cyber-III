@extends('layouts.app')
@php
  $isId = app()->getLocale() === 'id';
  $canInstall = Auth::user()->hasPermission('skills.install');
@endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Tata Kelola Skills' : 'Skills Governance'))

@section('content')
<div class="border-b border-[var(--border-color)] pb-3">
  <h1 class="font-mono text-sm font-bold uppercase tracking-wider">
    {{ $isId ? 'Katalog & Manajemen Skills Teks Terkontrol' : 'Controlled Text-Only Skills Catalog & Governance' }}
  </h1>
  <p class="text-xs text-[var(--text-muted)] mt-0.5">
    {{ $isId
      ? 'Hanya manifest tepercaya yang terdaftar di allowlist internal. Tidak ada eksekusi shell, jaringan, atau SQL mentah.'
      : 'Only signed catalog manifests bound to the internal text-only ToolRegistry allowlist can be installed or activated.' }}
  </p>
</div>

<div class="panel overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table-mil">
      <thead>
        <tr>
          <th>SKILL</th>
          <th>VERSION</th>
          <th>TOOL ALLOWLIST</th>
          <th>INSTALL STATUS</th>
          <th>ACTIVATION</th>
          <th>{{ $isId ? 'TINDAKAN' : 'ACTIONS' }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($catalog as $key => $m)
          @php $inst = $installed->get($key); @endphp
          <tr>
            <td class="text-xs">
              <div class="font-mono font-bold">{{ $m['name'] ?? $key }}</div>
              <div class="text-[11px] text-[var(--text-muted)]">{{ $m['description'] ?? '-' }}</div>
              @if(!empty($m['_errors']))
                <div class="text-[11px] text-[#991B1B] font-mono mt-1">{{ implode(' ', $m['_errors']) }}</div>
              @endif
            </td>
            <td class="font-mono text-xs">
              {{ $inst?->version ?? '-' }}
              <div class="text-[10px] text-[var(--text-muted)]">catalog: {{ $m['version'] ?? '-' }}</div>
            </td>
            <td class="font-mono text-xs">{{ $m['tool'] ?? '-' }}</td>
            <td>
              @if($inst && $inst->installation_status === 'INSTALLED')
                <span class="badge badge-olive">INSTALLED</span>
              @elseif($inst && $inst->installation_status === 'REMOVED')
                <span class="badge badge-danger">REMOVED</span>
              @else
                <span class="badge badge-neutral">NOT INSTALLED</span>
              @endif
            </td>
            <td>
              @if($inst && $inst->installation_status === 'INSTALLED')
                <span class="badge {{ $inst->status === 'ENABLED' ? 'badge-olive' : 'badge-warn' }}">{{ $inst->status }}</span>
              @else
                <span class="text-xs text-[var(--text-muted)]">-</span>
              @endif
            </td>
            <td class="space-x-1 whitespace-nowrap">
              @if($inst && $inst->installation_status === 'INSTALLED')
                <a href="{{ route('admin.skills.show', $key) }}" class="btn-secondary py-1 px-2 text-[10px]">{{ $isId ? 'Konfigurasi & Akses' : 'Configure & Assign' }}</a>
                @if($canInstall && $inst->version !== ($m['version'] ?? ''))
                  <form method="POST" action="{{ route('admin.skills.act', $key) }}" class="inline">
                    @csrf
                    <input type="hidden" name="action" value="update">
                    <button type="submit" class="btn-primary py-1 px-2 text-[10px]">Update</button>
                  </form>
                @endif
              @elseif($canInstall && empty($m['_errors']))
                <form method="POST" action="{{ route('admin.skills.act', $key) }}" class="inline">
                  @csrf
                  <input type="hidden" name="action" value="install">
                  <button type="submit" class="btn-primary py-1 px-2 text-[10px]">Install</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-xs text-[var(--text-muted)] py-6">No skills in catalog.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
