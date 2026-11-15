<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>{{ $title }}</title>
  <style>
    @page {
      margin: 14mm 14mm 16mm 14mm;
      size: a4 portrait;
    }
    body {
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      font-size: 8.2pt;
      line-height: 1.45;
      color: #1a201c;
      background: #ffffff;
      margin: 0;
      padding: 0;
    }
    .header-banner {
      width: 100%;
      border-bottom: 2.5pt solid #17251F;
      padding-bottom: 6pt;
      margin-bottom: 10pt;
    }
    .header-sub {
      font-size: 7.5pt;
      letter-spacing: 1.5pt;
      color: #3b5333;
      text-transform: uppercase;
      font-weight: bold;
    }
    .header-title {
      font-size: 14pt;
      font-weight: bold;
      color: #17251F;
      text-transform: uppercase;
      letter-spacing: 0.2pt;
      margin: 2pt 0;
    }
    .header-desc {
      font-size: 8pt;
      color: #4b5550;
      font-weight: 500;
    }
    .badge-tactical {
      display: inline-block;
      padding: 2.5pt 6pt;
      background: #17251F;
      color: #ffffff;
      font-size: 7pt;
      font-weight: bold;
      text-transform: uppercase;
      border-radius: 2pt;
    }
    .badge-green {
      display: inline-block;
      padding: 2pt 5pt;
      background: #2D4A22;
      color: #ffffff;
      font-size: 6.8pt;
      font-weight: bold;
      border-radius: 2pt;
    }
    .info-box {
      background: #F4F6F4;
      border: 0.8pt solid #8C9690;
      padding: 6pt 8pt;
      margin-bottom: 12pt;
    }
    .info-box table {
      width: 100%;
      font-size: 7.5pt;
      border-collapse: collapse;
      margin: 0;
    }
    .info-box td {
      padding: 2pt;
      vertical-align: top;
      border: none;
      background: transparent;
    }
    .mono {
      font-family: 'Courier New', Courier, monospace;
      font-weight: bold;
      font-size: 7.8pt;
    }
    
    /* Markdown Styles */
    h1 {
      color: #17251F;
      font-size: 13pt;
      border-bottom: 1.5pt solid #17251F;
      padding-bottom: 3pt;
      margin-top: 14pt;
      margin-bottom: 6pt;
      text-transform: uppercase;
      letter-spacing: 0.3pt;
    }
    h2 {
      color: #2D4A22;
      font-size: 10.5pt;
      border-bottom: 0.8pt solid #8C9690;
      padding-bottom: 2pt;
      margin-top: 12pt;
      margin-bottom: 5pt;
      text-transform: uppercase;
    }
    h3 {
      color: #17251F;
      font-size: 9.2pt;
      margin-top: 10pt;
      margin-bottom: 3pt;
      border-left: 2.5pt solid #3B5335;
      padding-left: 4pt;
    }
    h4 {
      color: #3B5335;
      font-size: 8.5pt;
      margin-top: 8pt;
      margin-bottom: 2pt;
    }
    p {
      margin: 3pt 0 5pt 0;
      text-align: justify;
    }
    ul, ol {
      margin: 2pt 0 5pt 14pt;
      padding: 0;
    }
    li {
      margin-bottom: 2pt;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 7.3pt;
      margin: 6pt 0 10pt 0;
      page-break-inside: avoid;
    }
    th {
      background: #17251F;
      color: #ffffff;
      text-align: left;
      padding: 4pt 5pt;
      border: 0.5pt solid #17251F;
      text-transform: uppercase;
      font-size: 6.8pt;
      font-weight: bold;
      letter-spacing: 0.3pt;
    }
    td {
      padding: 3.5pt 5pt;
      border: 0.5pt solid #8C9690;
      vertical-align: top;
    }
    tr:nth-child(even) {
      background-color: #FAFCFA;
    }
    pre {
      background-color: #17251F;
      color: #E2E8F0;
      padding: 6pt 8pt;
      border-radius: 3pt;
      font-family: 'Courier New', Courier, monospace;
      font-size: 6.8pt;
      line-height: 1.25;
      white-space: pre-wrap;
      word-wrap: break-word;
      page-break-inside: avoid;
      margin: 5pt 0 8pt 0;
    }
    code {
      font-family: 'Courier New', Courier, monospace;
      font-size: 7.2pt;
      background: #EBF3EA;
      color: #1E3A8A;
      padding: 1pt 3pt;
      border-radius: 2pt;
      font-weight: bold;
    }
    pre code {
      background: transparent;
      color: #E2E8F0;
      padding: 0;
      font-weight: normal;
    }
    blockquote {
      border-left: 3pt solid #3B5335;
      background: #F4F6F4;
      margin: 5pt 0;
      padding: 4pt 8pt;
      font-style: italic;
      color: #2F3E34;
    }
    hr {
      border: none;
      border-top: 0.8pt solid #CBD5E1;
      margin: 10pt 0;
    }
    .page-break {
      page-break-after: always;
    }
  </style>
</head>
<body>

  <!-- HEADER BANNER -->
  <table class="header-banner">
    <tr>
      <td style="width: 72%;">
        <div class="header-sub">KARTIKA HACKATHON CYBER 2026 // PUSSIBERAD TNI AD</div>
        <div class="header-title">{{ $title }}</div>
        <div class="header-desc">{{ $subtitle }}</div>
      </td>
      <td style="width: 28%; text-align: right; vertical-align: middle;">
        <span class="badge-tactical">KATEGORI 04: SIBER SOSIAL</span><br>
        <span style="font-size: 6.8pt; color: #444; margin-top: 3pt; display: inline-block;">
          TIM: <strong>SINDIKAT_PAWPATROL</strong>
        </span>
      </td>
    </tr>
  </table>

  <!-- METADATA BOX -->
  <div class="info-box">
    <table>
      <tr>
        <td style="width: 17%;"><strong>PENGUSUL:</strong></td>
        <td style="width: 33%;">Tim Sindikat_PawPatrol</td>
        <td style="width: 20%;"><strong>LIVE CLOUD RAILWAY:</strong></td>
        <td style="width: 30%;"><span class="mono" style="color:#1E3A8A;">https://trace-s-web-production.up.railway.app</span></td>
      </tr>
      <tr>
        <td><strong>INSTANSI MITRA:</strong></td>
        <td>PUSSIBERAD TNI AD</td>
        <td><strong>KODE INSTANSI:</strong></td>
        <td><span class="mono">PUS001TNIAD</span> (Mitra) & <span class="mono">010TNI</span> (Dev)</td>
      </tr>
      <tr>
        <td><strong>REPOSITORI GITHUB:</strong></td>
        <td><span class="mono">TIM-CYBER-SECURITY/Trace-S-Project-for-Social-Cyber-III</span></td>
        <td><strong>METODOLOGI UJI:</strong></td>
        <td><strong>Zero-Simulation</strong> (60/60 Empirical Runs &bull; 49 Tests)</td>
      </tr>
      <tr>
        <td><strong>STATUS SISTEM:</strong></td>
        <td>Siap Operasional / Production-Ready</td>
        <td><strong>AKUN VERIFIKASI JURI:</strong></td>
        <td><span class="mono">TRS-984Y-SCC7</span> (Sandi: <span class="mono">Mh@2?8gxgY@*m5uQ</span>)</td>
      </tr>
    </table>
  </div>

  <!-- DOCUMENT CONTENT -->
  <div class="doc-content">
    {!! $htmlContent !!}
  </div>

</body>
</html>
