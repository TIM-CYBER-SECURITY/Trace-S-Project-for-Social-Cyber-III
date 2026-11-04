@extends('layouts.tactical')

@section('title', 'Threat Actor Network & Spread Analysis // TRACE-S')

@section('content')

<!-- Header Bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div>
    <div class="flex items-center gap-2">
      <h1 class="text-lg font-bold font-mono tracking-wide text-neutral-100 uppercase">Threat Actor Network &amp; Spread Analysis</h1>
      <span class="text-[10px] font-mono px-2 py-0.5 border border-amber-500/40 bg-amber-950/30 text-amber-500 rounded-sm">MODUL C</span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">Pemetaan jejaring aktor penyebar, indikator otomatisasi, dan kronologi amplifikasi lintas platform.</p>
  </div>

  <!-- Case Filter -->
  <form action="{{ route('network.index') }}" method="GET" class="flex items-center gap-2 text-xs font-mono">
    <label class="text-[10px] uppercase text-neutral-400">Kasus:</label>
    <select name="case_id" onchange="this.form.submit()" class="bg-neutral-900 border border-neutral-700 text-neutral-200 px-3 py-1.5 rounded-sm focus:outline-none focus:border-cyan-500 text-xs font-mono">
      @foreach($cases as $c)
        <option value="{{ $c->id }}" {{ $selectedCase && $selectedCase->id === $c->id ? 'selected' : '' }}>
          {{ $c->case_code }} - {{ Str::limit($c->title, 35) }}
        </option>
      @endforeach
    </select>
  </form>
</div>

<!-- START: DATA SCOPE DISCLAIMER BANNER (Master §7.4 / Supplemental §8) -->
<div class="p-3 bg-neutral-900 border border-neutral-800 rounded-sm flex items-center justify-between text-xs font-mono text-neutral-400">
  <div class="flex items-center gap-2">
    <i class="fa-solid fa-clock-rotate-left text-cyan-400"></i>
    <span>Titik Publikasi Terawal: <b class="text-neutral-200">{{ $earliestPost?->publisher ?? 'N/A' }}</b> ({{ $earliestPost?->published_at ? $earliestPost->published_at->format('d M Y H:i') . ' WIB' : '-' }} pada platform {{ $earliestPost?->platform ?? '-' }})</span>
  </div>
  <div class="text-[10px] text-amber-500 font-semibold px-2 py-0.5 border border-amber-500/30 bg-amber-950/20 rounded-sm">
    * Paling awal dalam dataset terkumpul, bukan publikasi pertama di internet
  </div>
</div>
<!-- END: DATA SCOPE DISCLAIMER BANNER -->

