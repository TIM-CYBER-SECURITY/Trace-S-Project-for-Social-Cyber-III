@extends('layouts.tactical')

@section('title', 'Early Warning Dashboard // TRACE-S')

@section('content')

<!-- Header Bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-4">
  <div>
    <div class="flex items-center gap-2">
      <h1 class="text-lg font-bold font-mono tracking-wide text-neutral-100 uppercase">Early Warning &amp; Situational Awareness</h1>
      <span class="text-[10px] font-mono px-2 py-0.5 border border-cyan-500/40 bg-cyan-950/30 text-cyan-400 rounded-sm">MODUL A</span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">Pemantauan sentimen dan eskalasi narasi disinformasi berbasis dataset konektor terhubung.</p>
  </div>

  <div class="flex items-center gap-2">
    <a href="{{ route('cases.index') }}" class="px-3 py-1.5 bg-neutral-900 border border-neutral-700 hover:border-cyan-500 text-neutral-300 hover:text-cyan-400 text-xs font-mono uppercase tracking-wider rounded-sm transition-all flex items-center gap-2">
      <i class="fa-solid fa-folder-open"></i>
      <span>Daftar Kasus Aktif</span>
    </a>
    <a href="{{ route('scanner.index') }}" class="px-3 py-1.5 bg-cyan-600/20 text-cyan-400 border border-cyan-500/50 hover:bg-cyan-600/40 text-xs font-mono uppercase tracking-wider rounded-sm transition-all flex items-center gap-2 shadow-[0_0_10px_rgba(6,182,212,0.15)]">
      <i class="fa-solid fa-bolt"></i>
      <span>Pindai Konten Baru</span>
    </a>
  </div>
</div>

<!-- START: 4 METRIC CARDS -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">

  <!-- Metric 1: Narasi Dianalisis -->
  <div class="bg-neutral-900 border border-neutral-800 p-4 rounded-sm space-y-2 relative overflow-hidden">
    <div class="flex items-center justify-between text-neutral-500 text-[10px] font-mono uppercase tracking-widest">
      <span>Narasi Dianalisis (24j)</span>
      <i class="fa-solid fa-chart-simple text-cyan-400"></i>
    </div>
    <div class="flex items-baseline justify-between">
      <div class="text-2xl font-bold font-mono text-neutral-100 tracking-tight">{{ $narrativesCount }}</div>
      <span class="text-[10px] font-mono text-emerald-400"><i class="fa-solid fa-arrow-trend-up"></i> +14.2%</span>
    </div>
    <div class="text-[10px] text-neutral-500 font-mono truncate">Meliputi portal berita &amp; media sosial</div>
    <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-cyan-500/50"></div>
  </div>

  <!-- Metric 2: Hoaks Kritis Terdeteksi -->
  <div class="bg-neutral-900 border border-rose-900/40 p-4 rounded-sm space-y-2 relative overflow-hidden shadow-[0_0_15px_rgba(225,29,72,0.1)]">
    <div class="flex items-center justify-between text-rose-400 text-[10px] font-mono uppercase tracking-widest">
      <span>Hoaks Kritis Terdeteksi</span>
      <i class="fa-solid fa-triangle-exclamation text-rose-500 animate-pulse"></i>
    </div>
    <div class="flex items-baseline justify-between">
      <div class="text-2xl font-bold font-mono text-rose-500 tracking-tight">{{ $criticalCount }}</div>
      <span class="text-[10px] font-mono px-1.5 py-0.2 bg-rose-950/60 border border-rose-600/40 text-rose-400 uppercase">ESKALASI TINGGI</span>
    </div>
    <div class="text-[10px] text-neutral-500 font-mono truncate">Disinformasi strategis prioritas komando</div>
    <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-rose-600"></div>
  </div>

  <!-- Metric 3: Sentimen Publik -->
  <div class="bg-neutral-900 border border-neutral-800 p-4 rounded-sm space-y-2 relative overflow-hidden">
    <div class="flex items-center justify-between text-neutral-500 text-[10px] font-mono uppercase tracking-widest">
      <span>Sentimen Publik (Negatif)</span>
      <i class="fa-solid fa-wave-square text-amber-400"></i>
    </div>
    <div class="flex items-baseline justify-between">
      <div class="text-2xl font-bold font-mono text-amber-400 tracking-tight">{{ $sentimentRatio }}%</div>
      <span class="text-[10px] font-mono text-rose-400"><i class="fa-solid fa-arrow-up"></i> +4.5%</span>
    </div>
    <div class="text-[10px] text-neutral-500 font-mono truncate">Anomali sentimen penolakan kebijakan</div>
    <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-amber-500/50"></div>
  </div>

  <!-- Metric 4: Klaster Narasi Aktif -->
  <div class="bg-neutral-900 border border-neutral-800 p-4 rounded-sm space-y-2 relative overflow-hidden">
    <div class="flex items-center justify-between text-neutral-500 text-[10px] font-mono uppercase tracking-widest">
      <span>Klaster Narasi Aktif</span>
      <i class="fa-solid fa-cubes-stacked text-cyan-400"></i>
    </div>
    <div class="flex items-baseline justify-between">
      <div class="text-2xl font-bold font-mono text-neutral-100 tracking-tight">{{ $activeClustersCount }}</div>
      <span class="text-[10px] font-mono text-cyan-400">{{ $casesCount }} Kasus Aktif</span>
    </div>
    <div class="text-[10px] text-neutral-500 font-mono truncate">Dikelompokkan secara semantik</div>
    <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-cyan-500/50"></div>
  </div>

