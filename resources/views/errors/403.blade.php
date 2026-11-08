@extends('layouts.tactical')

@php
  $isId = app()->getLocale() === 'id';
@endphp

@section('title', '403 // Access Restricted')

@section('content')
<!-- [Error403Panel] START -->
<div class="bg-neutral-900 border border-rose-600/50 rounded-sm p-8 max-w-xl mx-auto mt-10 text-center space-y-4 font-mono">
  <div class="inline-flex w-12 h-12 rounded-sm text-rose-600 bg-rose-500/10 border border-rose-600/50 items-center justify-center text-lg">
    <i class="fa-solid fa-lock"></i>
  </div>
  <div class="space-y-1">
    <div class="text-xs uppercase tracking-widest text-rose-600 font-semibold">HTTP 403 // CLEARANCE REQUIRED</div>
    <h1 class="text-sm font-semibold text-neutral-300 uppercase">
      {{ $isId ? 'Izin Akses Tidak Mencukupi' : 'Insufficient Role or Clearance Level' }}
    </h1>
    <p class="text-xs text-neutral-500 font-sans leading-relaxed">
      {{ $isId
        ? 'Peran akun Anda saat ini tidak memiliki hak akses untuk membuka modul atau tindakan ini. Percobaan akses dicatat pada log audit.'
        : 'Your current account role does not have permission to access this module or action. This access attempt has been recorded in the audit log.' }}
    </p>
  </div>
  <div class="pt-2">
    <a href="{{ route('dashboard') }}" class="px-4 py-2 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 text-xs uppercase tracking-wider rounded-sm inline-block">
      {{ $isId ? 'Kembali ke Dasbor Utama' : 'Return to Main Dashboard' }}
    </a>
  </div>
</div>
<!-- [Error403Panel] END -->
@endsection
