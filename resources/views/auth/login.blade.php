<!DOCTYPE html>
@php
  $isId = app()->getLocale() === 'id';
@endphp
<html lang="{{ $isId ? 'id' : 'en' }}" class="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TRACE-S // {{ $isId ? 'Otentikasi Akses' : 'Access Authentication' }}</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-neutral-950 text-neutral-300 min-h-screen flex items-center justify-center p-4 font-sans select-none antialiased">

  <!-- [LoginScreenContainer] START -->
  <div class="w-full max-w-md space-y-4">

    <!-- Language Toggle Top Right -->
    <div class="flex justify-end">
      <div class="flex items-center bg-neutral-900 border border-neutral-800 rounded-sm p-0.5 font-mono text-[10px]" role="group" aria-label="Language Switcher">
        <a href="{{ route('lang.switch', 'en') }}" class="px-2 py-1 rounded-sm {{ !$isId ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-semibold' : 'text-neutral-500 hover:text-neutral-300' }}">EN</a>
        <a href="{{ route('lang.switch', 'id') }}" class="px-2 py-1 rounded-sm {{ $isId ? 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 font-semibold' : 'text-neutral-500 hover:text-neutral-300' }}">ID</a>
      </div>
    </div>

    <!-- Brand Header -->
    <div class="text-center space-y-2">
      <div class="inline-flex w-11 h-11 rounded-sm text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 items-center justify-center mb-1">
        <i class="fa-solid fa-shield-halved text-lg"></i>
      </div>
      <div>
        <h1 class="text-lg font-semibold font-mono tracking-widest text-neutral-300 uppercase">TRACE-S</h1>
        <p class="text-[10px] font-mono tracking-widest text-cyan-500 uppercase">Social Cyber Intelligence Platform</p>
        <p class="text-[10px] font-mono tracking-widest text-neutral-500 uppercase mt-0.5">PUSSIBERAD TNI AD &middot; CATEGORY 04</p>
      </div>
    </div>

    <!-- Login Panel -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-5 space-y-4">
      <div class="flex items-center justify-between border-b border-neutral-800 pb-2.5">
        <div class="text-xs font-mono uppercase tracking-wide text-neutral-300 font-semibold flex items-center gap-2">
          <i class="fa-solid fa-terminal text-cyan-500"></i>
          <span>{{ $isId ? 'Masuk ke Ruang Analisis' : 'Sign In to Analysis Console' }}</span>
        </div>
        <span class="text-[10px] font-mono px-1.5 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 uppercase rounded-sm">
          RESTRICTED
        </span>
      </div>

      @if(session('warning'))
      <div class="px-3 py-2 text-amber-500 bg-amber-500/10 border border-amber-500/30 text-xs font-mono rounded-sm">
        {{ session('warning') }}
      </div>
      @endif

      @if($errors->any())
      <div class="px-3 py-2 text-rose-600 bg-rose-500/10 border border-rose-600/50 text-xs font-mono rounded-sm">
        {{ $errors->first() }}
      </div>
      @endif

      <form action="{{ route('login.post') }}" method="POST" class="space-y-3.5">
        @csrf
        <div class="space-y-1">
          <label class="text-[10px] font-mono uppercase tracking-widest text-neutral-500">
            {{ $isId ? 'EMAIL DINAS' : 'OFFICIAL EMAIL' }}
          </label>
          <input type="email" name="email" value="{{ old('email', 'operator@traces.mil.id') }}" required autofocus
                 class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
        </div>

        <div class="space-y-1">
          <label class="text-[10px] font-mono uppercase tracking-widest text-neutral-500">
            {{ $isId ? 'KATA SANDI' : 'PASSWORD' }}
          </label>
          <input type="password" name="password" value="operator123" required
                 class="w-full bg-neutral-950 border border-neutral-800 text-neutral-300 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
        </div>

        <button type="submit" aria-label="Sign in" class="w-full py-2.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 hover:bg-neutral-800/50 font-mono text-xs uppercase tracking-wider transition-opacity duration-150 rounded-sm flex items-center justify-center gap-2 font-semibold">
          <i class="fa-solid fa-key text-xs"></i>
          <span>{{ $isId ? 'Masuk Sistem' : 'Sign In' }}</span>
        </button>
      </form>

      <!-- Quick Role Switch for Jury Evaluation (Dynamic from $demoUsers, never hardcoded static names) -->
      <div class="border-t border-neutral-800 pt-3.5 space-y-2">
        <div class="text-[10px] font-mono uppercase tracking-widest text-neutral-500 flex items-center justify-between">
          <span>{{ $isId ? 'Akses Cepat Evaluasi Juri:' : 'Quick Role Access (Jury Evaluation):' }}</span>
          <span class="text-amber-500">SIMULATION</span>
        </div>

        <div class="grid grid-cols-2 gap-2">
          @foreach($demoUsers as $du)
          <form action="{{ route('login.quick') }}" method="POST">
            @csrf
            <input type="hidden" name="role" value="{{ $du->role }}">
            <button type="submit"
                    aria-label="Quick login as {{ $du->role }}"
                    class="w-full text-left p-2 bg-neutral-950 hover:bg-neutral-800/50 border border-neutral-800 rounded-sm font-mono text-[10px] text-neutral-300 transition-opacity duration-150">
              <div class="text-cyan-500 font-semibold uppercase">{{ strtoupper($du->role) }} ({{ $du->clearance_level ?? 'L1' }})</div>
              <div class="text-neutral-500 text-[10px] truncate">{{ $du->name }}</div>
            </button>
          </form>
          @endforeach
        </div>
      </div>
    </div>

    <div class="text-center text-[10px] font-mono text-neutral-500 leading-relaxed px-2">
      {{ $isId
        ? 'Sistem analisis tertutup. Seluruh aktivitas tercatat pada log audit permanen (append-only).'
        : 'Closed intelligence analysis system. All actions are recorded in the immutable append-only audit log.' }}
    </div>

  </div>
  <!-- [LoginScreenContainer] END -->

</body>
</html>