</div>
<!-- END: 4 METRIC CARDS -->

<!-- START: SITUATIONAL MAP & LIVE FEED GRID -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

  <!-- Left Canvas: Vector Threat Radar Map Indonesia (8 Cols) -->
  <div class="lg:col-span-8 bg-neutral-900 border border-neutral-800 rounded-sm p-4 flex flex-col justify-between space-y-3">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
      <div class="flex items-center gap-2">
        <i class="fa-solid fa-map-location-dot text-cyan-400 text-xs"></i>
        <span class="text-xs font-mono uppercase tracking-wider text-neutral-300 font-semibold">Peta Sebaran Ancaman Kognitif Wilayah NKRI</span>
      </div>
      <div class="flex items-center gap-3 text-[10px] font-mono">
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-600"></span> Kritis</span>
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Tinggi</span>
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-cyan-400"></span> Sedang/Rendah</span>
      </div>
    </div>

    <!-- SVG Tactical Map Canvas -->
    <div class="relative w-full h-72 sm:h-80 bg-neutral-950 border border-neutral-850 rounded-sm flex items-center justify-center overflow-hidden p-2">
      <!-- Grid Background Lines -->
      <div class="absolute inset-0 bg-[linear-gradient(to_right,#1f1f1f_1px,transparent_1px),linear-gradient(to_bottom,#1f1f1f_1px,transparent_1px)] bg-[size:2rem_2rem] opacity-25"></div>

      <!-- Stylized Indonesia Archipelago SVG Silhouette -->
      <svg class="w-full h-full text-neutral-800/80 fill-current opacity-40" viewBox="0 0 1000 450" preserveAspectRatio="xMidYMid meet">
        <!-- Sumatera -->
        <path d="M120,60 L240,240 L180,290 L90,140 Z"/>
        <!-- Jawa -->
        <path d="M220,310 L480,315 L470,335 L210,330 Z"/>
        <!-- Kalimantan -->
        <path d="M370,110 L520,120 L510,250 L380,240 Z"/>
        <!-- Sulawesi -->
        <path d="M560,140 L640,160 L620,280 L580,260 L570,180 Z"/>
        <!-- Maluku & Papua -->
        <path d="M720,160 L780,180 L760,250 Z"/>
        <path d="M790,190 L960,200 L950,300 L810,280 Z"/>
      </svg>

      <!-- Pulsing Tactical Nodes -->
      @foreach($threatPoints as $pt)
      <div class="absolute group cursor-pointer" style="left: {{ $pt['x'] }}%; top: {{ $pt['y'] }}%;">
        <div class="relative flex items-center justify-center -translate-x-1/2 -translate-y-1/2">
          @if($pt['severity'] === 'KRITIS')
            <span class="animate-ping absolute inline-flex h-6 w-6 rounded-full bg-rose-500 opacity-60"></span>
            <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-rose-600 border border-rose-300 shadow-[0_0_10px_rgba(225,29,72,0.8)]"></span>
          @elseif($pt['severity'] === 'TINGGI')
            <span class="animate-pulse absolute inline-flex h-5 w-5 rounded-full bg-amber-500 opacity-50"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500 border border-amber-200"></span>
          @else
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-cyan-500 border border-cyan-300"></span>
          @endif
        </div>
        <!-- Tooltip Label -->
        <div class="absolute left-1/2 -translate-x-1/2 -top-7 hidden group-hover:flex px-2 py-0.5 bg-neutral-900 border border-neutral-700 text-[10px] font-mono text-neutral-200 rounded-sm whitespace-nowrap z-20 shadow-lg">
          {{ $pt['name'] }}: {{ $pt['count'] }} Kasus ({{ $pt['severity'] }})
        </div>
      </div>
      @endforeach

      <!-- Tactical Corner Markers -->
      <div class="absolute top-2 left-2 text-[9px] font-mono text-neutral-600">SECTOR: INDO-PACOM-04</div>
      <div class="absolute bottom-2 right-2 text-[9px] font-mono text-neutral-600">GRID: WGS-84 ACTIVE</div>
    </div>

    <!-- Data Scope Banner (Master Prompt §7.2 / Supplemental §4) -->
    <div class="px-3 py-2 bg-neutral-950/80 border border-neutral-800 rounded-sm flex items-center justify-between text-[11px] font-mono text-neutral-400">
      <div class="flex items-center gap-2">
        <i class="fa-solid fa-circle-info text-cyan-400"></i>
        <span>Cakupan Pemantauan: <b>{{ $connectedSourcesCount }} sumber digital</b> yang berhasil terhubung melalui konektor aktif.</span>
      </div>
      <span class="text-[9px] text-amber-500 uppercase px-1.5 py-0.5 border border-amber-500/40 bg-amber-950/20">Batas Lingkup Dataset</span>
    </div>
  </div>

  <!-- Right Terminal: Live Threat Feed with Polling (4 Cols) -->
  <div class="lg:col-span-4 bg-neutral-900 border border-neutral-800 rounded-sm p-4 flex flex-col justify-between space-y-3">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
        <span class="text-xs font-mono uppercase tracking-wider text-neutral-300 font-semibold">Live Threat Feed</span>
      </div>
      <div class="flex items-center gap-1.5">
        <button id="toggle-feed-btn" class="text-[10px] font-mono text-cyan-400 hover:text-cyan-300 px-1.5 py-0.5 border border-cyan-500/30 bg-cyan-950/30 rounded-sm">
          <i class="fa-solid fa-pause text-[9px]"></i> Jeda
        </button>
      </div>
    </div>

    <!-- Log Terminal Box -->
    <div id="feed-terminal-container" class="h-72 sm:h-80 bg-neutral-950 border border-neutral-850 rounded-sm p-3 font-mono text-[11px] overflow-y-auto space-y-2.5">
      <div class="text-neutral-500 text-[10px] pb-1 border-b border-neutral-800/80">
        // POLLING CONNECTED FEEDS (INTERVAL: 5s)
      </div>
      <div id="feed-items-list" class="space-y-2">
        <!-- Injected via Vanilla JS -->
        <div class="text-neutral-500 text-xs text-center py-8">Memuat aliran ancaman...</div>
      </div>
    </div>

    <div class="text-[10px] font-mono text-neutral-500 flex items-center justify-between pt-1">
      <span>Auto-refresh: <b class="text-cyan-400">AKTIF</b></span>
      <span class="text-[9px] text-amber-500">SIMULASI LIVE STREAM</span>
    </div>
  </div>

