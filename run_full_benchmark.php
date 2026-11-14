<?php
/**
 * Script Uji Coba Komparasi Real-Time 20 Kasus (10 Valid + 10 Hoaks)
 * Menggunakan Akun TRS-G4BM-42SA (Sersan Kepala Dandy Aulia, PUSSIBERAD TNI, 010TNI)
 * Pada 3 Model AI Lokal LM Studio:
 * 1. qwen2.5-0.5b-instruct (~0.8GB RAM)
 * 2. qwen2.5-1.5b-instruct (~2.07GB RAM)
 * 3. qwen2.5-coder-1.5b-instruct (~2.07GB RAM)
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\User::where('trace_id', 'TRS-G4BM-42SA')->first();
auth()->login($user);

$aiMgr = app(\App\Services\Ai\AiManager::class);
$audit = app(\App\Services\Support\AuditLogger::class);

$testCases = [
    // 10 Berita Valid / Faktual
    [
        'id' => 'VAL-01',
        'type' => 'VALID',
        'claim' => 'Bank Indonesia mempertahankan suku bunga acuan BI-Rate sebesar 6,00 persen pada pengumuman Rapat Dewan Gubernur Oktober 2024.',
        'topic' => 'Moneter & Perbankan Nasional'
    ],
    [
        'id' => 'VAL-02',
        'type' => 'VALID',
        'claim' => 'Presiden Prabowo Subianto resmi melantik menteri dan wakil menteri Kabinet Merah Putih di Istana Negara Jakarta pada Oktober 2024.',
        'topic' => 'Pemerintahan & Tata Negara'
    ],
    [
        'id' => 'VAL-03',
        'type' => 'VALID',
        'claim' => 'Badan Meteorologi Klimatologi dan Geofisika (BMKG) mengimbau masyarakat pesisir waspada terhadap potensi banjir rob dan gelombang tinggi.',
        'topic' => 'Kebencanaan & Cuaca Ekstrem'
    ],
    [
        'id' => 'VAL-04',
        'type' => 'VALID',
        'claim' => 'Kementerian Komunikasi dan Digital menggencarkan pemblokiran ratusan ribu situs web perjudian online ilegal yang menyasar masyarakat.',
        'topic' => 'Keamanan Siber & Regulasi Digital'
    ],
    [
        'id' => 'VAL-05',
        'type' => 'VALID',
        'claim' => 'PT Kereta Api Indonesia mengoperasikan Kereta Cepat Whoosh rute Jakarta-Bandung dengan kecepatan operasional hingga 350 km per jam.',
        'topic' => 'Infrastruktur & Transportasi'
    ],
    [
        'id' => 'VAL-06',
        'type' => 'VALID',
        'claim' => 'Tim Nasional Sepak Bola Indonesia bertanding melawan Jepang dalam lanjutan Kualifikasi Piala Dunia 2026 zona Asia di Stadion Utama GBK.',
        'topic' => 'Olahraga & Event Internasional'
    ],
    [
        'id' => 'VAL-07',
        'type' => 'VALID',
        'claim' => 'Kementerian Kesehatan RI mendistribusikan vaksin polio tambahan untuk mencegah perluasan Kejadian Luar Biasa (KLB) di sejumlah provinsi.',
        'topic' => 'Kesehatan Masyarakat'
    ],
    [
        'id' => 'VAL-08',
        'type' => 'VALID',
        'claim' => 'Pemerintah menetapkan Upah Minimum Provinsi (UMP) mengalami penyesuaian berkala mengacu pada regulasi ketenagakerjaan dan inflasi tahunan.',
        'topic' => 'Ketenagakerjaan & Sosial Ekonomi'
    ],
    [
        'id' => 'VAL-09',
        'type' => 'VALID',
        'claim' => 'Pusat Siber TNI AD (PUSSIBERAD) memperkuat sistem deteksi dini dan respons insiden siber untuk melindungi infrastruktur pertahanan negara.',
        'topic' => 'Pertahanan Siber Militer'
    ],
    [
        'id' => 'VAL-10',
        'type' => 'VALID',
        'claim' => 'Badan Pusat Statistik (BPS) mencatat pertumbuhan ekonomi Indonesia kuartal III-2024 tumbuh solid di kisaran 4,95 persen secara tahunan (yoy).',
        'topic' => 'Statistik & Makroekonomi'
    ],

    // 10 Berita Hoaks / Disinformasi Rekayasa
    [
        'id' => 'HOAX-01',
        'type' => 'HOAKS',
        'claim' => 'Beredar informasi bahwa pemerintah membagikan subsidi BBM gratis senilai Rp 2 juta per kepala keluarga melalui tautan situs Telegram tidak resmi.',
        'topic' => 'Disinformasi Bantuan Sosial / Phishing'
    ],
    [
        'id' => 'HOAX-02',
        'type' => 'HOAKS',
        'claim' => 'Klaim video viral menyebutkan bendungan Katulampa Bogor jebol total dan menenggelamkan seluruh wilayah Jakarta Pusat hingga Istana Merdeka.',
        'topic' => 'Hoaks Kebencanaan & Kepanikan Publik'
    ],
    [
        'id' => 'HOAX-03',
        'type' => 'HOAKS',
        'claim' => 'Pesan berantai WhatsApp menyatakan Bank Indonesia akan menghapus uang kertas rupiah dan menggantinya 100 persen dengan koin emas kripto swasta bulan depan.',
        'topic' => 'Disinformasi Finansial & Perbankan'
    ],
    [
        'id' => 'HOAX-04',
        'type' => 'HOAKS',
        'claim' => 'Beredar kabar bahwa Menteri Luar Negeri menyatakan Indonesia resmi menyatakan perang terbuka terhadap negara tetangga dalam sidang PBB.',
        'topic' => 'Disinformasi Geopolitik & Provokasi Perang'
    ],
    [
        'id' => 'HOAX-05',
        'type' => 'HOAKS',
        'claim' => 'Klaim disinformasi: Bandara Internasional Soekarno-Hatta ditutup permanen karena seluruh landasan pacu amblas sedalam 10 meter.',
        'topic' => 'Hoaks Transportasi & Fasilitas Publik'
    ],
    [
        'id' => 'HOAX-06',
        'type' => 'HOAKS',
        'claim' => 'Klaim unggahan media sosial menyebutkan seluruh jaringan internet nasional akan dipadamkan total selama satu minggu penuh tanpa pemberitahuan.',
        'topic' => 'Hoaks Pemadaman Infrastruktur Telekomunikasi'
    ],
    [
        'id' => 'HOAX-07',
        'type' => 'HOAKS',
        'claim' => 'Pemberitaan palsu mengklaim vaksinasi influenza wajib di sekolah disusupi cip pelacak militer asing untuk mengontrol pikiran siswa.',
        'topic' => 'Teori Konspirasi & Disinformasi Medis'
    ],
    [
        'id' => 'HOAX-08',
        'type' => 'HOAKS',
        'claim' => 'Surat edaran palsu mengatasnamakan Mabes TNI mewajibkan setiap pemuda berusia 18 tahun langsung masuk medan tempur luar negeri besok pagi.',
        'topic' => 'Pemalsuan Dokumen Resmi Pertahanan'
    ],
    [
        'id' => 'HOAX-09',
        'type' => 'HOAKS',
        'claim' => 'Klaim narasi rekaman audio tiruan AI menyebutkan Mahkamah Konstitusi membatalkan seluruh undang-undang ketenagakerjaan secara mendadak.',
        'topic' => 'Manipulasi Regulasi & Audio Deepfake'
    ],
    [
        'id' => 'HOAX-10',
        'type' => 'HOAKS',
        'claim' => 'Klaim foto editan menyatakan seluruh cadangan beras di gudang BULOG habis diserbu hama dan Indonesia berada di ambang kelaparan akut besok.',
        'topic' => 'Hoaks Ketahanan Pangan & Agitasi Sosial'
    ],
];

$modelsToTest = [
    'qwen2.5-0.5b-instruct',
    'qwen2.5-coder-1.5b-instruct',
    'qwen2.5-1.5b-instruct',
];

$results = [];

echo "========================================================================\n";
echo "MEMULAI BENCHMARK REAL-TIME 20 KASUS PADA 3 MODEL LOCAL AI LM STUDIO\n";
echo "Akun Operator/Admin : {$user->name} ({$user->trace_id})\n";
echo "Instansi            : PUSSIBERAD TNI (Kode: 010TNI)\n";
echo "Waktu Mulai         : " . date('Y-m-d H:i:s') . "\n";
echo "========================================================================\n\n";

foreach ($modelsToTest as $mName) {
    echo ">>> MENGAKTIFKAN MODEL: {$mName} ...\n";
    $mModel = App\Models\AiModel::where('name', $mName)->first();
    if (!$mModel) {
        echo "MODEL {$mName} TIDAK DITEMUKAN DI DB!\n";
        continue;
    }
    
    $switchStart = microtime(true);
    $aiMgr->activateModel($mModel, $user, $audit, "Testing Benchmarking 20 Kasus");
    $switchDuration = round(microtime(true) - $switchStart, 2);
    echo "Model aktif: " . ($aiMgr->status()['active_model'] ?? 'none') . " (Waktu Switch: {$switchDuration}s)\n";

    $results[$mName] = [
        'model_name' => $mName,
        'estimated_ram' => $mModel->estimated_ram_gb . ' GB',
        'switch_duration_s' => $switchDuration,
        'cases' => []
    ];

    foreach ($testCases as $idx => $tc) {
        $caseNo = $idx + 1;
        $timestamp = date('Y-m-d H:i:s');
        $t0 = microtime(true);
        
        $error = null;
        $scanResult = null;
        try {
            $res = $aiMgr->analyze('credibility_scan', [
                'text' => $tc['claim'],
                'evidence_list' => [],
            ], []);
            $scanResult = $res['result'];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $duration = round(microtime(true) - $t0, 2);

        $entry = [
            'case_no' => $caseNo,
            'case_id' => $tc['id'],
            'type' => $tc['type'],
            'topic' => $tc['topic'],
            'claim' => $tc['claim'],
            'timestamp' => $timestamp,
            'duration_s' => $duration,
            'status' => $error ? 'ERROR' : 'SUCCESS',
            'error_message' => $error,
            'summary' => $scanResult['summary'] ?? '-',
            'threat_level' => $scanResult['threat_level'] ?? 'UNIDENTIFIED',
            'verification_status' => $scanResult['verification_status'] ?? 'UNIDENTIFIED',
            'credibility_score' => $scanResult['credibility_score'] ?? 0,
            'fallacy' => $scanResult['fallacy'] ?? '-',
            'tactical_summary' => $scanResult['tactical_summary'] ?? '-'
        ];

        $results[$mName]['cases'][] = $entry;

        echo "  [{$caseNo}/20] {$tc['id']} ({$tc['type']}) -> Status: " . ($error ? 'ERROR' : 'OK') . " | {$duration}s | Skor: " . ($entry['credibility_score']) . " | Level: " . ($entry['threat_level']) . "\n";
    }
    echo "\n";
}

// Simpan hasil mentah ke file JSON terstruktur untuk diproses ke PDF
$jsonPath = storage_path('app/benchmark_results_realtime.json');
file_put_contents($jsonPath, json_encode([
    'meta' => [
        'generated_at' => date('Y-m-d H:i:s'),
        'user_name' => $user->name,
        'trace_id' => $user->trace_id,
        'organization' => 'PUSSIBERAD TNI',
        'organization_code' => '010TNI',
        'role' => 'ADMINISTRATOR',
        'total_cases' => count($testCases),
        'valid_cases' => 10,
        'hoax_cases' => 10,
    ],
    'models' => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "========================================================================\n";
echo "BENCHMARK SELESAI. Data disimpan ke: {$jsonPath}\n";
echo "========================================================================\n";
