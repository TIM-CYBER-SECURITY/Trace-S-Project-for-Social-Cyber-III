@extends('layouts.tactical')

@php
  $isId = app()->getLocale() === 'id';
@endphp

@section('title', ($isId ? 'Dasbor Analisis Sentimen' : 'Sentiment Analysis Dashboard') . ' // TRACE-S')

@section('content')
<div class="space-y-4">

  <!-- [ModuleA2Header] START -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
    <div>
      <div class="flex items-center gap-2.5">
        <h1 class="text-sm font-semibold uppercase tracking-wide text-neutral-300 font-mono">
          {{ $isId ? 'Dasbor Analisis Sentimen & Pola Bahasa Publik' : 'Sentiment & Linguistic Polarity Dashboard' }}
        </h1>
        <span class="text-[10px] font-mono px-2 py-0.5 text-violet-400 bg-violet-500/10 border border-violet-500/30 rounded-sm">
          MODULE A2
        </span>
      </div>
      <p class="text-xs text-neutral-500 mt-0.5 font-sans">
        {{ $isId
          ? 'Pemantauan tren polaritas kalimat dan indikator nada bahasa pada korpus data yang terkumpul.'
          : 'Monitoring sentence polarity trends and linguistic tone indicators across the collected data corpus.' }}
      </p>
    </div>

    <div class="flex items-center gap-2">
      <span class="px-2.5 py-1 text-amber-500 bg-amber-500/10 border border-amber-500/30 text-[10px] font-mono uppercase tracking-widest rounded-sm" title="is_simulation: true">
        {{ $isId ? 'SIMULASI (is_simulation: true)' : 'SIMULATION (is_simulation: true)' }}
      </span>
    </div>
  </div>
  <!-- [ModuleA2Header] END -->

  <!-- [MethodologicalDisclaimerBanner] START -->
  <div class="p-3.5 text-violet-400 bg-violet-500/10 border border-violet-500/30 rounded-sm flex items-start gap-3">
    <i class="fa-solid fa-circle-info text-violet-400 mt-0.5 text-sm"></i>
    <div class="space-y-1 text-xs">
      <div class="font-mono font-semibold uppercase tracking-wide">
        {{ $isId
          ? 'CAKUPAN TERBATAS: Skor mencerminkan korpus data terkumpul, bukan opini seluruh warga.'
          : 'LIMITED SCOPE: Scores reflect the collected data corpus, not the opinion of all citizens.' }}
      </div>
      <p class="text-neutral-300 font-sans leading-relaxed">
        {{ $isId
          ? 'Kategori di bawah ini mengukur pola kata dan nada bahasa pada teks publik yang masuk ke dataset Trace-S, bukan kondisi psikologis atau emosi asli manusia di balik akun (§5.2).'
          : 'The categories below measure word patterns and linguistic tone within public text ingested by Trace-S, not the psychological state or true emotions of individuals behind accounts (§5.2).' }}
      </p>
    </div>
  </div>
  <!-- [MethodologicalDisclaimerBanner] END -->

  <!-- [SentimentSummaryCards] START -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 font-mono">
    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-1">
      <div class="flex items-center justify-between">
        <span class="text-[10px] uppercase tracking-widest text-neutral-500">
          {{ $isId ? 'POLARITAS NEGATIF (KORPUS)' : 'NEGATIVE POLARITY (CORPUS)' }}
        </span>
        <span class="text-[10px] px-1.5 py-0.5 text-violet-400 bg-violet-500/10 border border-violet-500/30 rounded-sm">68.4%</span>
      </div>
      <div class="text-2xl font-semibold text-violet-400 pt-1">33,324</div>
      <div class="text-[10px] text-neutral-500">{{ $isId ? 'Teks dengan nada keluhan / penolakan' : 'Texts with complaint / rejection tone' }}</div>
    </div>

    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-1">
      <div class="flex items-center justify-between">
        <span class="text-[10px] uppercase tracking-widest text-neutral-500">
          {{ $isId ? 'POLARITAS NETRAL (KORPUS)' : 'NEUTRAL POLARITY (CORPUS)' }}
        </span>
        <span class="text-[10px] px-1.5 py-0.5 text-neutral-300 bg-neutral-950 border border-neutral-800 rounded-sm">19.2%</span>
      </div>
      <div class="text-2xl font-semibold text-neutral-300 pt-1">9,354</div>
      <div class="text-[10px] text-neutral-500">{{ $isId ? 'Teks berita faktual / informatif' : 'Factual / informational news text' }}</div>
    </div>

    <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-1">
      <div class="flex items-center justify-between">
        <span class="text-[10px] uppercase tracking-widest text-neutral-500">
          {{ $isId ? 'POLARITAS POSITIF (KORPUS)' : 'POSITIVE POLARITY (CORPUS)' }}
        </span>
        <span class="text-[10px] px-1.5 py-0.5 text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 rounded-sm">12.4%</span>
      </div>
      <div class="text-2xl font-semibold text-emerald-500 pt-1">6,042</div>
      <div class="text-[10px] text-neutral-500">{{ $isId ? 'Teks dukungan / apresiasi kebijakan' : 'Supportive / appreciative text' }}</div>
    </div>
  </div>
  <!-- [SentimentSummaryCards] END -->

  <!-- [PolarityTrendAndEmotionRow] START -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

    <!-- [PolarityTrendChart] START -->
    <div class="lg:col-span-7 bg-neutral-900 border border-neutral-800 rounded-sm p-4 flex flex-col justify-between space-y-3">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-800 pb-2.5">
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-chart-line text-violet-400 text-xs"></i>
          <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-300 font-mono">
            {{ $isId ? 'Tren Polaritas Sepanjang Waktu (24 Jam)' : 'Polarity Trend Over Time (24 Hours)' }}
          </h2>
        </div>
        <div class="flex items-center gap-3 text-[10px] font-mono">
          <span class="flex items-center gap-1.5 text-violet-400">
            <span class="w-2.5 h-0.5 bg-violet-400 inline-block"></span>
            {{ $isId ? 'Negatif' : 'Negative' }}
          </span>
          <span class="flex items-center gap-1.5 text-neutral-300">
            <span class="w-2.5 h-0.5 bg-neutral-400 inline-block"></span>
            {{ $isId ? 'Netral' : 'Neutral' }}
          </span>
          <span class="flex items-center gap-1.5 text-emerald-500">
            <span class="w-2.5 h-0.5 bg-emerald-500 inline-block"></span>
            {{ $isId ? 'Positif' : 'Positive' }}
          </span>
        </div>
      </div>

      <div class="relative w-full h-60 bg-neutral-950 border border-neutral-800 rounded-sm p-3 flex">
        <!-- Y-Axis (font-mono) -->
        <div class="w-10 h-full flex flex-col justify-between text-[10px] font-mono text-neutral-500 text-right pr-2 select-none pb-5">
          <span>100%</span>
          <span>75%</span>
          <span>50%</span>
          <span>25%</span>
          <span>0%</span>
        </div>

        <!-- Custom SVG Trend Canvas -->
        <div class="flex-1 h-full flex flex-col justify-between">
          <svg class="w-full h-[calc(100%-20px)] overflow-visible" viewBox="0 0 600 180" preserveAspectRatio="none" aria-label="Sentiment Polarity Chart">
            <line x1="0" y1="0" x2="600" y2="0" stroke="#262626" stroke-width="1"/>
            <line x1="0" y1="45" x2="600" y2="45" stroke="#262626" stroke-width="1" stroke-dasharray="3 3"/>
            <line x1="0" y1="90" x2="600" y2="90" stroke="#262626" stroke-width="1" stroke-dasharray="3 3"/>
            <line x1="0" y1="135" x2="600" y2="135" stroke="#262626" stroke-width="1" stroke-dasharray="3 3"/>
            <line x1="0" y1="180" x2="600" y2="180" stroke="#404040" stroke-width="1"/>

            <!-- Negative Polarity (Violet-400: #a78bfa) -->
            <path d="M 0 85 L 100 80 L 200 72 L 300 68 L 400 50 L 500 54 L 600 56" fill="none" stroke="#a78bfa" stroke-width="2"/>
            <!-- Neutral Polarity (Neutral-400: #a3a3a3) -->
            <path d="M 0 135 L 100 138 L 200 140 L 300 142 L 400 148 L 500 146 L 600 145" fill="none" stroke="#a3a3a3" stroke-width="1.5"/>
            <!-- Positive Polarity (Emerald-500: #10b981) -->
            <path d="M 0 155 L 100 156 L 200 158 L 300 160 L 400 164 L 500 162 L 600 160" fill="none" stroke="#10b981" stroke-width="1.5"/>
          </svg>

          <!-- X-Axis (font-mono) -->
          <div class="w-full flex justify-between text-[10px] font-mono text-neutral-500 pt-1 select-none">
            <span>00:00</span>
            <span>04:00</span>
            <span>08:00</span>
            <span>12:00</span>
            <span>16:00</span>
            <span>20:00</span>
            <span>24:00 UTC</span>
          </div>
        </div>
      </div>

      <div class="text-[10px] font-mono text-neutral-500 flex items-center justify-between">
        <span>{{ $isId ? 'Metode: Klasifikasi leksikon & konteks kalimat Bahasa Indonesia' : 'Method: Indonesian lexicon & sentence-context classification' }}</span>
        <span class="text-amber-500">is_simulation: true</span>
      </div>
    </div>
    <!-- [PolarityTrendChart] END -->

    <!-- [LinguisticEmotionBreakdown] START -->
    <div class="lg:col-span-5 bg-neutral-900 border border-neutral-800 rounded-sm p-4 flex flex-col justify-between space-y-3">
      <div class="space-y-3">
        <div class="flex items-center justify-between border-b border-neutral-800 pb-2.5">
          <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-300 font-mono">
              {{ $isId ? 'Indikator Pola Bahasa Dominan' : 'Dominant Linguistic Tone Indicators' }}
            </h2>
            <p class="text-[10px] text-neutral-500 font-mono mt-0.5">
              {{ $isId ? 'Pola kosakata pada korpus (bukan emosi asli pengguna)' : 'Vocabulary patterns in corpus (not actual user emotions)' }}
            </p>
          </div>
          <span class="text-[10px] font-mono px-1.5 py-0.5 text-violet-400 bg-violet-500/10 border border-violet-500/30 rounded-sm">
            CORPUS LEXICON
          </span>
        </div>

        @php
          $tones = [
            ['en' => 'Anger / Indignation Pattern', 'id' => 'Pola Kata Marah / Kemarahan', 'pct' => 38],
            ['en' => 'Fear / Anxiety Pattern', 'id' => 'Pola Kata Takut / Kecemasan', 'pct' => 26],
            ['en' => 'Cynicism / Distrust Pattern', 'id' => 'Pola Kata Sinis / Ketidakpercayaan', 'pct' => 19],
            ['en' => 'Sympathy / Concern Pattern', 'id' => 'Pola Kata Simpati / Kepedulian', 'pct' => 10],
            ['en' => 'Optimism / Support Pattern', 'id' => 'Pola Kata Optimis / Dukungan', 'pct' => 7],
          ];
        @endphp

        <div class="space-y-3 font-mono text-xs pt-1">
          @foreach($tones as $t)
          <div class="space-y-1">
            <div class="flex items-center justify-between text-xs">
              <span class="text-neutral-300 font-sans">{{ $isId ? $t['id'] : $t['en'] }}</span>
              <span class="text-violet-400 font-mono font-semibold">{{ $t['pct'] }}%</span>
            </div>
            <div class="w-full h-2 bg-neutral-950 border border-neutral-800 rounded-none overflow-hidden">
              <div class="h-full bg-violet-400/70" style="width: {{ $t['pct'] }}%;"></div>
            </div>
          </div>
          @endforeach
        </div>
      </div>

      <div class="p-2.5 bg-neutral-950 border border-neutral-800 rounded-sm text-[10px] font-mono text-neutral-500">
        {{ $isId
          ? 'Catatan: Persentase dihitung dari frekuensi kemunculan kata kunci emosional dalam teks sampel.'
          : 'Note: Percentages are derived from emotional keyword frequency within sampled texts.' }}
      </div>
    </div>
    <!-- [LinguisticEmotionBreakdown] END -->

  </div>
  <!-- [PolarityTrendAndEmotionRow] END -->

  <!-- [PlatformAndClusterSentimentBreakdown] START -->
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-neutral-800 pb-2.5">
      <div>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-300 font-mono">
          {{ $isId ? 'Rincian Sentimen per Platform & Konektor Sumber' : 'Sentiment Breakdown by Platform & Source Connector' }}
        </h2>
        <p class="text-xs text-neutral-500 mt-0.5">
          {{ $isId
            ? 'Menampilkan rincian polaritas pada sumber yang terhubung dan status NOT CONFIGURED pada API yang belum diatur (§5.2).'
            : 'Displays polarity breakdown for connected sources and explicit NOT CONFIGURED status for unconfigured APIs (§5.2).' }}
        </p>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left font-mono text-xs">
        <thead>
          <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-widest text-neutral-500 bg-neutral-950">
            <th class="py-2 px-3">PLATFORM / CHANNEL</th>
            <th class="py-2 px-3">{{ $isId ? 'STATUS KONEKTOR' : 'CONNECTOR STATUS' }}</th>
            <th class="py-2 px-3">{{ $isId ? 'VOLUME SAMPEL' : 'SAMPLE VOLUME' }}</th>
            <th class="py-2 px-3">{{ $isId ? 'POLARITAS NEGATIF' : 'NEGATIVE POLARITY' }}</th>
            <th class="py-2 px-3">{{ $isId ? 'POLARITAS NETRAL' : 'NEUTRAL POLARITY' }}</th>
            <th class="py-2 px-3">{{ $isId ? 'POLARITAS POSITIF' : 'POSITIVE POLARITY' }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-800 leading-tight">
          <tr class="hover:bg-neutral-800/50">
            <td class="py-2.5 px-3 text-neutral-300 font-semibold">Online News Portals (RSS)</td>
            <td class="py-2.5 px-3">
              <span class="px-1.5 py-0.5 text-[10px] text-amber-500 bg-amber-500/10 border border-amber-500/30 rounded-sm">
                {{ $isId ? 'SIMULASI' : 'SIMULATION' }}
              </span>
            </td>
            <td class="py-2.5 px-3 text-neutral-300">18,420</td>
            <td class="py-2.5 px-3 text-violet-400 font-semibold">61.2%</td>
            <td class="py-2.5 px-3 text-neutral-300">26.4%</td>
            <td class="py-2.5 px-3 text-emerald-500">12.4%</td>
          </tr>

          <tr class="hover:bg-neutral-800/50">
            <td class="py-2.5 px-3 text-neutral-300 font-semibold">Internal Case Evidence Corpus</td>
            <td class="py-2.5 px-3">
              <span class="px-1.5 py-0.5 text-[10px] text-emerald-500 bg-emerald-500/10 border border-emerald-500/30 rounded-sm">
                ACTIVE
              </span>
            </td>
            <td class="py-2.5 px-3 text-neutral-300">{{ $narrativesCount > 0 ? $narrativesCount : 12 }}</td>
            <td class="py-2.5 px-3 text-violet-400 font-semibold">75.0%</td>
            <td class="py-2.5 px-3 text-neutral-300">16.7%</td>
            <td class="py-2.5 px-3 text-emerald-500">8.3%</td>
          </tr>

          <tr class="hover:bg-neutral-800/50">
            <td class="py-2.5 px-3 text-neutral-500">X / Twitter Live Stream API</td>
            <td class="py-2.5 px-3">
              <span class="px-1.5 py-0.5 text-[10px] text-neutral-500 bg-neutral-950 border border-neutral-800 rounded-sm">
                NOT CONFIGURED
              </span>
            </td>
            <td class="py-2.5 px-3 text-neutral-500">—</td>
            <td class="py-2.5 px-3 text-neutral-500">—</td>
            <td class="py-2.5 px-3 text-neutral-500">—</td>
            <td class="py-2.5 px-3 text-neutral-500">—</td>
          </tr>

          <tr class="hover:bg-neutral-800/50">
            <td class="py-2.5 px-3 text-neutral-500">TikTok / Instagram Graph Connector</td>
            <td class="py-2.5 px-3">
              <span class="px-1.5 py-0.5 text-[10px] text-neutral-500 bg-neutral-950 border border-neutral-800 rounded-sm">
                NOT CONFIGURED
              </span>
            </td>
            <td class="py-2.5 px-3 text-neutral-500">—</td>
            <td class="py-2.5 px-3 text-neutral-500">—</td>
            <td class="py-2.5 px-3 text-neutral-500">—</td>
            <td class="py-2.5 px-3 text-neutral-500">—</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <!-- [PlatformAndClusterSentimentBreakdown] END -->

</div>
@endsection