</div>
<!-- END: SITUATIONAL MAP & LIVE FEED GRID -->

<!-- START: RECENT CASES OVERVIEW -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
    <div>
      <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-neutral-200">Kasus Investigasi Aktif</h2>
      <p class="text-[11px] text-neutral-500 mt-0.5">Daftar kasus siber sosial yang sedang ditangani oleh operator dan analis.</p>
    </div>
    <a href="{{ route('cases.index') }}" class="text-xs font-mono text-cyan-400 hover:text-cyan-300 flex items-center gap-1">
      <span>Lihat Seluruh Kasus</span>
      <i class="fa-solid fa-arrow-right text-[10px]"></i>
    </a>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-wider text-neutral-500">
          <th class="py-2 px-3">Kode Kasus</th>
          <th class="py-2 px-3">Judul Investigasi</th>
          <th class="py-2 px-3">Kategori</th>
          <th class="py-2 px-3">Prioritas</th>
          <th class="py-2 px-3">Status</th>
          <th class="py-2 px-3 text-right">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800/60">
        @forelse($recentCases as $rc)
        <tr class="hover:bg-neutral-850/50 transition-colors">
          <td class="py-2.5 px-3 font-semibold text-cyan-400">
            <a href="{{ route('cases.show', $rc->id) }}">{{ $rc->case_code }}</a>
            @if($rc->is_simulation)
            <span class="ml-1 text-[8px] px-1 py-0.2 border border-amber-500/40 text-amber-500 bg-amber-950/20">SIMULASI</span>
            @endif
          </td>
          <td class="py-2.5 px-3 font-sans text-neutral-200 max-w-md truncate">{{ $rc->title }}</td>
          <td class="py-2.5 px-3 text-[11px] text-neutral-400">{{ $rc->category }}</td>
          <td class="py-2.5 px-3">
            @if($rc->priority === 'KRITIS')
              <span class="px-1.5 py-0.5 bg-rose-950/50 border border-rose-600/50 text-rose-400 text-[9px] uppercase">KRITIS</span>
            @elseif($rc->priority === 'TINGGI')
              <span class="px-1.5 py-0.5 bg-amber-950/50 border border-amber-500/50 text-amber-400 text-[9px] uppercase">TINGGI</span>
            @else
              <span class="px-1.5 py-0.5 bg-cyan-950/50 border border-cyan-500/50 text-cyan-400 text-[9px] uppercase">{{ $rc->priority }}</span>
            @endif
          </td>
          <td class="py-2.5 px-3">
            <span class="px-1.5 py-0.5 bg-neutral-800 border border-neutral-700 text-neutral-300 text-[9px] uppercase">
              {{ $rc->status }}
            </span>
          </td>
          <td class="py-2.5 px-3 text-right space-x-2">
            <a href="{{ route('scanner.index', ['case_id' => $rc->id]) }}" class="text-cyan-400 hover:text-cyan-300 text-[11px]" title="Buka Scanner">
              <i class="fa-solid fa-shield-halved"></i>
            </a>
            <a href="{{ route('cases.show', $rc->id) }}" class="text-neutral-400 hover:text-neutral-200 text-[11px]" title="Detail Kasus">
              <i class="fa-solid fa-arrow-right"></i>
            </a>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="6" class="py-4 text-center text-neutral-500">Belum ada kasus investigasi terdaftar.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<!-- END: RECENT CASES OVERVIEW -->

