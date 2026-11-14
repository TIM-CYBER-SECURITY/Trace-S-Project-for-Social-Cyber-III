<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

$resultsPath = storage_path('app/benchmark_results_realtime.json');

if (!File::exists($resultsPath)) {
    echo "Berkas hasil benchmark belum ditemukan di: {$resultsPath}\n";
    exit(1);
}

$rawJson = File::get($resultsPath);
$data = json_decode($rawJson, true);

if (!$data || !isset($data['models'])) {
    echo "Format JSON benchmark tidak valid.\n";
    exit(1);
}

echo "Memuat data benchmark...\n";

$meta = $data['meta'] ?? [];
$models = $data['models'] ?? [];

$modelsSummary = [];
$benchmarks = [];
$casesMethodology = [];

// Extract cases methodology from the first model
$firstModelCases = reset($models)['cases'] ?? [];
foreach ($firstModelCases as $cs) {
    $casesMethodology[] = [
        'id' => $cs['case_id'],
        'type' => $cs['type'],
        'topic' => $cs['topic'],
        'claim' => $cs['claim'],
        'source_ref' => ($cs['type'] === 'VALID' ? 'Fakta Resmi Pemerintah / Lembaga Terkait' : 'Fabrikasi / Narasi Disinformasi Terekam'),
    ];
}

foreach ($models as $modelKey => $mInfo) {
    $modelName = $mInfo['model_name'] ?? $modelKey;
    $cases = $mInfo['cases'] ?? [];
    $totalCases = count($cases);
    $casesOk = count(array_filter($cases, fn($c) => ($c['status'] ?? '') === 'SUCCESS'));
    $avgScan = $totalCases > 0 ? round(array_sum(array_column($cases, 'duration_s')) / $totalCases, 2) : 0;
    
    $arch = 'Qwen2.5 Dense Transformers (4-bit Quantized)';
    if (str_contains($modelName, '0.5b')) {
        $arch = 'Qwen2.5 Ultra-Compact (GGUF Q4_K_M)';
    } elseif (str_contains($modelName, 'coder')) {
        $arch = 'Qwen2.5-Coder Logic-Specialized (GGUF Q4_K_M)';
    } else {
        $arch = 'Qwen2.5-Instruct Generalist (GGUF Q4_K_M)';
    }

    $modelsSummary[] = [
        'model_id' => $modelName,
        'architecture' => $arch,
        'ram_footprint' => $mInfo['estimated_ram'] ?? '2.07 GB',
        'switch_time_sec' => $mInfo['switch_duration_s'] ?? 0,
        'avg_scan_sec' => $avgScan,
        'cases_ok' => $casesOk,
        'total_cases' => $totalCases,
        'success_rate' => $totalCases > 0 ? round(($casesOk / $totalCases) * 100, 1) . '%' : '0%',
    ];

    $formattedResults = [];
    foreach ($cases as $c) {
        $formattedResults[] = [
            'case_id' => $c['case_id'],
            'expected_type' => $c['type'],
            'timestamp' => $c['timestamp'],
            'duration_sec' => $c['duration_s'],
            'status' => ($c['status'] === 'SUCCESS' ? 'OK' : $c['status']),
            'credibility_score' => $c['credibility_score'],
            'threat_level' => $c['threat_level'],
            'claim_short' => mb_strimwidth($c['claim'], 0, 75, '...'),
            'reasoning_summary' => mb_strimwidth($c['tactical_summary'] ?? ($c['summary'] ?? '-'), 0, 190, '...'),
        ];
    }

    $benchmarks[] = [
        'model_id' => $modelName,
        'switch_time' => $mInfo['switch_duration_s'] ?? 0,
        'results' => $formattedResults,
    ];
}

$viewData = [
    'operator' => [
        'name' => $meta['user_name'] ?? 'Sersan Kepala Dandy Aulia',
        'trace_id' => $meta['trace_id'] ?? 'TRS-G4BM-42SA',
        'org' => $meta['organization'] ?? 'PUSSIBERAD TNI',
        'org_code' => $meta['organization_code'] ?? '010TNI',
        'role' => $meta['role'] ?? 'ADMINISTRATOR',
    ],
    'environment' => [
        'endpoint' => 'http://127.0.0.1:1234/v1',
    ],
    'models_summary' => $modelsSummary,
    'cases' => $casesMethodology,
    'benchmarks' => $benchmarks,
];

echo "Merender PDF dengan Blade dan DomPDF...\n";

$pdf = Pdf::loadView('reports.benchmark_report', $viewData)
    ->setPaper('a4', 'portrait')
    ->setOptions([
        'defaultFont' => 'sans-serif',
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled' => false,
    ]);

$outputPath1 = storage_path('app/public/TRACE-S_BENCHMARK_REPORT_REALTIME_TRS-G4BM-42SA.pdf');
$outputPath2 = base_path('TRACE-S_BENCHMARK_REPORT_REALTIME_TRS-G4BM-42SA.pdf');

$pdf->save($outputPath1);
$pdf->save($outputPath2);

echo "PDF Berhasil dibuat di:\n - {$outputPath1}\n - {$outputPath2}\n";
