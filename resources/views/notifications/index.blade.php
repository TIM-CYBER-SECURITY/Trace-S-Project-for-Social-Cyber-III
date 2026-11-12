@extends('layouts.app')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Notifikasi' : 'Notifications'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
  <h1 class="font-mono text-sm font-bold uppercase tracking-wider">{{ $isId ? 'Pusat Notifikasi Sistem' : 'System Notification Center' }}</h1>
  <form method="POST" action="{{ route('notifications.read-all') }}">
    @csrf
    <button type="submit" class="btn-secondary">{{ $isId ? 'Tandai Semua Dibaca' : 'Mark All Read' }}</button>
  </form>
</div>

<form method="GET" action="{{ route('notifications.index') }}" class="panel p-3 flex flex-wrap items-end gap-3">
  <div class="w-44">
    <label class="block label-meta mb-1">{{ $isId ? 'TIPE' : 'TYPE' }}</label>
    <select name="type" class="input-mil font-mono text-xs">
      <option value="">ALL</option>
      @foreach($types as $t)
        <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
      @endforeach
    </select>
  </div>
  <label class="flex items-center gap-2 text-xs font-mono pb-2 cursor-pointer">
    <input type="checkbox" name="unread" value="1" @checked(request('unread')) class="accent-[#586B47]">
    <span>{{ $isId ? 'Hanya Belum Dibaca' : 'Unread Only' }}</span>
  </label>
  <button type="submit" class="btn-primary">{{ $isId ? 'Saring' : 'Filter' }}</button>
</form>

<div class="panel divide-y divide-[var(--border-color)]">
  @forelse($items as $n)
    <div class="p-4 flex flex-wrap items-center justify-between gap-3 {{ $n->read_at ? 'opacity-75' : '' }}">
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <span class="badge {{ $n->read_at ? 'badge-neutral' : 'badge-olive' }}">{{ $n->notification_type }}</span>
          <strong class="text-xs">{{ $n->title }}</strong>
          <span class="font-mono text-[10px] text-[var(--text-muted)]">{{ $n->created_at?->format('Y-m-d H:i') }}</span>
        </div>
        <p class="text-xs text-[var(--text-secondary)]">{{ $n->body }}</p>
      </div>
      <form method="POST" action="{{ route('notifications.read', $n->id) }}">
        @csrf
        <button type="submit" class="btn-secondary py-1 px-2.5 text-[10px]">
          {{ $n->link ? ($isId ? 'Buka' : 'Open') : ($n->read_at ? ($isId ? 'Dibaca' : 'Read') : ($isId ? 'Tandai Dibaca' : 'Mark Read')) }}
        </button>
      </form>
    </div>
  @empty
    <div class="p-6 text-center text-xs text-[var(--text-muted)]">{{ $isId ? 'Tidak ada notifikasi.' : 'No notifications.' }}</div>
  @endforelse
</div>

@if($items->hasPages())
  <div class="pt-2">{{ $items->links() }}</div>
@endif
@endsection
