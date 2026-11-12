<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'id' ? 'id' : 'en' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'TRACE-S // Access Gateway')</title>
  <script>
    if (localStorage.getItem('trace-theme') === 'dark') {
      document.documentElement.classList.add('dark');
    }
  </script>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
  $isId = app()->getLocale() === 'id';
  $settings = app(\App\Services\Support\AppSettings::class);
  $classificationLabel = $settings->get('classification_label');
@endphp
<body class="min-h-screen flex flex-col bg-[var(--bg-canvas)] text-[var(--text-primary)] antialiased">

  <div class="bg-[#17251F] border-b border-[#36413C] px-4 py-2 flex items-center justify-between text-[#F2F2EC]">
    <div class="flex items-center gap-2.5">
      <img src="{{ asset('images/trace-s-logo.png') }}" alt="TRACE-S" class="h-6 w-auto bg-[#F2F2EC] rounded px-1.5 py-0.5">
      <span class="font-mono text-xs font-bold tracking-wider">TRACE-S</span>
      <span class="hidden sm:inline font-mono text-[10px] text-[#C5B89C] uppercase tracking-widest">{{ $classificationLabel }}</span>
    </div>

    <div class="flex items-center gap-2 font-mono text-[10px]">
      <button type="button" onclick="window.toggleTraceTheme()"
              class="px-2 py-1 rounded bg-[#202622] border border-[#46514C] text-[#F2F2EC] flex items-center gap-1 uppercase">
        <i class="fa-solid fa-circle-half-stroke text-[#C5B89C]"></i>
        <span data-theme-label>LIGHT</span>
      </button>
      <div class="flex items-center bg-[#202622] border border-[#46514C] rounded p-0.5">
        <a href="{{ route('lang.switch', 'en') }}" class="px-2 py-0.5 rounded {{ !$isId ? 'bg-[#586B47] text-[#F2F2EC]' : 'text-[#8C9690]' }}">EN</a>
        <a href="{{ route('lang.switch', 'id') }}" class="px-2 py-0.5 rounded {{ $isId ? 'bg-[#586B47] text-[#F2F2EC]' : 'text-[#8C9690]' }}">ID</a>
      </div>
    </div>
  </div>

  <main class="flex-1 flex items-center justify-center p-4 sm:p-6">
    <div class="w-full @yield('container_class', 'max-w-md') space-y-4">
      @include('partials.flash')
      @yield('content')
    </div>
  </main>

  <footer class="border-t border-[var(--border-color)] bg-[var(--bg-surface)] px-4 py-3 text-center font-mono text-[10px] text-[var(--text-muted)]">
    TRACE-S INSTITUTIONAL ACCESS GATEWAY &middot; ALL ACCESS ATTEMPTS ARE LOGGED IN APPEND-ONLY AUDIT TRAIL
  </footer>
</body>
</html>
