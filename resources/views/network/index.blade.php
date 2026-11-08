@extends('layouts.tactical')

@php
  $isId = app()->getLocale() === 'id';
@endphp

@section('title', ($isId ? 'Jaringan Penyebaran & Pola Akun' : 'Spread Network & Account Pattern Analysis') . ' // TRACE-S')

@section('content')

<!-- [ModuleCHeader] START -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div>
    <div class="flex items-center gap-2.5">
      <h1 class="text-sm font-semibold font-mono tracking-wide text-neutral-300 uppercase">
        {{ $isId ? 'Analisis Jaringan Penyebaran & Pola Akun' : 'Spread Network & Account Pattern Analysis' }}
      </h1>
      <span class="text-[10px] font-mono px-2 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 rounded-sm">
        MODULE C (§5.5)
      </span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">
      {{ $isId
        ? 'Memetakan hubungan penyebaran konten dalam dataset terkumpul dan menampilkan indikator otomatisasi akun (bukan identitas orang).'
        : 'Maps content spread relationships within the collected dataset and displays account automation indicators (never personal identities).' }}
    </p>
  </div>

  <!-- Case Filter -->
  <form action="{{ route('network.index') }}" method="GET" class="flex items-center gap-2 text-xs font-mono">
    <label class="text-[10px] uppercase tracking-widest text-neutral-500">{{ $isId ? 'KASUS:' : 'CASE:' }}</label>
    <select name="case_id" onchange="this.form.submit()" aria-label="Select case for spread network" class="bg-neutral-900 border border-neutral-800 text-neutral-300 px-3 py-1.5 rounded-sm focus:outline-none focus:border-cyan-500 text-xs font-mono">
      @foreach($cases as $c)
        <option value="{{ $c->id }}" {{ $selectedCase && $selectedCase->id === $c->id ? 'selected' : '' }}>
          {{ $c->case_code }} — {{ Str::limit($c->title, 35) }}
        </option>
      @endforeach
    </select>
  </form>
</div>
<!-- [ModuleCHeader] END -->

<!-- [DatasetScopeDisclaimerBanner] START -->
<div class="p-3 bg-neutral-900 border border-neutral-800 rounded-sm flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs font-mono">
  <div class="flex items-center gap-2 text-neutral-300">
    <i class="fa-solid fa-clock-rotate-left text-cyan-500"></i>
    <span>
      {{ $isId ? 'Titik Publikasi Paling Awal dalam Dataset Terkumpul:' : 'Earliest Publication Point in Collected Dataset:' }}
      <b class="text-cyan-500">{{ $earliestPost?->publisher ?? '@meme_nusantara_fiktif' }}</b>
      ({{ $earliestPost?->published_at ? $earliestPost->published_at->format('d M Y H:i') . ' UTC' : '2026-11-01 08:12 UTC' }} &middot; {{ $earliestPost?->platform ?? 'INSTAGRAM' }})
    </span>
  </div>
  <span class="text-[10px] px-2 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm">
    {{ $isId ? '* Bukan klaim sumber pertama di seluruh internet (§5.5)' : '* Not a claim of first publication on the entire internet (§5.5)' }}
  </span>
</div>
<!-- [DatasetScopeDisclaimerBanner] END -->

