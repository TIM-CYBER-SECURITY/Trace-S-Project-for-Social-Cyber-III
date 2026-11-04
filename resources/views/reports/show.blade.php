@extends('layouts.tactical')

@section('title', $report->report_code . ' // Intel Brief')

@section('content')

@php
  $c = $report->content;
  $id = $c['section_1_case_identity'] ?? [];
  $exec = $c['section_2_executive_summary'] ?? [];
  $scope = $c['section_3_data_scope'] ?? [];
  $claims = $c['section_4_main_claims'] ?? [];
  $sources = $c['section_5_sources_and_evidence'] ?? [];
  $cross = $c['section_6_cross_source_verification'] ?? [];
  $indep = $c['section_7_source_independence'] ?? [];
  $auth = $c['section_8_authenticity_comparison'] ?? [];
  $clusters = $c['section_9_narrative_clustering'] ?? [];
  $spread = $c['section_10_spread_analysis'] ?? [];
  $findings = $c['section_11_key_findings'] ?? [];
  $threat = $c['section_12_threat_indicators'] ?? [];
  $validation = $c['section_13_analyst_validation'] ?? [];
  $limitations = $c['section_14_limitations'] ?? [];
  $recomms = $c['section_15_recommendations'] ?? [];
  $hashes = $c['section_16_evidence_hashes'] ?? [];
@endphp

<!-- Action Toolbar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
  <div class="flex items-center gap-2">
    <a href="{{ route('reports.index') }}" class="text-xs font-mono text-neutral-400 hover:text-cyan-400 flex items-center gap-1.5">
      <i class="fa-solid fa-arrow-left"></i>
      <span>Kembali ke Arsip Laporan</span>
    </a>
    <span class="text-neutral-600">|</span>
    <span class="text-xs font-mono font-bold text-cyan-400">{{ $report->report_code }}</span>
    @if($report->is_simulation)
      <span class="text-[8px] font-mono px-1.5 py-0.2 border border-amber-500/40 text-amber-500 bg-amber-950/20">SIMULASI</span>
    @endif
  </div>

  <div class="flex items-center gap-2">
    <button onclick="window.print()" class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-mono uppercase rounded-sm border border-neutral-700 flex items-center gap-1.5">
      <i class="fa-solid fa-print"></i>
      <span>Cetak</span>
    </button>
    <a href="{{ route('reports.pdf', $report->id) }}" class="px-3.5 py-1.5 bg-emerald-600/20 text-emerald-400 border border-emerald-500/50 hover:bg-emerald-600/40 text-xs font-mono uppercase tracking-wider rounded-sm transition-all flex items-center gap-1.5 shadow-[0_0_12px_rgba(16,185,129,0.2)]">
      <i class="fa-solid fa-file-pdf"></i>
      <span>Export Intel Brief (PDF)</span>
    </a>
  </div>
</div>