<!-- START: SNA GRAPH & SPREAD METRICS -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

  <!-- Left Column: Tactical Dot-Grid SNA Canvas (7 Cols) -->
  <div class="lg:col-span-7 bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
      <div class="text-xs font-mono uppercase tracking-wider text-neutral-300 font-semibold flex items-center gap-2">
        <i class="fa-solid fa-diagram-project text-cyan-400"></i>
        <span>Social Network Analysis (SNA) Graph</span>
      </div>
      <span class="text-[9px] font-mono text-cyan-400">CLUSTER: NC-2026-001</span>
    </div>

    <!-- Dot-Grid Canvas with Connected HTML/SVG Nodes -->
    <div class="relative w-full h-80 sm:h-96 bg-neutral-950 border border-neutral-850 rounded-sm overflow-hidden select-none">
      <!-- Dot grid texture -->
      <div class="absolute inset-0 bg-[radial-gradient(#262626_1px,transparent_1px)] [background-size:16px_16px] opacity-40"></div>

      <!-- Center Node (Patient Zero / Node-A1) -->
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-20 flex flex-col items-center">
        <div class="relative flex items-center justify-center">
          <span class="animate-ping absolute inline-flex h-14 w-14 rounded-full bg-rose-600 opacity-40"></span>
          <div class="w-12 h-12 rounded-full bg-rose-950 border-2 border-rose-500 flex items-center justify-center text-rose-300 font-mono font-bold text-xs shadow-[0_0_20px_rgba(225,29,72,0.6)]">
            A1
          </div>
        </div>
        <div class="mt-1 px-2 py-0.5 bg-neutral-900 border border-rose-600/50 rounded-sm text-[9px] font-mono text-rose-400 font-semibold text-center whitespace-nowrap shadow-lg">
          Node-A1 (Titik Awal Dataset)<br>
          <span class="text-neutral-400 font-normal">@meme_nusantara_fiktif</span>
        </div>
      </div>

      <!-- Orbiting Satellite Nodes -->
      @php
        $satelliteCoords = [
          ['x' => 20, 'y' => 22, 'label' => 'A2', 'user' => '@politik_santuy', 'ind' => 'SEDANG'],
          ['x' => 78, 'y' => 20, 'label' => 'A3', 'user' => '@info_warga', 'ind' => 'TINGGI'],
          ['x' => 84, 'y' => 60, 'label' => 'A4', 'user' => '@berita_viral', 'ind' => 'RENDAH'],
          ['x' => 70, 'y' => 82, 'label' => 'A5', 'user' => '@suara_rakyat', 'ind' => 'RENDAH'],
          ['x' => 30, 'y' => 84, 'label' => 'A6', 'user' => '@opini_terkini', 'ind' => 'TINGGI'],
          ['x' => 14, 'y' => 58, 'label' => 'A7', 'user' => '@wacana_kita', 'ind' => 'RENDAH'],
        ];
      @endphp

      <!-- SVG Connection Lines -->
      <svg class="absolute inset-0 w-full h-full pointer-events-none z-10">
        @foreach($satelliteCoords as $sc)
        <line x1="50%" y1="50%" x2="{{ $sc['x'] }}%" y2="{{ $sc['y'] }}%"
              stroke="{{ $sc['ind'] === 'TINGGI' ? '#f43f5e' : ($sc['ind'] === 'SEDANG' ? '#fbbf24' : '#06b6d4') }}"
              stroke-width="1.5" stroke-dasharray="3 3" opacity="0.6"/>
        @endforeach
      </svg>

      @foreach($satelliteCoords as $sc)
      <div class="absolute -translate-x-1/2 -translate-y-1/2 z-20 flex flex-col items-center group cursor-pointer"
           style="left: {{ $sc['x'] }}%; top: {{ $sc['y'] }}%;">
        <div class="w-8 h-8 rounded-full bg-neutral-900 border flex items-center justify-center font-mono text-[10px] font-bold transition-transform group-hover:scale-110 {{ $sc['ind'] === 'TINGGI' ? 'border-rose-500 text-rose-400' : ($sc['ind'] === 'SEDANG' ? 'border-amber-400 text-amber-400' : 'border-cyan-500 text-cyan-400') }}">
          {{ $sc['label'] }}
        </div>
        <div class="mt-1 px-1.5 py-0.2 bg-neutral-900/90 border border-neutral-800 rounded-sm text-[8px] font-mono text-neutral-400 whitespace-nowrap">
          {{ $sc['user'] }}
        </div>
      </div>
      @endforeach

      <div class="absolute bottom-2 left-2 text-[9px] font-mono text-neutral-600">
        ALGORITMA: FORCE-DIRECTED SIMULATION (TOLERANSI WAKTU 18j)
      </div>
    </div>

    <!-- Canvas Legend -->
    <div class="flex items-center justify-between text-[10px] font-mono text-neutral-500 pt-1">
      <div class="flex items-center gap-3">
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-600 inline-block"></span> Indikator Otomatisasi Tinggi</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span> Sedang</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-cyan-500 inline-block"></span> Rendah</span>
      </div>
      <span class="text-amber-500">SIMULASI SNA</span>
    </div>
  </div>

  <!-- Right Column: Platform Breakdown & Metrics (5 Cols) -->
  <div class="lg:col-span-5 space-y-4">

    <!-- Platform Distribution Card -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
      <div class="text-xs font-mono uppercase tracking-wider text-cyan-400 font-semibold flex items-center justify-between border-b border-neutral-800 pb-2">
        <span>Distribusi Platform dalam Dataset</span>
        <i class="fa-solid fa-share-nodes"></i>
      </div>

      <div class="space-y-2 font-mono text-xs">
        @foreach($platformDistribution as $plat => $cnt)
        @php
          $pct = $posts->count() > 0 ? round(($cnt / $posts->count()) * 100) : 0;
        @endphp
        <div class="space-y-1">
          <div class="flex items-center justify-between text-[11px]">
            <span class="text-neutral-300 font-medium">{{ $plat }}</span>
            <span class="text-cyan-400">{{ $cnt }} Unggahan ({{ $pct }}%)</span>
          </div>
          <div class="w-full h-1.5 bg-neutral-950 rounded-xs overflow-hidden">
            <div class="h-full bg-cyan-500" style="width: {{ $pct }}%;"></div>
          </div>
        </div>
        @endforeach
      </div>

      <div class="text-[10px] font-mono text-neutral-500 pt-1 border-t border-neutral-800">
        Engagement terakumulasi: <b>8.000.000+ views</b>, <b>621.000+ likes</b> (berdasarkan dataset yang terkumpul).
      </div>
    </div>

    <!-- Cluster Overview Card -->
    @if($clusters->isNotEmpty())
    @php $cl = $clusters->first(); @endphp
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-2">
      <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
        <span class="text-xs font-mono uppercase tracking-wider text-neutral-200 font-semibold">Klaster Narasi [{{ $cl->cluster_code }}]</span>
        <span class="text-[9px] font-mono px-1.5 py-0.2 border border-amber-500/40 text-amber-500 bg-amber-950/20">
          {{ $cl->coordination_assessment }}
        </span>
      </div>

      <div class="text-xs font-sans text-neutral-300 font-medium leading-relaxed">
        "{{ $cl->main_claim }}"
      </div>

      <div class="text-[10px] font-mono text-neutral-500 space-y-1 pt-2 border-t border-neutral-850">
        <div>Metode: <b class="text-neutral-400">{{ $cl->method }}</b></div>
        <div>Jumlah Anggota Klaster: <b class="text-cyan-400">{{ $cl->items->count() }} Variasi Redaksi</b></div>
        <div class="text-neutral-500 italic mt-1">
          * Kesamaan pola waktu atau redaksi adalah indikator tinjauan, bukan bukti koordinasi terorganisir maupun niat jahat.
        </div>
      </div>
    </div>
    @endif

  </div>

