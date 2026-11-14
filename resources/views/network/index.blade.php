@extends('layouts.app')

@php
  $isId = app()->getLocale() === 'id';
  $earliestActor = $actors->firstWhere('is_earliest_in_dataset', true) ?? $actors->first();
  $satellites = $actors->filter(fn ($a) => !$earliestActor || $a->id !== $earliestActor->id)->values();
  $coords = [
    ['x' => 18, 'y' => 22], ['x' => 80, 'y' => 22], ['x' => 84, 'y' => 62],
    ['x' => 70, 'y' => 84], ['x' => 28, 'y' => 84], ['x' => 16, 'y' => 60],
    ['x' => 48, 'y' => 16], ['x' => 52, 'y' => 86],
  ];
@endphp

@section('title', ($isId ? 'Jaringan Penyebaran & Pola Akun' : 'Spread Network & Account Patterns') . ' // TRACE-S')

@section('content')
<div class="space-y-5">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
    <div>
      <div class="flex flex-wrap items-center gap-2">
        <h1 class="text-lg font-semibold tracking-tight">
          {{ $isId ? 'Analisis Jaringan Penyebaran & Pola Akun' : 'Spread Network & Account Pattern Analysis' }}
        </h1>
      </div>
      <p class="text-xs text-[var(--text-muted)] mt-0.5">
        {{ $isId
          ? 'Memetakan hubungan penyebaran konten pada dataset dan indikator otomatisasi akun (bukan identitas personal).'
          : 'Maps content spread relationships within collected dataset and account automation indicators (never personal identity).' }}
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <form action="{{ route('network.index') }}" method="GET" class="flex items-center gap-2 text-xs font-mono">
        <label class="label-meta">{{ $isId ? 'Kasus:' : 'Case:' }}</label>
        <select name="case_id" onchange="this.form.submit()" aria-label="Select case for spread network" class="input-mil py-1.5 font-mono text-xs w-auto max-w-xs">
          @foreach($cases as $c)
            <option value="{{ $c->id }}" {{ $selectedCase && $selectedCase->id === $c->id ? 'selected' : '' }}>
              {{ $c->case_code }} — {{ Str::limit($c->title, 35) }}
            </option>
          @endforeach
        </select>
      </form>

      @if($selectedCase && Auth::user()->hasPermission('analysis.run'))
      <form action="{{ route('network.cluster') }}" method="POST" class="inline">
        @csrf
        <input type="hidden" name="case_id" value="{{ $selectedCase->id }}">
        <button type="submit" class="btn-primary py-1.5 px-3 text-xs">
          <i class="fa-solid fa-diagram-project"></i>
          <span>{{ $isId ? 'Jalankan Klasterisasi' : 'Run Clustering' }}</span>
        </button>
      </form>
      @endif

      @if($selectedCase && Auth::user()->hasPermission('case.write'))
      <button type="button" onclick="document.getElementById('record-actor-modal')?.classList.remove('hidden')" class="btn-secondary py-1.5 px-3 text-xs">
        <i class="fa-solid fa-user-plus"></i>
        <span>{{ $isId ? 'Catat Pola Akun' : 'Log Public Handle' }}</span>
      </button>
      @endif

      @if($selectedCase)
      <a href="{{ route('cases.show', $selectedCase->id) }}" class="btn-secondary py-1.5 px-3 text-xs">
        <i class="fa-solid fa-folder-open"></i>
        <span>{{ $isId ? 'Dossier Kasus' : 'Case Dossier' }}</span>
      </a>
      @endif
    </div>
  </div>

  <!-- Earliest Publication Disclaimer Banner -->
  <div class="panel-subtle p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs font-mono">
    <div class="flex items-center gap-2">
      <i class="fa-solid fa-clock-rotate-left text-[var(--accent-olive)]"></i>
      <span>
        {{ $isId ? 'Titik Publikasi Paling Awal dalam Dataset:' : 'Earliest Publication Point in Collected Dataset:' }}
        <b>{{ $earliestPost?->publisher ?? $earliestActor?->username ?? '-' }}</b>
        @if($earliestPost?->published_at)
          ({{ $earliestPost->published_at->format('Y-m-d H:i') }} UTC &middot; {{ $earliestPost->platform }})
        @endif
      </span>
    </div>
    <span class="badge badge-warn">
      {{ $isId ? '* Cakupan terbatas pada dataset kasus aktif' : '* Scoped strictly to active case dataset' }}
    </span>
  </div>

  <!-- Interactive Graph + Distribution -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <div class="lg:col-span-7 panel p-4 space-y-3">
      <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-2.5">
        <span class="font-mono text-xs font-semibold uppercase tracking-wider">
          {{ $isId ? 'Graf Relasi Penyebaran dalam Dataset' : 'Dataset Spread Relationship Graph' }}
        </span>
      </div>

      <div class="relative w-full h-80 panel-subtle overflow-hidden select-none">
        <svg class="absolute inset-0 w-full h-full pointer-events-none" aria-label="Network spread connections">
          @foreach($satellites as $idx => $node)
            @php
              $pos = $coords[$idx % count($coords)];
              $stroke = $node->automation_indicator === 'TINGGI' ? '#991B1B' : ($node->automation_indicator === 'SEDANG' ? '#B47818' : '#586B47');
            @endphp
            <line x1="50%" y1="50%" x2="{{ $pos['x'] }}%" y2="{{ $pos['y'] }}%" stroke="{{ $stroke }}" stroke-width="1.5" stroke-dasharray="4 3"/>
          @endforeach
        </svg>

        @if($earliestActor)
        <button type="button"
                onclick='selectGraphNode("N-01", @json($earliestActor->username), @json($earliestActor->automation_indicator), @json(($earliestActor->post_rate ?? "-") . "/h"), @json(($earliestActor->account_age_days ?? "-") . "d"), @json($isId ? "Titik Publikasi Paling Awal" : "Earliest Point in Dataset"))'
                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-20 flex flex-col items-center focus:outline-none">
          <div class="w-11 h-11 rounded-full bg-[#17251F] border-2 border-[#C5B89C] flex items-center justify-center text-[#F2F2EC] font-mono font-bold text-xs shadow">
            N-01
          </div>
          <div class="mt-1 px-2 py-0.5 bg-[var(--bg-surface)] border border-[var(--border-strong)] rounded text-[10px] font-mono">
            {{ $earliestActor->username }}
          </div>
        </button>
        @endif

        @foreach($satellites as $idx => $node)
          @php
            $pos = $coords[$idx % count($coords)];
            $nodeNum = 'N-' . str_pad((string) ($idx + 2), 2, '0', STR_PAD_LEFT);
            $borderClass = $node->automation_indicator === 'TINGGI' ? 'border-[#991B1B] text-[#991B1B] dark:text-[#FCA5A5]' : ($node->automation_indicator === 'SEDANG' ? 'border-[#B47818] text-[#8A580B] dark:text-[#E7C37B]' : 'border-[#586B47] text-[#3E4D31] dark:text-[#D5E2C8]');
          @endphp
          <button type="button"
                  onclick='selectGraphNode(@json($nodeNum), @json($node->username), @json($node->automation_indicator), @json(($node->post_rate ?? "-") . "/h"), @json(($node->account_age_days ?? "-") . "d"), "Relay Node")'
                  style="left: {{ $pos['x'] }}%; top: {{ $pos['y'] }}%;"
                  class="absolute -translate-x-1/2 -translate-y-1/2 z-20 flex flex-col items-center focus:outline-none">
            <div class="w-8 h-8 rounded-full bg-[var(--bg-surface)] border-2 {{ $borderClass }} flex items-center justify-center font-mono text-[10px] font-bold">
              {{ $nodeNum }}
            </div>
            <div class="mt-0.5 px-1.5 py-0.5 bg-[var(--bg-surface)] border border-[var(--border-color)] rounded text-[10px] font-mono whitespace-nowrap">
              {{ $node->username }}
            </div>
          </button>
        @endforeach
      </div>

      <div class="panel-subtle p-2.5 flex flex-wrap items-center justify-between gap-2 font-mono text-xs">
        <div>
          <span class="label-meta">{{ $isId ? 'Node Terpilih:' : 'Selected Node:' }}</span>
          <span id="inspNodeHandle" class="font-semibold ml-1">{{ $earliestActor?->username ?? '-' }} (N-01)</span>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-[11px]">
          <span>{{ $isId ? 'Rate:' : 'Rate:' }} <b id="inspNodeRate">{{ $earliestActor?->post_rate ?? '-' }}/h</b></span>
          <span>{{ $isId ? 'Umur:' : 'Age:' }} <b id="inspNodeAge">{{ $earliestActor?->account_age_days ?? '-' }}d</b></span>
          <span>Automation: <b id="inspNodeInd">{{ $earliestActor?->automation_indicator ?? '-' }}</b></span>
        </div>
      </div>
    </div>

    <div class="lg:col-span-5 space-y-4">
      <div class="panel p-4 space-y-3">
        <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-2.5">
          <span class="font-mono text-xs font-semibold uppercase tracking-wider">
            {{ $isId ? 'Sebaran Platform pada Dataset' : 'Platform Distribution' }}
          </span>
          <i class="fa-solid fa-share-nodes text-[var(--accent-olive)]"></i>
        </div>

        <div class="space-y-2.5 text-xs">
          @forelse($platformDistribution as $plat => $cnt)
            @php $pct = $posts->count() > 0 ? round(($cnt / $posts->count()) * 100) : 0; @endphp
            <div class="space-y-1">
              <div class="flex items-center justify-between font-mono text-xs">
                <span class="font-semibold">{{ $plat }}</span>
                <span>{{ $cnt }} ({{ $pct }}%)</span>
              </div>
              <div class="w-full h-2 panel-subtle overflow-hidden">
                <div class="h-full bg-[#586B47]" style="width: {{ $pct }}%;"></div>
              </div>
            </div>
          @empty
            <p class="text-[var(--text-muted)] text-xs">—</p>
          @endforelse
        </div>
      </div>

      @php $primaryCluster = $clusters->first(); @endphp
      <div class="panel p-4 space-y-2.5">
        <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-2">
          <span class="font-mono text-xs font-semibold uppercase tracking-wider">
            {{ $isId ? 'Ringkasan Klaster Narasi' : 'Narrative Cluster Summary' }}
          </span>
          <span class="badge badge-neutral">{{ $clusters->count() }} {{ $isId ? 'KLASTER' : 'CLUSTERS' }}</span>
        </div>
        @if($primaryCluster)
          <div class="space-y-1.5 text-xs font-mono">
            <div class="flex items-center justify-between">
              <span class="text-[var(--text-muted)]">{{ $primaryCluster->cluster_code }}</span>
              <span class="badge {{ $primaryCluster->coordination_assessment === 'INDICATORS_PRESENT' ? 'badge-warn' : 'badge-neutral' }}">
                {{ $primaryCluster->coordination_assessment }}
              </span>
            </div>
            <div class="font-sans font-semibold text-[var(--text-primary)]">{{ $primaryCluster->title }}</div>
            <div class="flex items-center justify-between text-[11px] text-[var(--text-muted)] pt-1 border-t border-[var(--border-color)]">
              <span>{{ $isId ? 'Status Analis:' : 'Analyst Status:' }} <b>{{ $primaryCluster->analyst_status }}</b></span>
              <span>{{ $isId ? 'Akun Terpantau:' : 'Accounts:' }} <b>{{ $actors->count() }}</b></span>
            </div>
          </div>
        @else
          <p class="text-xs text-[var(--text-muted)]">
            {{ $isId ? 'Belum ada klaster narasi terbentuk untuk kasus ini.' : 'No narrative cluster generated for this case yet.' }}
          </p>
        @endif
      </div>
    </div>

  </div>

  <!-- Public Account Roster -->
  <div class="panel">
    <div class="panel-header flex-wrap">
      <div>
        <h2 class="font-mono text-xs font-semibold uppercase tracking-wider">
          {{ $isId ? 'Roster Akun Publik' : 'Public Account Roster' }}
        </h2>
        <p class="text-[11px] text-[var(--text-muted)] mt-0.5">
          {{ $isId ? 'Hanya memuat handle publik, laju posting, umur akun, dan Automation Indicator.' : 'Contains only public handles, posting rates, account ages, and Automation Indicators.' }}
        </p>
      </div>
      <span class="badge badge-neutral">PUBLIC HANDLES ONLY</span>
    </div>

    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead>
          <tr>
            <th>{{ $isId ? 'Handle Publik' : 'Public Handle' }}</th>
            <th>Platform</th>
            <th>{{ $isId ? 'Laju Posting' : 'Post Rate' }}</th>
            <th>{{ $isId ? 'Umur Akun' : 'Account Age' }}</th>
            <th>Automation Indicator</th>
            <th>{{ $isId ? 'Dasar Pola' : 'Pattern Basis' }}</th>
            <th class="text-right">Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($actors as $act)
          <tr>
            <td class="font-mono font-semibold">
              {{ $act->username }}
              @if($act->is_earliest_in_dataset)
                <span class="block text-[10px] font-normal text-[var(--accent-olive)] dark:text-[#C5B89C]">
                  {{ $isId ? 'Titik Paling Awal (Dataset)' : 'Earliest Point (Dataset)' }}
                </span>
              @endif
            </td>
            <td class="font-mono text-xs">{{ $act->platform }}</td>
            <td class="font-mono text-xs">{{ $act->post_rate ? $act->post_rate . ' /h' : 'N/A' }}</td>
            <td class="font-mono text-xs">{{ $act->account_age_days ? $act->account_age_days . 'd' : 'N/A' }}</td>
            <td>
              <span class="badge {{ $act->automation_indicator === 'TINGGI' ? 'badge-danger' : ($act->automation_indicator === 'SEDANG' ? 'badge-warn' : 'badge-olive') }}">
                {{ $act->automation_indicator }}
              </span>
            </td>
            <td class="text-xs max-w-sm">{{ $act->indicator_basis ?? '-' }}</td>
            <td class="text-right"><span class="badge badge-neutral">{{ $act->status }}</span></td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center text-[var(--text-muted)] py-6">
              {{ $isId ? 'Belum ada data pola akun pada kasus ini.' : 'No account pattern data in this case.' }}
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

