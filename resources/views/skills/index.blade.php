@extends('layouts.app')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Konsol Skills' : 'Skills Workspace'))

@section('content')
<div class="border-b border-[var(--border-color)] pb-3">
  <h1 class="font-mono text-sm font-bold uppercase tracking-wider">
    {{ $isId ? 'Modul Skills Analisis Teks Terkontrol' : 'Controlled Text-Only Skills Workspace' }}
  </h1>
  <p class="text-xs text-[var(--text-muted)] mt-0.5">
    {{ $isId ? 'Jalankan alat bantu teks yang diaktifkan oleh Administrator untuk akun Anda.' : 'Execute Administrator-approved text analysis utilities assigned to your Trace-ID.' }}
  </p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
  @forelse($available as $sk)
    <div class="panel p-4 flex flex-col justify-between space-y-3">
      <div>
        <div class="flex items-center justify-between gap-2">
          <span class="font-mono text-xs font-bold">{{ $sk->name }}</span>
          <span class="badge badge-olive">v{{ $sk->version }}</span>
        </div>
        <div class="text-[11px] font-mono text-[var(--text-muted)] mt-0.5">{{ $sk->tool }}</div>
        <p class="text-xs text-[var(--text-secondary)] mt-2">{{ $sk->description }}</p>
      </div>
      <a href="{{ route('skills.show', $sk->skill_key) }}" class="btn-primary justify-center">{{ $isId ? 'Buka & Jalankan' : 'Open & Execute' }}</a>
    </div>
  @empty
    <div class="md:col-span-3 panel p-6 text-center text-xs text-[var(--text-muted)]">
      {{ $isId ? 'Belum ada Skill aktif yang ditugaskan untuk akun Anda.' : 'No enabled skills are assigned to your account.' }}
    </div>
  @endforelse
</div>

<div class="panel overflow-hidden">
  <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Riwayat Eksekusi Saya' : 'My Skill Executions' }}</span></div>
  <div class="overflow-x-auto">
    <table class="table-mil">
      <thead><tr><th>ID</th><th>SKILL</th><th>STATUS</th><th>DURATION</th><th>DATE</th><th>ACTION</th></tr></thead>
      <tbody>
        @forelse($history as $ex)
          <tr>
            <td class="font-mono text-xs">#{{ $ex->id }}</td>
            <td class="font-mono text-xs">{{ $ex->skill?->name ?? '-' }}</td>
            <td><span class="badge {{ $ex->status === 'SUCCESS' ? 'badge-olive' : 'badge-danger' }}">{{ $ex->status }}</span></td>
            <td class="font-mono text-xs">{{ $ex->duration_ms }} ms</td>
            <td class="font-mono text-xs text-[var(--text-muted)]">{{ $ex->created_at?->format('Y-m-d H:i') }}</td>
            <td><a href="{{ route('skills.execution', $ex->id) }}" class="btn-secondary py-1 px-2 text-[10px]">{{ $isId ? 'Hasil' : 'View Result' }}</a></td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-xs text-[var(--text-muted)] py-5">-</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 border-t border-[var(--border-color)]">{{ $history->links() }}</div>
</div>
@endsection