<!-- [SpreadNetworkGraphAndMetrics] START -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

  <!-- Left Column: Interactive Dot-Grid SVG Canvas (7 Cols — §5.5) -->
  <div class="lg:col-span-7 bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-2.5">
      <div class="text-xs font-mono uppercase tracking-wide text-neutral-300 font-semibold flex items-center gap-2">
        <i class="fa-solid fa-diagram-project text-cyan-500"></i>
        <span>{{ $isId ? 'Graf Relasi Penyebaran dalam Dataset' : 'Dataset Spread Relationship Graph' }}</span>
      </div>
      <span class="text-[10px] font-mono px-1.5 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm" title="is_simulation: true">
        {{ $isId ? 'SIMULASI (is_simulation: true)' : 'SIMULATION (is_simulation: true)' }}
      </span>
    </div>

    @php
      $satelliteNodes = [
        ['x' => 20, 'y' => 22, 'id' => 'N-02', 'handle' => '@politik_santuy_fiktif', 'ind' => 'MODERATE', 'rate' => '18/h', 'age' => '142d'],
        ['x' => 78, 'y' => 20, 'id' => 'N-03', 'handle' => '@info_warga_fiktif', 'ind' => 'HIGH', 'rate' => '42/h', 'age' => '14d'],
        ['x' => 84, 'y' => 60, 'id' => 'N-04', 'handle' => '@berita_viral_fiktif', 'ind' => 'LOW', 'rate' => '4/h', 'age' => '620d'],
        ['x' => 70, 'y' => 82, 'id' => 'N-05', 'handle' => '@suara_rakyat_fiktif', 'ind' => 'LOW', 'rate' => '3/h', 'age' => '410d'],
        ['x' => 28, 'y' => 82, 'id' => 'N-06', 'handle' => '@opini_terkini_fiktif', 'ind' => 'HIGH', 'rate' => '39/h', 'age' => '19d'],
        ['x' => 16, 'y' => 58, 'id' => 'N-07', 'handle' => '@wacana_kita_fiktif', 'ind' => 'LOW', 'rate' => '5/h', 'age' => '890d'],
      ];
    @endphp

    <!-- Custom Dot-Grid Interactive Canvas (Zero External Graph Library — §5.5) -->
    <div class="relative w-full h-80 bg-neutral-950 border border-neutral-800 rounded-sm overflow-hidden select-none">
      <!-- Monochrome Dot Grid Background -->
      <div class="absolute inset-0 bg-[radial-gradient(#262626_1px,transparent_1px)] [background-size:16px_16px]"></div>

      <!-- Custom SVG Relationship Edges -->
      <svg class="absolute inset-0 w-full h-full pointer-events-none z-10" aria-label="Network spread connections">
        @foreach($satelliteNodes as $node)
        <line x1="50%" y1="50%" x2="{{ $node['x'] }}%" y2="{{ $node['y'] }}%"
              stroke="{{ $node['ind'] === 'HIGH' ? '#e11d48' : ($node['ind'] === 'MODERATE' ? '#f59e0b' : '#06b6d4') }}"
              stroke-width="1.5" stroke-dasharray="3 3"/>
        @endforeach
      </svg>

      <!-- Center Node: Mandatory Label (§5.5) -->
      <button type="button"
              onclick="selectGraphNode('N-01', '@meme_nusantara_fiktif', 'HIGH', '36/h', '21d', '{{ $isId ? 'Titik Publikasi Paling Awal dalam Dataset Terkumpul' : 'Earliest Publication Point in Collected Dataset' }}')"
              aria-label="Inspect earliest node in dataset"
              class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-20 flex flex-col items-center group focus:outline-none">
        <div class="w-11 h-11 rounded-full bg-neutral-900 border-2 border-cyan-500 flex items-center justify-center text-cyan-500 font-mono font-semibold text-xs">
          N-01
        </div>
        <div class="mt-1 px-2 py-0.5 bg-neutral-900 border border-cyan-500/50 rounded-sm text-[10px] font-mono text-cyan-500 text-center">
          {{ $isId ? 'Titik Publikasi Paling Awal dalam Dataset Terkumpul' : 'Earliest Publication Point in Collected Dataset' }}<br>
          <span class="text-neutral-300">@meme_nusantara_fiktif</span>
        </div>
      </button>

      <!-- Satellite Account Nodes -->
      @foreach($satelliteNodes as $node)
      <button type="button"
              onclick="selectGraphNode('{{ $node['id'] }}', '{{ $node['handle'] }}', '{{ $node['ind'] }}', '{{ $node['rate'] }}', '{{ $node['age'] }}', 'Amplification Relay Node')"
              aria-label="Inspect node {{ $node['handle'] }}"
              style="left: {{ $node['x'] }}%; top: {{ $node['y'] }}%;"
              class="absolute -translate-x-1/2 -translate-y-1/2 z-20 flex flex-col items-center focus:outline-none">
        <div class="w-8 h-8 rounded-full bg-neutral-900 border flex items-center justify-center font-mono text-[10px] font-semibold hover:bg-neutral-800/50 {{ $node['ind'] === 'HIGH' ? 'border-rose-600/50 text-rose-600' : ($node['ind'] === 'MODERATE' ? 'border-amber-500/30 text-amber-500' : 'border-cyan-500/50 text-cyan-500') }}">
          {{ $node['id'] }}
        </div>
        <div class="mt-1 px-1.5 py-0.5 bg-neutral-900 border border-neutral-800 rounded-sm text-[10px] font-mono text-neutral-300 whitespace-nowrap">
          {{ $node['handle'] }}
        </div>
      </button>
      @endforeach
    </div>

    <!-- Interactive Node Inspector Bar -->
    <div id="nodeInspectorBar" class="p-2.5 bg-neutral-950 border border-neutral-800 rounded-sm flex flex-wrap items-center justify-between gap-2 font-mono text-xs">
      <div>
        <span class="text-[10px] text-neutral-500 uppercase tracking-widest">{{ $isId ? 'NODE TERPILIH:' : 'SELECTED NODE:' }}</span>
        <span id="inspNodeHandle" class="text-cyan-500 font-semibold ml-1">@meme_nusantara_fiktif (N-01)</span>
      </div>
      <div class="flex items-center gap-3 text-[11px]">
        <span>{{ $isId ? 'Frekuensi:' : 'Post Rate:' }} <b id="inspNodeRate" class="text-neutral-300">36/h</b></span>
        <span>{{ $isId ? 'Umur Akun:' : 'Account Age:' }} <b id="inspNodeAge" class="text-neutral-300">21d</b></span>
        <span>Automation Indicator: <b id="inspNodeInd" class="text-amber-500">HIGH</b></span>
      </div>
    </div>
  </div>

  <!-- Right Column: Platform Distribution & Cluster Summary (5 Cols) -->
  <div class="lg:col-span-5 space-y-4">

    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
      <div class="text-xs font-mono uppercase tracking-wide text-neutral-300 font-semibold flex items-center justify-between border-b border-neutral-800 pb-2.5">
        <span>{{ $isId ? 'Sebaran Platform dalam Dataset' : 'Platform Distribution in Dataset' }}</span>
        <i class="fa-solid fa-share-nodes text-cyan-500"></i>
      </div>

      <div class="space-y-2.5 font-mono text-xs">
        @forelse($platformDistribution as $plat => $cnt)
        @php
          $pct = $posts->count() > 0 ? round(($cnt / $posts->count()) * 100) : 0;
        @endphp
        <div class="space-y-1">
          <div class="flex items-center justify-between text-xs">
            <span class="text-neutral-300">{{ $plat }}</span>
            <span class="text-cyan-500">{{ $cnt }} {{ $isId ? 'unggahan' : 'posts' }} ({{ $pct }}%)</span>
          </div>
          <div class="w-full h-1.5 bg-neutral-950 border border-neutral-800 overflow-hidden">
            <div class="h-full bg-cyan-500" style="width: {{ $pct }}%;"></div>
          </div>
        </div>
        @empty
        <div class="text-neutral-500 text-xs">—</div>
        @endforelse
      </div>
    </div>

    <!-- Automation Indicator Policy Card (§2.7 & §2A-A) -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-2">
      <div class="text-xs font-mono uppercase tracking-wide text-amber-500 font-semibold">
        {{ $isId ? 'Prinsip Indikator Otomatisasi (§2.7 & §5.5)' : 'Automation Indicator Principle (§2.7 & §5.5)' }}
      </div>
      <p class="text-xs text-neutral-300 leading-relaxed font-sans">
        {{ $isId
          ? 'Kemiripan waktu unggah atau teks dinamai "Automation Indicator" (Rendah/Sedang/Tinggi), bukan vonis jaringan bot atau pelaku terkoordinasi tanpa bukti digital memadai.'
          : 'Similar posting times or text patterns are labeled as an "Automation Indicator" (Low/Medium/High), not a definitive bot verdict or coordinated actor accusation.' }}
      </p>
      <div class="pt-2 border-t border-neutral-800 text-[10px] font-mono text-neutral-500">
        {{ $isId
          ? 'Larangan §2A-A: Skema database & UI dilarang memuat nama asli, alamat rumah, nomor KTP/telepon, atau relasi keluarga.'
          : 'Prohibition §2A-A: Database schema & UI strictly exclude real names, home addresses, ID/phone numbers, or family relations.' }}
      </div>
    </div>

  </div>

