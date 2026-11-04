<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>TRACE-S INTEL BRIEF - {{ $report->report_code }}</title>
  <style>
    @page {
      margin: 20mm 15mm 20mm 15mm;
      size: a4 portrait;
    }
    body {
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      font-size: 9pt;
      line-height: 1.35;
      color: #1a1a1a;
      background: #ffffff;
      margin: 0;
      padding: 0;
    }
    .header-table {
      width: 100%;
      border-bottom: 2pt solid #0f172a;
      padding-bottom: 8pt;
      margin-bottom: 12pt;
    }
    .title-sub {
      font-size: 7.5pt;
      letter-spacing: 1.5pt;
      color: #0284c7;
      text-transform: uppercase;
      font-weight: bold;
    }
    .title-main {
      font-size: 13pt;
      font-weight: bold;
      color: #0f172a;
      text-transform: uppercase;
      letter-spacing: 0.5pt;
      margin: 2pt 0;
    }
    .title-agency {
      font-size: 7.5pt;
      color: #64748b;
      text-transform: uppercase;
    }
    .classification-tag {
      display: inline-block;
      padding: 2pt 6pt;
      border: 1pt solid #be123c;
      color: #be123c;
      font-size: 7.5pt;
      font-weight: bold;
      text-transform: uppercase;
      margin-bottom: 3pt;
    }
    .section-title {
      font-size: 8.5pt;
      font-weight: bold;
      color: #0369a1;
      text-transform: uppercase;
      letter-spacing: 0.5pt;
      border-bottom: 1pt solid #cbd5e1;
      padding-bottom: 2pt;
      margin-top: 10pt;
      margin-bottom: 4pt;
    }
    .grid-box {
      background: #f8fafc;
      border: 0.5pt solid #cbd5e1;
      padding: 6pt;
      margin-bottom: 6pt;
    }
    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 7.5pt;
      margin-top: 4pt;
    }
    .data-table th {
      background: #f1f5f9;
      color: #334155;
      text-align: left;
      padding: 3pt 4pt;
      border-bottom: 1pt solid #cbd5e1;
      text-transform: uppercase;
      font-size: 6.5pt;
    }
    .data-table td {
      padding: 3pt 4pt;
      border-bottom: 0.5pt solid #e2e8f0;
      vertical-align: top;
    }
    .marker-tag {
      font-size: 6.5pt;
      font-weight: bold;
      padding: 1pt 3pt;
      border: 0.5pt solid #475569;
      display: inline-block;
      text-transform: uppercase;
    }
    .font-mono {
      font-family: 'Courier New', Courier, monospace;
      font-size: 7.5pt;
    }
    .footer {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      height: 15pt;
      border-top: 0.5pt solid #cbd5e1;
      font-size: 6.5pt;
      color: #94a3b8;
      text-align: justify;
      padding-top: 2pt;
    }
  </style>
