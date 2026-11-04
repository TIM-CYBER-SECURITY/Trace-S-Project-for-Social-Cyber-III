<!DOCTYPE html>
<html lang="id" class="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'TRACE-S // Cognitive Defense Platform')</title>

  <!-- Google Fonts: Inter & JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- FontAwesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <!-- Tailwind CSS via CDN with Tactical Tokens -->
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
              900: '#121212',
              950: '#0a0a0a',
            }
          }
        }
      }
    }
  </script>

  <style>
    /* Tactical extreme dark mode adjustments */
    body {
      background-color: #0a0a0a;
      color: #d4d4d4;
      font-family: 'Inter', sans-serif;
    }
    .font-mono {
      font-family: 'JetBrains Mono', monospace;
    }
    /* Custom tactful scrollbars */
    ::-webkit-scrollbar {
      width: 5px;
      height: 5px;
    }
    ::-webkit-scrollbar-track {
      background: #0a0a0a;
    }
    ::-webkit-scrollbar-thumb {
      background: #262626;
      border-radius: 1px;
    }
    ::-webkit-scrollbar-thumb:hover {
      background: #404040;
    }
  </style>
  @stack('styles')
</head>
<body class="bg-neutral-950 text-neutral-300 min-h-screen flex flex-col antialiased selection:bg-cyan-500/30 selection:text-cyan-200">

  <!-- START: TOP BAR -->
  <header class="h-14 bg-neutral-900 border-b border-neutral-800 px-4 flex items-center justify-between shrink-0 select-none z-30">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 rounded-sm bg-cyan-950/60 border border-cyan-500/50 flex items-center justify-center text-cyan-400 shadow-[0_0_12px_rgba(6,182,212,0.25)]">
        <i class="fa-solid fa-shield-halved text-sm"></i>
      </div>
      <div>
        <div class="flex items-center gap-2">
          <span class="font-mono font-bold tracking-wider text-sm text-neutral-100">TRACE-S</span>
          <span class="text-[9px] font-mono tracking-widest text-cyan-400 px-1.5 py-0.2 border border-cyan-500/30 bg-cyan-950/40 rounded-sm">C4ISR</span>
          <span class="text-[9px] font-mono tracking-widest text-amber-500 px-1.5 py-0.2 border border-amber-500/40 bg-amber-950/30 rounded-sm">SIMULASI</span>
        </div>
        <div class="text-[10px] font-mono uppercase tracking-widest text-neutral-500 leading-none">
          COGNITIVE DEFENSE INITIATIVE // PUSSIBERAD
        </div>
      </div>
    </div>

    <!-- Center Clock & Coordinates -->
    <div class="hidden md:flex items-center gap-6 font-mono text-xs">
      <div class="flex items-center gap-2 text-neutral-400">
        <i class="fa-regular fa-clock text-cyan-400 text-[11px]"></i>
        <span id="utc-clock" class="tracking-widest">--:--:-- UTC</span>
      </div>
      <div class="text-neutral-600 text-[10px]">|</div>
      <div class="flex items-center gap-2 text-neutral-400">
        <i class="fa-solid fa-location-crosshairs text-cyan-400 text-[11px]"></i>
        <span class="text-[11px] tracking-wider text-neutral-400">06°10'30"S 106°49'42"E (HQ)</span>
      </div>
    </div>

    <!-- Right Controls: Status Link & User Profile -->
    <div class="flex items-center gap-3">
      <!-- AI Engine Indicator -->
      @php
        $aiStatus = app(App\Services\Ai\AiManager::class)->status();
      @endphp
      <div class="hidden sm:flex items-center gap-2 px-2.5 py-1 bg-neutral-950 border border-neutral-800 rounded-sm font-mono text-[11px]">
        <span class="relative flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 {{ $aiStatus['status'] === 'ONLINE' ? 'bg-emerald-400' : 'bg-cyan-400' }}"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 {{ $aiStatus['status'] === 'ONLINE' ? 'bg-emerald-500' : 'bg-cyan-500' }}"></span>
        </span>
        <span class="text-neutral-400 text-[10px] uppercase">AI:</span>
        <span class="text-cyan-400 font-semibold">{{ $aiStatus['driver'] }}</span>
        <span class="text-neutral-500 text-[9px] uppercase">({{ $aiStatus['status'] }})</span>
      </div>

      <!-- User Clearance Badge -->
      @auth
      <div class="flex items-center gap-2 pl-2 border-l border-neutral-800">
        <div class="text-right">
          <div class="text-xs font-semibold text-neutral-200 leading-tight">{{ Auth::user()->name }}</div>
          <div class="flex items-center justify-end gap-1.5 mt-0.5">
            <span class="text-[9px] font-mono uppercase px-1 py-0.2 rounded-sm border border-neutral-700 bg-neutral-800 text-neutral-300">
              {{ Auth::user()->roleLabel() }}
            </span>
            <span class="text-[9px] font-mono uppercase px-1 py-0.2 rounded-sm border border-rose-600/40 bg-rose-950/30 text-rose-400">
              {{ Auth::user()->clearance_level }}
            </span>
          </div>
        </div>

        <!-- Quick Switch Roles / Logout Dropdown -->
        <div class="relative" id="user-menu-wrapper">
          <button id="user-menu-btn" class="w-8 h-8 rounded-sm bg-neutral-800 hover:bg-neutral-700 border border-neutral-700 flex items-center justify-center text-neutral-300 transition-colors" aria-label="Menu Pengguna">
            <i class="fa-solid fa-user-shield text-xs"></i>
          </button>
          <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-64 bg-neutral-900 border border-neutral-800 rounded-sm shadow-xl p-2 z-50 text-xs">
            <div class="px-2 py-1.5 border-b border-neutral-800 mb-1">
              <div class="text-[10px] uppercase tracking-wider text-neutral-500 font-mono">Beralih Peran (Evaluasi):</div>
            </div>
            <div class="space-y-1">
              <form action="{{ route('login.quick') }}" method="POST">
                @csrf
                <input type="hidden" name="role" value="operator">
                <button type="submit" class="w-full text-left px-2 py-1.5 rounded-sm hover:bg-neutral-800 font-mono text-[11px] flex items-center justify-between {{ Auth::user()->role === 'operator' ? 'text-cyan-400 font-bold' : 'text-neutral-400' }}">
                  <span><i class="fa-solid fa-desktop w-4 text-center"></i> Operator</span>
                  @if(Auth::user()->role === 'operator') <i class="fa-solid fa-check text-[10px]"></i> @endif
                </button>
              </form>
              <form action="{{ route('login.quick') }}" method="POST">
                @csrf
                <input type="hidden" name="role" value="analis">
                <button type="submit" class="w-full text-left px-2 py-1.5 rounded-sm hover:bg-neutral-800 font-mono text-[11px] flex items-center justify-between {{ Auth::user()->role === 'analis' ? 'text-cyan-400 font-bold' : 'text-neutral-400' }}">
                  <span><i class="fa-solid fa-magnifying-glass-chart w-4 text-center"></i> Analis</span>
                  @if(Auth::user()->role === 'analis') <i class="fa-solid fa-check text-[10px]"></i> @endif
                </button>
              </form>
              <form action="{{ route('login.quick') }}" method="POST">
                @csrf
                <input type="hidden" name="role" value="komandan">
                <button type="submit" class="w-full text-left px-2 py-1.5 rounded-sm hover:bg-neutral-800 font-mono text-[11px] flex items-center justify-between {{ Auth::user()->role === 'komandan' ? 'text-cyan-400 font-bold' : 'text-neutral-400' }}">
                  <span><i class="fa-solid fa-star w-4 text-center"></i> Komandan</span>
                  @if(Auth::user()->role === 'komandan') <i class="fa-solid fa-check text-[10px]"></i> @endif
                </button>
              </form>
              <form action="{{ route('login.quick') }}" method="POST">
                @csrf
                <input type="hidden" name="role" value="admin">
                <button type="submit" class="w-full text-left px-2 py-1.5 rounded-sm hover:bg-neutral-800 font-mono text-[11px] flex items-center justify-between {{ Auth::user()->role === 'admin' ? 'text-cyan-400 font-bold' : 'text-neutral-400' }}">
                  <span><i class="fa-solid fa-key w-4 text-center"></i> Administrator</span>
                  @if(Auth::user()->role === 'admin') <i class="fa-solid fa-check text-[10px]"></i> @endif
                </button>
              </form>
            </div>
            <div class="border-t border-neutral-800 mt-2 pt-1">
              <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full text-left px-2 py-1.5 rounded-sm hover:bg-rose-950/40 text-rose-400 font-mono text-[11px] flex items-center gap-2">
                  <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center"></i>
                  <span>Keluar Sesi Terminal</span>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
      @endauth
    </div>
  </header>
  <!-- END: TOP BAR -->

  <!-- START: BODY (SIDEBAR + MAIN) -->
  <div class="flex-1 flex overflow-hidden">

    <!-- START: LEFT SIDEBAR -->
    <aside class="w-60 bg-neutral-900 border-r border-neutral-800 flex flex-col justify-between shrink-0 select-none overflow-y-auto">
      <div class="py-2">
        <div class="px-3 py-1.5 text-[10px] uppercase tracking-widest text-neutral-500 font-mono">Modul Analisis</div>

        <nav class="space-y-0.5">
          <!-- Modul A: Early Warning Dashboard -->
          <a href="{{ route('dashboard') }}" class="w-full flex items-center gap-3 px-3 py-2 border-l-2 text-[11px] uppercase tracking-wider font-mono transition-colors {{ request()->routeIs('dashboard*') ? 'border-cyan-500 text-cyan-300 bg-cyan-950/30' : 'border-transparent text-neutral-400 hover:bg-neutral-800/60 hover:text-neutral-200' }}">
            <i class="fa-solid fa-chart-line w-4 text-center"></i>
            <span class="flex-1 text-left">Early Warning</span>
            <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
          </a>

          <!-- Modul G: Case Management -->
          <a href="{{ route('cases.index') }}" class="w-full flex items-center gap-3 px-3 py-2 border-l-2 text-[11px] uppercase tracking-wider font-mono transition-colors {{ request()->routeIs('cases*') ? 'border-cyan-500 text-cyan-300 bg-cyan-950/30' : 'border-transparent text-neutral-400 hover:bg-neutral-800/60 hover:text-neutral-200' }}">
            <i class="fa-solid fa-folder-closed w-4 text-center"></i>
            <span class="flex-1 text-left">Investigasi Kasus</span>
            <span class="text-[9px] text-cyan-400 font-mono">TS-001</span>
          </a>

          <!-- Modul B: Credibility Scanner -->
          <a href="{{ route('scanner.index') }}" class="w-full flex items-center gap-3 px-3 py-2 border-l-2 text-[11px] uppercase tracking-wider font-mono transition-colors {{ request()->routeIs('scanner*') ? 'border-cyan-500 text-cyan-300 bg-cyan-950/30' : 'border-transparent text-neutral-400 hover:bg-neutral-800/60 hover:text-neutral-200' }}">
            <i class="fa-solid fa-shield-halved w-4 text-center"></i>
            <span class="flex-1 text-left">Credibility Scanner</span>
            <span class="text-[9px] px-1 bg-neutral-800 text-neutral-400 rounded-sm">3 Tab</span>
          </a>

          <!-- Modul C: Threat Actor Network & Spread Analysis -->
          <a href="{{ route('network.index') }}" class="w-full flex items-center gap-3 px-3 py-2 border-l-2 text-[11px] uppercase tracking-wider font-mono transition-colors {{ request()->routeIs('network*') ? 'border-cyan-500 text-cyan-300 bg-cyan-950/30' : 'border-transparent text-neutral-400 hover:bg-neutral-800/60 hover:text-neutral-200' }}">
            <i class="fa-solid fa-diagram-project w-4 text-center"></i>
            <span class="flex-1 text-left">Spread Analysis</span>
            <span class="text-[9px] text-amber-500 font-mono">SNA</span>
          </a>

          <!-- Modul D: Command Reporting & Evidence -->
          <a href="{{ route('reports.index') }}" class="w-full flex items-center gap-3 px-3 py-2 border-l-2 text-[11px] uppercase tracking-wider font-mono transition-colors {{ request()->routeIs('reports*') ? 'border-cyan-500 text-cyan-300 bg-cyan-950/30' : 'border-transparent text-neutral-400 hover:bg-neutral-800/60 hover:text-neutral-200' }}">
            <i class="fa-solid fa-file-shield w-4 text-center"></i>
            <span class="flex-1 text-left">Intel Brief (PDF)</span>
            <span class="text-[9px] text-emerald-400 font-mono">16-SEC</span>
          </a>

          <!-- Modul E: AI Analyst Console -->
          <a href="{{ route('console.index') }}" class="w-full flex items-center gap-3 px-3 py-2 border-l-2 text-[11px] uppercase tracking-wider font-mono transition-colors {{ request()->routeIs('console*') ? 'border-cyan-500 text-cyan-300 bg-cyan-950/30' : 'border-transparent text-neutral-400 hover:bg-neutral-800/60 hover:text-neutral-200' }}">
            <i class="fa-solid fa-terminal w-4 text-center"></i>
            <span class="flex-1 text-left">AI Analyst Console</span>
            <span class="text-[9px] text-cyan-400 font-mono"><i class="fa-solid fa-microchip"></i></span>
          </a>
        </nav>

        <!-- Admin Area -->
        @if(Auth::check() && Auth::user()->hasRole('admin'))
        <div class="px-3 pt-4 pb-1 text-[10px] uppercase tracking-widest text-neutral-500 font-mono">Administrasi & Konfigurasi</div>
        <nav class="space-y-0.5">
          <a href="{{ route('admin.ai.index') }}" class="w-full flex items-center gap-3 px-3 py-2 border-l-2 text-[11px] uppercase tracking-wider font-mono transition-colors {{ request()->routeIs('admin.ai*') ? 'border-cyan-500 text-cyan-300 bg-cyan-950/30' : 'border-transparent text-neutral-400 hover:bg-neutral-800/60 hover:text-neutral-200' }}">
            <i class="fa-solid fa-server w-4 text-center"></i>
            <span class="flex-1 text-left">Local AI Management</span>
          </a>
          <a href="{{ route('admin.audit.index') }}" class="w-full flex items-center gap-3 px-3 py-2 border-l-2 text-[11px] uppercase tracking-wider font-mono transition-colors {{ request()->routeIs('admin.audit*') ? 'border-cyan-500 text-cyan-300 bg-cyan-950/30' : 'border-transparent text-neutral-400 hover:bg-neutral-800/60 hover:text-neutral-200' }}">
            <i class="fa-solid fa-clock-rotate-left w-4 text-center"></i>
            <span class="flex-1 text-left">Audit Log (Immutable)</span>
          </a>
        </nav>
        @endif
      </div>

      <!-- Bottom Connector Nodes Status -->
      <div class="p-3 border-t border-neutral-800/80 bg-neutral-950/60 text-[10px] font-mono space-y-2">
        <div class="text-[9px] uppercase tracking-wider text-neutral-500 flex items-center justify-between">
          <span>Konektor Data</span>
          <span class="text-neutral-600">Trace-S</span>
        </div>
        <div class="flex items-center justify-between text-neutral-400">
          <span class="flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> News / RSS Feed
          </span>
          <span class="text-emerald-500 text-[9px] font-semibold">ACTIVE</span>
        </div>
        <div class="flex items-center justify-between text-neutral-400">
          <span class="flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> URL Evidence Fetch
          </span>
          <span class="text-emerald-500 text-[9px] font-semibold">ACTIVE</span>
        </div>
        <div class="flex items-center justify-between text-neutral-400">
          <span class="flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Social Media API
          </span>
          <span class="text-amber-500 text-[9px]">SIMULASI</span>
        </div>
        <div class="flex items-center justify-between text-neutral-500">
          <span class="flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-neutral-600"></span> Web Search Gateway
          </span>
          <span class="text-neutral-500 text-[9px]">NOT CONFIGURED</span>
        </div>
      </div>
    </aside>
    <!-- END: LEFT SIDEBAR -->

    <!-- START: MAIN CONTENT -->
    <main class="flex-1 overflow-y-auto bg-neutral-950 relative">

      <!-- Flash Notification Banners -->
      <div class="max-w-7xl mx-auto px-4 pt-3">
        @if(session('success'))
        <div class="mb-3 px-3.5 py-2.5 bg-emerald-950/40 border border-emerald-500/40 text-emerald-400 text-xs font-mono rounded-sm flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
          </div>
          <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-300"><i class="fa-solid fa-xmark"></i></button>
        </div>
        @endif

        @if(session('error'))
        <div class="mb-3 px-3.5 py-2.5 bg-rose-950/40 border border-rose-600/50 text-rose-400 text-xs font-mono rounded-sm flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>{{ session('error') }}</span>
          </div>
          <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-300"><i class="fa-solid fa-xmark"></i></button>
        </div>
        @endif

        @if(session('warning'))
        <div class="mb-3 px-3.5 py-2.5 bg-amber-950/40 border border-amber-500/40 text-amber-400 text-xs font-mono rounded-sm flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ session('warning') }}</span>
          </div>
          <button onclick="this.parentElement.remove()" class="text-amber-500 hover:text-amber-300"><i class="fa-solid fa-xmark"></i></button>
        </div>
        @endif
      </div>

      <!-- Content Yield -->
      <div class="p-4 lg:p-6 max-w-7xl mx-auto space-y-5">
        @yield('content')
      </div>
    </main>
    <!-- END: MAIN CONTENT -->

  </div>
  <!-- END: BODY -->

  <!-- Application Script (Vanilla JS Only) -->
  <script>
    // Real-time UTC Clock
    function updateUtcClock() {
      const now = new Date();
      const utcString = now.toUTCString().split(' ')[4] + ' UTC';
      const el = document.getElementById('utc-clock');
      if (el) el.textContent = utcString;
    }
    setInterval(updateUtcClock, 1000);
    updateUtcClock();

    // User Dropdown toggle
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
  @stack('scripts')
</body>
</html>
