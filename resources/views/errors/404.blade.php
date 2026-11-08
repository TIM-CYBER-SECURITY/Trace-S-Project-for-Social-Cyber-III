@extends('layouts.tactical')

@php
  $isId = app()->getLocale() === 'id';
@endphp

@section('title', '404 // Resource Not Found')

@section('content')
<!-- [Error404Panel] START -->
<div class="bg-neutral-900 border border-amber-500/30 rounded-sm p-8 max-w-xl mx-auto mt-10 text-center space-y-4 font-mono">
  <div class="inline-flex w-12 h-12 rounded-sm text-amber-500 bg-amber-500/10 border border-amber-500/30 items-center justify-center text-lg">
    <i class="fa-solid fa-triangle-exclamation"></i>
  </div>
  <div class="space-y-1">
    <div class="text-xs uppercase tracking-widest text-amber-500 font-semibold">HTTP 404 // NOT FOUND</div>
    <h1 class="text-sm font-semibold text-neutral-300 uppercase">
      {{ $isId ? 'Halaman atau Berkas Tidak Ditemukan' : 'Requested Resource Not Found' }}
    </h1>
    <p class="text-xs text-neutral-500 font-sans leading-relaxed">
      {{ $isId
        ? 'Alamat halaman atau ID kasus yang Anda tuju tidak terdaftar di dalam penyimpanan.'
        : 'The requested page path or case identifier does not exist in the repository.' }}
    </p>
  </div>
  <div class="pt-2">
    <a href="{{ route('dashboard') }}" class="px-4 py-2 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 text-xs uppercase tracking-wider rounded-sm inline-block">
      {{ $isId ? 'Kembali ke Dasbor Utama' : 'Return to Main Dashboard' }}
    </a>
  </div>
</div>
<!-- [Error404Panel] END -->
@endsection