@endsection

@push('scripts')
<script>
  // Vanilla JS Polling untuk Live Threat Feed
  let feedActive = true;
  const feedList = document.getElementById('feed-items-list');
  const toggleBtn = document.getElementById('toggle-feed-btn');

  async function fetchThreatFeed() {
    if (!feedActive) return;
    try {
      const res = await fetch("{{ route('api.threat-feed') }}");
      if (!res.ok) return;
      const json = await res.json();
      if (!json.data || !json.data.length) return;

      feedList.innerHTML = json.data.map(item => {
        let badgeColor = 'text-cyan-400 border-cyan-500/30 bg-cyan-950/20';
        if (item.severity === 'KRITIS') badgeColor = 'text-rose-400 border-rose-600/40 bg-rose-950/30';
        else if (item.severity === 'TINGGI') badgeColor = 'text-amber-400 border-amber-500/40 bg-amber-950/30';

        return `
          <div class="p-2 bg-neutral-900 border border-neutral-800 rounded-sm hover:border-neutral-700 transition-colors">
            <div class="flex items-center justify-between text-[10px] text-neutral-500 mb-1">
              <span class="text-cyan-400 font-semibold">[${item.time}]</span>
              <span class="px-1 py-0.2 border rounded-sm text-[8px] uppercase ${badgeColor}">${item.severity}</span>
            </div>
            <div class="text-neutral-200 text-xs font-sans leading-tight line-clamp-2">${item.title}</div>
            <div class="mt-1 flex items-center justify-between text-[9px] text-neutral-500">
              <span>Sektor: <b class="text-neutral-400">${item.region}</b></span>
              <span class="text-amber-500">SIMULASI</span>
            </div>
          </div>
        `;
      }).join('');
    } catch (e) {
      console.warn("Polling feed error:", e);
    }
  }

  // Toggle button handler
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      feedActive = !feedActive;
      toggleBtn.innerHTML = feedActive
        ? '<i class="fa-solid fa-pause text-[9px]"></i> Jeda'
        : '<i class="fa-solid fa-play text-[9px]"></i> Lanjutkan';
      toggleBtn.classList.toggle('text-amber-400', !feedActive);
    });
  }

  // Run immediately and poll every 5s
  fetchThreatFeed();
  setInterval(fetchThreatFeed, 5000);
</script>
@endpush