</div>
<!-- END: SNA GRAPH & SPREAD METRICS -->

<!-- START: THREAT ACTOR ROSTER (TABLE) -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-neutral-800 pb-3">
    <div>
      <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-neutral-200 flex items-center gap-2">
        <i class="fa-solid fa-users-viewfinder text-cyan-400"></i>
        <span>Roster Akun Terpantau &amp; Automation Indicator</span>
      </h2>
      <p class="text-[11px] text-neutral-500 mt-0.5">Daftar akun yang terpantau menyebarkan variasi narasi kasus ini beserta indikator otomatisasi perilaku.</p>
    </div>
    <div class="text-[10px] font-mono text-neutral-400">
      Metrik: <b class="text-cyan-400">Automation Indicator</b> (Bukan Bot Probability)
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-wider text-neutral-500 bg-neutral-950/40">
          <th class="py-2 px-3">Identitas Akun</th>
          <th class="py-2 px-3">Platform</th>
          <th class="py-2 px-3">Laju Unggahan</th>
          <th class="py-2 px-3">Umur Akun</th>
          <th class="py-2 px-3">Automation Indicator</th>
          <th class="py-2 px-3">Dasar Penilaian Indikator</th>
          <th class="py-2 px-3 text-right">Status Peninjauan</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800/60">
        @forelse($actors as $act)
        @php
          $isHigh = $act->automation_indicator === 'TINGGI';
        @endphp
        <tr class="{{ $isHigh ? 'bg-rose-950/20 hover:bg-rose-950/30' : 'hover:bg-neutral-850/50' }} transition-colors">
          <td class="py-2.5 px-3 font-semibold {{ $act->is_earliest_in_dataset ? 'text-rose-400' : 'text-neutral-200' }}">
            {{ $act->username }}
            @if($act->is_earliest_in_dataset)
              <span class="block text-[8px] uppercase tracking-widest text-rose-500">Node-A1 (Earliest in Dataset)</span>
            @endif
          </td>
          <td class="py-2.5 px-3 text-[11px] text-neutral-400 uppercase">{{ $act->platform }}</td>
          <td class="py-2.5 px-3 font-bold {{ $act->post_rate >= 30 ? 'text-rose-400' : 'text-neutral-300' }}">
            {{ $act->post_rate ? $act->post_rate . '/jam' : 'N/A' }}
          </td>
          <td class="py-2.5 px-3 text-neutral-300">
            {{ $act->account_age_days ? $act->account_age_days . ' hari' : 'N/A' }}
          </td>
          <td class="py-2.5 px-3">
            @if($act->automation_indicator === 'TINGGI')
              <span class="px-2 py-0.5 bg-rose-950/60 border border-rose-600/50 text-rose-400 text-[9px] font-bold uppercase">TINGGI</span>
            @elseif($act->automation_indicator === 'SEDANG')
              <span class="px-2 py-0.5 bg-amber-950/60 border border-amber-500/50 text-amber-400 text-[9px] font-bold uppercase">SEDANG</span>
            @else
              <span class="px-2 py-0.5 bg-cyan-950/60 border border-cyan-500/50 text-cyan-400 text-[9px] uppercase">{{ $act->automation_indicator }}</span>
            @endif
          </td>
          <td class="py-2.5 px-3 font-sans text-xs text-neutral-300 max-w-sm">
            {{ $act->indicator_basis ?? 'Tidak ada data anomali.' }}
          </td>
          <td class="py-2.5 px-3 text-right">
            <span class="px-2 py-0.5 bg-neutral-800 border border-neutral-700 text-neutral-400 text-[9px] uppercase">
              {{ $act->status }}
            </span>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="py-6 text-center text-neutral-500">Belum ada akun terdaftar dalam dataset penyebaran kasus ini.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<!-- END: THREAT ACTOR ROSTER -->

@endsection
