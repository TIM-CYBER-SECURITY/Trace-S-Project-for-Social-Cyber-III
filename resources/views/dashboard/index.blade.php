@extends('layouts.app')

@php
  $isId = app()->getLocale() === 'id';
  $aiStatus = app(\App\Services\Ai\AiManager::class)->status();
  $activeDriver = strtoupper($aiStatus['driver'] ?? 'MOCK');

  $buildSparkline = function (array $coords): string {
      if (empty($coords)) {
          return 'M 0 36 L 100 36';
      }
      $maxY = 190;
      $pts = [];
      $count = count($coords);
      foreach ($coords as $i => $c) {
          $sx = (int) round(($i / max(1, $count - 1)) * 100);
          $sy = (int) max(4, min(36, round(($c['y'] / $maxY) * 36)));
          $pts[] = $sx . ' ' . $sy;
      }
      return 'M ' . implode(' L ', $pts);
  };
  $sparkTotal = $buildSparkline($trajectory['total']['coords'] ?? []);
  $sparkNegative = $buildSparkline($trajectory['negative']['coords'] ?? []);
@endphp

@section('title', ($isId ? 'Dasbor Peringatan Dini' : 'Early Warning Dashboard') . ' // TRACE-S')

@section('content')
<div class="space-y-5">

  <!-- Header Bar -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
    <div>
      <div class="flex flex-wrap items-center gap-2">
        <h1 class="text-lg font-semibold tracking-tight">
          {{ $isId ? 'Dasbor Peringatan Dini Narasi' : 'Early Warning Narrative Dashboard' }}
        </h1>
        @if($hasSimulationData)
          <span class="badge badge-warn">{{ $isId ? 'SIMULASI' : 'SIMULATION' }}</span>
        @endif
      </div>
      <p class="text-xs text-[var(--text-muted)] mt-0.5">
        {{ $isId
          ? 'Pemantauan perkembangan narasi digital, agregasi paparan jaringan netral, dan status konektor data.'
          : 'Operational monitoring of digital narratives, aggregate network exposure, and ingestion connector status.' }}
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
      @if(Auth::user()->hasPermission('case.write'))
        <button type="button" onclick="document.getElementById('logNarrativeModal')?.classList.remove('hidden')"
                class="btn-primary py-1.5 px-3 text-xs">
          <i class="fa-solid fa-plus"></i>
          <span>{{ $isId ? 'Catat Narasi' : 'Log Narrative' }}</span>
        </button>
      @endif

      @if(Auth::user()->hasPermission('connector.use'))
        <form method="POST" action="{{ route('early-warning.sync-rss') }}" class="inline">
          @csrf
          <button type="submit" class="btn-secondary py-1.5 px-3 text-xs">
            <i class="fa-solid fa-rss"></i>
            <span>{{ $isId ? 'Sinkron RSS Berita' : 'Sync News RSS' }}</span>
          </button>
        </form>
      @endif

      <div class="relative" id="timeRangeDropdownWrapper">
        <button type="button" id="timeRangeBtn" onclick="toggleTimeRangeDropdown()"
                class="btn-secondary py-1.5 px-3 text-xs">
          <i class="fa-regular fa-calendar"></i>
          <span id="timeRangeLabel">{{ $isId ? '24 Jam Terakhir' : 'Last 24 Hours' }}</span>
          <i class="fa-solid fa-chevron-down text-[10px]"></i>
        </button>
        <div id="timeRangeMenu" class="hidden absolute right-0 mt-1 w-44 bg-[var(--bg-surface)] border border-[var(--border-strong)] rounded shadow-lg p-1 z-30 font-mono text-xs">
          <button type="button" onclick="setTimeRange('1h', '{{ $isId ? '1 Jam Terakhir' : 'Last 1 Hour' }}')" class="w-full text-left px-3 py-1.5 rounded hover:bg-[var(--bg-subtle)]">{{ $isId ? '1 Jam Terakhir' : 'Last 1 Hour' }}</button>
          <button type="button" onclick="setTimeRange('6h', '{{ $isId ? '6 Jam Terakhir' : 'Last 6 Hours' }}')" class="w-full text-left px-3 py-1.5 rounded hover:bg-[var(--bg-subtle)]">{{ $isId ? '6 Jam Terakhir' : 'Last 6 Hours' }}</button>
          <button type="button" onclick="setTimeRange('24h', '{{ $isId ? '24 Jam Terakhir' : 'Last 24 Hours' }}')" class="w-full text-left px-3 py-1.5 rounded hover:bg-[var(--bg-subtle)] font-semibold text-[var(--accent-olive)] dark:text-[#C5B89C]">{{ $isId ? '24 Jam Terakhir' : 'Last 24 Hours' }}</button>
          <button type="button" onclick="setTimeRange('7d', '{{ $isId ? '7 Hari Terakhir' : 'Last 7 Days' }}')" class="w-full text-left px-3 py-1.5 rounded hover:bg-[var(--bg-subtle)]">{{ $isId ? '7 Hari Terakhir' : 'Last 7 Days' }}</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Scope Notice -->
  <div class="panel-subtle p-3 flex flex-wrap items-center justify-between gap-2 text-xs">
    <div class="flex items-center gap-2 text-[var(--text-secondary)]">
      <i class="fa-solid fa-database text-[var(--accent-olive)]"></i>
      <span id="feedSourceScope">
        {{ $isId
          ? "Sumber data aktif: basis data internal Trace-S ({$casesCount} kasus, {$connectedSourcesCount} sumber rujukan)."
          : "Active Trace-S internal database records ({$casesCount} cases, {$connectedSourcesCount} reference sources)." }}
      </span>
    </div>
    <span class="font-mono text-[11px] text-[var(--text-muted)] uppercase">
      DRIVER: {{ $activeDriver }}
    </span>
  </div>

  <!-- 4 Top KPI Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3.5">
    <div class="panel p-4 flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <span class="label-meta">{{ $isId ? 'Narasi Terkumpul' : 'Narratives Ingested' }}</span>
        @if($hasSimulationData)
          <span class="badge badge-warn">{{ $isId ? 'SIMULASI' : 'SIMULATION' }}</span>
        @else
          <span class="badge badge-olive">{{ $isId ? 'AKTIF' : 'LIVE' }}</span>
        @endif
      </div>
      <div class="mt-3 flex items-end justify-between gap-2">
        <div>
          <div class="text-2xl font-bold font-mono" id="kpi-narratives">{{ number_format($narrativesCount) }}</div>
          <div class="text-[11px] text-[var(--text-muted)] mt-0.5">{{ $isId ? 'Rekaman narasi korpus' : 'Corpus narrative items' }}</div>
        </div>
        <svg class="w-20 h-9 shrink-0" viewBox="0 0 100 40" aria-hidden="true">
          <path d="{{ $sparkTotal }}" fill="none" stroke="#586B47" stroke-width="2.2"/>
        </svg>
      </div>
    </div>

    <div class="panel p-4 flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <span class="label-meta">{{ $isId ? 'Temuan Kritis' : 'Critical Findings' }}</span>
        <span class="badge badge-danger">{{ $isId ? 'PRIORITAS' : 'CRITICAL' }}</span>
      </div>
      <div class="mt-3 flex items-end justify-between gap-2">
        <div>
          <div class="text-2xl font-bold font-mono text-[#991B1B] dark:text-[#FCA5A5]" id="kpi-critical">{{ number_format($criticalCount) }}</div>
          <div class="text-[11px] text-[var(--text-muted)] mt-0.5">{{ $isId ? 'Perlu validasi analis' : 'Requires analyst review' }}</div>
        </div>
        <svg class="w-20 h-9 shrink-0" viewBox="0 0 100 40" aria-hidden="true">
          <path d="M 0 34 L 35 28 L 70 22 L 100 {{ $criticalCount > 0 ? 12 : 34 }}" fill="none" stroke="#991B1B" stroke-width="2.2"/>
        </svg>
      </div>
    </div>

    <div class="panel p-4 flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <span class="label-meta">{{ $isId ? 'Rasio Polaritas Negatif' : 'Negative Tone Ratio' }}</span>
        <span class="badge badge-sand">{{ $isId ? 'KORPUS' : 'CORPUS' }}</span>
      </div>
      <div class="mt-3 flex items-end justify-between gap-2">
        <div>
          <div class="text-2xl font-bold font-mono" id="kpi-sentiment">{{ $sentimentRatio }}%</div>
          <div class="text-[11px] text-[var(--text-muted)] mt-0.5">{{ $isId ? 'Dihitung dari DB aktif' : 'Computed from active DB' }}</div>
        </div>
        <svg class="w-20 h-9 shrink-0" viewBox="0 0 100 40" aria-hidden="true">
          <path d="{{ $sparkNegative }}" fill="none" stroke="#B47818" stroke-width="2.2"/>
        </svg>
      </div>
    </div>

    <div class="panel p-4 flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <span class="label-meta">{{ $isId ? 'Klaster Narasi Aktif' : 'Active Narrative Clusters' }}</span>
        <span class="badge badge-olive">{{ $casesCount }} {{ $isId ? 'KASUS' : 'CASES' }}</span>
      </div>
      <div class="mt-3 flex items-end justify-between gap-2">
        <div>
          <div class="text-2xl font-bold font-mono" id="kpi-clusters">{{ number_format($activeClustersCount) }}</div>
          <div class="text-[11px] text-[var(--text-muted)] mt-0.5">{{ $isId ? 'Klaster lintas platform' : 'Cross-platform clusters' }}</div>
        </div>
        <svg class="w-20 h-9 shrink-0" viewBox="0 0 100 40" aria-hidden="true">
          <path d="M 0 34 L 40 26 L 75 20 L 100 {{ $activeClustersCount > 0 ? 14 : 34 }}" fill="none" stroke="#46514C" stroke-width="2.2"/>
        </svg>
      </div>
    </div>
  </div>

  <!-- Middle Row: Narrative Velocity Chart (7 Cols) + Geospatial & Network Exposure Grid (5 Cols) -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <!-- Narrative Development Chart -->
    <div class="lg:col-span-7 panel flex flex-col">
      <div class="panel-header flex-wrap">
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-chart-line text-[var(--accent-olive)] text-xs"></i>
          <span class="font-mono text-xs font-semibold uppercase tracking-wider">
            {{ $isId ? 'Perkembangan Volume Narasi' : 'Narrative Volume Trajectory' }}
          </span>
          @if($hasSimulationData)
            <span class="badge badge-warn">{{ $isId ? 'SIMULASI' : 'SIMULATION' }}</span>
          @endif
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
          <button type="button" onclick="toggleChartSeries('total')" id="toggleSeriesTotal"
                  class="px-2 py-0.5 rounded border border-[#586B47] bg-[#586B47]/15 text-[11px] font-mono flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-[#586B47]"></span>
            <span>{{ $isId ? 'Total' : 'Total' }}</span>
          </button>
          <button type="button" onclick="toggleChartSeries('negative')" id="toggleSeriesNegative"
                  class="px-2 py-0.5 rounded border border-[#B47818] bg-[#B47818]/15 text-[11px] font-mono flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-[#B47818]"></span>
            <span>{{ $isId ? 'Negatif' : 'Negative' }}</span>
          </button>
          <button type="button" onclick="toggleChartSeries('positive')" id="toggleSeriesPositive"
                  class="px-2 py-0.5 rounded border border-[#46514C] bg-[#46514C]/15 text-[11px] font-mono flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-[#46514C]"></span>
            <span>{{ $isId ? 'Positif' : 'Positive' }}</span>
          </button>
        </div>
      </div>

      <div class="p-4 flex-1 flex flex-col justify-between space-y-2">
        <div class="relative w-full h-60 panel-subtle p-3 flex">
          <div class="w-9 h-full flex flex-col justify-between text-[10px] font-mono text-[var(--text-muted)] text-right pr-2 select-none pb-5">
            <span id="axisMax">{{ $trajectory['max_value'] ?? 1 }}</span>
            <span>{{ (int) round(($trajectory['max_value'] ?? 1) * 0.5) }}</span>
            <span>0</span>
          </div>

          <div class="relative flex-1 h-full flex flex-col justify-between">
            <svg id="narrativeChartSvg" class="w-full h-[calc(100%-20px)] overflow-visible" viewBox="0 0 600 190" preserveAspectRatio="none" aria-label="Narrative volume trajectory chart">
              <line x1="0" y1="0" x2="600" y2="0" stroke="currentColor" stroke-opacity="0.15" stroke-width="1" stroke-dasharray="3 3"/>
              <line x1="0" y1="95" x2="600" y2="95" stroke="currentColor" stroke-opacity="0.15" stroke-width="1" stroke-dasharray="3 3"/>
              <line x1="0" y1="190" x2="600" y2="190" stroke="currentColor" stroke-opacity="0.3" stroke-width="1"/>

              <path id="pathAreaTotal" d="{{ $trajectory['total']['area'] ?? 'M 0 190 L 600 190 Z' }}" fill="#586B47" fill-opacity="0.14"/>
              <path id="pathTotal" d="{{ $trajectory['total']['path'] ?? 'M 0 190 L 600 190' }}" fill="none" stroke="#586B47" stroke-width="2.5"/>
              <g id="chartPointsGroup">
                @foreach(($trajectory['total']['coords'] ?? []) as $i => $pt)
                  <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="4" fill="#586B47" class="cursor-pointer"
                          onmouseenter="showChartTip(evt, '{{ $trajectory['labels'][$i] ?? '' }} UTC — {{ $pt['val'] }} {{ $isId ? 'narasi' : 'narratives' }}')"
                          onmouseleave="hideChartTip()"/>
                @endforeach
              </g>

              <path id="pathNegative" d="{{ $trajectory['negative']['path'] ?? 'M 0 190 L 600 190' }}" fill="none" stroke="#B47818" stroke-width="2"/>
              <path id="pathPositive" d="{{ $trajectory['positive']['path'] ?? 'M 0 190 L 600 190' }}" fill="none" stroke="#46514C" stroke-width="1.8"/>
            </svg>

            <div id="chartTooltip" class="hidden fixed pointer-events-none px-2 py-1 bg-[#17251F] border border-[#C5B89C] text-[#F2F2EC] text-[10px] font-mono rounded shadow z-40"></div>

            <div id="chartAxisLabels" class="w-full flex justify-between text-[10px] font-mono text-[var(--text-muted)] pt-1 select-none">
              @foreach(($trajectory['labels'] ?? ['00:00','04:00','08:00','12:00','16:00','20:00','24:00']) as $lbl)
                <span>{{ $lbl }}</span>
              @endforeach
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] font-mono text-[var(--text-muted)]">
          <span>{{ $isId ? 'Dihitung langsung dari tabel narratives aktif' : 'Computed directly from active narratives table' }}</span>
          <span>{{ $narrativesCount }} {{ $isId ? 'REKAMAN' : 'RECORDS' }}</span>
        </div>
      </div>
    </div>

    <!-- Geospatial & Network Exposure Grid -->
    <div class="lg:col-span-5 panel flex flex-col">
      <div class="panel-header">
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-network-wired text-[var(--accent-olive)] text-xs"></i>
          <span class="font-mono text-xs font-semibold uppercase tracking-wider">
            {{ $isId ? 'Grid Paparan Geospasial & Jaringan' : 'Geospatial & Network Exposure Grid' }}
          </span>
        </div>
        @if($hasSimulationData)
          <span class="badge badge-warn">{{ $isId ? 'SIMULASI' : 'SIMULATION' }}</span>
        @endif
      </div>

      <div class="p-3.5 flex-1 flex flex-col justify-between space-y-3">
        <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
          @forelse($exposureGrid as $grid)
          <div class="panel-subtle p-2.5 font-mono text-[11px] space-y-1">
            <div class="flex flex-wrap items-center justify-between gap-1">
              <span class="font-semibold text-[var(--text-primary)]">
                {{ $grid['island'] }} &rarr; {{ $grid['regency'] }} &bull; {{ $grid['district'] }}
              </span>
              <span class="badge {{ $grid['velocity_status'] === 'HIGH' ? 'badge-warn' : 'badge-neutral' }}">
                {{ $grid['velocity_status'] }}
              </span>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 text-[10px] text-[var(--text-secondary)]">
              <span class="px-1.5 py-0.5 rounded bg-[var(--bg-surface)] border border-[var(--border-color)]">{{ $grid['subnet'] }}</span>
              <span>{{ $grid['asn'] }}</span>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 text-[10px] text-[var(--text-muted)] pt-0.5">
              <span>{{ $isId ? 'Narasi Terpantau:' : 'Tracked Narratives:' }} <b>{{ $grid['narrative_count'] }}</b></span>
              <span>{{ $isId ? 'Tinggi/Kritis:' : 'High/Critical:' }} <b>{{ $grid['critical_count'] }}</b></span>
            </div>
          </div>
          @empty
          <div class="p-6 text-center text-xs font-mono text-[var(--text-muted)]">
            {{ $isId ? 'Belum ada rekaman narasi wilayah di basis data.' : 'No regional narrative records stored in database.' }}
          </div>
          @endforelse
        </div>

        <div class="flex items-center justify-between text-[11px] font-mono pt-2 border-t border-[var(--border-color)]">
          <span class="text-[var(--text-muted)]">{{ count($exposureGrid) }} {{ $isId ? 'segmen wilayah terpantau' : 'active network segments' }}</span>
          @if(count($exposureGrid) > 0)
            <button type="button" onclick="openExposureModal()" class="text-[var(--accent-olive)] dark:text-[#C5B89C] hover:underline font-semibold">
              {{ $isId ? 'Buka Grid Lengkap' : 'Expand Exposure Grid' }} &rarr;
            </button>
          @endif
        </div>
      </div>
    </div>

  </div>

  <!-- Bottom Row: Live Threat Feed (8 Cols) + Data Connectors Status (4 Cols) -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <!-- Live Threat Feed -->
    <div class="lg:col-span-8 panel">
      <div class="panel-header flex-wrap">
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-list-check text-[var(--accent-olive)] text-xs"></i>
          <span class="font-mono text-xs font-semibold uppercase tracking-wider">Live Narrative Feed</span>
          @if($hasSimulationData)
            <span class="badge badge-warn">{{ $isId ? 'SIMULASI' : 'SIMULATION' }}</span>
          @endif
        </div>

        <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-mono">
          <button type="button" onclick="filterThreatTable('ALL')" id="filter-ALL" class="px-2 py-1 rounded border border-[#586B47] bg-[#586B47]/15 font-semibold">{{ $isId ? 'Semua' : 'All' }}</button>
          <button type="button" onclick="filterThreatTable('KRITIS')" id="filter-KRITIS" class="px-2 py-1 rounded border border-[var(--border-color)] text-[var(--text-muted)]">KRITIS</button>
          <button type="button" onclick="filterThreatTable('TINGGI')" id="filter-TINGGI" class="px-2 py-1 rounded border border-[var(--border-color)] text-[var(--text-muted)]">TINGGI</button>
          <button type="button" onclick="filterThreatTable('SEDANG')" id="filter-SEDANG" class="px-2 py-1 rounded border border-[var(--border-color)] text-[var(--text-muted)]">SEDANG</button>
          <button type="button" onclick="filterThreatTable('RENDAH')" id="filter-RENDAH" class="px-2 py-1 rounded border border-[var(--border-color)] text-[var(--text-muted)]">RENDAH</button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="table-mil">
          <thead>
            <tr>
              <th>{{ $isId ? 'Waktu' : 'Time' }}</th>
              <th>{{ $isId ? 'Ringkasan Narasi' : 'Narrative Summary' }}</th>
              <th>{{ $isId ? 'Wilayah' : 'Region' }}</th>
              <th>{{ $isId ? 'Tingkat / Sentimen' : 'Severity / Sentiment' }}</th>
              <th class="text-right">{{ $isId ? 'Aksi' : 'Action' }}</th>
            </tr>
          </thead>
          <tbody id="threatTableBody">
            @forelse($narratives as $row)
            @php
              $timeLabel = ($row->captured_at ?? $row->created_at)?->format('H:i:s') ?? '-';
              $sev = strtoupper((string) ($row->severity ?? 'RENDAH'));
              $sent = strtoupper((string) ($row->sentiment ?? 'NETRAL'));
              $code = 'NARR-' . str_pad((string) $row->id, 4, '0', STR_PAD_LEFT);
            @endphp
            <tr class="threat-row" data-severity="{{ $sev }}">
              <td class="font-mono text-xs text-[var(--text-muted)] whitespace-nowrap">{{ $timeLabel }}</td>
              <td class="text-xs font-medium max-w-xs">
                {{ $row->title }}
                @if($row->is_simulation)
                  <span class="badge badge-warn ml-1">SIMULASI</span>
                @endif
              </td>
              <td class="text-xs text-[var(--text-secondary)] whitespace-nowrap">{{ $row->region ?? 'Nasional' }}</td>
              <td class="whitespace-nowrap space-x-1">
                <span class="badge {{ $sev === 'KRITIS' ? 'badge-danger' : ($sev === 'TINGGI' ? 'badge-warn' : 'badge-neutral') }}">{{ $sev }}</span>
                <span class="badge badge-neutral">{{ $sent }}</span>
              </td>
              <td class="text-right">
                <button type="button"
                        onclick='inspectThreat(@json($timeLabel), @json($row->title), @json($row->region ?? "Nasional"), @json($sev . " / " . $sent), @json($code), @json($row->case_id), @json($row->id))'
                        class="btn-secondary py-1 px-2 text-[11px]">
                  {{ $isId ? 'Inspeksi' : 'Inspect' }}
                </button>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="5" class="text-center text-xs text-[var(--text-muted)] py-6">
                {{ $isId ? 'Belum ada rekaman narasi aktif di basis data.' : 'No active narrative records in database.' }}
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <!-- Data Connectors Honest Status -->
    <div class="lg:col-span-4 panel flex flex-col justify-between">
      <div>
        <div class="panel-header">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-plug text-[var(--accent-olive)] text-xs"></i>
            <span class="font-mono text-xs font-semibold uppercase tracking-wider">
              {{ $isId ? 'Status Konektor Data' : 'Data Connector Status' }}
            </span>
          </div>
          <span class="badge badge-neutral">{{ $connectors->count() }} {{ $isId ? 'MODUL' : 'MODULES' }}</span>
        </div>

        <div class="p-3.5 space-y-2.5">
          @forelse($connectors as $conn)
          <div class="panel-subtle p-2.5 flex items-center justify-between gap-2 text-xs">
            <div class="min-w-0">
              <div class="font-semibold truncate">{{ $conn->name }}</div>
              <div class="text-[11px] text-[var(--text-muted)] truncate">{{ $conn->description }}</div>
            </div>
            <span class="badge shrink-0 {{ $conn->status === 'ACTIVE' ? 'badge-olive' : ($conn->status === 'SIMULASI' ? 'badge-warn' : 'badge-neutral') }}">
              {{ $conn->status }}
            </span>
          </div>
          @empty
          <p class="text-xs text-[var(--text-muted)]">{{ $isId ? 'Belum ada konektor terdaftar.' : 'No connectors registered.' }}</p>
          @endforelse
        </div>
      </div>

      <div class="p-3.5 border-t border-[var(--border-color)]">
        <button type="button" onclick="refreshThreatFeed()" id="btnRefreshFeed" class="btn-secondary w-full justify-center text-xs">
          <i class="fa-solid fa-arrows-rotate" id="refreshFeedIcon"></i>
          <span id="refreshFeedText">{{ $isId ? 'Muat Ulang Feed' : 'Refresh Feed' }}</span>
        </button>
      </div>
    </div>

  </div>

  <!-- Recent Investigation Cases Overview -->
  <div class="panel">
    <div class="panel-header">
      <div>
        <h2 class="font-mono text-xs font-semibold uppercase tracking-wider">
          {{ $isId ? 'Kasus Investigasi Aktif' : 'Active Investigation Cases' }}
        </h2>
        <p class="text-[11px] text-[var(--text-muted)] mt-0.5">
          {{ $isId ? 'Daftar kasus siber sosial terbaru pada repositori internal.' : 'Recent social cyber cases in internal case repository.' }}
        </p>
      </div>
      <a href="{{ route('cases.index') }}" class="text-xs font-mono text-[var(--accent-olive)] dark:text-[#C5B89C] hover:underline font-semibold">
        {{ $isId ? 'Lihat Semua Kasus' : 'View All Cases' }} &rarr;
      </a>
    </div>

    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead>
          <tr>
            <th>{{ $isId ? 'Kode Kasus' : 'Case Code' }}</th>
            <th>{{ $isId ? 'Judul Investigasi' : 'Investigation Title' }}</th>
            <th>{{ $isId ? 'Kategori' : 'Category' }}</th>
            <th>{{ $isId ? 'Prioritas' : 'Priority' }}</th>
            <th>Status</th>
            <th class="text-right">{{ $isId ? 'Aksi' : 'Actions' }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recentCases as $rc)
          <tr>
            <td class="font-mono font-semibold whitespace-nowrap">
              <a href="{{ route('cases.show', $rc->id) }}" class="text-[var(--accent-olive)] dark:text-[#C5B89C] hover:underline">{{ $rc->case_code }}</a>
              @if($rc->is_simulation)
                <span class="badge badge-warn ml-1">SIMULASI</span>
              @endif
            </td>
            <td class="max-w-md font-medium">{{ $rc->title }}</td>
            <td class="text-xs text-[var(--text-muted)]">{{ $rc->category }}</td>
            <td>
              <span class="badge {{ $rc->priority === 'KRITIS' ? 'badge-danger' : ($rc->priority === 'TINGGI' ? 'badge-warn' : 'badge-neutral') }}">
                {{ $rc->priority }}
              </span>
            </td>
            <td><span class="badge badge-neutral">{{ $rc->status }}</span></td>
            <td class="text-right whitespace-nowrap space-x-1.5">
              @if(Auth::user()->hasPermission('analysis.run'))
                <a href="{{ route('scanner.index', ['case_id' => $rc->id]) }}" class="btn-secondary py-1 px-2 text-[11px]">
                  <i class="fa-solid fa-magnifying-glass"></i>
                  <span>Scanner</span>
                </a>
              @endif
              <a href="{{ route('cases.show', $rc->id) }}" class="btn-primary py-1 px-2 text-[11px]">
                <span>{{ $isId ? 'Detail' : 'Open' }}</span>
              </a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center text-[var(--text-muted)] py-6">
              {{ $isId ? 'Belum ada kasus investigasi terdaftar.' : 'No investigation cases registered.' }}
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Narrative Inspection Modal -->
<div id="threatInspectModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
  <div class="panel max-w-xl w-full shadow-2xl">
    <div class="panel-header">
      <div>
        <h3 class="font-mono text-xs font-semibold uppercase tracking-wider">
          {{ $isId ? 'Inspeksi Narasi Terdeteksi' : 'Detected Narrative Inspection' }}
        </h3>
        <div class="text-[11px] font-mono text-[var(--text-muted)]" id="modalThreatCode">ID: -</div>
      </div>
      <button type="button" onclick="closeThreatModal()" class="btn-secondary py-1 px-2 text-xs" aria-label="Close modal">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="p-4 space-y-3 text-xs">
      <div>
        <div class="label-meta mb-1">{{ $isId ? 'Ringkasan Narasi' : 'Narrative Summary' }}</div>
        <div class="panel-subtle p-3 font-medium" id="modalThreatNarrative">-</div>
      </div>

      <div class="grid grid-cols-3 gap-2 text-center font-mono">
        <div class="panel-subtle p-2">
          <div class="label-meta">{{ $isId ? 'Waktu' : 'Time' }}</div>
          <div class="font-semibold mt-0.5" id="modalThreatTime">-</div>
        </div>
        <div class="panel-subtle p-2">
          <div class="label-meta">{{ $isId ? 'Wilayah' : 'Region' }}</div>
          <div class="font-semibold mt-0.5" id="modalThreatSource">-</div>
        </div>
        <div class="panel-subtle p-2">
          <div class="label-meta">{{ $isId ? 'Evaluasi' : 'Evaluation' }}</div>
          <div class="font-semibold mt-0.5" id="modalThreatStatus">-</div>
        </div>
      </div>

      <div class="panel-subtle p-3 space-y-1">
        <div class="label-meta">{{ $isId ? 'Status Engine Analisis Aktif' : 'Active Analysis Engine Status' }}</div>
        <p class="text-[var(--text-secondary)] leading-relaxed">
          {{ $isId
            ? "Verifikasi lanjutan menggunakan driver aktif ({$activeDriver}) dilakukan dengan pencocokan terhadap bukti primer di Pemeriksa Kredibilitas."
            : "Detailed verification via active driver ({$activeDriver}) compares this claim against primary evidence in Credibility Scanner." }}
        </p>
      </div>
    </div>

    <div class="p-4 border-t border-[var(--border-color)] flex flex-wrap items-center justify-end gap-2">
      <button type="button" onclick="closeThreatModal()" class="btn-secondary">{{ $isId ? 'Tutup' : 'Close' }}</button>
      @if(Auth::user()->hasPermission('case.write'))
      <form id="escalateNarrativeForm" method="POST" action="#" class="inline hidden">
        @csrf
        <button type="submit" class="btn-secondary">
          <i class="fa-solid fa-folder-plus"></i>
          <span>{{ $isId ? 'Eskalasi ke Kasus' : 'Escalate to Case' }}</span>
        </button>
      </form>
      <a id="btnOpenLinkedCase" href="#" class="btn-secondary hidden">
        <i class="fa-solid fa-folder-open"></i>
        <span>{{ $isId ? 'Buka Kasus Terkait' : 'Open Linked Case' }}</span>
      </a>
      @endif
      @if(Auth::user()->hasPermission('analysis.run'))
      <a id="btnSendScanner" href="{{ route('scanner.index') }}" class="btn-primary">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>{{ $isId ? 'Buka di Scanner' : 'Inspect in Scanner' }}</span>
      </a>
      @endif
    </div>
  </div>