</head>
<body>

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

  <!-- Header -->
  <table class="header-table">
    <tr>
      <td>
        <div class="title-sub">PUSAT SIBER TNI ANGKATAN DARAT (PUSSIBERAD)</div>
        <div class="title-main">INTELLIGENCE BRIEF SIBER SOSIAL</div>
        <div class="title-agency">SATUAN PENANGGULANGAN OPERASI INFORMASI &amp; PERANG KOGNITIF</div>
      </td>
      <td style="text-align: right; vertical-align: top;">
        <div class="classification-tag">{{ $report->classification_label }}</div>
        <div style="font-size: 8pt; font-weight: bold; color: #0f172a;">NO: {{ $report->report_code }}</div>
        <div style="font-size: 7.5pt; color: #64748b;">{{ $report->created_at->format('d/m/Y H:i') }} WIB</div>
      </td>
    </tr>
  </table>

  <!-- 1. Identitas Kasus -->
  <div class="section-title">1. Identitas Kasus Investigasi</div>
  <table style="width: 100%; font-size: 8pt; margin-bottom: 4pt;">
    <tr>
      <td style="width: 25%;">Kode Kasus: <b>{{ $id['case_code'] ?? '-' }}</b></td>
      <td style="width: 35%;">Kategori: <b>{{ $id['category'] ?? '-' }}</b></td>
      <td style="width: 20%;">Prioritas: <b style="color: #b91c1c;">{{ $id['priority'] ?? '-' }}</b></td>
      <td style="width: 20%;">Status: <b style="color: #047857;">{{ $id['status'] ?? '-' }}</b></td>
    </tr>
  </table>

  <!-- 2. Ringkasan Eksekutif -->
  <div class="section-title">2. Ringkasan Eksekutif [INTERPRETASI AI]</div>
  <div class="grid-box" style="border-left: 2pt solid #d97706;">
    {{ $exec['summary'] ?? 'Ringkasan belum tersedia.' }}
  </div>

  <!-- 3. Ruang Lingkup Data -->
  <div class="section-title">3. Ruang Lingkup &amp; Batasan Dataset Pengumpulan</div>
  <p style="font-size: 8pt; margin: 2pt 0 4pt 0;">
    {{ $scope['scope'] ?? 'Pengumpulan data berpedoman pada dataset terbatas.' }}
  </p>

  <!-- 4. Klaim Utama yang Diperiksa -->
  <div class="section-title">4. Klaim Utama yang Diinvestigasi</div>
  <div class="grid-box" style="font-style: italic;">
    "{{ $claims['primary_claim'] ?? '-' }}"
  </div>

  <!-- 6 & 7. Cross-Source & Independence -->
  <div class="section-title">6 &amp; 7. Verifikasi Lintas Sumber &amp; Independensi</div>
  <table style="width: 100%; font-size: 8pt; margin-bottom: 2pt;">
    <tr>
      <td>Status Dukungan: <b>{{ $cross['status'] ?? 'BELUM DIANALISIS' }}</b></td>
      <td>Kelompok Rujukan Independen: <b>{{ $indep['independent_units'] ?? 1 }} Unit</b></td>
    </tr>
  </table>
  <div class="grid-box">
    <b>Penilaian Independensi:</b> {{ $indep['assessment'] ?? 'Belum dianalisis.' }}
  </div>

  <!-- 8. Authenticity Comparison -->
  <div class="section-title">8. Hasil Perbandingan Autentisitas Konten (Diff)</div>
  <div class="grid-box">
    {{ $auth['assessment'] ?? 'Tidak ada data perbandingan langsung.' }}
  </div>

  <!-- 11. Temuan Utama (5 Visual Markers) -->
  <div class="section-title">11. Temuan Utama Berbasis Bukti</div>
  @foreach($findings as $f)
  <div style="font-size: 7.5pt; margin-bottom: 3pt;">
    <span class="marker-tag">[{{ $f['marker'] }}]</span>
    <span>{{ $f['statement'] }}</span>
  </div>
  @endforeach

  <!-- 12 & 13. Threat & Validation -->
  <div class="section-title">12 &amp; 13. Indikator Ancaman &amp; Validasi Analis</div>
  <table style="width: 100%; font-size: 8pt; margin-bottom: 4pt;">
    <tr>
      <td style="width: 50%; vertical-align: top;">
        Tingkat Ancaman: <b style="color: #b91c1c;">{{ $threat['threat_level'] ?? 'SEDANG' }}</b><br>
        Skor Kredibilitas: <b>{{ $threat['credibility_score'] ?? 50 }}/100</b><br>
        Status Verifikasi: <b>{{ $threat['verification_status'] ?? 'BELUM TERVERIFIKASI' }}</b>
      </td>
      <td style="width: 50%; vertical-align: top;">
        Status Validasi: <b style="color: #047857;">{{ $validation['status'] ?? 'PENDING REVIEW' }}</b><br>
        Analis Penanggung Jawab: <b>{{ $validation['analyst'] ?? '-' }}</b><br>
        Catatan: <i>"{{ $validation['reason'] ?? '-' }}"</i>
      </td>
    </tr>
  </table>

  <!-- 15. Rekomendasi -->
  <div class="section-title">15. Rekomendasi Tindak Lanjut</div>
  <ul style="font-size: 8pt; margin: 2pt 0; padding-left: 15pt;">
    @foreach($recomms as $rec)
      <li>{{ $rec }}</li>
    @endforeach
  </ul>

  <!-- 16. Lampiran Hash SHA-256 -->
  <div class="section-title" style="page-break-before: auto;">16. Lampiran Evidence ID &amp; Hash Kriptografis (SHA-256)</div>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 15%;">Evidence ID</th>
        <th style="width: 20%;">Metode</th>
        <th style="width: 65%;">SHA-256 Content Hash</th>
      </tr>
    </thead>
    <tbody>
      @foreach($hashes as $h)
      <tr>
        <td class="font-mono"><b>{{ $h['code'] }}</b></td>
        <td>{{ $h['method'] }}</td>
        <td class="font-mono">{{ $h['hash'] }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <!-- Footer -->
  <div class="footer">
    TRACE-S // COGNITIVE DEFENSE INITIATIVE // DOKUMEN TERTUTUP PUSSIBERAD TNI AD // TIDAK UNTUK DISTRIBUSI PUBLIK
  </div>

</body>
</html>
