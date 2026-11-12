@extends('layouts.app')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // ' . $user->trace_id)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
  <div>
    <div class="label-meta">{{ $user->trace_id }} &middot; {{ $user->roleLabel() }}</div>
    <h1 class="font-mono text-sm font-bold uppercase">{{ $user->name }}</h1>
  </div>
  <a href="{{ route('admin.accounts.index') }}" class="btn-secondary">{{ $isId ? 'Kembali' : 'Back' }}</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
  <div class="lg:col-span-2 space-y-4">
    <div class="panel p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
      <div><span class="label-meta">ORGANIZATION:</span> <div>{{ $user->organization?->name ?? '-' }}</div></div>
      <div><span class="label-meta">UNIT:</span> <div>{{ $user->unit?->name ?? '-' }}</div></div>
      <div><span class="label-meta">POSITION:</span> <div>{{ $user->position ?? '-' }}</div></div>
      <div><span class="label-meta">ACCOUNT STATUS:</span> <div><span class="badge badge-sand mt-0.5">{{ $user->account_status }}</span></div></div>
      <div><span class="label-meta">MUST CHANGE PASSWORD:</span> <div class="font-mono">{{ $user->must_change_password ? 'YES' : 'NO' }}</div></div>
      <div><span class="label-meta">EXPIRES AT:</span> <div class="font-mono">{{ $user->expires_at?->format('Y-m-d H:i') ?? 'NO EXPIRY' }}</div></div>
    </div>

    <div class="panel overflow-hidden">
      <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Riwayat Siklus Akun' : 'Account Lifecycle History' }}</span></div>
      <div class="overflow-x-auto">
        <table class="table-mil">
          <thead><tr><th>TIME</th><th>TRANSITION</th><th>ACTOR</th><th>REASON</th></tr></thead>
          <tbody>
            @forelse($history as $h)
              <tr>
                <td class="font-mono text-xs text-[var(--text-muted)]">{{ $h->created_at?->format('Y-m-d H:i') }}</td>
                <td class="font-mono text-xs">{{ $h->from_status }} &rarr; <strong>{{ $h->to_status }}</strong></td>
                <td class="font-mono text-xs">{{ $h->actor_label ?? $h->actor_type }}</td>
                <td class="text-xs">{{ $h->reason ?? '-' }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-xs text-[var(--text-muted)] py-4">-</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="space-y-4">
    @if($user->hasRole('OPERATOR') && $user->id !== Auth::id())
      <form method="POST" action="{{ route('admin.accounts.act', $user->trace_id) }}" class="panel p-4 space-y-3">
        @csrf
        <input type="hidden" name="action" value="reset">
        <div class="label-meta">{{ $isId ? 'RESET KREDENSIAL SEMENTARA' : 'RESET TEMPORARY PASSWORD' }}</div>
        <p class="text-xs text-[var(--text-secondary)]">
          {{ $isId ? 'Menghasilkan kata sandi sementara acak satu kali dan memaksa penggantian saat login.' : 'Generates a one-time random temporary password and requires change on next login.' }}
        </p>
        <button type="submit" class="w-full btn-primary justify-center">{{ $isId ? 'Reset Kata Sandi' : 'Reset Credentials' }}</button>
      </form>

      @if($user->account_status !== 'SUSPENDED' && $user->account_status !== 'REVOKED')
      <form method="POST" action="{{ route('admin.accounts.act', $user->trace_id) }}" class="panel p-4 space-y-3">
        @csrf
        <input type="hidden" name="action" value="suspend">
        <div class="label-meta">{{ $isId ? 'TANGGUHKAN AKUN & CABUT SESI' : 'SUSPEND ACCOUNT & REVOKE SESSIONS' }}</div>
        <input type="text" name="reason" required minlength="3" placeholder="{{ $isId ? 'Alasan penangguhan...' : 'Suspension reason...' }}" class="input-mil text-xs">
        <button type="submit" class="w-full btn-danger justify-center">{{ $isId ? 'Tangguhkan Akun' : 'Suspend Account' }}</button>
      </form>
      @elseif($user->account_status === 'SUSPENDED')
      <form method="POST" action="{{ route('admin.accounts.act', $user->trace_id) }}" class="panel p-4 space-y-3">
        @csrf
        <input type="hidden" name="action" value="reactivate">
        <div class="label-meta">{{ $isId ? 'AKTIFKAN KEMBALI AKUN' : 'REACTIVATE ACCOUNT' }}</div>
        <input type="text" name="reason" required minlength="3" placeholder="{{ $isId ? 'Alasan pengaktifan kembali...' : 'Reactivation reason...' }}" class="input-mil text-xs">
        <button type="submit" class="w-full btn-primary justify-center">{{ $isId ? 'Aktifkan Kembali' : 'Reactivate Account' }}</button>
      </form>
      @endif

      <form method="POST" action="{{ route('admin.accounts.act', $user->trace_id) }}" class="panel p-4 space-y-3">
        @csrf
        <input type="hidden" name="action" value="expiry">
        <div class="label-meta">{{ $isId ? 'ATUR MASA BERLAKU' : 'SET ACCOUNT EXPIRATION' }}</div>
        <input type="datetime-local" name="expires_at" value="{{ $user->expires_at?->format('Y-m-d\TH:i') }}" class="input-mil font-mono text-xs">
        <button type="submit" class="w-full btn-secondary justify-center">{{ $isId ? 'Simpan Masa Berlaku' : 'Save Expiry' }}</button>
      </form>
    @else
      <div class="panel p-4 text-xs text-[var(--text-secondary)]">
        {{ $isId
          ? 'Akun Administrator dan akun pribadi dikelola melalui utilitas provisioning Trace-ID eksternal atau menu Penggantian Kata Sandi pribadi.'
          : 'Administrator accounts and your own account are governed via external Trace-ID CLI provisioning or personal password update.' }}
      </div>
    @endif
  </div>
</div>
@endsection
