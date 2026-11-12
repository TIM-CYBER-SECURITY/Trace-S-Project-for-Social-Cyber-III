@extends('layouts.app')

@php
  $isId = app()->getLocale() === 'id';
  $total = (int) $narrativesCount;
  $denom = max(1, $total);
  $negPct = $total > 0 ? round(($negatives / $denom) * 100, 1) : 0.0;
  $neuPct = $total > 0 ? round(($neutrals / $denom) * 100, 1) : 0.0;
  $posPct = $total > 0 ? round(($positives / $denom) * 100, 1) : 0.0;
@endphp

@section('title', ($isId ? 'Analisis Sentimen Narasi' : 'Narrative Sentiment Analysis') . ' // TRACE-S')

@section('content')
<div class="space-y-5">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
    <div>
      <div class="flex flex-wrap items-center gap-2">
        <h1 class="text-lg font-semibold tracking-tight">
          {{ $isId ? 'Dasbor Analisis Sentimen & Pola Bahasa' : 'Sentiment & Linguistic Polarity Dashboard' }}
        </h1>
        @if($hasSimulationData)
          <span class="badge badge-warn">{{ $isId ? 'SIMULASI' : 'SIMULATION' }}</span>
        @endif
      </div>
      <p class="text-xs text-[var(--text-muted)] mt-0.5">
        {{ $isId
          ? 'Pemantauan tren polaritas teks dan indikator nada bahasa pada korpus data yang terkumpul.'
          : 'Monitoring text polarity trends and linguistic tone indicators across the collected data corpus.' }}
      </p>
    </div>
  </div>

  <!-- Methodological Scope Disclaimer -->
  <div class="panel-subtle p-4 border-l-4 border-l-[#586B47] flex items-start gap-3">
    <i class="fa-solid fa-circle-info text-[var(--accent-olive)] mt-0.5"></i>
    <div class="space-y-1 text-xs">
      <div class="font-mono font-semibold uppercase tracking-wider">
        {{ $isId
          ? 'CAKUPAN TERBATAS: Skor mencerminkan korpus data terkumpul, bukan opini seluruh warga.'
          : 'LIMITED SCOPE: Scores reflect the collected data corpus, not the opinion of all citizens.' }}
      </div>
      <p class="text-[var(--text-secondary)] leading-relaxed">
        {{ $isId
          ? 'Metrik berikut dihitung dari ' . $narrativesCount . ' rekaman narasi aktif pada basis data Trace-S dan tidak memetakan psikologi individu.'
          : 'Metrics are derived strictly from ' . $narrativesCount . ' active narrative items stored in the Trace-S corpus without individual psychological profiling.' }}
      </p>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 font-mono">
    <div class="panel p-4 space-y-1">
      <div class="flex items-center justify-between">
        <span class="label-meta">{{ $isId ? 'Polaritas Negatif' : 'Negative Polarity' }}</span>
        <span class="badge badge-warn">{{ $negPct }}%</span>
      </div>
      <div class="text-2xl font-bold pt-1 text-[#8A580B] dark:text-[#E7C37B]">{{ number_format($negatives) }}</div>
      <div class="text-[11px] font-sans text-[var(--text-muted)]">
        {{ $isId ? 'Teks dengan nada penolakan / keluhan pada korpus' : 'Corpus texts expressing objection / escalation tone' }}
      </div>
    </div>

    <div class="panel p-4 space-y-1">
      <div class="flex items-center justify-between">
        <span class="label-meta">{{ $isId ? 'Polaritas Netral' : 'Neutral Polarity' }}</span>
        <span class="badge badge-neutral">{{ $neuPct }}%</span>
      </div>
      <div class="text-2xl font-bold pt-1">{{ number_format($neutrals) }}</div>
      <div class="text-[11px] font-sans text-[var(--text-muted)]">
        {{ $isId ? 'Teks berita faktual / informatif netral' : 'Neutral factual / informational text items' }}
      </div>
    </div>

    <div class="panel p-4 space-y-1">
      <div class="flex items-center justify-between">
        <span class="label-meta">{{ $isId ? 'Polaritas Positif' : 'Positive Polarity' }}</span>
        <span class="badge badge-olive">{{ $posPct }}%</span>
      </div>
      <div class="text-2xl font-bold pt-1 text-[var(--accent-olive)] dark:text-[#D5E2C8]">{{ number_format($positives) }}</div>
      <div class="text-[11px] font-sans text-[var(--text-muted)]">
        {{ $isId ? 'Teks klarifikasi / dukungan informatif' : 'Supportive / clarification texts' }}
      </div>
    </div>
  </div>

  <!-- Trend Chart & Linguistic Indicators -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <div class="lg:col-span-7 panel flex flex-col justify-between">
      <div class="panel-header flex-wrap">
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-chart-area text-[var(--accent-olive)] text-xs"></i>
          <h2 class="font-mono text-xs font-semibold uppercase tracking-wider">
            {{ $isId ? 'Tren Polaritas 24 Jam (Basis Data Aktif)' : '24-Hour Polarity Trend (Active Database)' }}
          </h2>
        </div>
        <div class="flex items-center gap-3 text-[11px] font-mono">
          <span class="flex items-center gap-1.5"><span class="w-2.5 h-0.5 bg-[#B47818] inline-block"></span>{{ $isId ? 'Negatif' : 'Negative' }}</span>
          <span class="flex items-center gap-1.5"><span class="w-2.5 h-0.5 bg-[#46514C] inline-block"></span>{{ $isId ? 'Netral' : 'Neutral' }}</span>
          <span class="flex items-center gap-1.5"><span class="w-2.5 h-0.5 bg-[#586B47] inline-block"></span>{{ $isId ? 'Positif' : 'Positive' }}</span>
        </div>
      </div>

      <div class="p-4 space-y-3">
        <div class="w-full h-56 panel-subtle p-3 flex">
          <div class="w-9 h-full flex flex-col justify-between text-[10px] font-mono text-[var(--text-muted)] text-right pr-2 select-none pb-5">
            <span>{{ $trajectory['max_value'] ?? 1 }}</span>
            <span>{{ (int) round(($trajectory['max_value'] ?? 1) * 0.5) }}</span>
            <span>0</span>
          </div>
          <div class="flex-1 h-full flex flex-col justify-between">
            <svg class="w-full h-[calc(100%-20px)] overflow-visible" viewBox="0 0 600 175" preserveAspectRatio="none" aria-label="Sentiment Polarity Chart">
              <line x1="0" y1="0" x2="600" y2="0" stroke="currentColor" stroke-opacity="0.15" stroke-width="1"/>
              <line x1="0" y1="88" x2="600" y2="88" stroke="currentColor" stroke-opacity="0.15" stroke-width="1" stroke-dasharray="3 3"/>
              <line x1="0" y1="175" x2="600" y2="175" stroke="currentColor" stroke-opacity="0.3" stroke-width="1"/>
              <path d="{{ $trajectory['sentiment_negative']['path'] ?? 'M 0 175 L 600 175' }}" fill="none" stroke="#B47818" stroke-width="2.2"/>
              <path d="{{ $trajectory['sentiment_neutral']['path'] ?? 'M 0 175 L 600 175' }}" fill="none" stroke="#46514C" stroke-width="1.6"/>
              <path d="{{ $trajectory['sentiment_positive']['path'] ?? 'M 0 175 L 600 175' }}" fill="none" stroke="#586B47" stroke-width="1.8"/>
            </svg>
            <div class="w-full flex justify-between text-[10px] font-mono text-[var(--text-muted)] pt-1 select-none">
              @foreach(($trajectory['labels'] ?? ['00:00','04:00','08:00','12:00','16:00','20:00','24:00']) as $lbl)
                <span>{{ $lbl }}</span>
              @endforeach
            </div>
          </div>
        </div>
        <div class="flex items-center justify-between text-[11px] font-mono text-[var(--text-muted)]">
          <span>{{ $isId ? 'Metode: Leksikon Bahasa Indonesia & catatan korpus DB' : 'Method: Indonesian lexicon & database corpus timestamps' }}</span>
          <span>{{ number_format($narrativesCount) }} {{ $isId ? 'NARASI' : 'ITEMS' }}</span>
        </div>
      </div>
    </div>

    <div class="lg:col-span-5 panel flex flex-col justify-between">
      <div>
        <div class="panel-header">
          <div>
            <h2 class="font-mono text-xs font-semibold uppercase tracking-wider">
              {{ $isId ? 'Indikator Pola Bahasa Dominan' : 'Dominant Linguistic Tone Patterns' }}
            </h2>
            <p class="text-[11px] text-[var(--text-muted)]">
              {{ $isId ? 'Kemunculan kata kunci leksikon pada korpus tersimpan' : 'Lexicon keyword distribution across stored texts' }}
            </p>
          </div>
          <span class="badge badge-sand">LEXICON</span>
        </div>

        <div class="p-4 space-y-3 text-xs">
          @forelse($tonePatterns as $t)
          <div class="space-y-1">
            <div class="flex items-center justify-between">
              <span class="font-medium">{{ $isId ? $t['id'] : $t['en'] }}</span>
              <span class="font-mono font-semibold">{{ $t['pct'] }}% <span class="text-[10px] text-[var(--text-muted)]">({{ $t['count'] }})</span></span>
            </div>
            <div class="w-full h-2 panel-subtle overflow-hidden">
              <div class="h-full bg-[#586B47]" style="width: {{ min(100, $t['pct']) }}%;"></div>
            </div>
          </div>
          @empty
          <p class="text-xs text-[var(--text-muted)]">{{ $isId ? 'Belum ada pola leksikon terhitung.' : 'No lexicon patterns computed.' }}</p>
          @endforelse
        </div>
      </div>

      <div class="p-3 border-t border-[var(--border-color)] bg-[var(--bg-subtle)] text-[11px] font-mono text-[var(--text-muted)]">
        {{ $isId
          ? 'Dihitung langsung dari judul narasi dan teks bukti pada database aktif.'
          : 'Computed directly from narrative titles and extracted evidence in the active database.' }}
      </div>
    </div>

  </div>

  <!-- Connector & Channel Breakdown -->
  <div class="panel">
    <div class="panel-header">
      <div>
        <h2 class="font-mono text-xs font-semibold uppercase tracking-wider">
          {{ $isId ? 'Status Sumber Data & Rincian Kanal' : 'Source Connector Status & Channel Breakdown' }}
        </h2>
        <p class="text-[11px] text-[var(--text-muted)] mt-0.5">
          {{ $isId
            ? 'Menampilkan sumber aktif dan konektor yang belum terkonfigurasi sebagai NOT CONFIGURED.'
            : 'Displays connected sources transparently and marks unconfigured external APIs explicitly as NOT CONFIGURED.' }}
        </p>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="table-mil">
        <thead>
          <tr>
            <th>{{ $isId ? 'Kanal / Konektor' : 'Channel / Connector' }}</th>
            <th>{{ $isId ? 'Status Konfigurasi' : 'Connector Status' }}</th>
            <th>{{ $isId ? 'Cakupan / Keterangan' : 'Scope / Description' }}</th>
            <th>{{ $isId ? 'Volume Sampel' : 'Sample Volume' }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="font-semibold">Internal Case Evidence Corpus</td>
            <td><span class="badge badge-olive">ACTIVE</span></td>
            <td class="text-xs text-[var(--text-secondary)]">
              {{ $isId ? 'Negatif:' : 'Negative:' }} <b>{{ $negPct }}%</b> &middot;
              {{ $isId ? 'Netral:' : 'Neutral:' }} <b>{{ $neuPct }}%</b> &middot;
              {{ $isId ? 'Positif:' : 'Positive:' }} <b>{{ $posPct }}%</b>
            </td>
            <td class="font-mono">{{ number_format($narrativesCount) }}</td>
          </tr>
          @foreach($connectors as $conn)
          <tr>
            <td class="font-medium">{{ $conn->name }}</td>
            <td>
              <span class="badge {{ $conn->status === 'ACTIVE' ? 'badge-olive' : ($conn->status === 'SIMULASI' ? 'badge-warn' : 'badge-neutral') }}">
                {{ $conn->status }}
              </span>
            </td>
            <td class="text-xs text-[var(--text-muted)]">{{ $conn->description }}</td>
            <td class="font-mono text-[var(--text-muted)]">
              {{ $conn->status === 'NOT CONFIGURED' ? '—' : ($conn->status === 'SIMULASI' ? 'SIMULASI' : 'OK') }}
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
