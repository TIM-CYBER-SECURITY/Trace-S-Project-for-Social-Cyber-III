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
      line-height: 1.4;
      color: #202622;
      background: #ffffff;
      margin: 0;
      padding: 0;
    }
    .header-table {
      width: 100%;
      border-bottom: 1.5pt solid #17251F;
      padding-bottom: 8pt;
      margin-bottom: 12pt;
    }
    .title-sub {
      font-size: 7.5pt;
      letter-spacing: 1.4pt;
      color: #586B47;
      text-transform: uppercase;
      font-weight: bold;
    }
    .title-main {
      font-size: 13pt;
      font-weight: bold;
      color: #17251F;
      text-transform: uppercase;
      letter-spacing: 0.4pt;
      margin: 2pt 0;
    }
    .title-agency {
      font-size: 7.5pt;
      color: #46514C;
      text-transform: uppercase;
    }
    .classification-tag {
      display: inline-block;
      padding: 2pt 6pt;
      border: 1pt solid #991B1B;
      color: #991B1B;
      font-size: 7.5pt;
      font-weight: bold;
      text-transform: uppercase;
      margin-bottom: 3pt;
    }
    .section-title {
      font-size: 8.5pt;
      font-weight: bold;
      color: #17251F;
      text-transform: uppercase;
      letter-spacing: 0.5pt;
      border-bottom: 1pt solid #586B47;
      padding-bottom: 2pt;
      margin-top: 10pt;
      margin-bottom: 5pt;
    }
    .grid-box {
      background: #F2F2EC;
      border: 0.6pt solid #8C9690;
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
      background: #E7E7DE;
      color: #202622;
      text-align: left;
      padding: 4pt;
      border-bottom: 1pt solid #46514C;
      text-transform: uppercase;
      font-size: 6.8pt;
    }
    .data-table td {
      padding: 3.5pt 4pt;
      border-bottom: 0.5pt solid #CFCFC4;
      vertical-align: top;
    }
    .marker-tag {
      font-size: 6.8pt;
      font-weight: bold;
      padding: 1pt 4pt;
      border: 0.6pt solid #586B47;
      color: #17251F;
      display: inline-block;
      text-transform: uppercase;
      margin-right: 4pt;
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
      border-top: 0.5pt solid #8C9690;
      font-size: 6.8pt;
      color: #46514C;
      padding-top: 3pt;
    }
  </style>
</head>
<body>

  @php
    $c = $report->content;
    $id = $c['section_1_case_identity'] ?? [];
    $exec = $c['section_2_executive_summary'] ?? [];
    $claims = $c['section_4_main_claims'] ?? [];
    $indep = $c['section_7_source_independence'] ?? [];
    $auth = $c['section_8_authenticity_comparison'] ?? [];
    $findings = $c['section_11_key_findings'] ?? [];
    $threat = $c['section_12_threat_indicators'] ?? [];
    $validation = $c['section_13_analyst_validation'] ?? [];
    $limitations = $c['section_14_limitations'] ?? [];
    $recomms = $c['section_15_recommendations'] ?? [];
    $hashes = $c['section_16_evidence_hashes'] ?? [];
  @endphp

  <table class="header-table">
    <tr>
      <td>
        <div class="title-sub">TRACE-S // SOCIAL CYBER INTELLIGENCE PLATFORM</div>
        <div class="title-main">SOCIAL CYBER INTELLIGENCE BRIEF</div>
        <div class="title-agency">EVIDENCE-BASED NARRATIVE &amp; FACT VERIFICATION DOSSIER</div>
      </td>
      <td style="text-align: right; vertical-align: top;">
        <div class="classification-tag">{{ $report->classification_label }}</div>
        <div style="font-size: 8pt; font-weight: bold;">REF: {{ $report->report_code }}</div>
        <div style="font-size: 7.5pt; color: #46514C;">{{ $report->created_at->format('Y-m-d H:i') }} UTC</div>
      </td>
    </tr>
  </table>

  <div class="section-title">1. Executive Summary (Case: {{ $id['case_code'] ?? '-' }})</div>
  <div class="grid-box">{{ $exec['summary'] ?? '-' }}</div>

  <div class="section-title">2. Claims &amp; Evidence-Based Findings</div>
  <div class="grid-box" style="font-style: italic;">"{{ $claims['primary_claim'] ?? '-' }}"</div>
  @foreach($findings as $f)
  <div style="font-size: 7.5pt; margin-bottom: 3pt;">
    <span class="marker-tag">[{{ $f['marker'] }}]</span>
    <span>{{ $f['statement'] }}</span>
  </div>
  @endforeach

  <div class="section-title">3. Evidence &amp; Source Independence</div>
  <div class="grid-box">
    <b>Source Independence:</b> {{ $indep['assessment'] ?? '-' }}<br>
    <b>Authenticity Comparison:</b> {{ $auth['assessment'] ?? '-' }}
  </div>

  <div class="section-title">4. Analyst Validation &amp; Credibility Indicators</div>
  <table style="width: 100%; font-size: 8pt; margin-bottom: 4pt;">
    <tr>
      <td style="width: 50%; vertical-align: top;">
        Threat Level: <b>{{ $threat['threat_level'] ?? 'MODERATE' }}</b><br>
        Credibility Indicator: <b>{{ $threat['credibility_score'] ?? 50 }}/100</b><br>
        Verification Status: <b>{{ $threat['verification_status'] ?? 'UNVERIFIED' }}</b>
      </td>
      <td style="width: 50%; vertical-align: top;">
        Analyst Decision: <b>{{ $validation['status'] ?? 'PENDING REVIEW' }}</b><br>
        Reviewer: <b>{{ $validation['analyst'] ?? '-' }}</b><br>
        Rationale: <i>"{{ $validation['reason'] ?? '-' }}"</i>
      </td>
    </tr>
  </table>

  <div class="section-title">5. Analysis Limitations &amp; Recommendations</div>
  <ul style="font-size: 7.5pt; margin: 2pt 0; padding-left: 15pt;">
    @foreach($limitations as $lim) <li>[Limitation] {{ $lim }}</li> @endforeach
    @foreach($recomms as $rec) <li>[Recommendation] {{ $rec }}</li> @endforeach
  </ul>

  <div class="section-title">6. Evidence File Integrity Hashes (SHA-256)</div>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 15%;">Evidence ID</th>
        <th style="width: 20%;">Method</th>
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

  <div class="footer">
    TRACE-S // SOCIAL CYBER INTELLIGENCE BRIEF // CLASSIFICATION: {{ $report->classification_label }}
  </div>

</body>
</html>
