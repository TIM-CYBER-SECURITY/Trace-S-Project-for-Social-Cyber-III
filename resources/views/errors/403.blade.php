<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'id' ? 'id' : 'en' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>403 // Access Denied — TRACE-S</title>
  <script>
    if (localStorage.getItem('trace-theme') === 'dark') document.documentElement.classList.add('dark');
  </script>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php $isId = app()->getLocale() === 'id'; @endphp
<body class="min-h-screen flex items-center justify-center bg-[var(--bg-canvas)] text-[var(--text-primary)] p-4">
  <div class="max-w-lg w-full panel p-6 space-y-4 border-l-4 border-l-[#991B1B]">
    <div class="flex items-center justify-between">
      <span class="badge badge-danger">HTTP 403 // ACCESS DENIED</span>
      <span class="label-meta">RBAC AUTHORIZATION</span>
    </div>
    <h1 class="text-lg font-mono font-bold">
      {{ $isId ? 'Akses Ditolak oleh Kebijakan Peran' : 'Access Denied by Role-Based Policy' }}
    </h1>
    <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
      {{ $exception->getMessage() ?: ($isId ? 'Akun Anda tidak memiliki izin untuk membuka sumber daya atau tindakan ini. Insiden ini dicatat di audit log.' : 'Your account lacks permission to access this resource or action. This attempt has been logged.') }}
    </p>
    <div class="flex items-center gap-2 pt-2">
      <a href="{{ url('/') }}" class="btn-primary">
        <i class="fa-solid fa-house"></i>
        <span>{{ $isId ? 'Kembali ke Beranda' : 'Return Home' }}</span>
      </a>
      <a href="{{ route('login') }}" class="btn-secondary">{{ $isId ? 'Halaman Masuk' : 'Login Page' }}</a>
    </div>
  </div>
</body>
</html>
