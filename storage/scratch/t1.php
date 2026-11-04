<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$ref="Pemerintah mempertimbangkan penyesuaian alokasi anggaran pada beberapa program.";
$cmp="Pemerintah resmi menghentikan seluruh program.";
$d=app(App\Services\Analysis\TextDiff::class)->diff($ref,$cmp);
echo json_encode($d['blocks']), PHP_EOL;
$c=app(App\Services\Analysis\ChangeImpact::class)->explain($ref,$cmp);
echo json_encode(array_column($c,'element')), PHP_EOL;
$m=app(App\Services\Analysis\ClaimMatcher::class)->compare("Anggaran direncanakan untuk disesuaikan.","Anggaran telah resmi dipotong.");
echo $m['match_status'], ' ', json_encode($m['differences']), PHP_EOL;
$l=app(App\Services\Analysis\LexicalSimilarity::class)->compare($ref,$cmp); echo $l['score'], PHP_EOL;
$n=app(App\Services\Analysis\NumericEntityComparer::class);
echo json_encode(array_column($n->numbers("8 juta views, 621 ribu likes, 7.841 komentar, 12,5% dan Rp 5 triliun tahun 2026"),'key')), PHP_EOL;
echo json_encode($n->entities("Prabowo resmi menghentikan MBG dan KDMP. Menteri Keuangan Sri menyatakan APBN dialihkan.")), PHP_EOL;