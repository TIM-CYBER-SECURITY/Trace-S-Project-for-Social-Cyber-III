<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Barryvdh\DomPDF\Facade\Pdf;

echo "Merender PDF Daftar Akun Resmi PUSSIBERAD TNI AD...\n";

$pdf = Pdf::loadView('reports.pussiberad_accounts_pdf')
    ->setPaper('a4', 'portrait')
    ->setOptions([
        'defaultFont' => 'sans-serif',
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled' => false,
    ]);

$outputPath = base_path('DAFTAR_AKUN_RESMI_PUS001TNIAD.pdf');
$pdf->save($outputPath);

echo "Sukses! PDF tersimpan di: {$outputPath} (" . filesize($outputPath) . " bytes)\n";
