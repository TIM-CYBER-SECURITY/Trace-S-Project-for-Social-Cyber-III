<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'id' ? 'id' : 'en' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>500 // Internal Service Error — TRACE-S</title>
  <script>
    if (localStorage.getItem('trace-theme') === 'dark') document.documentElement.classList.add('dark');
  </script>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php $isId = app()->getLocale() === 'id'; @endphp
<body class="min-h-screen flex items-center justify-center bg-[var(--bg-canvas)] text-[var(--text-primary)] p-4">
  <div class="max-w-lg w-full panel p-6 space-y-4 border-l-4 border-l-[#991B1B]">
    <div class="flex items-center justify-between">
      <span class="badge badge-danger">HTTP 500 // SYSTEM FAULT</span>
      <span class="label-meta">NO TRACE DISCLOSURE</span>
    </div>
    <h1 class="text-lg font-mono font-bold">
      {{ $isId ? 'Kesalahan Pemrosesan Sistem' : 'Internal Processing Fault' }}
    </h1>
    <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
      {{ $isId ? 'Permintaan tidak dapat diselesaikan karena gangguan internal. Silakan hubungi Administrator sistem.' : 'The request could not be completed due to an internal fault. Contact your system administrator.' }}
    </p>
    <div class="flex items-center gap-2 pt-2">
      <a href="{{ url('/') }}" class="btn-primary">{{ $isId ? 'Kembali' : 'Return' }}</a>
    </div>
  </div>
</body>
</html>
