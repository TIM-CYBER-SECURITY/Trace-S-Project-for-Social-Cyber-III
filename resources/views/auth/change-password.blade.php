@extends('layouts.guest')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Penggantian Kata Sandi' : 'Change Password'))

@section('content')
<div class="panel overflow-hidden shadow-sm">
  <div class="bg-[#17251F] text-[#F2F2EC] px-5 py-4 border-b border-[#36413C] flex items-center justify-between">
    <div>
      <div class="font-mono text-xs font-bold uppercase tracking-wider">
        {{ $isId ? 'PEMBARUAN KREDENSIAL AKUN' : 'ACCOUNT CREDENTIAL UPDATE' }}
      </div>
      <div class="font-mono text-[10px] text-[#C5B89C] mt-0.5">
        TRACE-ID: {{ Auth::user()->trace_id }}
      </div>
    </div>
    @if($forced)
      <span class="badge badge-warn">{{ $isId ? 'WAJIB' : 'MANDATORY' }}</span>
    @endif
  </div>

  <div class="p-5 space-y-4">
    @if($forced)
      <div class="p-3 rounded border border-[#B47818]/50 bg-[#B47818]/10 text-xs text-[var(--text-primary)]">
        {{ $isId
          ? 'Akun Anda menggunakan kata sandi sementara. Anda wajib menetapkan kata sandi pribadi baru sebelum mengakses sistem.'
          : 'Your account uses a temporary password. You must set a new personal password before accessing the platform.' }}
      </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="space-y-3.5">
      @csrf

      <div>
        <label for="current_password" class="block label-meta mb-1">{{ $isId ? 'Kata Sandi Saat Ini' : 'Current Password' }}</label>
        <div class="relative">
          <input type="password" id="current_password" name="current_password" required class="input-mil font-mono pr-10">
          <button type="button"
                  data-password-toggle="current_password"
                  data-show-label="{{ $isId ? 'Tampilkan kata sandi' : 'Show password' }}"
                  data-hide-label="{{ $isId ? 'Sembunyikan kata sandi' : 'Hide password' }}"
                  aria-label="{{ $isId ? 'Tampilkan kata sandi' : 'Show password' }}"
                  aria-pressed="false"
                  class="absolute inset-y-0 right-0 px-3 flex items-center text-[var(--text-muted)] hover:text-[var(--text-primary)] focus:outline-none">
            <i class="fa-regular fa-eye text-xs"></i>
          </button>
        </div>
      </div>

      <div>
        <label for="password" class="block label-meta mb-1">{{ $isId ? 'Kata Sandi Baru' : 'New Password' }}</label>
        <div class="relative">
          <input type="password" id="password" name="password" required class="input-mil font-mono pr-10">
          <button type="button"
                  data-password-toggle="password"
                  data-show-label="{{ $isId ? 'Tampilkan kata sandi' : 'Show password' }}"
                  data-hide-label="{{ $isId ? 'Sembunyikan kata sandi' : 'Hide password' }}"
                  aria-label="{{ $isId ? 'Tampilkan kata sandi' : 'Show password' }}"
                  aria-pressed="false"
                  class="absolute inset-y-0 right-0 px-3 flex items-center text-[var(--text-muted)] hover:text-[var(--text-primary)] focus:outline-none">
            <i class="fa-regular fa-eye text-xs"></i>
          </button>
        </div>
      </div>

      <div>
        <label for="password_confirmation" class="block label-meta mb-1">{{ $isId ? 'Konfirmasi Kata Sandi Baru' : 'Confirm New Password' }}</label>
        <div class="relative">
          <input type="password" id="password_confirmation" name="password_confirmation" required class="input-mil font-mono pr-10">
          <button type="button"
                  data-password-toggle="password_confirmation"
                  data-show-label="{{ $isId ? 'Tampilkan kata sandi' : 'Show password' }}"
                  data-hide-label="{{ $isId ? 'Sembunyikan kata sandi' : 'Hide password' }}"
                  aria-label="{{ $isId ? 'Tampilkan kata sandi' : 'Show password' }}"
                  aria-pressed="false"
                  class="absolute inset-y-0 right-0 px-3 flex items-center text-[var(--text-muted)] hover:text-[var(--text-primary)] focus:outline-none">
            <i class="fa-regular fa-eye text-xs"></i>
          </button>
        </div>
      </div>

      <div class="p-2.5 rounded bg-[var(--bg-subtle)] border border-[var(--border-color)] text-[11px] font-mono text-[var(--text-secondary)]">
        {{ $isId
          ? 'Kebijakan: minimal 12 karakter, kombinasi huruf besar/kecil, angka, simbol, dan tidak memuat Trace-ID.'
          : 'Policy: minimum 12 characters with mixed case, digits, symbols, and must not contain your Trace-ID.' }}
      </div>

      <button type="submit" class="w-full btn-primary py-2.5 justify-center">
        <i class="fa-solid fa-check"></i>
        <span>{{ $isId ? 'Simpan Kata Sandi Baru' : 'Save New Password' }}</span>
      </button>
    </form>

    <div class="pt-3 border-t border-[var(--border-color)] flex items-center justify-between text-xs font-mono">
      @if(!$forced)
        <a href="{{ route('dashboard') }}" class="text-[var(--text-secondary)] hover:underline">
          <i class="fa-solid fa-arrow-left mr-1"></i>{{ $isId ? 'Kembali' : 'Back' }}
        </a>
      @else
        <span></span>
      @endif

      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="text-[#991B1B] dark:text-[#FCA5A5] hover:underline">
          {{ $isId ? 'Keluar Sesi' : 'Sign Out' }}
        </button>
      </form>
    </div>
  </div>
</div>
@endsection
