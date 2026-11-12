@extends('layouts.app')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Dashboard Administrator' : 'Administrator Dashboard'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
  <div>
    <h1 class="font-mono text-sm font-bold uppercase tracking-wider">
      {{ $isId ? 'Dashboard Tata Kelola Administrator' : 'Administrator Governance Dashboard' }}
    </h1>
    <p class="text-xs text-[var(--text-muted)] mt-0.5">
      {{ $isId ? 'Organisasi:' : 'Organization:' }} <strong>{{ $org?->name ?? '-' }}</strong>
    </p>
  </div>
  <div class="flex flex-wrap items-center gap-2">
    <a href="{{ route('admin.accounts.index') }}" class="btn-secondary">{{ $isId ? 'Kelola Akun' : 'Manage Accounts' }}</a>
    <a href="{{ route('admin.skills.index') }}" class="btn-secondary">{{ $isId ? 'Kelola Skills' : 'Manage Skills' }}</a>
    <a href="{{ route('records.index') }}" class="btn-primary">{{ $isId ? 'Antrean Review Informasi' : 'Review Information' }}</a>
  </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 font-mono">
  <div class="panel p-4">
    <div class="label-meta">{{ $isId ? 'AKUN AKTIF / TOTAL' : 'ACTIVE / TOTAL ACCOUNTS' }}</div>
    <div class="text-2xl font-bold mt-1">{{ $stats['accounts_active'] }} <span class="text-sm text-[var(--text-muted)]">/ {{ $stats['accounts_total'] }}</span></div>
    <div class="text-[11px] text-[var(--text-muted)] mt-1">PROVISIONED: {{ $stats['accounts_provisioned'] }} &middot; SUSPENDED: {{ $stats['accounts_suspended'] }}</div>
  </div>
  <div class="panel p-4">
    <div class="label-meta">{{ $isId ? 'ANTRIAN REVIEW INFORMASI' : 'PENDING INFO REVIEWS' }}</div>
    <div class="text-2xl font-bold mt-1 text-[#8A580B] dark:text-[#E7C37B]">{{ $stats['reviews_pending'] }}</div>
    <div class="text-[11px] text-[var(--text-muted)] mt-1">REVISION REQUIRED: {{ $stats['info_revision'] }}</div>
  </div>
  <div class="panel p-4">
    <div class="label-meta">{{ $isId ? 'INFORMASI DISETUJUI' : 'APPROVED RECORDS' }}</div>
    <div class="text-2xl font-bold mt-1 text-[#586B47]">{{ $stats['info_approved'] }}</div>
    <div class="text-[11px] text-[var(--text-muted)] mt-1">{{ $isId ? 'Terpublikasi di organisasi' : 'Published in org' }}</div>
  </div>
  <div class="panel p-4">
    <div class="label-meta">{{ $isId ? 'SKILLS AKTIF & ANALISIS' : 'ACTIVE SKILLS & QUEUE' }}</div>
    <div class="text-2xl font-bold mt-1">{{ $stats['skills_enabled'] }} <span class="text-sm text-[var(--text-muted)]">/ {{ $stats['skills_installed'] }}</span></div>
    <div class="text-[11px] text-[var(--text-muted)] mt-1">
      <a href="{{ route('admin.skills.index') }}" class="underline">{{ $isId ? 'Kelola Skills' : 'Skills' }}</a>
      &middot; PENDING SCAN: {{ $stats['analyses_pending'] }}
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  <div class="panel overflow-hidden">
    <div class="panel-header">
      <span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Antrean Review Informasi Organisasi' : 'Organization Information Review Queue' }}</span>
      <a href="{{ route('records.index') }}" class="text-xs font-mono text-[#586B47] hover:underline">{{ $isId ? 'Semua Rekaman' : 'All Records' }}</a>
    </div>
    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead>
          <tr>
            <th>CODE</th>
            <th>{{ $isId ? 'JUDUL' : 'TITLE' }}</th>
            <th>{{ $isId ? 'PENGAJU' : 'SUBMITTER' }}</th>
            <th>STATUS</th>
          </tr>
        </thead>
        <tbody>
          @forelse($queue as $r)
            <tr>
              <td class="font-mono text-xs"><a href="{{ route('records.show', $r->record_code) }}" class="font-bold text-[#586B47] hover:underline">{{ $r->record_code }}</a></td>
              <td class="text-xs">{{ $r->title }}</td>
              <td class="font-mono text-xs">{{ $r->creator?->trace_id ?? '-' }}</td>
              <td><span class="badge badge-warn">{{ $r->status }}</span></td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-xs text-[var(--text-muted)] py-5">{{ $isId ? 'Tidak ada pengajuan menunggu review.' : 'No records awaiting review.' }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel overflow-hidden">
    <div class="panel-header">
      <span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Aktivitas Audit Organisasi Terkini' : 'Recent Organization Audit Activity' }}</span>
      <a href="{{ route('admin.audit.index') }}" class="text-xs font-mono text-[#586B47] hover:underline">{{ $isId ? 'Audit Lengkap' : 'Full Audit' }}</a>
    </div>
    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead>
          <tr>
            <th>TIME</th>
            <th>ACTOR</th>
            <th>ACTION</th>
            <th>RESULT</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recent as $l)
            <tr>
              <td class="font-mono text-xs text-[var(--text-muted)] whitespace-nowrap">{{ $l->created_at?->format('m-d H:i') }}</td>
              <td class="font-mono text-xs">{{ $l->actor_label ?? ($l->user?->trace_id ?? 'SYSTEM') }}</td>
              <td><span class="badge badge-neutral">{{ $l->action }}</span></td>
              <td><span class="badge {{ $l->result === 'SUCCESS' ? 'badge-olive' : 'badge-danger' }}">{{ $l->result }}</span></td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-xs text-[var(--text-muted)] py-5">-</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
