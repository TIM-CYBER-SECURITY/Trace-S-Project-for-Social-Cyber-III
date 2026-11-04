<?php

namespace App\Services\Analysis;

use App\Services\Ai\AiManager;
use App\Services\Ai\Exceptions\AiUnavailableException;

/**
 * Semantic Similarity (Supplemental §5.2-B): cosine embedding.
 * Bila model embedding tidak tersedia, hasilnya "available=false" (bukan angka palsu).
 * Keluaran MockDriver selalu berlabel SIMULASI.
 */
class SemanticSimilarity
{
    public function __construct(private AiManager $ai)
    {
    }

    private function meta(): array
    {
        $d = $this->ai->driver();
        $sim = $d->isSimulation();
        return [
            'method' => $sim ? 'Hashing trigram karakter (SIMULASI, bukan semantik sungguhan)' : 'Embedding cosine',
            'model' => $sim ? $d->modelName() : (config('trace.ai.embedding_model') ?: null),
            'version' => $sim ? '1.0' : null,
            'is_simulation' => $sim,
        ];
    }

    /** @return array{available:bool,score:?float,method:string,model:?string,version:?string,is_simulation:bool,note:?string} */
    public function compare(string $a, string $b): array
    {
        $m = $this->matrix([0 => $a, 1 => $b]);
        return $m['available']
            ? ['available' => true, 'score' => $m['matrix'][0][1]] + $m['meta'] + ['note' => null]
            : ['available' => false, 'score' => null, 'note' => $m['note']] + $m['meta'];
    }

    /**
     * @param  array<int|string,string>  $texts
     * @return array{available:bool,matrix:array,meta:array,note:?string}
     */
    public function matrix(array $texts): array
    {
        try {
            $meta = $this->meta();
        } catch (\Throwable $e) {
            return ['available' => false, 'matrix' => [], 'meta' => ['method' => 'Embedding cosine', 'model' => null, 'version' => null, 'is_simulation' => false], 'note' => $e->getMessage()];
        }

        $keys = array_keys($texts);
        try {
            $vectors = $this->ai->embed(array_values($texts));
        } catch (AiUnavailableException $e) {
            return ['available' => false, 'matrix' => [], 'meta' => $meta, 'note' => 'Semantic similarity tidak tersedia: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            return ['available' => false, 'matrix' => [], 'meta' => $meta, 'note' => 'Semantic similarity gagal: ' . $e->getMessage()];
        }

        $byKey = array_combine($keys, $vectors);
        $m = [];
        foreach ($keys as $i) {
            foreach ($keys as $j) {
                $m[$i][$j] = $i === $j ? 100.0 : round(max(0.0, $this->cosine($byKey[$i], $byKey[$j])) * 100, 1);
            }
        }
        return ['available' => true, 'matrix' => $m, 'meta' => $meta, 'note' => null];
    }

    private function cosine(array $a, array $b): float
    {
        $dot = $na = $nb = 0.0;
        foreach ($a as $i => $x) {
            $y = $b[$i] ?? 0.0;
            $dot += $x * $y;
            $na += $x * $x;
            $nb += $y * $y;
        }
        return ($na > 0 && $nb > 0) ? $dot / (sqrt($na) * sqrt($nb)) : 0.0;
    }
}
