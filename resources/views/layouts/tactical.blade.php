<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'id' ? 'id' : 'en' }}" class="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'TRACE-S // Social Cyber Intelligence Platform')</title>

  <!-- Google Fonts: Inter (400-600) & JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- FontAwesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <!-- Tailwind CSS via CDN with Obsidian Command Tokens (§4.2) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
            mono: ['"JetBrains Mono"', 'monospace'],
          },
          colors: {
            neutral: {
              850: '#1f1f1f',
              900: '#171717',
              950: '#0a0a0a',
            }
          }
        }
      }
    }
  </script>

  <style>
    /* Obsidian Command (§4.1 - §4.6) */
    body {
      background-color: #0a0a0a;
      color: #d4d4d4;
      font-family: 'Inter', sans-serif;
    }
    .font-mono {
      font-family: 'JetBrains Mono', monospace;
    }
    ::-webkit-scrollbar {
      width: 5px;
      height: 5px;
    }
    ::-webkit-scrollbar-track {
      background: #0a0a0a;
    }
    ::-webkit-scrollbar-thumb {
      background: #262626;
      border-radius: 0px;
    }
    ::-webkit-scrollbar-thumb:hover {
      background: #404040;
    }
    @keyframes slow-pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.35; }
    }
    .animate-pulse-slow {
      animation: slow-pulse 2.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
  </style>
  @stack('styles')
</head>
<body class="h-screen w-screen overflow-hidden flex flex-col bg-neutral-950 text-neutral-300 text-sm antialiased selection:bg-cyan-500/20 selection:text-cyan-400">

@php
  $isId = app()->getLocale() === 'id';
  $aiManager = app(\App\Services\Ai\AiManager::class);
  $aiStatus = $aiManager->status();
  $driverUpper = strtoupper($aiStatus['driver'] ?? 'MOCK');
  $isSimDriver = (bool) ($aiStatus['is_simulation'] ?? true);
  $serverOnline = ($aiStatus['status'] ?? 'OFFLINE') === 'ONLINE';
@endphp

  <!-- [TopBar] START -->
  <header class="h-14 bg-neutral-900 border-b border-neutral-800 px-4 flex items-center justify-between shrink-0 select-none z-30">
    <!-- Left: Crest + Brand + Subtitle (§5.0) -->
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 rounded-sm bg-cyan-500/10 border border-cyan-500/50 flex items-center justify-center text-cyan-500">
        <i class="fa-solid fa-shield-halved text-sm"></i>
      </div>
      <div>
        <div class="flex items-center gap-2">
          <span class="font-bold tracking-wider text-sm text-neutral-100 font-mono">TRACE-S</span>
          @if($isSimDriver)
          <span class="text-[10px] font-mono tracking-widest px-1.5 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm" title="is_simulation: true">
            {{ $isId ? 'SIMULASI' : 'SIMULATION' }}
          </span>
          @endif
        </div>
        <div class="text-[10px] uppercase tracking-widest text-neutral-500 leading-none mt-0.5 font-mono">
          SOCIAL CYBER INTELLIGENCE PLATFORM
        </div>
      </div>
    </div>

    <!-- Center: Real-time UTC Clock, SECURE LINK, Active AI Driver Indicator (§5.0) -->
    <div class="hidden lg:flex items-center gap-5 font-mono text-xs">
      <div class="flex items-center gap-2 text-neutral-300" title="Universal Coordinated Time">
        <i class="fa-regular fa-clock text-neutral-500 text-xs"></i>
        <span id="utc-clock" class="tracking-wide">00:00:00 — 01 JAN 2026 UTC</span>
      </div>

      <span class="text-neutral-800">|</span>

      <!-- SECURE LINK Status -->
      <div class="flex items-center gap-2 px-2 py-0.5 rounded-sm text-emerald-500 bg-emerald-500/10 border border-emerald-500/30">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse-slow"></span>
        <span class="text-[10px] uppercase tracking-widest font-semibold">SECURE LINK (TLS 1.3)</span>
      </div>

      <span class="text-neutral-800">|</span>

      <!-- Active AI Driver Status (Model-Agnostic, dynamic from backend, never hardcoded model name) -->
      @if($isSimDriver)
      <div class="flex items-center gap-2 px-2.5 py-0.5 rounded-sm text-amber-500 bg-amber-500/10 border border-amber-500/30" title="is_simulation: true | Driver: {{ $driverUpper }}">
        <i class="fa-solid fa-microchip text-xs"></i>
        <span class="text-[10px] uppercase tracking-widest">
          {{ $isId ? 'DRIVER AI:' : 'AI DRIVER:' }} <b>{{ $driverUpper }} ({{ $isId ? 'SIMULASI' : 'SIMULATION' }})</b>
        </span>
      </div>
      @elseif($serverOnline)
      <div class="flex items-center gap-2 px-2.5 py-0.5 rounded-sm text-emerald-500 bg-emerald-500/10 border border-emerald-500/30">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse-slow"></span>
        <span class="text-[10px] uppercase tracking-widest">
          {{ $isId ? 'DRIVER AI:' : 'AI DRIVER:' }} <b>{{ $driverUpper }} (ACTIVE)</b>
        </span>
      </div>
      @else
      <div class="flex items-center gap-2 px-2.5 py-0.5 rounded-sm text-rose-600 bg-rose-500/10 border border-rose-600/50">
        <span class="w-2 h-2 rounded-full bg-rose-600"></span>
        <span class="text-[10px] uppercase tracking-widest">
          {{ $isId ? 'DRIVER AI:' : 'AI DRIVER:' }} <b>{{ $driverUpper }} (AI SERVER OFFLINE)</b>
        </span>
      </div>
      @endif
    </div>

    <!-- Right: Language Switcher (EN Default / ID Second) & Dynamic Session User Profile (§5.0) -->
    <div class="flex items-center gap-3">
      <!-- Language Toggle -->
      <div class="flex items-center bg-neutral-950 border border-neutral-800 rounded-sm p-0.5 font-mono text-[10px]" role="group" aria-label="Language Switcher">
        <a href="{{ route('lang.switch', 'en') }}"
           aria-label="Switch to English"
           class="px-2 py-1 rounded-sm transition-opacity duration-150 {{ !$isId ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-semibold' : 'text-neutral-500 hover:text-neutral-300' }}">
          EN
        </a>
        <a href="{{ route('lang.switch', 'id') }}"
           aria-label="Ganti ke Bahasa Indonesia"
           class="px-2 py-1 rounded-sm transition-opacity duration-150 {{ $isId ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-semibold' : 'text-neutral-500 hover:text-neutral-300' }}">
          ID
        </a>
      </div>

      @auth
      <div class="relative" id="user-menu-wrapper">
        <button id="user-menu-btn"
                aria-label="{{ $isId ? 'Menu Profil Pengguna' : 'User Profile Menu' }}"
                class="flex items-center gap-2.5 px-3 py-1.5 rounded-sm bg-neutral-950 hover:bg-neutral-800/50 border border-neutral-800 transition-opacity duration-150 text-left">
          <div class="w-6 h-6 rounded-full bg-cyan-500/10 border border-cyan-500/50 flex items-center justify-center text-xs text-cyan-500">
            <i class="fa-solid fa-user text-[10px]"></i>
          </div>
          <div class="leading-tight hidden sm:block">
            <div class="text-xs font-medium text-neutral-300">{{ Auth::user()->name }}</div>
            <div class="text-[10px] text-neutral-500 font-mono uppercase tracking-widest">
              {{ Auth::user()->clearance_level ?? 'LEVEL-1' }} · {{ Auth::user()->roleLabel() }}
            </div>
          </div>
          <i class="fa-solid fa-chevron-down text-[10px] text-neutral-500 ml-1"></i>
        </button>

        <div id="user-dropdown" class="hidden absolute right-0 mt-1.5 w-64 bg-neutral-900 border border-neutral-800 rounded-sm p-2 z-50 text-xs font-sans divide-y divide-neutral-800">
          <div class="pb-2 px-2">
            <div class="text-[10px] uppercase tracking-widest text-neutral-500 font-mono">
              {{ $isId ? 'Sesi Aktif' : 'Active Session' }}
            </div>
            <div class="text-xs font-medium text-neutral-300 mt-0.5">{{ Auth::user()->name }}</div>
            <div class="text-[10px] font-mono text-cyan-500">{{ Auth::user()->unit ?? 'PUSSIBERAD' }} · {{ Auth::user()->clearance_level ?? 'CLEARANCE L1' }}</div>
          </div>

          <div class="py-2 space-y-1">
            <div class="px-2 pb-1 text-[10px] uppercase tracking-widest text-neutral-500 font-mono">
              {{ $isId ? 'Ganti Peran Uji (Demo):' : 'Switch Role (Evaluation):' }}
            </div>
            @foreach(['operator' => ['label_en' => 'Operator (Level 1)', 'label_id' => 'Operator (Level 1)', 'icon' => 'fa-desktop'],
                      'analis' => ['label_en' => 'Analyst (Level 2)', 'label_id' => 'Analis (Level 2)', 'icon' => 'fa-magnifying-glass-chart'],
                      'komandan' => ['label_en' => 'Commander (Level 3)', 'label_id' => 'Komandan (Level 3)', 'icon' => 'fa-star'],
                      'admin' => ['label_en' => 'System Admin (Level 4)', 'label_id' => 'Admin Sistem (Level 4)', 'icon' => 'fa-server']] as $roleKey => $roleMeta)
            <form action="{{ route('login.quick') }}" method="POST">
              @csrf
              <input type="hidden" name="role" value="{{ $roleKey }}">
              <button type="submit"
                      aria-label="Switch role to {{ $roleKey }}"
                      class="w-full text-left px-2 py-1.5 rounded-sm hover:bg-neutral-800/50 text-xs flex items-center justify-between {{ Auth::user()->role === $roleKey ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-medium' : 'text-neutral-300' }}">
                <span>
                  <i class="fa-solid {{ $roleMeta['icon'] }} w-4 text-center mr-1.5 {{ Auth::user()->role === $roleKey ? 'text-cyan-500' : 'text-neutral-500' }}"></i>
                  {{ $isId ? $roleMeta['label_id'] : $roleMeta['label_en'] }}
                </span>
                @if(Auth::user()->role === $roleKey)
                  <i class="fa-solid fa-check text-[10px] text-cyan-500"></i>
                @endif
              </button>
            </form>
            @endforeach
          </div>

          <div class="pt-2">
            <form action="{{ route('logout') }}" method="POST">
              @csrf
              <button type="submit"
                      aria-label="{{ $isId ? 'Keluar Sistem' : 'Sign Out' }}"
                      class="w-full text-left px-2 py-1.5 rounded-sm text-rose-600 bg-rose-500/10 border border-rose-600/50 hover:bg-neutral-800/50 text-xs flex items-center gap-2 font-mono uppercase tracking-wider">
                <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center"></i>
                <span>{{ $isId ? 'Keluar Sesi' : 'Sign Out' }}</span>
              </button>
            </form>
          </div>
        </div>
      </div>
      @endauth
    </div>
  </header>
  <!-- [TopBar] END -->

  <!-- [MainWorkspaceShell] START -->
  <div class="flex-1 flex overflow-hidden">

    <!-- [SidebarNavigation] START -->
    <aside class="w-64 bg-neutral-900 border-r border-neutral-800 flex flex-col justify-between shrink-0 select-none overflow-y-auto">
      <div class="p-3 space-y-5">

        <!-- Category 1: INTELLIGENCE (§5.0) -->
        <div>
          <div class="px-2.5 pb-1.5 text-[10px] uppercase tracking-widest text-neutral-500 font-mono">
            {{ $isId ? 'INTELIJEN' : 'INTELLIGENCE' }}
          </div>
          <nav class="space-y-1">
            <a href="{{ route('dashboard') }}"
               class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-sm text-xs transition-opacity duration-150 {{ request()->routeIs('dashboard*') ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-medium' : 'text-neutral-300 hover:bg-neutral-800/50 border border-transparent' }}">
              <i class="fa-solid fa-chart-line w-4 text-center {{ request()->routeIs('dashboard*') ? 'text-cyan-500' : 'text-neutral-500' }}"></i>
              <span class="flex-1 text-left">{{ $isId ? 'Peringatan Dini (A1)' : 'Early Warning (A1)' }}</span>
              <span class="w-2 h-2 rounded-full bg-amber-500" title="SIMULATION DATASET"></span>
            </a>
          </nav>
        </div>

        <!-- Category 2: ANALYSIS (§5.0) -->
        <div>
          <div class="px-2.5 pb-1.5 text-[10px] uppercase tracking-widest text-neutral-500 font-mono">
            {{ $isId ? 'ANALISIS' : 'ANALYSIS' }}
          </div>
          <nav class="space-y-1">
            <a href="{{ route('scanner.index', ['tab' => 'scan']) }}"
               class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-sm text-xs transition-opacity duration-150 {{ request()->routeIs('scanner*') && request()->query('tab', 'scan') !== 'crosssource' ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-medium' : 'text-neutral-300 hover:bg-neutral-800/50 border border-transparent' }}">
              <i class="fa-solid fa-magnifying-glass w-4 text-center {{ request()->routeIs('scanner*') && request()->query('tab', 'scan') !== 'crosssource' ? 'text-cyan-500' : 'text-neutral-500' }}"></i>
              <span class="flex-1 text-left">{{ $isId ? 'Pemeriksa Kredibilitas (B)' : 'Credibility Scanner (B)' }}</span>
              <span class="w-2 h-2 rounded-full {{ $isSimDriver ? 'bg-amber-500' : ($serverOnline ? 'bg-emerald-500 animate-pulse-slow' : 'bg-rose-600') }}"
                    title="{{ $isSimDriver ? 'SIMULATION' : ($serverOnline ? 'ACTIVE' : 'OFFLINE') }}"></span>
            </a>

            <a href="{{ route('scanner.index', ['tab' => 'crosssource']) }}"
               class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-sm text-xs transition-opacity duration-150 {{ request()->routeIs('scanner*') && request()->query('tab') === 'crosssource' ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-medium' : 'text-neutral-300 hover:bg-neutral-800/50 border border-transparent' }}">
              <i class="fa-solid fa-code-compare w-4 text-center {{ request()->routeIs('scanner*') && request()->query('tab') === 'crosssource' ? 'text-cyan-500' : 'text-neutral-500' }}"></i>
              <span class="flex-1 text-left">{{ $isId ? 'Verifikasi Lintas Sumber' : 'Cross-Source Workspace' }}</span>
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse-slow" title="ACTIVE"></span>
            </a>

            <a href="{{ route('network.index') }}"
               class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-sm text-xs transition-opacity duration-150 {{ request()->routeIs('network*') ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-medium' : 'text-neutral-300 hover:bg-neutral-800/50 border border-transparent' }}">
              <i class="fa-solid fa-circle-nodes w-4 text-center {{ request()->routeIs('network*') ? 'text-cyan-500' : 'text-neutral-500' }}"></i>
              <span class="flex-1 text-left">{{ $isId ? 'Jaringan Penyebaran (C)' : 'Spread Network (C)' }}</span>
              <span class="w-2 h-2 rounded-full bg-amber-500" title="SIMULATION"></span>
            </a>

            <a href="{{ route('sentiment.index') }}"
               class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-sm text-xs transition-opacity duration-150 {{ request()->routeIs('sentiment*') ? 'text-violet-400 bg-violet-500/10 border border-violet-500/30 font-medium' : 'text-neutral-300 hover:bg-neutral-800/50 border border-transparent' }}">
              <i class="fa-solid fa-heart-pulse w-4 text-center text-violet-400"></i>
              <span class="flex-1 text-left">{{ $isId ? 'Analisis Sentimen (A2)' : 'Sentiment Analysis (A2)' }}</span>
              <span class="w-2 h-2 rounded-full bg-amber-500" title="SIMULATION"></span>
            </a>
          </nav>
        </div>

        <!-- Category 3: REPORTING (§5.0) -->
        <div>
          <div class="px-2.5 pb-1.5 text-[10px] uppercase tracking-widest text-neutral-500 font-mono">
            {{ $isId ? 'PELAPORAN' : 'REPORTING' }}
          </div>
          <nav class="space-y-1">
            <a href="{{ route('reports.index') }}"
               class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-sm text-xs transition-opacity duration-150 {{ request()->routeIs('reports*') ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-medium' : 'text-neutral-300 hover:bg-neutral-800/50 border border-transparent' }}">
              <i class="fa-regular fa-file-lines w-4 text-center {{ request()->routeIs('reports*') ? 'text-cyan-500' : 'text-neutral-500' }}"></i>
              <span class="flex-1 text-left">{{ $isId ? 'Laporan Intelijen (D)' : 'Intelligence Reports (D)' }}</span>
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse-slow" title="ACTIVE"></span>
            </a>

            <a href="{{ route('cases.index') }}"
               class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-sm text-xs transition-opacity duration-150 {{ request()->routeIs('cases*') ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-medium' : 'text-neutral-300 hover:bg-neutral-800/50 border border-transparent' }}">
              <i class="fa-solid fa-folder-closed w-4 text-center {{ request()->routeIs('cases*') ? 'text-cyan-500' : 'text-neutral-500' }}"></i>
              <span class="flex-1 text-left">{{ $isId ? 'Arsip Kasus' : 'Case Archive' }}</span>
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse-slow" title="ACTIVE"></span>
            </a>
          </nav>
        </div>

        <!-- Category 4: AI SYSTEM (§5.0) -->
        <div>
          <div class="px-2.5 pb-1.5 text-[10px] uppercase tracking-widest text-neutral-500 font-mono">
            {{ $isId ? 'SISTEM AI' : 'AI SYSTEM' }}
          </div>
          <nav class="space-y-1">
            <a href="{{ route('console.index') }}"
               class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-sm text-xs transition-opacity duration-150 {{ request()->routeIs('console*') ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-medium' : 'text-neutral-300 hover:bg-neutral-800/50 border border-transparent' }}">
              <i class="fa-solid fa-terminal w-4 text-center {{ request()->routeIs('console*') ? 'text-cyan-500' : 'text-neutral-500' }}"></i>
              <span class="flex-1 text-left">{{ $isId ? 'Konsol Analis AI (E)' : 'AI Analyst Console (E)' }}</span>
              <span class="w-2 h-2 rounded-full {{ $isSimDriver ? 'bg-amber-500' : ($serverOnline ? 'bg-emerald-500 animate-pulse-slow' : 'bg-rose-600') }}"></span>
            </a>

            @if(Auth::check() && Auth::user()->hasRole('admin'))
            <a href="{{ route('admin.ai.index') }}"
               class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-sm text-xs transition-opacity duration-150 {{ request()->routeIs('admin.ai*') ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-medium' : 'text-neutral-300 hover:bg-neutral-800/50 border border-transparent' }}">
              <i class="fa-solid fa-server w-4 text-center {{ request()->routeIs('admin.ai*') ? 'text-cyan-500' : 'text-neutral-500' }}"></i>
              <span class="flex-1 text-left">{{ $isId ? 'Manajemen AI Lokal (F)' : 'Local AI Mgmt (F)' }}</span>
              <span class="text-[10px] font-mono text-neutral-500">ADMIN</span>
            </a>

            <a href="{{ route('admin.audit.index') }}"
               class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-sm text-xs transition-opacity duration-150 {{ request()->routeIs('admin.audit*') ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-medium' : 'text-neutral-300 hover:bg-neutral-800/50 border border-transparent' }}">
              <i class="fa-solid fa-clock-rotate-left w-4 text-center {{ request()->routeIs('admin.audit*') ? 'text-cyan-500' : 'text-neutral-500' }}"></i>
              <span class="flex-1 text-left">{{ $isId ? 'Log Audit (Permanen)' : 'Audit Log (Immutable)' }}</span>
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse-slow" title="APPEND-ONLY"></span>
            </a>
            @endif
          </nav>
        </div>

      </div>

      <!-- Bottom Compliance Notice in Sidebar (§2A Guardrail) -->
      <div class="p-3 border-t border-neutral-800 bg-neutral-950 select-none">
        <div class="p-3 bg-neutral-900 border border-neutral-800 rounded-sm space-y-1.5">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-mono uppercase tracking-widest text-cyan-500 font-semibold">
              {{ $isId ? 'BATAS PRIVASI AKTIF' : 'PRIVACY BOUNDARY' }}
            </span>
            <i class="fa-solid fa-lock text-neutral-500 text-xs"></i>
          </div>
          <p class="text-[10px] text-neutral-500 leading-relaxed font-sans">
            {{ $isId
              ? 'Menganalisis pola narasi & konten publik. Tanpa pelacakan identitas pribadi, alamat rumah, atau data keluarga (§2A).'
              : 'Analyzes public narrative & account patterns only. No personal identity, home address, or family resolution (§2A).' }}
          </p>
        </div>
      </div>
    </aside>
    <!-- [SidebarNavigation] END -->

    <!-- [MainContentPanel] START -->
    <main class="flex-1 overflow-y-auto bg-neutral-950 relative">

      <!-- Flash Notification Banners -->
      <div class="max-w-7xl mx-auto px-4 pt-3">
        @if(session('success'))
        <div class="mb-3 px-3.5 py-2.5 text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 text-xs font-mono rounded-sm flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
          </div>
          <button onclick="this.parentElement.remove()" aria-label="Close notification" class="text-emerald-500 hover:opacity-75">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        @endif

        @if(session('error'))
        <div class="mb-3 px-3.5 py-2.5 text-rose-600 bg-rose-500/10 border border-rose-600/50 text-xs font-mono rounded-sm flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>{{ session('error') }}</span>
          </div>
          <button onclick="this.parentElement.remove()" aria-label="Close notification" class="text-rose-600 hover:opacity-75">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        @endif

        @if(session('warning'))
        <div class="mb-3 px-3.5 py-2.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 text-xs font-mono rounded-sm flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ session('warning') }}</span>
          </div>
          <button onclick="this.parentElement.remove()" aria-label="Close notification" class="text-amber-500 hover:opacity-75">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        @endif
      </div>

      <!-- Content Yield -->
      <div class="p-4 lg:p-5 max-w-7xl mx-auto space-y-4">
        @yield('content')
      </div>
    </main>
    <!-- [MainContentPanel] END -->

  </div>
  <!-- [MainWorkspaceShell] END -->

  <!-- [GlobalShellScript] START -->
  <script>
    // Real-time UTC Clock: format HH:mm:ss — DD MMM YYYY (§5.0)
    function updateUtcClock() {
      const now = new Date();
      const pad = (n) => String(n).padStart(2, '0');
      const months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
      const hh = pad(now.getUTCHours());
      const mm = pad(now.getUTCMinutes());
      const ss = pad(now.getUTCSeconds());
      const dd = pad(now.getUTCDate());
      const mmm = months[now.getUTCMonth()];
      const yyyy = now.getUTCFullYear();
      const el = document.getElementById('utc-clock');
      if (el) {
        el.textContent = `${hh}:${mm}:${ss} — ${dd} ${mmm} ${yyyy} UTC`;
      }
    }
    setInterval(updateUtcClock, 1000);
    updateUtcClock();

    // User Profile Dropdown toggle
    const userMenuBtn = document.getElementById('user-menu-btn');
    const userDropdown = document.getElementById('user-dropdown');
    if (userMenuBtn && userDropdown) {
      userMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle('hidden');
      });
      document.addEventListener('click', (e) => {
        if (!userDropdown.contains(e.target) && !userMenuBtn.contains(e.target)) {
          userDropdown.classList.add('hidden');
        }
      });
    }
  </script>
  <!-- [GlobalShellScript] END -->
  @stack('scripts')
</body>
</html>
