@extends('layouts.tactical')

@section('title', 'Early Warning Dashboard // TRACE-S')

@section('content')
<div class="space-y-4">

  <!-- Header Bar: Early Warning Dashboard -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
      <div class="flex items-center gap-2.5">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-100 tracking-tight">Early Warning Dashboard</h1>
        <span class="text-[10px] font-mono px-2 py-0.5 border border-blue-500/30 bg-blue-950/40 text-blue-400 rounded">MODUL A1</span>
      </div>
      <p class="text-xs text-slate-400 mt-0.5 font-sans">Real-time monitoring and analysis of digital narratives</p>
    </div>

    <div class="flex items-center gap-2.5 self-start sm:self-auto">
      <span class="px-2.5 py-1 bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-mono rounded-lg flex items-center gap-1.5">
        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
        SIMULASI
      </span>

      <!-- Date Filter Dropdown -->
      <div class="relative" id="timeRangeDropdownWrapper">
        <button id="timeRangeBtn" onclick="toggleTimeRangeDropdown()" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-700/80 rounded-lg text-xs font-mono text-slate-300 flex items-center gap-2 transition-colors">
          <i class="fa-regular fa-calendar text-slate-400"></i>
          <span id="timeRangeLabel">Last 24 Hours</span>
          <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
        </button>
        <div id="timeRangeMenu" class="hidden absolute right-0 mt-1 w-44 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl p-1 z-30 font-mono text-xs">
          <button onclick="setTimeRange('1h', 'Last 1 Hour')" class="w-full text-left px-3 py-1.5 rounded-lg hover:bg-slate-800 text-slate-300">Last 1 Hour</button>
          <button onclick="setTimeRange('6h', 'Last 6 Hours')" class="w-full text-left px-3 py-1.5 rounded-lg hover:bg-slate-800 text-slate-300">Last 6 Hours</button>
          <button onclick="setTimeRange('24h', 'Last 24 Hours')" class="w-full text-left px-3 py-1.5 rounded-lg hover:bg-slate-800 text-blue-400 font-semibold bg-blue-950/30">Last 24 Hours</button>
          <button onclick="setTimeRange('7d', 'Last 7 Days')" class="w-full text-left px-3 py-1.5 rounded-lg hover:bg-slate-800 text-slate-300">Last 7 Days</button>
        </div>
      </div>
    </div>
  </div>

  <!-- START: 4 TOP KPI CARDS (Matching Reference Screenshot) -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
    
    <!-- KPI 1: Narasi Dianalisis -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-4 relative overflow-hidden flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="w-9 h-9 rounded-lg bg-blue-600/20 border border-blue-500/40 text-blue-400 flex items-center justify-center text-sm shadow-[0_0_10px_rgba(59,130,246,0.25)]">
            <i class="fa-regular fa-file-lines"></i>
          </div>
          <div>
            <div class="text-xs text-slate-400 font-medium">Narasi Dianalisis (24J)</div>
          </div>
        </div>
        <span class="text-[9px] px-1.5 py-0.5 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded font-mono">SIMULASI</span>
      </div>
      <div class="mt-3 flex items-baseline justify-between">
        <div>
          <div class="text-2xl font-bold font-mono text-white tracking-tight" id="kpi-narratives">{{ $narrativesCount > 0 ? number_format($narrativesCount) : '48.720' }}</div>
          <div class="text-xs text-emerald-400 flex items-center gap-1 font-medium mt-0.5">
            <i class="fa-solid fa-arrow-trend-up text-[10px]"></i>
            <span>+12.5% dari periode sebelumnya</span>
          </div>
        </div>
        <div class="w-24 h-10 shrink-0">
          <svg class="w-full h-full overflow-visible" viewBox="0 0 100 40">
            <defs>
              <linearGradient id="bladeGradBlue" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.35"/>
                <stop offset="100%" stop-color="#3b82f6" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <path d="M 0 32 Q 25 28, 45 20 T 80 12 T 100 6 L 100 40 L 0 40 Z" fill="url(#bladeGradBlue)"/>
            <path d="M 0 32 Q 25 28, 45 20 T 80 12 T 100 6" fill="none" stroke="#3b82f6" stroke-width="2"/>
          </svg>
        </div>
      </div>
    </div>

    <!-- KPI 2: Hoaks Kritis Terdeteksi -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-4 relative overflow-hidden flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="w-9 h-9 rounded-lg bg-rose-600/20 border border-rose-500/40 text-rose-400 flex items-center justify-center text-sm shadow-[0_0_10px_rgba(244,63,94,0.25)]">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>
          <div>
            <div class="text-xs text-slate-400 font-medium">Hoaks Kritis Terdeteksi</div>
          </div>
        </div>
        <span class="text-[9px] px-1.5 py-0.5 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded font-mono">SIMULASI</span>
      </div>
      <div class="mt-3 flex items-baseline justify-between">
        <div>
          <div class="text-2xl font-bold font-mono text-white tracking-tight" id="kpi-critical">{{ $criticalCount > 0 ? $criticalCount : '7' }}</div>
          <div class="text-xs text-rose-400 flex items-center gap-1 font-medium mt-0.5">
            <i class="fa-solid fa-arrow-trend-up text-[10px]"></i>
            <span>+1 dari kemarin</span>
          </div>
        </div>
        <div class="w-24 h-10 shrink-0">
          <svg class="w-full h-full overflow-visible" viewBox="0 0 100 40">
            <defs>
              <linearGradient id="bladeGradRed" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#f43f5e" stop-opacity="0.35"/>
                <stop offset="100%" stop-color="#f43f5e" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <path d="M 0 35 Q 20 30, 40 26 T 70 14 T 100 8 L 100 40 L 0 40 Z" fill="url(#bladeGradRed)"/>
            <path d="M 0 35 Q 20 30, 40 26 T 70 14 T 100 8" fill="none" stroke="#f43f5e" stroke-width="2"/>
          </svg>
        </div>
      </div>
    </div>

    <!-- KPI 3: Sentimen Publik -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-4 relative overflow-hidden flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="w-9 h-9 rounded-lg bg-emerald-600/20 border border-emerald-500/40 text-emerald-400 flex items-center justify-center text-sm shadow-[0_0_10px_rgba(16,185,129,0.25)]">
            <i class="fa-solid fa-chart-simple"></i>
          </div>
          <div>
            <div class="text-xs text-slate-400 font-medium">Sentimen Publik</div>
          </div>
        </div>
        <span class="text-[9px] px-1.5 py-0.5 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded font-mono">SIMULASI</span>
      </div>
      <div class="mt-3 flex items-baseline justify-between">
        <div>
          <div class="text-2xl font-bold font-mono text-white tracking-tight" id="kpi-sentiment">{{ $sentimentRatio ? $sentimentRatio . '%' : '72.3%' }}</div>
          <div class="text-xs text-emerald-400 flex items-center gap-1 font-medium mt-0.5">
            <i class="fa-solid fa-arrow-trend-up text-[10px]"></i>
            <span>+2.1% dari kemarin</span>
          </div>
        </div>
        <div class="w-24 h-10 shrink-0">
          <svg class="w-full h-full overflow-visible" viewBox="0 0 100 40">
            <defs>
              <linearGradient id="bladeGradGreen" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#10b981" stop-opacity="0.35"/>
                <stop offset="100%" stop-color="#10b981" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <path d="M 0 30 Q 30 32, 50 22 T 80 18 T 100 10 L 100 40 L 0 40 Z" fill="url(#bladeGradGreen)"/>
            <path d="M 0 30 Q 30 32, 50 22 T 80 18 T 100 10" fill="none" stroke="#10b981" stroke-width="2"/>
          </svg>
        </div>
      </div>
    </div>

    <!-- KPI 4: Klaster Narasi Aktif -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-4 relative overflow-hidden flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="w-9 h-9 rounded-lg bg-purple-600/20 border border-purple-500/40 text-purple-400 flex items-center justify-center text-sm shadow-[0_0_10px_rgba(168,85,247,0.25)]">
            <i class="fa-solid fa-circle-nodes"></i>
          </div>
          <div>
            <div class="text-xs text-slate-400 font-medium">Klaster Narasi Aktif</div>
          </div>
        </div>
        <span class="text-[9px] px-1.5 py-0.5 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded font-mono">SIMULASI</span>
      </div>
      <div class="mt-3 flex items-baseline justify-between">
        <div>
          <div class="text-2xl font-bold font-mono text-white tracking-tight" id="kpi-clusters">{{ $activeClustersCount > 0 ? $activeClustersCount : '12' }}</div>
          <div class="text-xs text-purple-400 flex items-center gap-1 font-medium mt-0.5">
            <i class="fa-solid fa-arrow-trend-up text-[10px]"></i>
            <span>+1 dari kemarin</span>
          </div>
        </div>
        <div class="w-24 h-10 shrink-0">
          <svg class="w-full h-full overflow-visible" viewBox="0 0 100 40">
            <defs>
              <linearGradient id="bladeGradPurple" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#a855f7" stop-opacity="0.35"/>
                <stop offset="100%" stop-color="#a855f7" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <path d="M 0 34 Q 30 25, 55 24 T 80 15 T 100 9 L 100 40 L 0 40 Z" fill="url(#bladeGradPurple)"/>
            <path d="M 0 34 Q 30 25, 55 24 T 80 15 T 100 9" fill="none" stroke="#a855f7" stroke-width="2"/>
          </svg>
        </div>
      </div>
    </div>

  </div>
  <!-- END: 4 TOP KPI CARDS -->

  <!-- START: MIDDLE ROW (Chart 7 Cols + Map 5 Cols) -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <!-- PERKEMBANGAN NARASI (7 Cols) -->
    <div class="lg:col-span-7 bg-slate-900/90 border border-slate-800 rounded-xl p-4 flex flex-col justify-between">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-chart-line text-blue-400 text-sm"></i>
          <span class="text-xs font-bold uppercase tracking-wider text-slate-200 font-mono">PERKEMBANGAN NARASI</span>
        </div>
        <div class="flex items-center gap-2">
          <!-- Series Toggles -->
          <button onclick="toggleChartSeries('total')" id="toggleSeriesTotal" class="flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-mono text-blue-400 bg-blue-950/40 border border-blue-500/30 transition-colors">
            <span class="w-2 h-2 rounded-full bg-blue-400"></span>
            <span>Total Narasi</span>
          </button>
          <button onclick="toggleChartSeries('negative')" id="toggleSeriesNegative" class="flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-mono text-amber-400 bg-amber-950/40 border border-amber-500/30 transition-colors">
            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            <span>Narasi Negatif</span>
          </button>
          <button onclick="toggleChartSeries('positive')" id="toggleSeriesPositive" class="flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-mono text-emerald-400 bg-emerald-950/40 border border-emerald-500/30 transition-colors">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            <span>Narasi Positif</span>
          </button>
          <span class="text-[9px] px-1.5 py-0.5 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded font-mono">SIMULASI</span>
        </div>
      </div>

      <!-- Curve Line Chart Area with Y and X Axis -->
      <div class="relative w-full h-64 bg-slate-950/60 border border-slate-850 rounded-lg p-2 overflow-hidden flex">
        <!-- Y-Axis labels -->
        <div class="w-10 h-full flex flex-col justify-between text-[10px] font-mono text-slate-400 text-right pr-2 select-none shrink-0 pb-5">
          <span>50K</span>
          <span>40K</span>
          <span>30K</span>
          <span>20K</span>
          <span>10K</span>
          <span>0</span>
        </div>

        <!-- SVG Curve Canvas -->
        <div class="relative flex-1 h-full flex flex-col justify-between">
          <svg id="narrativeChartSvg" class="w-full h-[calc(100%-20px)] overflow-visible" viewBox="0 0 600 200" preserveAspectRatio="none">
            <!-- Grid Lines -->
            <line x1="0" y1="0" x2="600" y2="0" stroke="#1e293b" stroke-width="1" stroke-dasharray="3 3"/>
            <line x1="0" y1="40" x2="600" y2="40" stroke="#1e293b" stroke-width="1" stroke-dasharray="3 3"/>
            <line x1="0" y1="80" x2="600" y2="80" stroke="#1e293b" stroke-width="1" stroke-dasharray="3 3"/>
            <line x1="0" y1="120" x2="600" y2="120" stroke="#1e293b" stroke-width="1" stroke-dasharray="3 3"/>
            <line x1="0" y1="160" x2="600" y2="160" stroke="#1e293b" stroke-width="1" stroke-dasharray="3 3"/>
            <line x1="0" y1="200" x2="600" y2="200" stroke="#334155" stroke-width="1"/>

            <!-- Area Shading Total Narasi -->
            <path id="pathAreaTotal" d="M 0 130 C 50 128, 100 120, 150 115 C 200 110, 250 135, 300 128 C 350 120, 400 95, 450 70 C 500 80, 550 95, 600 98 L 600 200 L 0 200 Z" fill="#3b82f6" fill-opacity="0.1"/>

            <!-- Curve 1: Total Narasi (Blue) -->
            <path id="pathTotal" d="M 0 130 C 50 128, 100 120, 150 115 C 200 110, 250 135, 300 128 C 350 120, 400 95, 450 70 C 500 80, 550 95, 600 98" fill="none" stroke="#3b82f6" stroke-width="2.5"/>
            <!-- Points Curve 1 -->
            <circle cx="150" cy="115" r="4" fill="#3b82f6" stroke="#0b0f19" stroke-width="2" class="cursor-pointer" onmouseenter="showChartTip(evt, '06:00 - Total: 28.4K')" onmouseleave="hideChartTip()"/>
            <circle cx="300" cy="128" r="4" fill="#3b82f6" stroke="#0b0f19" stroke-width="2" class="cursor-pointer" onmouseenter="showChartTip(evt, '12:00 - Total: 26.1K')" onmouseleave="hideChartTip()"/>
            <circle cx="450" cy="70" r="5" fill="#3b82f6" stroke="#60a5fa" stroke-width="2" class="cursor-pointer" onmouseenter="showChartTip(evt, '18:00 - Puncak: 42.5K')" onmouseleave="hideChartTip()"/>
            <circle cx="600" cy="98" r="4" fill="#3b82f6" stroke="#0b0f19" stroke-width="2" class="cursor-pointer" onmouseenter="showChartTip(evt, '24:00 - Total: 35.8K')" onmouseleave="hideChartTip()"/>

            <!-- Curve 2: Narasi Negatif (Orange) -->
            <path id="pathNegative" d="M 0 165 C 50 162, 100 150, 150 145 C 200 152, 250 156, 300 154 C 350 148, 400 132, 450 120 C 500 130, 550 140, 600 145" fill="none" stroke="#f59e0b" stroke-width="2.2"/>
            <circle cx="450" cy="120" r="4" fill="#f59e0b" stroke="#0b0f19" stroke-width="2" class="cursor-pointer" onmouseenter="showChartTip(evt, '18:00 - Negatif: 22.3K')" onmouseleave="hideChartTip()"/>

            <!-- Curve 3: Narasi Positif (Green) -->
            <path id="pathPositive" d="M 0 185 C 50 183, 100 180, 150 176 C 200 180, 250 182, 300 180 C 350 175, 400 165, 450 155 C 500 160, 550 168, 600 172" fill="none" stroke="#10b981" stroke-width="2"/>
            <circle cx="450" cy="155" r="4" fill="#10b981" stroke="#0b0f19" stroke-width="2" class="cursor-pointer" onmouseenter="showChartTip(evt, '18:00 - Positif: 12.8K')" onmouseleave="hideChartTip()"/>
          </svg>

          <!-- Interactive Tooltip Overlay -->
          <div id="chartTooltip" class="hidden absolute pointer-events-none px-2 py-1 bg-slate-900 border border-slate-700 text-slate-200 text-[10px] font-mono rounded shadow-lg z-20"></div>

          <!-- X-Axis labels -->
          <div class="w-full flex justify-between text-[10px] font-mono text-slate-400 select-none pt-1">
            <span>00:00</span>
            <span>04:00</span>
            <span>08:00</span>
            <span>12:00</span>
            <span>16:00</span>
            <span>20:00</span>
            <span>24:00</span>
          </div>
        </div>
      </div>

      <div class="mt-2 flex items-center justify-between text-[11px] font-mono text-slate-400">
        <span class="flex items-center gap-1.5">
          <i class="fa-regular fa-calendar-check text-slate-400"></i>
          <span>Periode: 03 Nov 2025, 00:00 - 24:00 (UTC)</span>
        </span>
        <span class="text-blue-400 hover:text-blue-300 cursor-pointer flex items-center gap-1" onclick="alert('Data agregat telemetri disaring dari 4 konektor aktif.')">
          <i class="fa-solid fa-circle-info"></i> Log Agregat
        </span>
      </div>
    </div>

    <!-- DISTRIBUSI WILAYAH (5 Cols) -->
    <div class="lg:col-span-5 bg-slate-900/90 border border-slate-800 rounded-xl p-4 flex flex-col justify-between">
      <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-location-dot text-blue-400 text-sm"></i>
          <span class="text-xs font-bold uppercase tracking-wider text-slate-200 font-mono">DISTRIBUSI WILAYAH</span>
        </div>
        <span class="text-[9px] px-1.5 py-0.5 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded font-mono">SIMULASI</span>
      </div>

      <!-- Archipelago Map with Glowing Concentric Radar Ripples -->
      <div class="relative w-full h-56 bg-slate-950/70 border border-slate-850 rounded-lg overflow-hidden p-2 flex items-center justify-center">
        <!-- Tactical Grid Overlay -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b_1px,transparent_1px),linear-gradient(to_bottom,#1e293b_1px,transparent_1px)] bg-[size:1.5rem_1.5rem] opacity-30 pointer-events-none"></div>

        <!-- Indonesia SVG Map Silhouette -->
        <svg class="w-full h-full relative z-10" viewBox="0 0 800 360" preserveAspectRatio="xMidYMid meet">
          <!-- Sumatera -->
          <path d="M 80 60 L 160 140 L 220 220 L 190 250 L 140 220 L 80 120 Z" fill="#1e293b" stroke="#334155" stroke-width="1.5" class="hover:fill-slate-700 cursor-pointer transition-colors" onclick="openRegionalModal('Sumatera', 28.7, 'Kodam I/Bukit Barisan')"/>
          <!-- Jawa -->
          <path d="M 190 260 L 260 265 L 360 270 L 400 275 L 390 290 L 220 285 L 180 275 Z" fill="#1e293b" stroke="#334155" stroke-width="1.5" class="hover:fill-slate-700 cursor-pointer transition-colors" onclick="openRegionalModal('Jawa', 42.1, 'Kodam Jaya & Kodam III/Siliwangi')"/>
          <!-- Kalimantan -->
          <path d="M 280 110 L 390 100 L 420 180 L 370 210 L 290 190 Z" fill="#1e293b" stroke="#334155" stroke-width="1.5" class="hover:fill-slate-700 cursor-pointer transition-colors" onclick="openRegionalModal('Kalimantan', 15.3, 'Kodam VI/Mulawarman')"/>
          <!-- Sulawesi -->
          <path d="M 450 120 L 520 110 L 510 160 L 470 170 L 490 220 L 450 210 Z" fill="#1e293b" stroke="#334155" stroke-width="1.5" class="hover:fill-slate-700 cursor-pointer transition-colors" onclick="openRegionalModal('Sulawesi', 8.2, 'Kodam XIV/Hasanuddin')"/>
          <!-- Maluku & Papua -->
          <path d="M 540 140 L 570 145 L 560 180 Z" fill="#1e293b" stroke="#334155" stroke-width="1.5"/>
          <path d="M 610 150 L 750 160 L 740 240 L 650 230 L 620 190 Z" fill="#1e293b" stroke="#334155" stroke-width="1.5" class="hover:fill-slate-700 cursor-pointer transition-colors" onclick="openRegionalModal('Papua & Maluku', 5.7, 'Kodam XVII/Cenderawasih')"/>
        </svg>

        <!-- Dynamic Concentric Pulsing Radar Rings on Island Hubs -->
        <!-- JAWA (Hub Utama - 42.1% - Blue) -->
        <div class="absolute left-[36%] top-[74%] -translate-x-1/2 -translate-y-1/2 pointer-events-none z-20 flex items-center justify-center">
          <span class="absolute w-12 h-12 rounded-full border border-blue-400/80 bg-blue-500/20 animate-radar-ripple"></span>
          <span class="absolute w-7 h-7 rounded-full border border-blue-400/90 bg-blue-500/30"></span>
          <span class="w-3 h-3 rounded-full bg-blue-400 shadow-[0_0_12px_#38bdf8]"></span>
        </div>

        <!-- SUMATERA (28.7% - Green) -->
        <div class="absolute left-[20%] top-[45%] -translate-x-1/2 -translate-y-1/2 pointer-events-none z-20 flex items-center justify-center">
          <span class="absolute w-9 h-9 rounded-full border border-emerald-400/70 bg-emerald-500/15 animate-radar-ripple" style="animation-delay: 0.6s;"></span>
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-[0_0_8px_#34d399]"></span>
        </div>

        <!-- KALIMANTAN (15.3% - Orange) -->
        <div class="absolute left-[44%] top-[42%] -translate-x-1/2 -translate-y-1/2 pointer-events-none z-20 flex items-center justify-center">
          <span class="absolute w-7 h-7 rounded-full border border-amber-400/70 bg-amber-500/15 animate-radar-ripple" style="animation-delay: 1.2s;"></span>
          <span class="w-2 h-2 rounded-full bg-amber-400 shadow-[0_0_8px_#fbbf24]"></span>
        </div>

        <!-- SULAWESI (Purple) -->
        <div class="absolute left-[60%] top-[48%] -translate-x-1/2 -translate-y-1/2 pointer-events-none z-20 flex items-center justify-center">
          <span class="absolute w-6 h-6 rounded-full border border-purple-400/70 bg-purple-500/15 animate-radar-ripple" style="animation-delay: 1.8s;"></span>
          <span class="w-2 h-2 rounded-full bg-purple-400 shadow-[0_0_8px_#c084fc]"></span>
        </div>

        <!-- PAPUA (Cyan) -->
        <div class="absolute left-[83%] top-[54%] -translate-x-1/2 -translate-y-1/2 pointer-events-none z-20 flex items-center justify-center">
          <span class="w-2 h-2 rounded-full bg-cyan-400 shadow-[0_0_8px_#22d3ee]"></span>
        </div>
      </div>

      <!-- Regional Distribution Legend Bar -->
      <div class="mt-3 flex items-center justify-between text-xs font-mono">
        <div class="flex items-center gap-1.5 cursor-pointer hover:opacity-80" onclick="openRegionalModal('Jawa', 42.1, 'Kodam Jaya')">
          <span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>
          <span class="text-slate-300">Jawa <b class="text-white ml-0.5">42.1%</b></span>
        </div>
        <div class="flex items-center gap-1.5 cursor-pointer hover:opacity-80" onclick="openRegionalModal('Sumatera', 28.7, 'Kodam I/Bukit Barisan')">
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
          <span class="text-slate-300">Sumatera <b class="text-white ml-0.5">28.7%</b></span>
        </div>
        <div class="flex items-center gap-1.5 cursor-pointer hover:opacity-80" onclick="openRegionalModal('Kalimantan', 15.3, 'Kodam VI/Mulawarman')">
          <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
          <span class="text-slate-300">Kalimantan <b class="text-white ml-0.5">15.3%</b></span>
        </div>
        <div class="flex items-center gap-1.5 cursor-pointer hover:opacity-80" onclick="openRegionalModal('Lainnya (Sulawesi & Papua)', 13.9, 'Kodam XIV & XVII')">
          <span class="w-2.5 h-2.5 rounded-full bg-purple-400"></span>
          <span class="text-slate-300">Lainnya <b class="text-white ml-0.5">13.9%</b></span>
        </div>
      </div>

      <!-- Footer Info -->
      <div class="mt-2 pt-2 border-t border-slate-800 text-[10px] text-slate-400 flex items-center justify-between font-mono">
        <span class="flex items-center gap-1">
          <i class="fa-solid fa-circle-info text-blue-400"></i>
          <span>Cakupan Data: Nasional (Agregat)</span>
        </span>
        <button onclick="openRegionalDrilldownFull()" class="text-blue-400 hover:text-blue-300 flex items-center gap-1 text-[11px]">
          <span>Peta Dampak Wilayah Teritorial &rarr;</span>
        </button>
      </div>
    </div>

  </div>
  <!-- END: MIDDLE ROW -->

  <!-- START: BOTTOM ROW (Live Threat Feed 8 Cols + Data Connectors 4 Cols) -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <!-- LIVE THREAT FEED (8 Cols) -->
    <div class="lg:col-span-8 bg-slate-900/90 border border-slate-800 rounded-xl p-4 flex flex-col justify-between">
      <div class="flex items-center justify-between mb-3 border-b border-slate-800 pb-2.5">
        <div class="flex items-center gap-2">
          <span class="relative flex h-2.5 w-2.5">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
          </span>
          <span class="text-xs font-bold uppercase tracking-wider text-slate-200 font-mono">LIVE THREAT FEED</span>
          <span class="text-[9px] px-1.5 py-0.5 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded font-mono">SIMULASI</span>
        </div>

        <div class="flex items-center gap-3">
          <!-- Status Filter Pills -->
          <div class="hidden sm:flex items-center gap-1.5 text-[10px] font-mono">
            <button onclick="filterThreatTable('all')" id="filter-all" class="px-2 py-0.5 rounded bg-blue-600/20 border border-blue-500/40 text-blue-400 font-bold">Semua</button>
            <button onclick="filterThreatTable('PERLU DITINJAU')" id="filter-review" class="px-2 py-0.5 rounded bg-slate-800/60 border border-slate-700/60 text-slate-400 hover:text-amber-400">Tinjau</button>
            <button onclick="filterThreatTable('BELUM TERVERIFIKASI')" id="filter-unverified" class="px-2 py-0.5 rounded bg-slate-800/60 border border-slate-700/60 text-slate-400 hover:text-blue-400">Unverified</button>
            <button onclick="filterThreatTable('TERIDENTIFIKASI')" id="filter-identified" class="px-2 py-0.5 rounded bg-slate-800/60 border border-slate-700/60 text-slate-400 hover:text-emerald-400">Teridentifikasi</button>
          </div>
          <a href="{{ route('scanner.index') }}" class="text-xs font-mono text-blue-400 hover:text-blue-300 flex items-center gap-1 transition-colors">
            <span>Lihat Semua</span>
            <i class="fa-solid fa-arrow-right text-[10px]"></i>
          </a>
        </div>
      </div>

      <!-- Feed Table Matching Reference Mockup -->
      <div class="overflow-x-auto">
        <table class="w-full text-left font-mono text-xs">
          <thead>
            <tr class="border-b border-slate-800 text-[10px] uppercase tracking-wider text-slate-400">
              <th class="py-2 px-2.5">TIME</th>
              <th class="py-2 px-2.5">NARRATIVE</th>
              <th class="py-2 px-2.5">SOURCE</th>
              <th class="py-2 px-2.5">PLATFORM</th>
              <th class="py-2 px-2.5">STATUS</th>
              <th class="py-2 px-2.5 text-right">AKSI</th>
            </tr>
          </thead>
          <tbody id="threatTableBody" class="divide-y divide-slate-800/60 font-sans">
            <!-- Row 1 -->
            <tr class="hover:bg-slate-800/40 transition-colors threat-row" data-status="PERLU DITINJAU">
              <td class="py-2.5 px-2.5 font-mono text-xs text-slate-400">00:41:17</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-200 font-medium max-w-xs truncate">Klaim anggaran pendidikan dipotong 50%</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-400">Media Online</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-300">
                <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-globe text-blue-400 text-xs"></i> Web</span>
              </td>
              <td class="py-2.5 px-2.5">
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-amber-500/10 border border-amber-500/30 text-amber-400 uppercase">PERLU DITINJAU</span>
              </td>
              <td class="py-2.5 px-2.5 text-right">
                <button onclick="inspectThreat('00:41:17', 'Klaim anggaran pendidikan dipotong 50%', 'Media Online', 'Web', 'PERLU DITINJAU', 'TS-2026-NARR-01')" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-[10px] font-mono transition-colors">
                  Inspeksi
                </button>
              </td>
            </tr>

            <!-- Row 2 -->
            <tr class="hover:bg-slate-800/40 transition-colors threat-row" data-status="BELUM TERVERIFIKASI">
              <td class="py-2.5 px-2.5 font-mono text-xs text-slate-400">00:41:32</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-200 font-medium max-w-xs truncate">Konten kehilangan konteks terkait kebijakan</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-400">Akun Publik</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-300">
                <span class="inline-flex items-center gap-1.5"><i class="fa-brands fa-x-twitter text-slate-400 text-xs"></i> X</span>
              </td>
              <td class="py-2.5 px-2.5">
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-blue-500/10 border border-blue-500/30 text-blue-400 uppercase">BELUM TERVERIFIKASI</span>
              </td>
              <td class="py-2.5 px-2.5 text-right">
                <button onclick="inspectThreat('00:41:32', 'Konten kehilangan konteks terkait kebijakan', 'Akun Publik', 'X', 'BELUM TERVERIFIKASI', 'TS-2026-NARR-02')" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-[10px] font-mono transition-colors">
                  Inspeksi
                </button>
              </td>
            </tr>

            <!-- Row 3 -->
            <tr class="hover:bg-slate-800/40 transition-colors threat-row" data-status="TERIDENTIFIKASI">
              <td class="py-2.5 px-2.5 font-mono text-xs text-slate-400">00:37:20</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-200 font-medium max-w-xs truncate">Variasi narasi subsidi energi</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-400">Akun Publik</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-300">
                <span class="inline-flex items-center gap-1.5"><i class="fa-brands fa-youtube text-red-500 text-xs"></i> YouTube</span>
              </td>
              <td class="py-2.5 px-2.5">
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 uppercase">TERIDENTIFIKASI</span>
              </td>
              <td class="py-2.5 px-2.5 text-right">
                <button onclick="inspectThreat('00:37:20', 'Variasi narasi subsidi energi', 'Akun Publik', 'YouTube', 'TERIDENTIFIKASI', 'TS-2026-NARR-03')" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-[10px] font-mono transition-colors">
                  Inspeksi
                </button>
              </td>
            </tr>

            <!-- Row 4 -->
            <tr class="hover:bg-slate-800/40 transition-colors threat-row" data-status="BELUM TERVERIFIKASI">
              <td class="py-2.5 px-2.5 font-mono text-xs text-slate-400">00:33:56</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-200 font-medium max-w-xs truncate">Informasi belum terverifikasi soal bantuan sosial</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-400">Media Online</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-300">
                <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-globe text-blue-400 text-xs"></i> Web</span>
              </td>
              <td class="py-2.5 px-2.5">
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-blue-500/10 border border-blue-500/30 text-blue-400 uppercase">BELUM TERVERIFIKASI</span>
              </td>
              <td class="py-2.5 px-2.5 text-right">
                <button onclick="inspectThreat('00:33:56', 'Informasi belum terverifikasi soal bantuan sosial', 'Media Online', 'Web', 'BELUM TERVERIFIKASI', 'TS-2026-NARR-04')" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-[10px] font-mono transition-colors">
                  Inspeksi
                </button>
              </td>
            </tr>

            <!-- Row 5 -->
            <tr class="hover:bg-slate-800/40 transition-colors threat-row" data-status="PERLU DITINJAU">
              <td class="py-2.5 px-2.5 font-mono text-xs text-slate-400">00:29:12</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-200 font-medium max-w-xs truncate">Narasi ajakan aksi di media sosial</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-400">Akun Anonim</td>
              <td class="py-2.5 px-2.5 text-xs text-slate-300">
                <span class="inline-flex items-center gap-1.5"><i class="fa-brands fa-telegram text-sky-400 text-xs"></i> Telegram</span>
              </td>
              <td class="py-2.5 px-2.5">
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-amber-500/10 border border-amber-500/30 text-amber-400 uppercase">PERLU DITINJAU</span>
              </td>
              <td class="py-2.5 px-2.5 text-right">
                <button onclick="inspectThreat('00:29:12', 'Narasi ajakan aksi di media sosial', 'Akun Anonim', 'Telegram', 'PERLU DITINJAU', 'TS-2026-NARR-05')" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-[10px] font-mono transition-colors">
                  Inspeksi
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- STATUS KONEKTOR DATA (4 Cols) -->
    <div class="lg:col-span-4 bg-slate-900/90 border border-slate-800 rounded-xl p-4 flex flex-col justify-between">
      <div>
        <div class="flex items-center justify-between mb-3 border-b border-slate-800 pb-2.5">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-database text-blue-400 text-sm"></i>
            <span class="text-xs font-bold uppercase tracking-wider text-slate-200 font-mono">STATUS KONEKTOR DATA</span>
          </div>
          <span class="text-[9px] px-1.5 py-0.5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded font-mono">4 CONNECTED</span>
        </div>

        <div class="space-y-3 font-mono text-xs">
          <!-- Connector 1: Web Crawler -->
          <div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/60 border border-slate-850">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
              <span class="text-slate-200">Web Crawler</span>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-emerald-400 font-medium">Online</span>
              <span class="text-slate-400 text-[11px] connector-latency" id="lat-crawler">~120ms</span>
            </div>
          </div>

          <!-- Connector 2: Social Media API -->
          <div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/60 border border-slate-850">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
              <span class="text-slate-200">Social Media API</span>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-emerald-400 font-medium">Online</span>
              <span class="text-slate-400 text-[11px] connector-latency" id="lat-social">~95ms</span>
            </div>
          </div>

          <!-- Connector 3: News Aggregator -->
          <div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/60 border border-slate-850">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
              <span class="text-slate-200">News Aggregator</span>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-emerald-400 font-medium">Online</span>
              <span class="text-slate-400 text-[11px] connector-latency" id="lat-news">~110ms</span>
            </div>
          </div>

          <!-- Connector 4: Internal Database -->
          <div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/60 border border-slate-850">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
              <span class="text-slate-200">Internal Database</span>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-emerald-400 font-medium">Online</span>
              <span class="text-slate-400 text-[11px] connector-latency" id="lat-db">~80ms</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Button -->
      <div class="mt-4 pt-3 border-t border-slate-800">
        <button onclick="runConnectorDiagnostics()" id="btnConnectorDiag" class="w-full py-2 px-3 rounded-lg bg-blue-600/15 hover:bg-blue-600/30 text-blue-400 border border-blue-500/40 text-xs font-mono font-medium flex items-center justify-center gap-2 transition-all shadow-[0_0_12px_rgba(59,130,246,0.15)]">
          <i class="fa-solid fa-arrows-rotate" id="diagIcon"></i>
          <span id="diagBtnText">Jalankan Tes Ping Diagnostik</span>
        </button>
      </div>
    </div>

  </div>
  <!-- END: BOTTOM ROW -->

  <!-- START: RECENT CASES OVERVIEW -->
  <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-4 space-y-3">
    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
      <div>
        <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-slate-200">Kasus Investigasi Aktif</h2>
        <p class="text-[11px] text-slate-400 mt-0.5">Daftar kasus siber sosial yang sedang ditangani oleh operator dan analis.</p>
      </div>
      <a href="{{ route('cases.index') }}" class="text-xs font-mono text-blue-400 hover:text-blue-300 flex items-center gap-1">
        <span>Lihat Seluruh Kasus</span>
        <i class="fa-solid fa-arrow-right text-[10px]"></i>
      </a>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left font-mono text-xs">
        <thead>
          <tr class="border-b border-slate-800 text-[10px] uppercase tracking-wider text-slate-400">
            <th class="py-2 px-3">Kode Kasus</th>
            <th class="py-2 px-3">Judul Investigasi</th>
            <th class="py-2 px-3">Kategori</th>
            <th class="py-2 px-3">Prioritas</th>
            <th class="py-2 px-3">Status</th>
            <th class="py-2 px-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60 font-sans">
          @forelse($recentCases as $rc)
          <tr class="hover:bg-slate-800/40 transition-colors">
            <td class="py-2.5 px-3 font-semibold text-blue-400 font-mono">
              <a href="{{ route('cases.show', $rc->id) }}">{{ $rc->case_code }}</a>
              @if($rc->is_simulation)
              <span class="ml-1 text-[8px] px-1 py-0.2 border border-amber-500/40 text-amber-500 bg-amber-950/20 rounded">SIMULASI</span>
              @endif
            </td>
            <td class="py-2.5 px-3 text-slate-200 max-w-md truncate">{{ $rc->title }}</td>
            <td class="py-2.5 px-3 text-[11px] text-slate-400">{{ $rc->category }}</td>
            <td class="py-2.5 px-3">
              @if($rc->priority === 'KRITIS')
                <span class="px-1.5 py-0.5 bg-rose-950/50 border border-rose-600/50 text-rose-400 text-[9px] uppercase rounded">KRITIS</span>
              @elseif($rc->priority === 'TINGGI')
                <span class="px-1.5 py-0.5 bg-amber-950/50 border border-amber-500/50 text-amber-400 text-[9px] uppercase rounded">TINGGI</span>
              @else
                <span class="px-1.5 py-0.5 bg-blue-950/50 border border-blue-500/50 text-blue-400 text-[9px] uppercase rounded">{{ $rc->priority }}</span>
              @endif
            </td>
            <td class="py-2.5 px-3">
              <span class="px-1.5 py-0.5 bg-slate-800 border border-slate-700 text-slate-300 text-[9px] uppercase rounded">
                {{ $rc->status }}
              </span>
            </td>
            <td class="py-2.5 px-3 text-right space-x-2 font-mono">
              <a href="{{ route('scanner.index', ['case_id' => $rc->id]) }}" class="text-blue-400 hover:text-blue-300 text-[11px]" title="Buka Scanner">
                <i class="fa-solid fa-shield-halved"></i>
              </a>
              <a href="{{ route('cases.show', $rc->id) }}" class="text-slate-400 hover:text-slate-200 text-[11px]" title="Detail Kasus">
                <i class="fa-solid fa-arrow-right"></i>
              </a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="py-4 text-center text-slate-500">Belum ada kasus investigasi terdaftar.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <!-- END: RECENT CASES OVERVIEW -->

</div>

<!-- START: MODAL INSPEKSI ANCAMAN -->
<div id="threatInspectModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full p-5 shadow-2xl space-y-4">
    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
      <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-blue-600/20 border border-blue-500/40 text-blue-400 flex items-center justify-center text-xs">
          <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div>
          <h3 class="text-sm font-bold text-white font-mono uppercase">INSPEKSI ANCAMAN SIBER SOSIAL</h3>
          <p class="text-[10px] text-slate-400 font-mono" id="modalThreatCode">ID: TS-2026-NARR-01</p>
        </div>
      </div>
      <button onclick="closeThreatModal()" class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center text-xs">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="space-y-3 text-xs">
      <div>
        <div class="text-[10px] uppercase font-mono text-slate-400">Ringkasan Narasi:</div>
        <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-800 text-slate-200 font-medium mt-1" id="modalThreatNarrative">
          Klaim anggaran pendidikan dipotong 50%
        </div>
      </div>

      <div class="grid grid-cols-3 gap-2 text-center font-mono">
        <div class="p-2 rounded-lg bg-slate-950 border border-slate-800">
          <div class="text-[9px] text-slate-400 uppercase">WAKTU DETEKSI</div>
          <div class="text-slate-200 font-bold mt-0.5" id="modalThreatTime">00:41:17</div>
        </div>
        <div class="p-2 rounded-lg bg-slate-950 border border-slate-800">
          <div class="text-[9px] text-slate-400 uppercase">SUMBER / KANAL</div>
          <div class="text-blue-400 font-bold mt-0.5" id="modalThreatSource">Media Online (Web)</div>
        </div>
        <div class="p-2 rounded-lg bg-slate-950 border border-slate-800">
          <div class="text-[9px] text-slate-400 uppercase">STATUS EVALUASI</div>
          <div class="text-amber-400 font-bold mt-0.5" id="modalThreatStatus">PERLU DITINJAU</div>
        </div>
      </div>

      <div class="p-3 rounded-lg bg-blue-950/20 border border-blue-500/20 text-slate-300 space-y-1.5 font-sans">
        <div class="flex items-center gap-1.5 text-blue-400 text-xs font-semibold">
          <i class="fa-solid fa-microchip"></i>
          <span>Analisis Awal Mesin Kredibilitas Llama-3.1-70B:</span>
        </div>
        <p class="text-[11px] leading-relaxed text-slate-300">
          Narasi mengandung divergensi angka signifikan dibanding alokasi APBN resmi Kemendikdasmen (20% mandatory spending konstitusional). Skor kepercayaan awal: <b>24/100 (Sangat Rendah)</b>.
        </p>
      </div>

      <!-- Anti-Doxxing Guardrails Reference Note -->
      <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-800 text-[10px] text-slate-400 font-mono flex items-start gap-2">
        <i class="fa-solid fa-shield-halved text-blue-400 mt-0.5"></i>
        <span>Guardrail Etik: Analisis berfokus pada konten dan amplifikasi narasi. Tidak memuat informasi identitas pribadi (PII) atau pelacakan IP individual.</span>
      </div>
    </div>

    <div class="flex items-center justify-between pt-3 border-t border-slate-800 font-mono text-xs">
      <button onclick="copyThreatHash()" class="text-slate-400 hover:text-slate-200 flex items-center gap-1 text-[11px]">
        <i class="fa-regular fa-copy"></i>
        <span id="copyHashText">Salin SHA-256 Hash</span>
      </button>
      <div class="flex items-center gap-2">
        <button onclick="closeThreatModal()" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300">Tutup</button>
        <a id="btnSendScanner" href="{{ route('scanner.index') }}" class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-medium flex items-center gap-1.5">
          <i class="fa-solid fa-bolt"></i>
          <span>Kirim ke Scanner</span>
        </a>
      </div>
    </div>
  </div>
</div>
<!-- END: MODAL INSPEKSI ANCAMAN -->

<!-- START: MODAL DRILLDOWN WILAYAH & MONOSPACED EXPOSURE GRID (§6B) -->
<div id="regionalDrilldownModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-5 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
      <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-blue-600/20 border border-blue-500/40 text-blue-400 flex items-center justify-center text-xs">
          <i class="fa-solid fa-map-location-dot"></i>
        </div>
        <div>
          <h3 class="text-sm font-bold text-white font-mono uppercase">Peta Dampak Wilayah Teritorial</h3>
          <p class="text-[10px] text-slate-400 font-mono">Cognitive Impact Zone &bull; Kesiapsiagaan Komando Kewilayahan</p>
        </div>
      </div>
      <button onclick="closeRegionalModal()" class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center text-xs">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Active Region Overview -->
    <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 flex items-center justify-between font-mono">
      <div>
        <div class="text-[10px] uppercase text-slate-400">PULAU / ZONA TERPILIH:</div>
        <div class="text-base font-bold text-white mt-0.5" id="modalRegionTitle">Jawa</div>
        <div class="text-[11px] text-blue-400" id="modalRegionKodam">Kodam Jaya &amp; Kodam III/Siliwangi</div>
      </div>
      <div class="text-right">
        <div class="text-[10px] uppercase text-slate-400">RESONANSI NARASI:</div>
        <div class="text-xl font-bold text-blue-400" id="modalRegionPct">42.1%</div>
        <span class="text-[9px] px-1.5 py-0.2 bg-amber-500/20 border border-amber-500/30 text-amber-400 rounded">SIMULASI</span>
      </div>
    </div>

    <!-- Territorial KODAM Matrix -->
    <div class="space-y-2">
      <div class="text-[11px] font-mono uppercase tracking-wider text-slate-400 flex items-center justify-between">
        <span>Matriks Komando Wilayah (KODAM) Terhubung:</span>
        <span class="text-slate-500 text-[10px]">Level 2 Drilldown</span>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs font-mono" id="kodamMatrixContainer">
        <!-- Kodam Jaya -->
        <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-800 space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-slate-200 font-bold">Kodam Jaya</span>
            <span class="text-[9px] px-1.5 py-0.2 bg-rose-950/60 border border-rose-500/40 text-rose-400 rounded">KRITIS</span>
          </div>
          <div class="text-[10px] text-slate-400">DKI Jakarta, Kota Bekasi, Depok</div>
          <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1">
            <span>48 Narasi Dianalisis</span>
            <span class="text-rose-400">Negatif: 84%</span>
          </div>
        </div>

        <!-- Kodam III/Siliwangi -->
        <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-800 space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-slate-200 font-bold">Kodam III/Siliwangi</span>
            <span class="text-[9px] px-1.5 py-0.2 bg-rose-950/60 border border-rose-500/40 text-rose-400 rounded">KRITIS</span>
          </div>
          <div class="text-[10px] text-slate-400">Jawa Barat, Banten</div>
          <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1">
            <span>Kab. Subang &amp; Kota Bandung</span>
            <span class="text-rose-400">Negatif: 81%</span>
          </div>
        </div>

        <!-- Kodam IV/Diponegoro -->
        <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-800 space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-slate-200 font-bold">Kodam IV/Diponegoro</span>
            <span class="text-[9px] px-1.5 py-0.2 bg-amber-950/60 border border-amber-500/40 text-amber-400 rounded">TINGGI</span>
          </div>
          <div class="text-[10px] text-slate-400">Jawa Tengah, D.I. Yogyakarta</div>
          <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1">
            <span>Kab. Banyumas, Kota Semarang</span>
            <span class="text-amber-400">Negatif: 70%</span>
          </div>
        </div>

        <!-- Kodam V/Brawijaya -->
        <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-800 space-y-1">
          <div class="flex items-center justify-between">
            <span class="text-slate-200 font-bold">Kodam V/Brawijaya</span>
            <span class="text-[9px] px-1.5 py-0.2 bg-amber-950/60 border border-amber-500/40 text-amber-400 rounded">TINGGI</span>
          </div>
          <div class="text-[10px] text-slate-400">Jawa Timur</div>
          <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1">
            <span>Kota Surabaya, Kab. Jember</span>
            <span class="text-amber-400">Negatif: 74%</span>
          </div>
        </div>
      </div>
    </div>

    <!-- START: §6B GEOSPATIAL & NETWORK EXPOSURE GRID -->
    <div class="p-3 bg-slate-950 border border-slate-850 rounded-xl space-y-2 font-mono text-xs">
      <div class="flex items-center justify-between text-[10px] uppercase tracking-wider text-slate-400 pb-1.5 border-b border-slate-800">
        <span class="text-blue-400 font-bold flex items-center gap-1.5">
          <i class="fa-solid fa-network-wired text-[10px]"></i>
          EXPOSURE GRID MONOSPACE (§6B)
        </span>
        <span class="text-slate-500">SUBNET /24 &bull; ANTI-DOXXING</span>
      </div>

      <div class="space-y-1.5 text-[11px]" id="exposureGridList">
        <!-- Subang Exposure -->
        <div class="p-2 rounded bg-slate-900 border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
          <div class="flex items-center gap-2">
            <span class="text-white font-bold">[Kab. Subang &bull; Kec. Pagaden]</span>
            <span class="text-cyan-400 font-mono bg-cyan-950/50 px-1.5 py-0.5 border border-cyan-500/30 rounded text-[10px]">36.88.42.0/24</span>
          </div>
          <div class="flex items-center gap-2 text-[10px] text-slate-400">
            <span>AS23693 (PT Telkom)</span>
            <span class="text-emerald-400 font-bold">EXIF: KONSISTEN</span>
            <span class="text-slate-200 bg-slate-800 px-1.5 py-0.2 rounded font-bold">16 Akun</span>
          </div>
        </div>

        <!-- Jakarta Exposure -->
        <div class="p-2 rounded bg-slate-900 border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
          <div class="flex items-center gap-2">
            <span class="text-white font-bold">[Jakarta Pusat &bull; Gambir]</span>
            <span class="text-cyan-400 font-mono bg-cyan-950/50 px-1.5 py-0.5 border border-cyan-500/30 rounded text-[10px]">103.247.12.0/24</span>
          </div>
          <div class="flex items-center gap-2 text-[10px] text-slate-400">
            <span>AS23693 (PT Telkom)</span>
            <span class="text-emerald-400 font-bold">EXIF: KONSISTEN</span>
            <span class="text-slate-200 bg-slate-800 px-1.5 py-0.2 rounded font-bold">26 Akun</span>
          </div>
        </div>

        <!-- Surabaya Exposure -->
        <div class="p-2 rounded bg-slate-900 border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
          <div class="flex items-center gap-2">
            <span class="text-white font-bold">[Kota Surabaya &bull; Gubeng]</span>
            <span class="text-cyan-400 font-mono bg-cyan-950/50 px-1.5 py-0.5 border border-cyan-500/30 rounded text-[10px]">103.247.112.0/24</span>
          </div>
          <div class="flex items-center gap-2 text-[10px] text-slate-400">
            <span>AS23693 (PT Telkom)</span>
            <span class="text-emerald-400 font-bold">EXIF: KONSISTEN</span>
            <span class="text-slate-200 bg-slate-800 px-1.5 py-0.2 rounded font-bold">15 Akun</span>
          </div>
        </div>
      </div>
    </div>
    <!-- END: §6B GEOSPATIAL & NETWORK EXPOSURE GRID -->

    <!-- Kebijakan data: bukan pelacakan pelaku -->
    <div class="p-2.5 rounded-lg bg-slate-950 border border-blue-500/20 text-[10px] font-mono text-slate-400 flex items-start gap-2">
      <i class="fa-solid fa-shield-halved text-blue-400 mt-0.5"></i>
      <span>Peta menunjukkan <b class="text-slate-200">wilayah yang terdampak narasi</b> (resonansi publik &amp; kesiapsiagaan KODAM), <b class="text-slate-200">bukan</b> lokasi, IP, atau identitas pengunggah. TRACE-S tidak melakukan pelacakan pribadi atau <i>doxxing</i>.</span>
    </div>

    <div class="flex items-center justify-end pt-3 border-t border-slate-800 font-mono text-xs">
      <button onclick="closeRegionalModal()" class="px-4 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-medium">Tutup Panel Drilldown</button>
    </div>
  </div>
</div>
<!-- END: MODAL DRILLDOWN WILAYAH -->

@endsection

@push('scripts')
<script>
  // Regional Data from Backend Controller
  const REGIONAL_DATA = @json($regionalData);

  // State variables
  let chartSeriesVisibility = { total: true, negative: true, positive: true };

  // Dropdown Time Range
  function toggleTimeRangeDropdown() {
    const menu = document.getElementById('timeRangeMenu');
    menu.classList.toggle('hidden');
  }

  function setTimeRange(range, label) {
    document.getElementById('timeRangeLabel').innerText = label;
    document.getElementById('timeRangeMenu').classList.add('hidden');
    // Simulate slight variance in KPI metrics on time range change
    const mul = range === '1h' ? 0.08 : (range === '6h' ? 0.35 : (range === '24h' ? 1.0 : 4.8));
    const narr = Math.round(48720 * mul);
    document.getElementById('kpi-narratives').innerText = narr.toLocaleString('id-ID');
  }

  // Close dropdown on outside click
  document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('timeRangeDropdownWrapper');
    if (wrapper && !wrapper.contains(e.target)) {
      document.getElementById('timeRangeMenu')?.classList.add('hidden');
    }
  });

  // Series Toggle in Chart
  function toggleChartSeries(series) {
    chartSeriesVisibility[series] = !chartSeriesVisibility[series];
    if (series === 'total') {
      const p = document.getElementById('pathTotal');
      const a = document.getElementById('pathAreaTotal');
      const btn = document.getElementById('toggleSeriesTotal');
      if (p) p.style.display = chartSeriesVisibility.total ? 'block' : 'none';
      if (a) a.style.display = chartSeriesVisibility.total ? 'block' : 'none';
      btn.classList.toggle('opacity-40', !chartSeriesVisibility.total);
    } else if (series === 'negative') {
      const p = document.getElementById('pathNegative');
      const btn = document.getElementById('toggleSeriesNegative');
      if (p) p.style.display = chartSeriesVisibility.negative ? 'block' : 'none';
      btn.classList.toggle('opacity-40', !chartSeriesVisibility.negative);
    } else if (series === 'positive') {
      const p = document.getElementById('pathPositive');
      const btn = document.getElementById('toggleSeriesPositive');
      if (p) p.style.display = chartSeriesVisibility.positive ? 'block' : 'none';
      btn.classList.toggle('opacity-40', !chartSeriesVisibility.positive);
    }
  }

  // Interactive Chart Tooltip
  function showChartTip(e, text) {
    const tip = document.getElementById('chartTooltip');
    if (!tip) return;
    tip.innerText = text;
    tip.style.left = (e.clientX - 100) + 'px';
    tip.style.top = (e.clientY - 40) + 'px';
    tip.classList.remove('hidden');
  }

  function hideChartTip() {
    const tip = document.getElementById('chartTooltip');
    if (tip) tip.classList.add('hidden');
  }

  // Threat Filter Pills
  function filterThreatTable(status) {
    const rows = document.querySelectorAll('.threat-row');
    rows.forEach(r => {
      if (status === 'all' || r.getAttribute('data-status') === status) {
        r.style.display = '';
      } else {
        r.style.display = 'none';
      }
    });

    ['all', 'review', 'unverified', 'identified'].forEach(k => {
      const btn = document.getElementById('filter-' + k);
      if (btn) {
        btn.classList.remove('bg-blue-600/20', 'border-blue-500/40', 'text-blue-400', 'font-bold');
        btn.classList.add('bg-slate-800/60', 'border-slate-700/60', 'text-slate-400');
      }
    });

    const activeMap = {
      'all': 'filter-all',
      'PERLU DITINJAU': 'filter-review',
      'BELUM TERVERIFIKASI': 'filter-unverified',
      'TERIDENTIFIKASI': 'filter-identified'
    };
    const activeBtn = document.getElementById(activeMap[status]);
    if (activeBtn) {
      activeBtn.classList.remove('bg-slate-800/60', 'border-slate-700/60', 'text-slate-400');
      activeBtn.classList.add('bg-blue-600/20', 'border-blue-500/40', 'text-blue-400', 'font-bold');
    }
  }

  // Threat Inspector Modal
  function inspectThreat(time, narrative, source, platform, status, code) {
    document.getElementById('modalThreatTime').innerText = time;
    document.getElementById('modalThreatNarrative').innerText = narrative;
    document.getElementById('modalThreatSource').innerText = `${source} (${platform})`;
    document.getElementById('modalThreatStatus').innerText = status;
    document.getElementById('modalThreatCode').innerText = `ID: ${code}`;
    const scanBtn = document.getElementById('btnSendScanner');
    if (scanBtn) {
      scanBtn.href = "{{ route('scanner.index') }}?q=" + encodeURIComponent(narrative);
    }
    document.getElementById('threatInspectModal').classList.remove('hidden');
  }

  function closeThreatModal() {
    document.getElementById('threatInspectModal').classList.add('hidden');
  }

  function copyThreatHash() {
    const hash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
    navigator.clipboard?.writeText(hash);
    const span = document.getElementById('copyHashText');
    if (span) {
      span.innerText = 'SHA-256 Tersalin!';
      setTimeout(() => span.innerText = 'Salin SHA-256 Hash', 2000);
    }
  }

  // Connector Diagnostics Ping Button
  function runConnectorDiagnostics() {
    const btn = document.getElementById('btnConnectorDiag');
    const icon = document.getElementById('diagIcon');
    const text = document.getElementById('diagBtnText');
    if (!btn) return;

    btn.disabled = true;
    icon.classList.add('animate-spin');
    text.innerText = 'Mengukur Latensi Konektor...';

    setTimeout(() => {
      // Simulate live random latencies
      const c = Math.floor(Math.random() * 25) + 105;
      const s = Math.floor(Math.random() * 20) + 85;
      const n = Math.floor(Math.random() * 30) + 95;
      const d = Math.floor(Math.random() * 15) + 70;

      document.getElementById('lat-crawler').innerText = `~${c}ms`;
      document.getElementById('lat-social').innerText = `~${s}ms`;
      document.getElementById('lat-news').innerText = `~${n}ms`;
      document.getElementById('lat-db').innerText = `~${d}ms`;

      icon.classList.remove('animate-spin');
      text.innerText = 'Diagnostik Selesai: 4 Konektor OK';
      btn.classList.add('border-emerald-500/50', 'text-emerald-400');

      setTimeout(() => {
        btn.disabled = false;
        text.innerText = 'Jalankan Tes Ping Diagnostik';
        btn.classList.remove('border-emerald-500/50', 'text-emerald-400');
      }, 3000);
    }, 1200);
  }

  // Regional Modal Drilldown
  function openRegionalModal(regionName, pct, kodamInfo) {
    document.getElementById('modalRegionTitle').innerText = regionName;
    document.getElementById('modalRegionPct').innerText = pct + '%';
    document.getElementById('modalRegionKodam').innerText = kodamInfo;
    document.getElementById('regionalDrilldownModal').classList.remove('hidden');
  }

  function openRegionalDrilldownFull() {
    openRegionalModal('Jawa (Prioritas Teritorial)', 42.1, 'Kodam Jaya, Kodam III/Siliwangi, Kodam IV/Diponegoro, Kodam V/Brawijaya');
  }

  function closeRegionalModal() {
    document.getElementById('regionalDrilldownModal').classList.add('hidden');
  }
</script>
@endpush