@extends('layouts.app')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Akun Trace-ID Organisasi' : 'Organization Trace-ID Accounts'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
  <div>
    <h1 class="font-mono text-sm font-bold uppercase tracking-wider">
      {{ $isId ? 'Direktori Akun Trace-ID Organisasi' : 'Organization Trace-ID Account Directory' }}
    </h1>
    <p class="text-xs text-[var(--text-muted)] mt-0.5">
      {{ $isId ? 'Administrator dapat menangguhkan, mengaktifkan ulang, mereset sandi sementara, dan mengatur masa berlaku akun Operator.' : 'Administrators manage Operator account status, credential resets, and account expiration within their organization.' }}
    </p>
  </div>
</div>

<form method="GET" action="{{ route('admin.accounts.index') }}" class="panel p-3 flex flex-wrap items-end gap-3">
  <div class="flex-1 min-w-[200px]">
    <label class="block label-meta mb-1">{{ $isId ? 'CARI TRACE-ID / NAMA' : 'SEARCH TRACE-ID / NAME' }}</label>
    <input type="text" name="q" value="{{ request('q') }}" class="input-mil font-mono text-xs">
  </div>
  <div class="w-40">
    <label class="block label-meta mb-1">ROLE</label>
    <select name="role" class="input-mil font-mono text-xs">
      <option value="">ALL</option>
      <option value="OPERATOR" @selected(request('role') === 'OPERATOR')>OPERATOR</option>
      <option value="ADMINISTRATOR" @selected(request('role') === 'ADMINISTRATOR')>ADMINISTRATOR</option>
    </select>
  </div>
  <div class="w-44">
    <label class="block label-meta mb-1">STATUS</label>
    <select name="status" class="input-mil font-mono text-xs">
      <option value="">ALL</option>
      @foreach(['PROVISIONED', 'ACTIVE', 'SUSPENDED', 'REVOKED'] as $st)
        <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
      @endforeach
    </select>
  </div>
  <button type="submit" class="btn-primary">{{ $isId ? 'Saring' : 'Filter' }}</button>
</form>

<div class="panel overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table-mil">
      <thead>
        <tr>
          <th>TRACE-ID</th>
          <th>{{ $isId ? 'NAMA & JABATAN' : 'NAME & POSITION' }}</th>
          <th>UNIT</th>
          <th>ROLE</th>
          <th>STATUS</th>
          <th>{{ $isId ? 'MASA BERLAKU' : 'EXPIRES AT' }}</th>
          <th>{{ $isId ? 'LOGIN TERAKHIR' : 'LAST LOGIN' }}</th>
          <th>{{ $isId ? 'AKSI' : 'ACTION' }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($users as $u)
          <tr>
            <td class="font-mono text-xs font-bold">{{ $u->trace_id }}</td>
            <td class="text-xs"><strong>{{ $u->name }}</strong><div class="text-[11px] text-[var(--text-muted)]">{{ $u->position ?? '-' }}</div></td>
            <td class="text-xs">{{ $u->unit?->name ?? '-' }}</td>
            <td class="font-mono text-xs">{{ $u->roleLabel() }}</td>
            <td><span class="badge {{ $u->account_status === 'ACTIVE' ? 'badge-olive' : ($u->account_status === 'REVOKED' ? 'badge-danger' : 'badge-warn') }}">{{ $u->account_status }}</span></td>
            <td class="font-mono text-xs">{{ $u->expires_at?->format('Y-m-d') ?? '-' }}</td>
            <td class="font-mono text-xs text-[var(--text-muted)]">{{ $u->last_login_at?->format('Y-m-d H:i') ?? '-' }}</td>
            <td>
              <a href="{{ route('admin.accounts.show', $u->trace_id) }}" class="btn-secondary py-1 px-2 text-[10px]">
                {{ $isId ? 'Detail' : 'Manage' }}
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-xs text-[var(--text-muted)] py-6">{{ $isId ? 'Tidak ada akun.' : 'No accounts found.' }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 border-t border-[var(--border-color)]">{{ $users->links() }}</div>
</div>
@endsection
