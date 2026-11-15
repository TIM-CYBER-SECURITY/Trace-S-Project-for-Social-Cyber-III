<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

echo "=========================================================\n";
echo "  TRACE-S: COMPILATION OF OFFICIAL HACKATHON PDFS        \n";
echo "=========================================================\n\n";

// Function to add footer to DomPDF
function addPageNumberFooter($pdf, $docTitle) {
    $dompdf = $pdf->getDomPDF();
    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->getFont("Helvetica", "normal");
    
    // Bottom left: doc title, Bottom right: page number
    $canvas->page_text(
        40, 
        820, 
        "TRACE-S // " . $docTitle . " • KARTIKA HACKATHON CYBER 2026 • TIM SINDIKAT_PAWPATROL", 
        $font, 
        6.5, 
        [0.4, 0.45, 0.4]
    );
    
    $canvas->page_text(
        500, 
        820, 
        "Hal {PAGE_NUM} dari {PAGE_COUNT}", 
        $font, 
        6.5, 
        [0.4, 0.45, 0.4]
    );
}

// -------------------------------------------------------------
// 1. DAFTAR AKUN RESMI PUSSIBERAD TNI AD
// -------------------------------------------------------------
echo "[1/3] Merender DAFTAR_AKUN_RESMI_PUS001TNIAD.pdf...\n";
$pdfAccounts = Pdf::loadView('reports.pussiberad_accounts_pdf')
    ->setPaper('a4', 'portrait')
    ->setOptions([
        'defaultFont' => 'sans-serif',
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled' => false,
    ]);

$pdfAccounts->render();
addPageNumberFooter($pdfAccounts, "DAFTAR DISTRIBUSI IDENTITAS OPERASIONAL TRACE-ID (PUS001TNIAD)");
$pathAccounts = base_path('DAFTAR_AKUN_RESMI_PUS001TNIAD.pdf');
File::put($pathAccounts, $pdfAccounts->output());
echo "  -> Tersimpan: {$pathAccounts} (" . number_format(filesize($pathAccounts)) . " bytes)\n\n";

// -------------------------------------------------------------
// 2. PROPOSAL SOLUSI TRACE-S
// -------------------------------------------------------------
echo "[2/3] Merender PROPOSAL_SOLUSI_TRACE-S.pdf...\n";
$proposalMdPath = base_path('PROPOSAL_SOLUSI_TRACE-S.md');
if (!File::exists($proposalMdPath)) {
    echo "  [ERROR] Berkas PROPOSAL_SOLUSI_TRACE-S.md tidak ditemukan!\n";
    exit(1);
}

$rawProposalMd = File::get($proposalMdPath);
// Remove first H1 from markdown content since it is in header banner
$cleanProposalMd = preg_replace('/^#\s+PROPOSAL SOLUSI: TRACE-S\s*\n(##\s+[^\n]+\n)?/i', '', $rawProposalMd);
$htmlProposal = Str::markdown($cleanProposalMd);

$pdfProposal = Pdf::loadView('reports.markdown_document_pdf', [
    'title' => 'PROPOSAL SOLUSI: PLATFORM TRACE-S',
    'subtitle' => 'Social Cyber Cognitive Defense & Intelligence Platform Berdaulat Berbasis Hybrid AI & Multi-Agency Isolation',
    'htmlContent' => $htmlProposal,
])
->setPaper('a4', 'portrait')
->setOptions([
    'defaultFont' => 'sans-serif',
    'isHtml5ParserEnabled' => true,
    'isRemoteEnabled' => false,
]);

$pdfProposal->render();
addPageNumberFooter($pdfProposal, "PROPOSAL SOLUSI TRACE-S (KARTIKA CYBER 2026)");
$pathProposal = base_path('PROPOSAL_SOLUSI_TRACE-S.pdf');
File::put($pathProposal, $pdfProposal->output());
echo "  -> Tersimpan: {$pathProposal} (" . number_format(filesize($pathProposal)) . " bytes)\n\n";

// -------------------------------------------------------------
// 3. DOKUMENTASI TEKNIS SISTEM TRACE-S
// -------------------------------------------------------------
echo "[3/3] Merender DOKUMENTASI_TEKNIS_TRACE-S.pdf...\n";
$techMdPath = base_path('DOKUMENTASI_TEKNIS_TRACE-S.md');
if (!File::exists($techMdPath)) {
    echo "  [ERROR] Berkas DOKUMENTASI_TEKNIS_TRACE-S.md tidak ditemukan!\n";
    exit(1);
}

$rawTechMd = File::get($techMdPath);
// Remove first H1 from markdown content since it is in header banner
$cleanTechMd = preg_replace('/^#\s+DOKUMENTASI TEKNIS SISTEM: TRACE-S & TRACE-ID\s*\n(##\s+[^\n]+\n)?/i', '', $rawTechMd);
$htmlTech = Str::markdown($cleanTechMd);

$pdfTech = Pdf::loadView('reports.markdown_document_pdf', [
    'title' => 'DOKUMENTASI TEKNIS SISTEM TRACE-S',
    'subtitle' => 'Arsitektur, Protokol Keamanan, Pipeline Verifikasi Kognitif, Tata Kelola Akun, dan Bukti Uji Ketahanan Multi-Pass',
    'htmlContent' => $htmlTech,
])
->setPaper('a4', 'portrait')
->setOptions([
    'defaultFont' => 'sans-serif',
    'isHtml5ParserEnabled' => true,
    'isRemoteEnabled' => false,
]);

$pdfTech->render();
addPageNumberFooter($pdfTech, "DOKUMENTASI TEKNIS TRACE-S & TRACE-ID");
$pathTech = base_path('DOKUMENTASI_TEKNIS_TRACE-S.pdf');
File::put($pathTech, $pdfTech->output());
echo "  -> Tersimpan: {$pathTech} (" . number_format(filesize($pathTech)) . " bytes)\n\n";

echo "=========================================================\n";
echo "  SEMUA PDF BERHASIL DIBUAT DENGAN SEMPURNA!             \n";
echo "=========================================================\n";