@if($selectedCase && Auth::user()->hasPermission('case.write'))
<div id="record-actor-modal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
  <div class="panel w-full max-w-lg shadow-2xl">
    <div class="panel-header">
      <h2 class="font-mono text-xs font-semibold uppercase tracking-wider">
        {{ $isId ? 'Catat Pola Akun Publik (Non-PII)' : 'Record Public Account Pattern (Non-PII)' }}
      </h2>
      <button type="button" onclick="document.getElementById('record-actor-modal')?.classList.add('hidden')" class="btn-secondary py-1 px-2 text-xs" aria-label="Close modal">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('network.actors.store') }}" method="POST" class="p-4 space-y-3 text-xs">
      @csrf
      <input type="hidden" name="case_id" value="{{ $selectedCase->id }}">
      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="label-meta">{{ $isId ? 'Handle Publik' : 'Public Handle' }}</label>
          <input type="text" name="username" required placeholder="@kanal_publik" class="input-mil font-mono">
        </div>
        <div class="space-y-1">
          <label class="label-meta">Platform</label>
          <input type="text" name="platform" required value="X" class="input-mil font-mono">
        </div>
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div class="space-y-1">
          <label class="label-meta">{{ $isId ? 'Laju (/jam)' : 'Rate (/hr)' }}</label>
          <input type="number" step="0.1" min="0" name="post_rate" placeholder="1.5" class="input-mil font-mono">
        </div>
        <div class="space-y-1">
          <label class="label-meta">{{ $isId ? 'Umur (hari)' : 'Age (days)' }}</label>
          <input type="number" min="0" name="account_age_days" placeholder="120" class="input-mil font-mono">
        </div>
        <div class="space-y-1">
          <label class="label-meta">Automation</label>
          <select name="automation_indicator" required class="input-mil font-mono text-xs">
            <option value="RENDAH">RENDAH</option>
            <option value="SEDANG">SEDANG</option>
            <option value="TINGGI">TINGGI</option>
            <option value="TIDAK DINILAI">TIDAK DINILAI</option>
          </select>
        </div>
      </div>
      <div class="space-y-1">
        <label class="label-meta">{{ $isId ? 'Dasar Observasi Pola' : 'Pattern Basis' }}</label>
        <textarea name="indicator_basis" rows="2" placeholder="{{ $isId ? 'Mis. frekuensi pengulangan narasi identik lintas platform dalam 30 menit.' : 'Basis of observed automation indicator...' }}" class="input-mil"></textarea>
      </div>
      <label class="flex items-center gap-2 text-xs">
        <input type="checkbox" name="is_earliest_in_dataset" value="1">
        <span>{{ $isId ? 'Tandai sebagai titik publikasi paling awal di dataset kasus' : 'Mark as earliest publication point in case dataset' }}</span>
      </label>
      <div class="flex justify-end gap-2 pt-2 border-t border-[var(--border-color)]">
        <button type="button" onclick="document.getElementById('record-actor-modal')?.classList.add('hidden')" class="btn-secondary">{{ $isId ? 'Batal' : 'Cancel' }}</button>
        <button type="submit" class="btn-primary">{{ $isId ? 'Simpan Pola Akun' : 'Save Pattern' }}</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection

@push('scripts')
<script>
  function selectGraphNode(id, handle, indicator, rate, age, roleLabel) {
    document.getElementById('inspNodeHandle').textContent = handle + ' (' + id + ') — ' + roleLabel;
    document.getElementById('inspNodeRate').textContent = rate;
    document.getElementById('inspNodeAge').textContent = age;
    document.getElementById('inspNodeInd').textContent = indicator;
  }
</script>
@endpush
