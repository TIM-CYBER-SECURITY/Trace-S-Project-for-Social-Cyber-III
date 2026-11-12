@extends('layouts.app')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Kesehatan Sistem & Pengaturan' : 'System Health & Settings'))

@section('content')
<div class="border-b border-[var(--border-color)] pb-3">
  <h1 class="font-mono text-sm font-bold uppercase tracking-wider">
    {{ $isId ? 'Pemeriksaan Kesehatan Sistem, Konektor & Pengaturan Runtime' : 'System Health, Connectors & Runtime Configuration' }}
  </h1>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-3">
  @foreach($checks as $c)
    <div class="panel p-3.5 space-y-1">
      <div class="flex items-center justify-between">
        <span class="font-mono text-xs font-bold">{{ $c['name'] }}</span>
        <span class="badge {{ $c['status'] === 'OK' ? 'badge-olive' : ($c['status'] === 'WARN' ? 'badge-warn' : 'badge-danger') }}">{{ $c['status'] }}</span>
      </div>
      <div class="text-xs text-[var(--text-secondary)]">{{ $c['detail'] }}</div>
    </div>
  @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  <form method="POST" action="{{ route('admin.system.update') }}" class="panel p-4 space-y-3">
    @csrf
    <div class="label-meta">{{ $isId ? 'PENGATURAN SISTEM (TERCATAT DI AUDIT LOG)' : 'SYSTEM SETTINGS (AUDITED)' }}</div>
    @foreach($definitions as $key => $meta)
      <div>
        <label class="block text-xs font-mono mb-1">{{ $meta[0] }} <span class="text-[var(--text-muted)]">({{ $key }})</span></label>
        <input type="{{ $meta[1] === 'int' ? 'number' : 'text' }}" name="{{ $key }}" value="{{ old($key, $values[$key] ?? '') }}" class="input-mil font-mono text-xs">
      </div>
    @endforeach
    @if(Auth::user()->hasPermission('system.configure'))
      <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
        <button type="submit" class="btn-primary">{{ $isId ? 'Simpan Pengaturan' : 'Save Settings' }}</button>
      </div>
    @endif
  </form>

  <div class="panel overflow-hidden flex flex-col justify-between">
    <div>
      <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Status Konektor Data' : 'Data Source Connectors' }}</span></div>
      <div class="overflow-x-auto">
        <table class="table-mil">
          <thead><tr><th>KEY</th><th>NAME</th><th>STATUS</th></tr></thead>
          <tbody>
            @forelse($connectors as $conn)
              <tr>
                <td class="font-mono text-xs">{{ $conn->key }}</td>
                <td class="text-xs">{{ $conn->name }}</td>
                <td><span class="badge badge-sand">{{ $conn->status }}</span></td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center text-xs text-[var(--text-muted)] py-4">-</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if(Auth::user()->hasPermission('system.configure'))
      <div class="p-3.5 border-t border-[var(--border-color)] flex flex-wrap items-center justify-between gap-2 text-xs">
        <span class="text-[var(--text-muted)]">{{ $isId ? 'Bersihkan contoh data simulasi awal (akun & audit log tetap aman).' : 'Clear sample simulation fixtures (accounts & audit log preserved).' }}</span>
        <form method="POST" action="{{ route('admin.system.purge-simulation') }}" onsubmit="return confirm('Hapus seluruh rekaman bertanda is_simulation=true?')">
          @csrf
          <button type="submit" class="btn-danger py-1 px-2.5 text-[11px]">
            <i class="fa-solid fa-broom"></i> <span>{{ $isId ? 'Bersihkan Data Simulasi' : 'Purge Simulation Data' }}</span>
          </button>
        </form>
      </div>
    @endif
  </div>
</div>