</div>
<!-- [SpreadNetworkGraphAndMetrics] END -->

<!-- [AccountPatternRosterTable] START -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-neutral-800 pb-2.5">
    <div>
      <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-neutral-300 flex items-center gap-2">
        <i class="fa-solid fa-table-list text-cyan-500 text-xs"></i>
        <span>{{ $isId ? 'Roster Akun (Bukan Roster Orang — §5.5)' : 'Account Roster (Not Person Roster — §5.5)' }}</span>
      </h2>
      <p class="text-xs text-neutral-500 mt-0.5">
        {{ $isId
          ? 'Hanya menampilkan handle publik, frekuensi posting, umur akun, dan Automation Indicator (Rendah / Sedang / Tinggi).'
          : 'Displays only public handle, posting frequency, account age, and Automation Indicator (Low / Medium / High).' }}
      </p>
    </div>
    <span class="text-[10px] font-mono px-2 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm">
      NO PERSONAL IDENTITY RESOLUTION (§2A-A)
    </span>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-widest text-neutral-500 bg-neutral-950">
          <th class="py-2 px-3">{{ $isId ? 'HANDLE PUBLIK' : 'PUBLIC HANDLE' }}</th>
          <th class="py-2 px-3">PLATFORM</th>
          <th class="py-2 px-3">{{ $isId ? 'FREKUENSI POSTING' : 'POSTING FREQUENCY' }}</th>
          <th class="py-2 px-3">{{ $isId ? 'UMUR AKUN' : 'ACCOUNT AGE' }}</th>
          <th class="py-2 px-3">AUTOMATION INDICATOR</th>
          <th class="py-2 px-3">{{ $isId ? 'DASAR POLA' : 'PATTERN BASIS' }}</th>
          <th class="py-2 px-3 text-right">STATUS</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800 leading-tight">
        @forelse($actors as $act)
        <tr class="hover:bg-neutral-800/50 transition-opacity duration-150">
          <td class="py-2.5 px-3 font-semibold text-neutral-300">
            {{ $act->username }}
            @if($act->is_earliest_in_dataset)
              <span class="block text-[10px] text-cyan-500 font-normal">
                {{ $isId ? 'Titik Publikasi Paling Awal dalam Dataset Terkumpul' : 'Earliest Publication Point in Collected Dataset' }}
              </span>
            @endif
          </td>
          <td class="py-2.5 px-3 text-xs text-neutral-500 uppercase">{{ $act->platform }}</td>
          <td class="py-2.5 px-3 text-neutral-300">
            {{ $act->post_rate ? $act->post_rate . ' posts/hour' : 'N/A' }}
          </td>
          <td class="py-2.5 px-3 text-neutral-300">
            {{ $act->account_age_days ? $act->account_age_days . ($isId ? ' hari' : ' days') : 'N/A' }}
          </td>
          <td class="py-2.5 px-3">
            @if($act->automation_indicator === 'TINGGI')
              <span class="px-2 py-0.5 text-rose-600 bg-rose-500/10 border border-rose-600/50 text-[10px] font-semibold uppercase rounded-sm">
                {{ $isId ? 'TINGGI' : 'HIGH' }}
              </span>
            @elseif($act->automation_indicator === 'SEDANG')
              <span class="px-2 py-0.5 text-amber-500 bg-amber-500/10 border border-amber-500/30 text-[10px] font-semibold uppercase rounded-sm">
                {{ $isId ? 'SEDANG' : 'MEDIUM' }}
              </span>
            @else
              <span class="px-2 py-0.5 text-cyan-500 bg-cyan-500/10 border border-cyan-500/50 text-[10px] uppercase rounded-sm">
                {{ $isId ? 'RENDAH' : 'LOW' }}
              </span>
            @endif
          </td>
          <td class="py-2.5 px-3 font-sans text-xs text-neutral-300 max-w-sm">
            {{ $act->indicator_basis ?? '-' }}
          </td>
          <td class="py-2.5 px-3 text-right">
            <span class="px-2 py-0.5 bg-neutral-950 border border-neutral-800 text-neutral-500 text-[10px] uppercase rounded-sm">
              {{ $act->status }}
            </span>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="py-6 text-center text-neutral-500">
            {{ $isId ? 'Belum ada data pola akun pada kasus ini.' : 'No account pattern records in this case dataset.' }}
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<!-- [AccountPatternRosterTable] END -->

@endsection

@push('scripts')
<!-- [ModuleCScripts] START -->
<script>
  function selectGraphNode(id, handle, indicator, rate, age, roleLabel) {
    document.getElementById('inspNodeHandle').textContent = `${handle} (${id}) — ${roleLabel}`;
    document.getElementById('inspNodeRate').textContent = rate;
    document.getElementById('inspNodeAge').textContent = age;
    document.getElementById('inspNodeInd').textContent = indicator;
  }
</script>
<!-- [ModuleCScripts] END -->
@endpush
