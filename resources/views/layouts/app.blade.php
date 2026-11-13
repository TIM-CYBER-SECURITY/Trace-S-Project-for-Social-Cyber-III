<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'id' ? 'id' : 'en' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'TRACE-S // Institutional Information & Social Cyber Operations')</title>
  <script>
    if (localStorage.getItem('trace-theme') === 'dark') {
      document.documentElement.classList.add('dark');
    }
  </script>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @stack('styles')
</head>
@php
  $isId = app()->getLocale() === 'id';
  $user = Auth::guard('web')->user();
  $settings = app(\App\Services\Support\AppSettings::class);
  $classificationLabel = $settings->get('classification_label');
  $systemNotice = $settings->get('system_notice');
  $aiStatus = app(\App\Services\Ai\AiManager::class)->status();
  $driverUpper = strtoupper($aiStatus['driver'] ?? 'MOCK');
  $isSimDriver = (bool) ($aiStatus['is_simulation'] ?? true);
  $serverOnline = ($aiStatus['status'] ?? 'OFFLINE') === 'ONLINE';
  $unreadNotifs = $user ? \App\Models\AppNotification::where('recipient_id', $user->id)->whereNull('read_at')->count() : 0;
@endphp
<body class="min-h-screen flex flex-col bg-[var(--bg-canvas)] text-[var(--text-primary)] text-sm antialiased">

  @if($classificationLabel || $systemNotice)
  <div class="bg-[#202622] border-b border-[#36413C] px-4 py-1 flex flex-wrap items-center justify-between gap-2 text-[10px] font-mono tracking-widest uppercase text-[#C5B89C]">
    <span>{{ $classificationLabel }}</span>
    @if($systemNotice)
      <span class="text-[#F2F2EC] normal-case tracking-normal">{{ $systemNotice }}</span>
    @endif
  </div>
  @endif

  <header class="h-14 bg-[#17251F] border-b border-[#36413C] px-4 flex items-center justify-between shrink-0 z-30 text-[#F2F2EC]">
    <div class="flex items-center gap-3 min-w-0">
      <button type="button" id="mobile-sidebar-btn" aria-label="Toggle Navigation"
              class="lg:hidden p-2 rounded border border-[#46514C] text-[#F2F2EC] hover:bg-[#202622]">
        <i class="fa-solid fa-bars text-xs"></i>
      </button>

      <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 min-w-0">
        <img src="{{ asset('images/trace-s-logo.png') }}" alt="TRACE-S Logo" class="h-7 w-auto object-contain bg-[#F2F2EC] rounded px-1.5 py-0.5">
        <div class="hidden sm:block truncate">
          <div class="flex items-center gap-2">
            <span class="font-mono font-bold tracking-wider text-xs text-[#F2F2EC]">TRACE-S</span>
            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-[#202622] border border-[#46514C] text-[#C5B89C]">
              v{{ config('trace.version', '2.0.0') }}
            </span>
          </div>
          <div class="text-[10px] font-mono uppercase tracking-widest text-[#8C9690] leading-none mt-0.5">
            {{ config('trace.tagline', 'COGNITIVE DEFENSE INITIATIVE') }}
          </div>
        </div>
      </a>
    </div>

    <div class="hidden xl:flex items-center gap-4 font-mono text-[11px]">
      <div class="flex items-center gap-1.5 text-[#C5B89C]" title="Coordinated Universal Time">
        <i class="fa-regular fa-clock text-[#8C9690]"></i>
        <span id="utc-clock">00:00:00 — 01 JAN 2026 UTC</span>
      </div>

      <span class="text-[#46514C]">|</span>

      @php
        $aiShortModel = !empty($aiStatus['active_model']) ? \Illuminate\Support\Str::limit($aiStatus['active_model'], 26) : null;
        $aiRamUsedGb = $aiStatus['ai_ram_used_gb'] ?? null;
      @endphp
      <a href="{{ $user && $user->hasPermission('ai.manage') ? route('admin.ai.index') : route('scanner.index') }}"
         class="flex items-center gap-1.5 px-2.5 py-1 rounded-sm bg-[#18201B] border border-[#3B4940] hover:border-[#B9AA88] text-[#F2F1EC]"
         title="AI Driver: {{ $driverUpper }}{{ $aiShortModel ? ' • Model: ' . $aiStatus['active_model'] : '' }}">
        <span class="w-2 h-2 rounded-full {{ $isSimDriver ? 'bg-[#B9AA88]' : ($serverOnline ? 'bg-[#688A4D]' : 'bg-[#991B1B]') }}"></span>
        <span class="text-[10px] tracking-wider uppercase tabular-nums">
          AI: <strong>{{ $driverUpper }}</strong>
          @if($aiShortModel && !$isSimDriver)
            <span class="text-[#C7C3B2] font-normal normal-case">&bull; {{ $aiShortModel }}</span>
          @endif
          @if(!$isSimDriver && $aiRamUsedGb !== null && $aiRamUsedGb > 0)
            <span class="text-[#B9AA88] font-normal">&bull; {{ number_format((float) $aiRamUsedGb, 2) }} GB RAM</span>
          @endif
          @if($isSimDriver)
            ({{ $isId ? 'SIMULASI' : 'SIMULATION' }})
          @elseif($serverOnline)
            (ACTIVE)
          @else
            (OFFLINE)
          @endif
        </span>
      </a>
    </div>

    <div class="flex items-center gap-2">
      <!-- Theme Switcher -->
      <button type="button" onclick="window.toggleTraceTheme()"
              class="px-2.5 py-1.5 rounded bg-[#202622] border border-[#46514C] hover:border-[#C5B89C] text-[10px] font-mono uppercase tracking-wider text-[#F2F2EC] flex items-center gap-1.5"
              title="Toggle Light / Dark Mode">
        <i class="fa-solid fa-circle-half-stroke text-[#C5B89C]"></i>
        <span data-theme-label>LIGHT</span>
      </button>

      <!-- Language Switcher -->
      <div class="flex items-center bg-[#202622] border border-[#46514C] rounded p-0.5 font-mono text-[10px]">
        <a href="{{ route('lang.switch', 'en') }}"
           class="px-2 py-0.5 rounded {{ !$isId ? 'bg-[#586B47] text-[#F2F2EC] font-semibold' : 'text-[#8C9690] hover:text-[#F2F2EC]' }}">EN</a>
        <a href="{{ route('lang.switch', 'id') }}"
           class="px-2 py-0.5 rounded {{ $isId ? 'bg-[#586B47] text-[#F2F2EC] font-semibold' : 'text-[#8C9690] hover:text-[#F2F2EC]' }}">ID</a>
      </div>

      @if($user)
        <!-- Notifications Shortcut -->
        <a href="{{ route('notifications.index') }}"
           class="relative px-2.5 py-1.5 rounded bg-[#202622] border border-[#46514C] hover:border-[#C5B89C] text-[#F2F2EC]"
           title="{{ $isId ? 'Notifikasi' : 'Notifications' }}">
          <i class="fa-regular fa-bell text-xs"></i>
          @if($unreadNotifs > 0)
            <span class="ml-1 px-1.5 py-0.2 rounded bg-[#C5B89C] text-[#17251F] font-mono font-bold text-[10px]">{{ $unreadNotifs }}</span>
          @endif
        </a>

        <!-- User Session Menu -->
        <div class="relative">
          <button type="button" data-dropdown-btn="user-dropdown"
                  class="flex items-center gap-2 px-2.5 py-1.5 rounded bg-[#202622] border border-[#46514C] hover:border-[#C5B89C] text-left">
            <div class="leading-tight hidden md:block">
              <div class="text-xs font-medium text-[#F2F2EC]">{{ $user->name }}</div>
              <div class="text-[10px] font-mono text-[#C5B89C] uppercase tracking-wider">
                {{ $user->trace_id ?? 'UNASSIGNED' }} &middot; {{ $user->roleLabel() }}
              </div>
            </div>
            <span class="md:hidden font-mono text-[10px] text-[#C5B89C]">{{ $user->trace_id }}</span>
            <i class="fa-solid fa-chevron-down text-[10px] text-[#8C9690]"></i>
          </button>

          <div id="user-dropdown" class="hidden absolute right-0 mt-1.5 w-64 bg-[var(--bg-surface)] text-[var(--text-primary)] border border-[var(--border-strong)] rounded shadow-lg p-2 z-50 text-xs">
            <div class="px-2 py-2 border-b border-[var(--border-color)] space-y-0.5">
              <div class="label-meta">{{ $isId ? 'Sesi Operasional' : 'Operational Session' }}</div>
              <div class="font-semibold">{{ $user->name }}</div>
              <div class="font-mono text-[11px] text-[var(--accent-olive)] dark:text-[#C5B89C]">{{ $user->trace_id }}</div>
              <div class="text-[11px] text-[var(--text-muted)]">
                {{ $user->roleLabel() }}
                @if($user->organization) &middot; {{ $user->organization->name }} @endif
                @if($user->unit) &middot; {{ $user->unit->name }} @endif
              </div>
            </div>

            <div class="py-1.5 space-y-1 border-b border-[var(--border-color)]">
              <a href="{{ route('notifications.index') }}" class="flex items-center justify-between px-2 py-1.5 rounded hover:bg-[var(--bg-subtle)]">
                <span><i class="fa-regular fa-bell w-4 text-center mr-1.5 text-[var(--text-muted)]"></i>{{ $isId ? 'Notifikasi' : 'Notifications' }}</span>
                @if($unreadNotifs > 0)
                  <span class="badge badge-olive">{{ $unreadNotifs }}</span>
                @endif
              </a>
              <a href="{{ route('password.change') }}" class="flex items-center px-2 py-1.5 rounded hover:bg-[var(--bg-subtle)]">
                <i class="fa-solid fa-key w-4 text-center mr-1.5 text-[var(--text-muted)]"></i>
                <span>{{ $isId ? 'Ganti Kata Sandi' : 'Change Password' }}</span>
              </a>
            </div>

            <div class="pt-1.5">
              <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full btn-danger justify-start py-1.5 px-2.5 text-[11px]">
                  <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center"></i>
                  <span>{{ $isId ? 'Keluar Sesi' : 'Sign Out' }}</span>
                </button>
              </form>
            </div>
          </div>
        </div>
      @endif
    </div>
  </header>

  <div class="flex-1 flex overflow-hidden relative">
    @if($user)
    <aside id="app-sidebar" class="hidden lg:flex w-64 bg-[#202622] text-[#F2F2EC] border-r border-[#36413C] flex-col justify-between shrink-0 overflow-y-auto z-20 fixed lg:static inset-y-0 left-0 top-14 lg:top-0">
      <div class="p-3 space-y-4">

        <!-- Workspace Overview -->
        <div>
          <div class="px-2.5 pb-1 text-[10px] font-mono uppercase tracking-widest text-[#8C9690]">
            {{ $isId ? 'RINGKASAN' : 'OVERVIEW' }}
          </div>
          <nav class="space-y-0.5 text-xs">
            @if($user->hasPermission('system.health'))
              <a href="{{ route('admin.dashboard') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('admin.dashboard') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-gauge w-4 text-center text-[#C5B89C]"></i>
                <span>{{ $isId ? 'Dashboard Administrator' : 'Administrator Dashboard' }}</span>
              </a>
            @else
              <a href="{{ route('operator.dashboard') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('operator.dashboard') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-gauge w-4 text-center text-[#C5B89C]"></i>
                <span>{{ $isId ? 'Workspace Operator' : 'Operator Workspace' }}</span>
              </a>
            @endif

            @if($user->hasPermission('history.view_own'))
              <a href="{{ route('operator.history') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('operator.history') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-clock-rotate-left w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Riwayat Aktivitas' : 'My Activity History' }}</span>
              </a>
            @endif
          </nav>
        </div>

        <!-- Information Governance -->
        @if($user->hasPermission('info.view') || $user->hasPermission('info.submit'))
        <div>
          <div class="px-2.5 pb-1 text-[10px] font-mono uppercase tracking-widest text-[#8C9690]">
            {{ $isId ? 'REKAMAN INFORMASI' : 'INFORMATION RECORDS' }}
          </div>
          <nav class="space-y-0.5 text-xs">
            @if($user->hasPermission('info.view'))
              <a href="{{ route('records.index') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('records.index', 'records.show', 'records.edit') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-folder-open w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Daftar Informasi' : 'Information Directory' }}</span>
              </a>
            @endif
            @if($user->hasPermission('info.submit'))
              <a href="{{ route('records.create') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('records.create') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-file-circle-plus w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Ajukan Informasi' : 'Submit Information' }}</span>
              </a>
            @endif
          </nav>
        </div>
        @endif

        <!-- Intelligence Modules -->
        <div>
          <div class="px-2.5 pb-1 text-[10px] font-mono uppercase tracking-widest text-[#8C9690]">
            {{ $isId ? 'OPERASI INTELIJEN' : 'INTELLIGENCE MODULES' }}
          </div>
          <nav class="space-y-0.5 text-xs">
            @if($user->hasPermission('dashboard.view'))
              <a href="{{ route('early-warning') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('early-warning') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-chart-line w-4 text-center text-[#8C9690]"></i>
                <span class="flex-1">{{ $isId ? 'Peringatan Dini' : 'Early Warning' }}</span>
              </a>
              <a href="{{ route('sentiment.index') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('sentiment.*') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-chart-area w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Analisis Sentimen' : 'Sentiment Analysis' }}</span>
              </a>
            @endif

            @if($user->hasPermission('analysis.run'))
              <a href="{{ route('scanner.index', ['tab' => 'scan']) }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('scanner.*') && request()->query('tab', 'scan') !== 'crosssource' ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-magnifying-glass w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Pemeriksa Kredibilitas' : 'Credibility Scanner' }}</span>
              </a>
              <a href="{{ route('scanner.index', ['tab' => 'crosssource']) }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('scanner.*') && request()->query('tab') === 'crosssource' ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-code-compare w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Verifikasi Lintas Sumber' : 'Cross-Source Workspace' }}</span>
              </a>
            @endif

            @if($user->hasPermission('spread.view'))
              <a href="{{ route('network.index') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('network.*') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-diagram-project w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Jaringan Penyebaran' : 'Spread Network' }}</span>
              </a>
            @endif

            @if($user->hasPermission('case.view'))
              <a href="{{ route('cases.index') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('cases.*') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-folder-closed w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Arsip Kasus' : 'Case Archive' }}</span>
              </a>
            @endif

            @if($user->hasPermission('report.view'))
              <a href="{{ route('reports.index') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('reports.*') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-regular fa-file-lines w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Intel Brief' : 'Intel Briefs' }}</span>
              </a>
            @endif

            @if($user->hasPermission('console.use'))
              <a href="{{ route('console.index') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('console.*') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-terminal w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Konsol Analis AI' : 'AI Analyst Console' }}</span>
              </a>
            @endif
          </nav>
        </div>

        <!-- Controlled Skills -->
        @if($user->hasPermission('skills.use'))
        <div>
          <div class="px-2.5 pb-1 text-[10px] font-mono uppercase tracking-widest text-[#8C9690]">
            {{ $isId ? 'MODUL SKILLS' : 'SKILLS WORKSPACE' }}
          </div>
          <nav class="space-y-0.5 text-xs">
            <a href="{{ route('skills.index') }}"
               class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('skills.*') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
              <i class="fa-solid fa-toolbox w-4 text-center text-[#8C9690]"></i>
              <span>{{ $isId ? 'Skills Tersedia' : 'Available Skills' }}</span>
            </a>
          </nav>
        </div>
        @endif

        <!-- Administration -->
        @if($user->hasPermission('accounts.manage') || $user->hasPermission('skills.configure') || $user->hasPermission('ai.manage') || $user->hasPermission('audit.view') || $user->hasPermission('system.health'))
        <div>
          <div class="px-2.5 pb-1 text-[10px] font-mono uppercase tracking-widest text-[#C5B89C]">
            {{ $isId ? 'ADMINISTRASI' : 'ADMINISTRATION' }}
          </div>
          <nav class="space-y-0.5 text-xs">
            @if($user->hasPermission('accounts.manage'))
              <a href="{{ route('admin.accounts.index') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('admin.accounts.*') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-users-gear w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Akun Trace-ID' : 'Trace-ID Accounts' }}</span>
              </a>
            @endif

            @if($user->hasPermission('skills.configure'))
              <a href="{{ route('admin.skills.index') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('admin.skills.*') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-sliders w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Manajemen Skills' : 'Skills Governance' }}</span>
              </a>
            @endif

            @if($user->hasPermission('ai.manage'))
              <a href="{{ route('admin.ai.index') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('admin.ai.*') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-microchip w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Server AI Lokal' : 'Local AI Server' }}</span>
              </a>
            @endif

            @if($user->hasPermission('system.health'))
              <a href="{{ route('admin.system.index') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('admin.system.*') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-heart-pulse w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Kesehatan & Pengaturan' : 'System & Settings' }}</span>
              </a>
            @endif

            @if($user->hasPermission('audit.view'))
              <a href="{{ route('admin.audit.index') }}"
                 class="flex items-center gap-2.5 px-2.5 py-2 rounded {{ request()->routeIs('admin.audit.*') ? 'bg-[#586B47] text-[#F2F2EC] font-medium' : 'text-[#F2F2EC]/85 hover:bg-[#17251F]' }}">
                <i class="fa-solid fa-shield-halved w-4 text-center text-[#8C9690]"></i>
                <span>{{ $isId ? 'Audit Log Permanen' : 'Immutable Audit Log' }}</span>
              </a>
            @endif
          </nav>
        </div>
        @endif
      </div>

      <div class="p-3 border-t border-[#36413C] bg-[#17251F]">
        <div class="p-2.5 rounded border border-[#46514C] bg-[#202622] space-y-1 font-mono">
          <div class="flex items-center justify-between text-[10px] uppercase tracking-wider text-[#C5B89C]">
            <span>{{ $user->roleLabel() }}</span>
            <span class="px-1.5 py-0.5 rounded bg-[#17251F] border border-[#46514C] text-[9px] text-[#586B47] font-bold">ACTIVE</span>
          </div>
          <div class="text-[11px] text-[#F2F2EC] truncate">{{ $user->trace_id ?? 'SYSTEM' }}</div>
          <div class="text-[10px] text-[#8C9690] font-sans truncate">
            {{ $user->organization?->name ?? 'TRACE-S Command' }}
          </div>
        </div>
      </div>
    </aside>
    @endif

    <main class="flex-1 overflow-y-auto bg-[var(--bg-canvas)]">
      <div class="max-w-7xl mx-auto p-4 lg:p-6 space-y-4">
        @include('partials.flash')
        @yield('content')
      </div>
    </main>
  </div>

  @stack('scripts')
</body>
</html>