<!-- Intel Brief Document Sheet (Print-friendly tactical layout) -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-6 sm:p-8 space-y-6 text-neutral-300 font-sans max-w-5xl mx-auto shadow-2xl">

  <!-- Document Official Header -->
  <div class="border-b-2 border-neutral-700 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <div class="w-12 h-12 bg-neutral-950 border border-cyan-500/60 rounded-sm flex items-center justify-center text-cyan-400 text-xl font-bold">
        <i class="fa-solid fa-shield-halved"></i>
      </div>
      <div>
        <div class="text-[10px] font-mono tracking-widest text-cyan-400 uppercase font-bold">PUSAT SIBER TNI ANGKATAN DARAT (PUSSIBERAD)</div>
        <div class="text-base sm:text-lg font-bold font-mono tracking-wide text-neutral-100 uppercase">INTELLIGENCE BRIEF SIBER SOSIAL</div>
        <div class="text-[10px] font-mono text-neutral-500 uppercase">SATUAN PENANGGULANGAN OPERASI INFORMASI &amp; PERANG KOGNITIF</div>
      </div>
    </div>

    <div class="text-right font-mono text-xs space-y-0.5">
      <div class="text-[10px] px-2 py-0.5 border border-rose-600/50 bg-rose-950/40 text-rose-400 uppercase font-bold inline-block">
        {{ $report->classification_label }}
      </div>
      <div class="text-neutral-400 text-[11px] pt-1">No: <b>{{ $report->report_code }}</b></div>
      <div class="text-neutral-500 text-[10px]">{{ $report->created_at->format('d F Y H:i') }} WIB</div>
    </div>
  </div>

  <!-- SECTION 1: Identitas Kasus -->
  <div class="space-y-2 border-b border-neutral-800 pb-4">
    <div class="text-xs font-mono uppercase tracking-widest text-cyan-400 font-bold flex items-center gap-1.5">
      <span>1. IDENTITAS KASUS INVESTIGASI</span>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-neutral-950 p-3 border border-neutral-850 rounded-sm text-xs font-mono">
      <div>Kode Kasus: <b class="text-neutral-200 block text-sm">{{ $id['case_code'] ?? '-' }}</b></div>
      <div>Kategori: <b class="text-neutral-200 block">{{ $id['category'] ?? '-' }}</b></div>
      <div>Prioritas: <b class="text-rose-400 block">{{ $id['priority'] ?? '-' }}</b></div>
      <div>Status Penyelidikan: <b class="text-emerald-400 block">{{ $id['status'] ?? '-' }}</b></div>
    </div>
  </div>

  <!-- SECTION 2: Ringkasan Eksekutif -->
  <div class="space-y-2 border-b border-neutral-800 pb-4">
    <div class="flex items-center justify-between">
      <div class="text-xs font-mono uppercase tracking-widest text-cyan-400 font-bold">2. RINGKASAN EKSEKUTIF (EXECUTIVE SUMMARY)</div>
      <span class="text-[9px] font-mono px-1.5 py-0.2 bg-amber-950/50 border border-amber-500/40 text-amber-400 font-bold uppercase">INTERPRETASI AI</span>
    </div>
    <p class="text-xs leading-relaxed text-neutral-200 font-sans bg-neutral-950/60 p-3 border-l-2 border-amber-500">
      {{ $exec['summary'] ?? 'Ringkasan belum tersedia.' }}
    </p>
  </div>

  <!-- SECTION 3: Ruang Lingkup Pengumpulan Data -->
  <div class="space-y-2 border-b border-neutral-800 pb-4">
    <div class="text-xs font-mono uppercase tracking-widest text-cyan-400 font-bold">3. RUANG LINGKUP &amp; BATASAN DATASET PENGUMPULAN</div>
    <p class="text-xs leading-relaxed text-neutral-300">
      {{ $scope['scope'] ?? 'Pengumpulan data berpedoman pada dataset terbatas.' }}
    </p>
  </div>

  <!-- SECTION 4: Klaim Utama yang Diperiksa -->
  <div class="space-y-2 border-b border-neutral-800 pb-4">
    <div class="text-xs font-mono uppercase tracking-widest text-cyan-400 font-bold">4. KLAIM UTAMA YANG DIINVESTIGASI</div>
    <div class="p-3 bg-neutral-950 border border-neutral-850 rounded-sm font-sans text-xs text-neutral-200 italic font-medium">
      "{{ $claims['primary_claim'] ?? '-' }}"
    </div>
  </div>

  <!-- SECTION 5: Daftar Sumber dan Bukti -->
  <div class="space-y-2 border-b border-neutral-800 pb-4">
    <div class="text-xs font-mono uppercase tracking-widest text-cyan-400 font-bold">5. DAFTAR SUMBER &amp; BERKAS BUKTI DIGITAL TERKUMPUL</div>
    <div class="overflow-x-auto text-xs font-mono">
      <table class="w-full text-left">
        <thead>
          <tr class="border-b border-neutral-800 text-[10px] text-neutral-500 uppercase bg-neutral-950">
            <th class="py-1.5 px-2">Evidence ID</th>
            <th class="py-1.5 px-2">Peran</th>
            <th class="py-1.5 px-2">Judul Materi</th>
            <th class="py-1.5 px-2">Penerbit / Platform</th>
            <th class="py-1.5 px-2">Akses</th>
            <th class="py-1.5 px-2">Hash Konten</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-850">
          @foreach($sources as $s)
          <tr>
            <td class="py-1.5 px-2 font-bold text-cyan-400">{{ $s['evidence_code'] }}</td>
            <td class="py-1.5 px-2 text-[10px]">{{ $s['role'] }}</td>
            <td class="py-1.5 px-2 font-sans truncate max-w-xs">{{ $s['title'] }}</td>
            <td class="py-1.5 px-2 text-[11px] text-neutral-400">{{ $s['publisher'] }} ({{ $s['platform'] }})</td>
            <td class="py-1.5 px-2 text-[10px] text-emerald-400">{{ $s['access_status'] }}</td>
            <td class="py-1.5 px-2 text-[9px] text-neutral-500">{{ substr($s['content_hash'] ?? 'N/A', 0, 10) }}...</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <!-- SECTION 6 & 7: Cross-Source Verification & Independence -->
  <div class="space-y-2 border-b border-neutral-800 pb-4">
    <div class="flex items-center justify-between">
      <div class="text-xs font-mono uppercase tracking-widest text-cyan-400 font-bold">6. HASIL CROSS-SOURCE VERIFICATION &amp; INDEPENDENSI SUMBER</div>
      <span class="text-[9px] font-mono px-2 py-0.5 border border-cyan-500/40 bg-cyan-950/40 text-cyan-400 uppercase font-bold">
        {{ $cross['status'] ?? 'BELUM DIANALISIS' }}
      </span>
    </div>
    <div class="p-3 bg-neutral-950 border border-neutral-850 rounded-sm space-y-2 text-xs">
      <div class="font-mono text-[11px] text-amber-400 font-semibold">Penilaian Hubungan Antarsumber (Source Independence):</div>
      <p class="font-sans leading-relaxed text-neutral-200">
        {{ $indep['assessment'] ?? 'Belum ada penilaian independensi.' }}
      </p>
    </div>
  </div>

  <!-- SECTION 8: Authenticity Comparison -->
  <div class="space-y-2 border-b border-neutral-800 pb-4">
    <div class="text-xs font-mono uppercase tracking-widest text-cyan-400 font-bold">8. HASIL PERBANDINGAN AUTENTISITAS KONTEN (AUTHENTICITY COMPARATOR)</div>
    <p class="text-xs font-sans leading-relaxed bg-neutral-950 p-3 border border-neutral-850 rounded-sm text-neutral-200">
      {{ $auth['assessment'] ?? 'Tidak ada data perbandingan materi pembanding terhadap referensi.' }}
    </p>
  </div>

  <!-- SECTION 9 & 10: Narrative Clustering & Spread Analysis -->
  <div class="space-y-2 border-b border-neutral-800 pb-4">
    <div class="text-xs font-mono uppercase tracking-widest text-cyan-400 font-bold">9 &amp; 10. KLASTERISASI NARASI &amp; PERSEBARAN LINTAS PLATFORM</div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs font-mono">
      <div class="p-3 bg-neutral-950 border border-neutral-850 rounded-sm space-y-1">
        <div class="text-[10px] text-neutral-500 uppercase">Klaster Narasi Terdeteksi:</div>
        <div class="text-sm font-bold text-neutral-200">{{ $clusters['clusters_count'] ?? 0 }} Klaster</div>
        <div class="text-[10px] text-neutral-400">Variasi klaim disatukan berdasarkan keterkaitan semantik.</div>
      </div>
      <div class="p-3 bg-neutral-950 border border-neutral-850 rounded-sm space-y-1">
        <div class="text-[10px] text-neutral-500 uppercase">Volume Unggahan pada Dataset:</div>
        <div class="text-sm font-bold text-cyan-400">{{ $spread['total_posts_in_dataset'] ?? 0 }} Unggahan Terdata</div>
        <div class="text-[9px] text-amber-500">{{ $spread['disclaimer'] ?? '' }}</div>
      </div>
    </div>
  </div>

  <!-- SECTION 11: Temuan Utama dengan 5 Penanda Visual (Supplemental §14) -->
  <div class="space-y-2.5 border-b border-neutral-800 pb-4">
    <div class="text-xs font-mono uppercase tracking-widest text-cyan-400 font-bold">11. TEMUAN UTAMA BERBASIS BUKTI (KEY FINDINGS)</div>
    <div class="space-y-2 font-mono text-xs">
      @foreach($findings as $f)
      <div class="p-2.5 bg-neutral-950 border border-neutral-850 rounded-sm flex items-start gap-3">
        <span class="px-2 py-0.5 rounded-xs text-[9px] font-bold uppercase shrink-0
          {{ $f['marker'] === 'FAKTA TERDOKUMENTASI' ? 'bg-emerald-950 text-emerald-400 border border-emerald-500/50' :
             ($f['marker'] === 'KLAIM SUMBER' ? 'bg-cyan-950 text-cyan-400 border border-cyan-500/50' :
             ($f['marker'] === 'INTERPRETASI AI' ? 'bg-amber-950 text-amber-400 border border-amber-500/50' :
             'bg-rose-950 text-rose-400 border border-rose-600/50')) }}">
          [{{ $f['marker'] }}]
        </span>
        <span class="font-sans text-neutral-200 text-xs leading-relaxed">{{ $f['statement'] }}</span>
      </div>
      @endforeach
    </div>
  </div>

  <!-- SECTION 12 & 13: Threat Indicators & Analyst Validation -->
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b border-neutral-800 pb-4 text-xs font-mono">
    <div class="p-3 bg-neutral-950 border border-neutral-850 rounded-sm space-y-1.5">
      <div class="text-xs uppercase tracking-wider text-rose-400 font-bold">12. INDIKATOR ANCAMAN</div>
      <div>Tingkat Ancaman: <b class="text-rose-400 text-sm font-bold">{{ $threat['threat_level'] ?? 'SEDANG' }}</b></div>
      <div>Skor Kredibilitas: <b class="text-cyan-400">{{ $threat['credibility_score'] ?? 50 }}/100</b></div>
      <div>Status Verifikasi: <b class="text-neutral-200">{{ $threat['verification_status'] ?? 'BELUM TERVERIFIKASI' }}</b></div>
    </div>

    <div class="p-3 bg-neutral-950 border border-neutral-850 rounded-sm space-y-1.5">
      <div class="text-xs uppercase tracking-wider text-emerald-400 font-bold">13. VALIDASI ANALIS MANUSIA</div>
      <div>Status Tinjauan: <b class="text-emerald-400 text-sm font-bold">{{ $validation['status'] ?? 'PENDING REVIEW' }}</b></div>
      <div>Analis Penanggung Jawab: <b class="text-neutral-200">{{ $validation['analyst'] ?? '-' }}</b></div>
      <div class="text-[11px] text-neutral-400 italic">"{{ $validation['reason'] ?? '-' }}"</div>
    </div>
  </div>

  <!-- SECTION 14 & 15: Keterbatasan & Rekomendasi -->
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-b border-neutral-800 pb-4 text-xs font-mono">
    <div class="space-y-1.5">
      <div class="text-xs uppercase tracking-wider text-neutral-400 font-bold">14. KETERBATASAN PEMERIKSAAN</div>
      <ul class="list-disc pl-4 space-y-1 text-neutral-400 text-[11px]">
        @foreach($limitations as $lim)
          <li>{{ $lim }}</li>
        @endforeach
      </ul>
    </div>

    <div class="space-y-1.5">
      <div class="text-xs uppercase tracking-wider text-cyan-400 font-bold">15. REKOMENDASI TINDAK LANJUT</div>
      <ul class="list-disc pl-4 space-y-1 text-neutral-200 text-xs font-sans">
        @foreach($recomms as $rec)
          <li>{{ $rec }}</li>
        @endforeach
      </ul>
    </div>
  </div>

  <!-- SECTION 16: Lampiran Evidence ID & Content Hashes -->
  <div class="space-y-2">
    <div class="text-xs font-mono uppercase tracking-widest text-cyan-400 font-bold">16. LAMPIRAN EVIDENCE ID &amp; HASH INTEGRITAS DIGITAL (SHA-256)</div>
    <div class="bg-neutral-950 border border-neutral-850 rounded-sm p-3 text-[10px] font-mono overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr class="border-b border-neutral-800 text-neutral-500 uppercase">
            <th class="py-1 px-2">Evidence Code</th>
            <th class="py-1 px-2">Metode Akuisisi</th>
            <th class="py-1 px-2">Timestamp Pengambilan</th>
            <th class="py-1 px-2">SHA-256 Cryptographic Hash</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-850">
          @foreach($hashes as $h)
          <tr>
            <td class="py-1 px-2 font-bold text-cyan-400">{{ $h['code'] }}</td>
            <td class="py-1 px-2 text-neutral-400">{{ $h['method'] }}</td>
            <td class="py-1 px-2 text-neutral-500">{{ $h['retrieved_at'] ?? '-' }}</td>
            <td class="py-1 px-2 select-all break-all text-neutral-300">{{ $h['hash'] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <!-- Document Sign-off Footer -->
  <div class="pt-6 border-t-2 border-neutral-700 flex justify-between items-end text-xs font-mono">
    <div>
      <div>DOKUMEN INTELIJEN ELEKTRONIK</div>
      <div class="text-neutral-500 text-[10px]">VERIFIED TRACE-S COGNITIVE DEFENSE ENGINE</div>
    </div>
    <div class="text-right">
      <div class="text-[10px] text-neutral-500">OTORISASI ANALIS:</div>
      <div class="font-bold text-neutral-200 mt-1 uppercase">{{ $validation['analyst'] ?? 'TIM SIBER SOSIAL' }}</div>
      <div class="text-[9px] text-neutral-500">PUSSIBERAD TNI AD</div>
    </div>
  </div>

</div>

@endsection