</div>

<!-- Exposure Grid Detail Modal -->
<div id="exposureGridModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
  <div class="panel max-w-3xl w-full shadow-2xl max-h-[90vh] flex flex-col">
    <div class="panel-header shrink-0">
      <div>
        <h3 class="font-mono text-xs font-semibold uppercase tracking-wider">
          {{ $isId ? 'Grid Paparan Geospasial & Jaringan' : 'Geospatial & Network Exposure Grid' }}
        </h3>
        <p class="text-[11px] text-[var(--text-muted)]">
          {{ $isId ? 'Agregasi wilayah, subnet /24, ASN & volume narasi terpantau' : 'Regional aggregation, /24 subnet, ASN & tracked narrative counts' }}
        </p>
      </div>
      <button type="button" onclick="closeExposureModal()" class="btn-secondary py-1 px-2 text-xs" aria-label="Close modal">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="p-4 overflow-y-auto space-y-3">
      <div class="overflow-x-auto">
        <table class="table-mil font-mono text-xs">
          <thead>
            <tr>
              <th>ID</th>
              <th>{{ $isId ? 'Wilayah (Pulau / Kab / Kec)' : 'Region (Island / Regency / District)' }}</th>
              <th>Subnet /24</th>
              <th>ASN</th>
              <th>{{ $isId ? 'Narasi' : 'Narratives' }}</th>
              <th>Velocity</th>
            </tr>
          </thead>
          <tbody>
            @foreach($exposureGrid as $g)
            <tr>
              <td class="font-semibold">{{ $g['id'] }}</td>
              <td class="font-sans">{{ $g['island'] }} &bull; {{ $g['regency'] }} &bull; {{ $g['district'] }}</td>
              <td><span class="badge badge-neutral">{{ $g['subnet'] }}</span></td>
              <td class="text-[11px]">{{ $g['asn'] }}</td>
              <td class="font-semibold">{{ $g['narrative_count'] }}</td>
              <td><span class="badge {{ $g['velocity_status'] === 'HIGH' ? 'badge-warn' : 'badge-neutral' }}">{{ $g['velocity_status'] }}</span></td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="p-4 border-t border-[var(--border-color)] flex justify-end shrink-0">
      <button type="button" onclick="closeExposureModal()" class="btn-primary">{{ $isId ? 'Tutup' : 'Close' }}</button>
    </div>
  </div>