<div class="panel overflow-hidden">
  <div class="panel-header flex-wrap">
    <span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Manajemen Sumber RSS Berita (P1)' : 'RSS News Feed Governance (P1)' }}</span>
    <form method="POST" action="{{ route('early-warning.sync-rss') }}" class="inline">
      @csrf
      <button type="submit" class="btn-secondary py-1 px-2.5 text-[11px]"><i class="fa-solid fa-rotate"></i> <span>{{ $isId ? 'Sinkron RSS Sekarang' : 'Sync Active Feeds' }}</span></button>
    </form>
  </div>
  <div class="p-4 space-y-4">
    <div class="overflow-x-auto border border-[var(--border-color)] rounded">
      <table class="table-mil">
        <thead><tr><th>FEED NAME</th><th>URL</th><th>LAST STATUS</th><th>STATE</th><th class="text-right">ACTIONS</th></tr></thead>
        <tbody>
          @forelse($rssFeeds ?? [] as $rf)
            <tr>
              <td class="text-xs font-semibold">{{ $rf->name }}</td>
              <td class="font-mono text-[11px] text-[var(--text-muted)] break-all">{{ $rf->feed_url }}</td>
              <td>
                @php $st = strtoupper((string) ($rf->last_status ?? 'PENDING')); @endphp
                <span class="badge {{ $st === 'OK' ? 'badge-olive' : ($st === 'PENDING' ? 'badge-neutral' : 'badge-danger') }}">{{ $st }}</span>
              </td>
              <td>
                <span class="badge {{ $rf->is_active ? 'badge-olive' : 'badge-neutral' }}">{{ $rf->is_active ? 'ACTIVE' : 'PAUSED' }}</span>
              </td>
              <td class="text-right whitespace-nowrap space-x-1">
                <form method="POST" action="{{ route('early-warning.rss-feeds.toggle', $rf->id) }}" class="inline">
                  @csrf
                  <button type="submit" class="btn-secondary py-1 px-2 text-[10px]">{{ $rf->is_active ? 'Pause' : 'Activate' }}</button>
                </form>
                <form method="POST" action="{{ route('early-warning.rss-feeds.destroy', $rf->id) }}" class="inline">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn-danger py-1 px-2 text-[10px]">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-xs text-[var(--text-muted)] py-4">-</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <form method="POST" action="{{ route('early-warning.rss-feeds.store') }}" class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end text-xs">
      @csrf
      <div class="md:col-span-4">
        <label class="block label-meta mb-1">{{ $isId ? 'Nama Feed Baru' : 'New Feed Name' }}</label>
        <input type="text" name="name" required placeholder="Antara News - Hukum" class="input-mil text-xs">
      </div>
      <div class="md:col-span-6">
        <label class="block label-meta mb-1">{{ $isId ? 'URL RSS/Atom Publik' : 'Public RSS/Atom URL' }}</label>
        <input type="url" name="feed_url" required placeholder="https://www.antaranews.com/rss/hukum.xml" class="input-mil font-mono text-xs">
      </div>
      <div class="md:col-span-2">
        <button type="submit" class="btn-primary w-full justify-center py-2">{{ $isId ? 'Tambah Feed' : 'Add Feed' }}</button>
      </div>
    </form>
  </div>
</div>

<div class="panel overflow-hidden">
  <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Kejadian Keamanan Terkini' : 'Recent Security Events' }}</span></div>
  <div class="overflow-x-auto">
    <table class="table-mil">
      <thead><tr><th>TIME</th><th>ACTION</th><th>ACTOR</th><th>IP</th><th>RESULT</th></tr></thead>
      <tbody>
        @forelse($security as $s)
          <tr>
            <td class="font-mono text-xs text-[var(--text-muted)]">{{ $s->created_at?->format('Y-m-d H:i') }}</td>
            <td><span class="badge badge-warn">{{ $s->action }}</span></td>
            <td class="font-mono text-xs">{{ $s->actor_label ?? $s->actor_type }}</td>
            <td class="font-mono text-xs">{{ $s->ip ?? '-' }}</td>
            <td class="font-mono text-xs">{{ $s->result }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-xs text-[var(--text-muted)] py-4">-</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
