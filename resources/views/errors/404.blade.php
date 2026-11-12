<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'id' ? 'id' : 'en' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>404 // Resource Not Found — TRACE-S</title>
  <script>
    if (localStorage.getItem('trace-theme') === 'dark') document.documentElement.classList.add('dark');
  </script>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php $isId = app()->getLocale() === 'id'; @endphp
<body class="min-h-screen flex items-center justify-center bg-[var(--bg-canvas)] text-[var(--text-primary)] p-4">
  <div class="max-w-lg w-full panel p-6 space-y-4 border-l-4 border-l-[#C5B89C]">
    <div class="flex items-center justify-between">
      <span class="badge badge-sand">HTTP 404 // NOT FOUND</span>
      <span class="label-meta">RESOURCE ISOLATION</span>
    </div>
    <h1 class="text-lg font-mono font-bold">
      {{ $isId ? 'Rekaman atau Halaman Tidak Ditemukan' : 'Record or Endpoint Not Found' }}
    </h1>
    <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
      {{ $isId ? 'Data yang diminta tidak tersedia dalam ruang lingkup akses organisasi Anda.' : 'The requested record is unavailable or outside your organization access boundary.' }}
    </p>
    <div class="flex items-center gap-2 pt-2">
      <a href="{{ url('/') }}" class="btn-primary">
        <i class="fa-solid fa-arrow-left"></i>
        <span>{{ $isId ? 'Kembali' : 'Return' }}</span>
      </a>
    </div>
  </div>
</body>
</html>
