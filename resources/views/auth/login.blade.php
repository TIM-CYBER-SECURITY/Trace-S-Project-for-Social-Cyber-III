@extends('layouts.guest')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // ' . ($isId ? 'Autentikasi Trace-ID' : 'Trace-ID Authentication'))

@section('content')
<div class="panel overflow-hidden shadow-sm">
  <div class="bg-[#17251F] text-[#F2F2EC] px-5 py-4 border-b border-[#36413C] flex items-center justify-between">
    <div>
      <div class="font-mono text-xs font-bold tracking-wider uppercase">TRACE-S COMMAND ACCESS</div>
      <div class="font-mono text-[10px] text-[#C5B89C] uppercase tracking-widest mt-0.5">
        {{ $isId ? 'Autentikasi Kredensial Trace-ID' : 'Trace-ID Credential Authentication' }}
      </div>
    </div>
    <span class="px-2 py-0.5 rounded bg-[#202622] border border-[#46514C] font-mono text-[10px] text-[#C5B89C] uppercase">RESTRICTED</span>
  </div>

  <form method="POST" action="{{ route('login.post') }}" class="p-5 space-y-4">
    @csrf

    <div>
      <label for="trace_id" class="block label-meta mb-1.5">
        {{ $isId ? 'TRACE-ID TERDAFTAR' : 'REGISTERED TRACE-ID' }}
      </label>
      <input type="text" id="trace_id" name="trace_id" value="{{ old('trace_id') }}"
             required autofocus autocomplete="username" placeholder="TRS-XXXX-XXXX"
             class="input-mil font-mono uppercase tracking-wider">
    </div>

    <div>
      <label for="password" class="block label-meta mb-1.5">
        {{ $isId ? 'KATA SANDI' : 'PASSWORD' }}
      </label>
      <div class="relative">
        <input type="password" id="password" name="password"
               required autocomplete="current-password" placeholder="••••••••••••"
               class="input-mil font-mono pr-10">
        <button type="button"
                data-password-toggle="password"
                data-show-label="{{ $isId ? 'Tampilkan kata sandi' : 'Show password' }}"
                data-hide-label="{{ $isId ? 'Sembunyikan kata sandi' : 'Hide password' }}"
                aria-label="{{ $isId ? 'Tampilkan kata sandi' : 'Show password' }}"
                aria-pressed="false"
                title="{{ $isId ? 'Tampilkan kata sandi' : 'Show password' }}"
                class="absolute inset-y-0 right-0 px-3 flex items-center text-[var(--text-muted)] hover:text-[var(--text-primary)] focus:outline-none">
          <i class="fa-regular fa-eye text-xs"></i>
        </button>
      </div>
    </div>

    <button type="submit" class="w-full btn-primary py-2.5 justify-center">
      <i class="fa-solid fa-shield-halved"></i>
      <span>{{ $isId ? 'MASUK KE SISTEM' : 'AUTHENTICATE SESSION' }}</span>
    </button>
  </form>

  <div class="px-5 py-3 bg-[var(--bg-subtle)] border-t border-[var(--border-color)] flex items-center justify-between gap-2 font-mono text-[10px] text-[var(--text-muted)]">
    <span><i class="fa-solid fa-lock mr-1.5 text-[#586B47] dark:text-[#C5B89C]"></i>{{ $isId ? 'AKSES TERBATAS INSTITUSIONAL' : 'RESTRICTED INSTITUTIONAL ACCESS' }}</span>
    <span class="uppercase">{{ $isId ? 'Penerbitan Trace-ID Terkontrol' : 'Controlled Trace-ID Issuance' }}</span>
  </div>
</div>
@endsection
