@extends('layouts.app')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Workspace Operator' : 'Operator Workspace'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
  <div>
    <h1 class="font-mono text-sm font-bold uppercase tracking-wider">
      {{ $isId ? 'Workspace Operasional Trace-ID' : 'Operational Trace-ID Workspace' }}
    </h1>
    <p class="text-xs text-[var(--text-muted)] mt-0.5">
      {{ Auth::user()->trace_id }} &middot; {{ Auth::user()->organization?->name ?? '-' }}
      @if(Auth::user()->organization?->code)
        <span class="badge badge-sand ml-1">{{ Auth::user()->organization->code }}</span>
      @endif
      @if(Auth::user()->unit) &middot; {{ Auth::user()->unit->name }} @endif
    </p>
  </div>
  <div class="flex flex-wrap items-center gap-2">
    <a href="{{ route('records.create') }}" class="btn-primary"><i class="fa-solid fa-plus"></i><span>{{ $isId ? 'Ajukan Informasi Baru' : 'New Information Record' }}</span></a>
    <a href="{{ route('operator.history') }}" class="btn-secondary">{{ $isId ? 'Riwayat Pribadi' : 'My History' }}</a>
  </div>
</div>

@include('partials.tactical-launchpad')

<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 font-mono">
  <div class="panel p-3.5"><div class="label-meta">DRAFT</div><div class="text-xl font-bold mt-1">{{ $counts['drafts'] }}</div></div>
  <div class="panel p-3.5"><div class="label-meta">PENDING REVIEW</div><div class="text-xl font-bold mt-1 text-[#8A580B] dark:text-[#E7C37B]">{{ $counts['pending'] }}</div></div>
  <div class="panel p-3.5"><div class="label-meta">REVISION</div><div class="text-xl font-bold mt-1 text-[#8A580B] dark:text-[#E7C37B]">{{ $counts['revision'] }}</div></div>
  <div class="panel p-3.5"><div class="label-meta">APPROVED</div><div class="text-xl font-bold mt-1 text-[#586B47]">{{ $counts['approved'] }}</div></div>
  <div class="panel p-3.5"><div class="label-meta">REJECTED</div><div class="text-xl font-bold mt-1 text-[#991B1B] dark:text-[#FCA5A5]">{{ $counts['rejected'] }}</div></div>
</div>

@if($revisions->isNotEmpty())
  <div class="panel p-4 border-l-4 border-l-[#B47818] space-y-2">
    <div class="font-mono text-xs font-bold uppercase text-[#8A580B] dark:text-[#E7C37B]">
      {{ $isId ? 'REKAMAN MEMERLUKAN REVISI' : 'RECORDS REQUIRING REVISION' }}
    </div>
    <div class="space-y-1.5">
      @foreach($revisions as $rev)
        <div class="flex items-center justify-between text-xs p-2 rounded bg-[var(--bg-subtle)]">
          <span><strong class="font-mono">{{ $rev->record_code }}</strong> &mdash; {{ $rev->title }}</span>
          <a href="{{ route('records.edit', $rev->record_code) }}" class="btn-secondary py-1 px-2 text-[10px]">{{ $isId ? 'Perbaiki' : 'Revise' }}</a>
        </div>
      @endforeach
    </div>
  </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  <div class="panel overflow-hidden">
    <div class="panel-header">
      <span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Pengajuan Saya Terkini' : 'My Recent Submissions' }}</span>
      <a href="{{ route('records.index', ['scope' => 'mine']) }}" class="text-xs font-mono text-[#586B47] hover:underline">{{ $isId ? 'Semua' : 'View All' }}</a>
    </div>
    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead><tr><th>CODE</th><th>TITLE</th><th>STATUS</th><th>VER</th></tr></thead>
        <tbody>
          @forelse($recent as $r)
            <tr>
              <td class="font-mono text-xs"><a href="{{ route('records.show', $r->record_code) }}" class="font-bold text-[#586B47] hover:underline">{{ $r->record_code }}</a></td>
              <td class="text-xs">{{ $r->title }}</td>
              <td><span class="badge badge-sand">{{ $r->status }}</span></td>
              <td class="font-mono text-xs">v{{ $r->current_version }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-xs text-[var(--text-muted)] py-5">{{ $isId ? 'Belum ada rekaman.' : 'No submissions yet.' }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel overflow-hidden">
    <div class="panel-header">
      <span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Skills Diizinkan untuk Akun Saya' : 'Skills Assigned to Me' }}</span>
      <a href="{{ route('skills.index') }}" class="text-xs font-mono text-[#586B47] hover:underline">{{ $isId ? 'Konsol Skills' : 'Skills Console' }}</a>
    </div>
    <div class="p-4 space-y-2">
      @forelse($availableSkills as $sk)
        <div class="flex items-center justify-between p-2.5 rounded border border-[var(--border-color)]">
          <div>
            <div class="font-mono text-xs font-bold">{{ $sk->name }}</div>
            <div class="text-[11px] text-[var(--text-muted)]">{{ $sk->description }}</div>
          </div>
          <a href="{{ route('skills.show', $sk->skill_key) }}" class="btn-primary py-1 px-2.5 text-[10px]">{{ $isId ? 'Jalankan' : 'Run' }}</a>
        </div>
      @empty
        <p class="text-xs text-[var(--text-muted)]">{{ $isId ? 'Belum ada Skill yang ditugaskan.' : 'No skills currently assigned to your account.' }}</p>
      @endforelse
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  <div class="panel overflow-hidden">
    <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Informasi Organisasi Disetujui' : 'Latest Approved Organization Records' }}</span></div>
    <div class="p-3 space-y-2">
      @forelse($latestApproved as $ap)
        <div class="flex items-center justify-between text-xs p-2 rounded border border-[var(--border-color)]">
          <div>
            <a href="{{ route('records.show', $ap->record_code) }}" class="font-mono font-bold text-[#586B47] hover:underline">{{ $ap->record_code }}</a>
            <span class="ml-1.5">{{ $ap->title }}</span>
          </div>
          <span class="font-mono text-[10px] text-[var(--text-muted)]">{{ $ap->approved_at?->format('Y-m-d') }}</span>
        </div>
      @empty
        <p class="text-xs text-[var(--text-muted)]">{{ $isId ? 'Belum ada rekaman disetujui.' : 'No approved records.' }}</p>
      @endforelse
    </div>
  </div>

  <div class="panel overflow-hidden">
    <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Aktivitas Akun Terkini' : 'My Recent Activity' }}</span></div>
    <div class="p-3 space-y-1.5">
      @forelse($activity as $act)
        <div class="flex items-center justify-between text-xs p-2 rounded border border-[var(--border-color)] font-mono">
          <span>{{ $act->action }} <span class="text-[var(--text-muted)]">({{ $act->target_type }}:{{ $act->target_id }})</span></span>
          <span class="text-[10px] text-[var(--text-muted)]">{{ $act->created_at?->format('m-d H:i') }}</span>
        </div>
      @empty
        <p class="text-xs text-[var(--text-muted)]">-</p>
      @endforelse
    </div>
  </div>
</div>

@include('partials.instansi-group-panel')
@endsection
