<!DOCTYPE html>
<html lang="id" class="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TRACE-S // Otentikasi Akses Personel</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-neutral-950 text-neutral-300 min-h-screen flex items-center justify-center p-4 font-sans select-none antialiased">

  <div class="w-full max-w-md space-y-4">

    <!-- Header Box -->
    <div class="text-center space-y-2">
      <div class="inline-flex w-12 h-12 rounded-sm bg-cyan-950/60 border border-cyan-500/50 items-center justify-center text-cyan-400 shadow-[0_0_15px_rgba(6,182,212,0.3)] mb-1">
        <i class="fa-solid fa-shield-halved text-xl"></i>
      </div>
      <div>
        <h1 class="text-lg font-bold font-mono tracking-widest text-neutral-100 uppercase">TRACE-S</h1>
        <p class="text-[11px] font-mono tracking-wider text-cyan-400 uppercase">Social Cyber Intelligence Platform</p>
        <p class="text-[9px] font-mono tracking-widest text-neutral-500 uppercase mt-0.5">PUSSIBERAD TNI AD // KATEGORI 04 SIBER SOSIAL</p>
      </div>
    </div>

    <!-- Login Card -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-6 shadow-2xl space-y-4">
      <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
        <div class="text-xs font-mono uppercase tracking-wider text-neutral-400 flex items-center gap-2">
          <i class="fa-solid fa-terminal text-cyan-400"></i>
          <span>Otentikasi Kredensial</span>
        </div>
        <span class="text-[9px] font-mono px-1.5 py-0.5 border border-cyan-500/40 text-cyan-400 bg-cyan-950/30 uppercase">TERBATAS</span>
      </div>

      @if(session('warning'))
      <div class="px-3 py-2 bg-amber-950/40 border border-amber-500/40 text-amber-400 text-xs font-mono rounded-sm">
        {{ session('warning') }}
      </div>
      @endif

      @if($errors->any())
      <div class="px-3 py-2 bg-rose-950/40 border border-rose-600/50 text-rose-400 text-xs font-mono rounded-sm">
        {{ $errors->first() }}
      </div>
      @endif

      <form action="{{ route('login.post') }}" method="POST" class="space-y-4">
        @csrf
        <div class="space-y-1">
          <label class="text-[10px] font-mono uppercase tracking-wider text-neutral-400">Alamat Email Dinas</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-neutral-500">
              <i class="fa-solid fa-id-badge text-xs"></i>
            </span>
            <input type="email" name="email" value="{{ old('email', 'operator@traces.mil.id') }}" required autofocus
                   class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 pl-9 pr-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500 placeholder-neutral-600">
          </div>
        </div>

        <div class="space-y-1">
          <label class="text-[10px] font-mono uppercase tracking-wider text-neutral-400">Kata Sandi</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-neutral-500">
              <i class="fa-solid fa-lock text-xs"></i>
            </span>
            <input type="password" name="password" value="operator123" required
                   class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 pl-9 pr-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500 placeholder-neutral-600">
          </div>
        </div>

        <button type="submit" class="w-full py-2.5 bg-cyan-600/20 text-cyan-400 border border-cyan-500/50 hover:bg-cyan-600/40 hover:text-cyan-300 font-mono text-xs uppercase tracking-wider transition-all rounded-sm flex items-center justify-center gap-2">
          <i class="fa-solid fa-key"></i>
          <span>Masuk Sistem Command Center</span>
        </button>
      </form>

      <!-- Quick Switch Section (Hackathon Demo Convenience) -->
      <div class="border-t border-neutral-800 pt-4 space-y-2">
        <div class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 flex items-center justify-between">
          <span>Akses Cepat Pengujian (Evaluasi Juri):</span>
          <span class="text-[9px] text-amber-500">DEMO MODE</span>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <form action="{{ route('login.quick') }}" method="POST">
            @csrf
            <input type="hidden" name="role" value="operator">
            <button type="submit" class="w-full text-left p-2 bg-neutral-950 hover:bg-neutral-800 border border-neutral-800 rounded-sm font-mono text-[10px] text-neutral-300 transition-colors">
              <div class="text-cyan-400 font-bold uppercase"><i class="fa-solid fa-desktop mr-1"></i> Operator</div>
              <div class="text-neutral-500 text-[9px] truncate">Serda Rama Danu</div>
            </button>
          </form>

          <form action="{{ route('login.quick') }}" method="POST">
            @csrf
            <input type="hidden" name="role" value="analis">
            <button type="submit" class="w-full text-left p-2 bg-neutral-950 hover:bg-neutral-800 border border-neutral-800 rounded-sm font-mono text-[10px] text-neutral-300 transition-colors">
              <div class="text-emerald-400 font-bold uppercase"><i class="fa-solid fa-magnifying-glass-chart mr-1"></i> Analis</div>
              <div class="text-neutral-500 text-[9px] truncate">Lettu Chb Aris M.</div>
            </button>
          </form>

          <form action="{{ route('login.quick') }}" method="POST">
            @csrf
            <input type="hidden" name="role" value="komandan">
            <button type="submit" class="w-full text-left p-2 bg-neutral-950 hover:bg-neutral-800 border border-neutral-800 rounded-sm font-mono text-[10px] text-neutral-300 transition-colors">
              <div class="text-amber-400 font-bold uppercase"><i class="fa-solid fa-star mr-1"></i> Komandan</div>
              <div class="text-neutral-500 text-[9px] truncate">Kolonel Inf Hendra</div>
            </button>
          </form>

          <form action="{{ route('login.quick') }}" method="POST">
            @csrf
            <input type="hidden" name="role" value="admin">
            <button type="submit" class="w-full text-left p-2 bg-neutral-950 hover:bg-neutral-800 border border-neutral-800 rounded-sm font-mono text-[10px] text-neutral-300 transition-colors">
              <div class="text-rose-400 font-bold uppercase"><i class="fa-solid fa-key mr-1"></i> Administrator</div>
              <div class="text-neutral-500 text-[9px] truncate">Mayor Chb Dimas</div>
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Security Warning Footer -->
    <div class="text-center text-[10px] font-mono text-neutral-600 leading-relaxed px-2">
      PERINGATAN: Sistem tertutup PUSSIBERAD TNI AD. Segala aktivitas pengumpulan dan analisis data tercatat dalam audit log yang tidak dapat diubah (immutable). Akses tanpa wewenang tunduk pada UU ITE dan regulasi pertahanan siber.
    </div>

  </div>

</body>
</html>