</div>

@if(Auth::user()->hasPermission('case.write'))
<!-- Log Operational Narrative Modal -->
<div id="logNarrativeModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
  <div class="panel max-w-lg w-full shadow-2xl">
    <div class="panel-header">
      <h3 class="font-mono text-xs font-semibold uppercase tracking-wider">
        {{ $isId ? 'Catat Narasi Operasional Baru (Non-Simulasi)' : 'Log New Operational Narrative' }}
      </h3>
      <button type="button" onclick="document.getElementById('logNarrativeModal')?.classList.add('hidden')" class="btn-secondary py-1 px-2 text-xs" aria-label="Close modal">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form method="POST" action="{{ route('early-warning.narratives.store') }}" class="p-4 space-y-3 text-xs">
      @csrf
      <div>
        <label class="label-meta block mb-1">{{ $isId ? 'Ringkasan Narasi / Isu Terpantau' : 'Narrative Summary' }}</label>
        <textarea name="title" rows="3" required minlength="5" maxlength="250"
                  placeholder="{{ $isId ? 'Contoh: Beredar unggahan klaim penutupan akses pelabuhan di Surabaya...' : 'Observed narrative summary...' }}"
                  class="input-mil text-xs"></textarea>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="label-meta block mb-1">{{ $isId ? 'Wilayah' : 'Region' }}</label>
          <select name="region" class="input-mil font-mono text-xs">
            <option value="Jakarta">Jakarta</option>
            <option value="Surabaya">Surabaya</option>
            <option value="Medan">Medan</option>
            <option value="Balikpapan">Balikpapan</option>
            <option value="Makassar">Makassar</option>
            <option value="Jayapura">Jayapura</option>
            <option value="Nasional">Nasional</option>
          </select>
        </div>
        <div>
          <label class="label-meta block mb-1">{{ $isId ? 'Kategori' : 'Category' }}</label>
          <input type="text" name="category" value="MONITORING_LAPANGAN" required class="input-mil font-mono text-xs">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="label-meta block mb-1">{{ $isId ? 'Tingkat Risiko' : 'Severity' }}</label>
          <select name="severity" class="input-mil font-mono text-xs">
            <option value="AUTO">AUTO (Leksikon)</option>
            <option value="RENDAH">RENDAH</option>
            <option value="SEDANG">SEDANG</option>
            <option value="TINGGI">TINGGI</option>
            <option value="KRITIS">KRITIS</option>
          </select>
        </div>
        <div>
          <label class="label-meta block mb-1">{{ $isId ? 'Sentimen' : 'Sentiment' }}</label>
          <select name="sentiment" class="input-mil font-mono text-xs">
            <option value="AUTO">AUTO (Leksikon)</option>
            <option value="NEGATIF">NEGATIF</option>
            <option value="NETRAL">NETRAL</option>
            <option value="POSITIF">POSITIF</option>
          </select>
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-2 border-t border-[var(--border-color)]">
        <button type="button" onclick="document.getElementById('logNarrativeModal')?.classList.add('hidden')" class="btn-secondary">{{ $isId ? 'Batal' : 'Cancel' }}</button>
        <button type="submit" class="btn-primary">{{ $isId ? 'Simpan ke Database' : 'Save Record' }}</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection

@push('scripts')
<script>
  const chartSeriesVisibility = { total: true, negative: true, positive: true };
  let activeSeverityFilter = 'ALL';
  let currentRange = '24h';

  function toggleTimeRangeDropdown() {
    document.getElementById('timeRangeMenu')?.classList.toggle('hidden');
  }

  document.addEventListener('click', function (e) {
    const wrapper = document.getElementById('timeRangeDropdownWrapper');
    if (wrapper && !wrapper.contains(e.target)) {
      document.getElementById('timeRangeMenu')?.classList.add('hidden');
    }
  });

  function renderFeedTable(rows) {
    const tbody = document.getElementById('threatTableBody');
    if (!tbody) return;
    tbody.replaceChildren();

    if (!rows || !rows.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 5;
      td.className = 'text-center text-xs text-[var(--text-muted)] py-6';
      td.textContent = @json($isId ? 'Tidak ada rekaman narasi pada rentang waktu ini.' : 'No narrative records within selected time window.');
      tr.appendChild(td);
      tbody.appendChild(tr);
      return;
    }

    rows.forEach((row) => {
      const sev = String(row.severity || 'RENDAH').toUpperCase();
      const sent = String(row.sentiment || 'NETRAL').toUpperCase();
      const tr = document.createElement('tr');
      tr.className = 'threat-row';
      tr.setAttribute('data-severity', sev);
      if (activeSeverityFilter !== 'ALL' && sev !== activeSeverityFilter) {
        tr.style.display = 'none';
      }

      const tdTime = document.createElement('td');
      tdTime.className = 'font-mono text-xs text-[var(--text-muted)] whitespace-nowrap';
      tdTime.textContent = row.time || '-';

      const tdTitle = document.createElement('td');
      tdTitle.className = 'text-xs font-medium max-w-xs';
      tdTitle.textContent = row.title || '-';
      if (row.is_simulation) {
        const sim = document.createElement('span');
        sim.className = 'badge badge-warn ml-1';
        sim.textContent = 'SIMULASI';
        tdTitle.appendChild(sim);
      }

      const tdRegion = document.createElement('td');
      tdRegion.className = 'text-xs text-[var(--text-secondary)] whitespace-nowrap';
      tdRegion.textContent = row.region || 'Nasional';

      const tdStatus = document.createElement('td');
      tdStatus.className = 'whitespace-nowrap space-x-1';
      const sevBadge = document.createElement('span');
      sevBadge.className = 'badge ' + (sev === 'KRITIS' ? 'badge-danger' : (sev === 'TINGGI' ? 'badge-warn' : 'badge-neutral'));
      sevBadge.textContent = sev;
      const sentBadge = document.createElement('span');
      sentBadge.className = 'badge badge-neutral';
      sentBadge.textContent = sent;
      tdStatus.append(sevBadge, sentBadge);

      const tdAction = document.createElement('td');
      tdAction.className = 'text-right';
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn-secondary py-1 px-2 text-[11px]';
      btn.textContent = @json($isId ? 'Inspeksi' : 'Inspect');
      btn.addEventListener('click', () => inspectThreat(row.time, row.title, row.region, sev + ' / ' + sent, row.code, row.case_id, row.id));
      tdAction.appendChild(btn);

      tr.append(tdTime, tdTitle, tdRegion, tdStatus, tdAction);
      tbody.appendChild(tr);
    });
  }

  async function setTimeRange(range, label) {
    currentRange = range;
    const labelEl = document.getElementById('timeRangeLabel');
    if (labelEl) labelEl.textContent = label;
    document.getElementById('timeRangeMenu')?.classList.add('hidden');

    try {
      const res = await fetch("{{ route('api.threat-feed') }}?range=" + encodeURIComponent(range), {
        headers: { 'Accept': 'application/json' }
      });
      if (!res.ok) return;
      const json = await res.json();
      if (json.metrics) {
        const m = json.metrics;
        const fmt = (n) => Number(n).toLocaleString('en-US');
        document.getElementById('kpi-narratives').textContent = fmt(m.narratives_count ?? 0);
        document.getElementById('kpi-critical').textContent = fmt(m.critical_count ?? 0);
        document.getElementById('kpi-sentiment').textContent = (m.sentiment_ratio ?? 0) + '%';
        document.getElementById('kpi-clusters').textContent = fmt(m.active_clusters ?? 0);
      }
      if (json.trajectory) {
        const t = json.trajectory;
        document.getElementById('pathTotal')?.setAttribute('d', t.total?.path || 'M 0 190 L 600 190');
        document.getElementById('pathAreaTotal')?.setAttribute('d', t.total?.area || 'M 0 190 L 600 190 Z');
        document.getElementById('pathNegative')?.setAttribute('d', t.negative?.path || 'M 0 190 L 600 190');
        document.getElementById('pathPositive')?.setAttribute('d', t.positive?.path || 'M 0 190 L 600 190');
        const axisMax = document.getElementById('axisMax');
        if (axisMax) axisMax.textContent = t.max_value ?? 1;
        const axisLabels = document.getElementById('chartAxisLabels');
        if (axisLabels && Array.isArray(t.labels)) {
          axisLabels.replaceChildren(...t.labels.map((l) => {
            const sp = document.createElement('span');
            sp.textContent = l;
            return sp;
          }));
        }
      }
      if (json.data) {
        renderFeedTable(json.data);
      }
    } catch (_) {}
  }

  async function refreshThreatFeed() {
    const icon = document.getElementById('refreshFeedIcon');
    const txt = document.getElementById('refreshFeedText');
    if (icon) icon.classList.add('fa-spin');
    await setTimeRange(currentRange, document.getElementById('timeRangeLabel')?.textContent || '24h');
    setTimeout(() => {
      if (icon) icon.classList.remove('fa-spin');
      if (txt) txt.textContent = @json($isId ? 'Feed Diperbarui' : 'Feed Refreshed');
      setTimeout(() => {
        if (txt) txt.textContent = @json($isId ? 'Muat Ulang Feed' : 'Refresh Feed');
      }, 2000);
    }, 300);
  }

  function toggleChartSeries(series) {
    chartSeriesVisibility[series] = !chartSeriesVisibility[series];
    const map = {
      total: ['pathTotal', 'pathAreaTotal', 'toggleSeriesTotal'],
      negative: ['pathNegative', null, 'toggleSeriesNegative'],
      positive: ['pathPositive', null, 'toggleSeriesPositive'],
    };
    const [pathId, areaId, btnId] = map[series];
    const p = document.getElementById(pathId);
    const a = areaId ? document.getElementById(areaId) : null;
    const b = document.getElementById(btnId);
    if (p) p.style.display = chartSeriesVisibility[series] ? 'block' : 'none';
    if (a) a.style.display = chartSeriesVisibility[series] ? 'block' : 'none';
    if (b) b.classList.toggle('opacity-45', !chartSeriesVisibility[series]);
  }

  function showChartTip(e, text) {
    const tip = document.getElementById('chartTooltip');
    if (!tip) return;
    tip.textContent = text;
    tip.style.left = Math.max(8, e.clientX - 70) + 'px';
    tip.style.top = Math.max(8, e.clientY - 36) + 'px';
    tip.classList.remove('hidden');
  }

  function hideChartTip() {
    document.getElementById('chartTooltip')?.classList.add('hidden');
  }

  function filterThreatTable(severity) {
    activeSeverityFilter = severity;
    document.querySelectorAll('.threat-row').forEach(r => {
      r.style.display = (severity === 'ALL' || r.getAttribute('data-severity') === severity) ? '' : 'none';
    });
    ['ALL', 'KRITIS', 'TINGGI', 'SEDANG', 'RENDAH'].forEach(key => {
      const btn = document.getElementById('filter-' + key);
      if (!btn) return;
      if (key === severity) {
        btn.classList.remove('border-[var(--border-color)]', 'text-[var(--text-muted)]');
        btn.classList.add('border-[#586B47]', 'bg-[#586B47]/15', 'font-semibold');
      } else {
        btn.classList.remove('border-[#586B47]', 'bg-[#586B47]/15', 'font-semibold');
        btn.classList.add('border-[var(--border-color)]', 'text-[var(--text-muted)]');
      }
    });
  }

  function inspectThreat(time, narrative, source, status, code, caseId, narrativeId) {
    document.getElementById('modalThreatTime').textContent = time || '-';
    document.getElementById('modalThreatNarrative').textContent = narrative || '-';
    document.getElementById('modalThreatSource').textContent = source || '-';
    document.getElementById('modalThreatStatus').textContent = status || '-';
    document.getElementById('modalThreatCode').textContent = 'ID: ' + (code || '-');
    const scanBtn = document.getElementById('btnSendScanner');
    if (scanBtn) {
      let url = "{{ route('scanner.index') }}?prefill=" + encodeURIComponent(narrative || '');
      if (caseId) url += "&case_id=" + encodeURIComponent(caseId);
      scanBtn.href = url;
    }
    const escalateForm = document.getElementById('escalateNarrativeForm');
    const openCaseBtn = document.getElementById('btnOpenLinkedCase');
    if (escalateForm && openCaseBtn) {
      if (caseId) {
        escalateForm.classList.add('hidden');
        openCaseBtn.href = "{{ url('/cases') }}/" + encodeURIComponent(caseId);
        openCaseBtn.classList.remove('hidden');
      } else if (narrativeId) {
        openCaseBtn.classList.add('hidden');
        escalateForm.action = "{{ url('/early-warning/narratives') }}/" + encodeURIComponent(narrativeId) + "/escalate";
        escalateForm.classList.remove('hidden');
      } else {
        escalateForm.classList.add('hidden');
        openCaseBtn.classList.add('hidden');
      }
    }
    document.getElementById('threatInspectModal').classList.remove('hidden');
  }

  function closeThreatModal() {
    document.getElementById('threatInspectModal').classList.add('hidden');
  }

  function openExposureModal() {
    document.getElementById('exposureGridModal').classList.remove('hidden');
  }

  function closeExposureModal() {
    document.getElementById('exposureGridModal').classList.add('hidden');
  }
</script>
@endpush
