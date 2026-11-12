<?php

namespace App\Services\Ai;

/**
 * ModelProfileResolver: mendeteksi perbedaan karakter, spesialisasi, kebutuhan RAM,
 * serta penyesuaian runtime (timeout, temperature, mode reasoning <think>, embedding)
 * secara otomatis untuk setiap model AI lokal yang berbeda.
 */
class ModelProfileResolver
{
    /**
     * Bangun profil kapabilitas & penyesuaian runtime terstruktur dari metadata model.
     *
     * @param  array<string,mixed>  $meta
     * @param  array<string,mixed>|null  $hardware
     * @return array<string,mixed>
     */
    public function resolve(string $modelName, array $meta = [], ?array $hardware = null): array
    {
        $low = strtolower($modelName);
        $arch = strtolower((string) ($meta['family'] ?? $meta['architecture'] ?? ''));

        $paramStr = $this->extractParamString($modelName, $meta['parameters'] ?? $meta['paramsString'] ?? null);
        $paramB = $this->parseParamBillions($paramStr);

        $sizeGb = null;
        if (isset($meta['size_bytes']) && is_numeric($meta['size_bytes']) && (float) $meta['size_bytes'] > 0) {
            $sizeGb = round(((float) $meta['size_bytes']) / 1073741824, 2);
        } elseif (isset($meta['size']) && is_string($meta['size']) && preg_match('/(\d+(?:\.\d+)?)\s*gb/i', $meta['size'], $sm)) {
            $sizeGb = round((float) $sm[1], 2);
        } elseif (isset($meta['size_gb']) && is_numeric($meta['size_gb'])) {
            $sizeGb = round((float) $meta['size_gb'], 2);
        }

        $quant = isset($meta['quantization']) && is_string($meta['quantization'])
            ? strtoupper($meta['quantization'])
            : $this->extractQuantFromName($modelName);

        $estimatedRamGb = $this->estimateRamGb($sizeGb, $paramB, $quant);

        $isEmbedding = (($meta['type'] ?? '') === 'embedding')
            || str_contains($low, 'embed')
            || str_contains($low, 'nomic')
            || str_contains($low, 'bge-')
            || str_contains($arch, 'bert');

        $isVision = !empty($meta['vision'])
            || str_contains($low, '-vl')
            || str_contains($low, 'qwen3vl')
            || str_contains($low, 'vision')
            || str_contains($low, 'llava')
            || str_contains($low, 'pixtral')
            || str_contains($arch, 'vl');

        $isReasoning = !$isEmbedding && (
            str_contains($low, 'reasoning')
            || str_contains($low, 'opus')
            || str_contains($low, 'deepseek-r1')
            || str_contains($low, 'qwq')
            || (str_contains($low, 'qwen3') && !$isVision && !str_contains($low, 'instruct'))
        );

        $isCoder = !$isEmbedding && (
            str_contains($low, 'coder')
            || str_contains($low, 'codellama')
            || str_contains($low, 'starcoder')
        );

        $capabilityType = match (true) {
            $isEmbedding => 'EMBEDDING',
            $isVision => 'MULTIMODAL',
            $isReasoning => 'REASONING',
            $isCoder => 'CODER_JSON',
            ($paramB !== null && $paramB <= 3.5) => 'LIGHTWEIGHT',
            default => 'GENERAL_INSTRUCT',
        };

        $profile = match (true) {
            $isVision => 'MULTIMODAL',
            $paramB !== null && $paramB >= 24.0 => 'ADVANCED',
            $paramB !== null && $paramB >= 11.0 => 'STANDARD',
            $paramB !== null && $paramB <= 4.0 => 'ENTRY',
            default => 'BASIC',
        };

        $family = $this->inferFamily($low, $arch);
        $recommendedTimeout = $this->recommendTimeout($capabilityType, $paramB, $isReasoning);
        $recommendedTemp = $capabilityType === 'CODER_JSON' ? 0.10 : 0.20;

        $fit = $this->evaluateHardwareFit($estimatedRamGb, $hardware);

        $displayName = !empty($meta['display_name'])
            ? (string) $meta['display_name']
            : $this->humanizeDisplayName($modelName);

        $notes = $this->buildIndonesianNote(
            $capabilityType,
            $paramStr,
            $estimatedRamGb,
            $recommendedTimeout,
            $fit['status']
        );

        return [
            'display_name' => $displayName,
            'family' => $family,
            'parameter_size' => $paramStr,
            'size' => $meta['size'] ?? ($sizeGb !== null ? "{$sizeGb} GB" : null),
            'size_gb' => $sizeGb,
            'quantization' => $quant,
            'context_length' => isset($meta['context_length']) ? (int) $meta['context_length'] : null,
            'profile' => $profile,
            'capability_type' => $capabilityType,
            'is_reasoning' => $isReasoning,
            'is_vision' => $isVision,
            'is_embedding' => $isEmbedding,
            'estimated_ram_gb' => $estimatedRamGb,
            'recommended_timeout' => $recommendedTimeout,
            'recommended_temperature' => $recommendedTemp,
            'hardware_fit' => $fit['status'],
            'hardware_fit_label' => $fit['label'],
            'notes' => $notes,
        ];
    }

    /** Evaluasi kecocokan memori model terhadap RAM/VRAM nyata perangkat host. */
    public function evaluateHardwareFit(?float $estimatedRamGb, ?array $hardware): array
    {
        if ($estimatedRamGb === null || !$hardware) {
            return ['status' => 'UNKNOWN', 'label' => 'Estimasi tidak tersedia'];
        }

        $vramTotal = isset($hardware['gpu_vram_total_gb']) ? (float) $hardware['gpu_vram_total_gb'] : null;
        $ramTotal = isset($hardware['ram_total_gb']) ? (float) $hardware['ram_total_gb'] : null;
        $ramFree = isset($hardware['ram_free_gb']) ? (float) $hardware['ram_free_gb'] : null;

        // Jika ada GPU NVIDIA diskret dengan VRAM cukup
        if ($vramTotal !== null && $vramTotal >= $estimatedRamGb) {
            return ['status' => 'FIT', 'label' => "Optimal di GPU VRAM (~{$estimatedRamGb} GB / {$vramTotal} GB)"];
        }

        if ($ramTotal === null) {
            return ['status' => 'UNKNOWN', 'label' => "Butuh ~{$estimatedRamGb} GB RAM"];
        }

        if ($estimatedRamGb > ($ramTotal * 0.85)) {
            return ['status' => 'HEAVY', 'label' => "Workstation Disarankan (~{$estimatedRamGb} GB vs RAM {$ramTotal} GB)"];
        }

        if ($ramFree !== null && $estimatedRamGb <= $ramFree) {
            return ['status' => 'FIT', 'label' => "Muat Nyaman (~{$estimatedRamGb} GB RAM)"];
        }

        if ($estimatedRamGb <= ($ramTotal * 0.58)) {
            return ['status' => 'TIGHT', 'label' => "Ketat RAM bebas (~{$estimatedRamGb} GB; tutup tab/app lain jika lambat)"];
        }

        return ['status' => 'TIGHT', 'label' => "Berat (~{$estimatedRamGb} GB; tutup aplikasi berat sebelum muat)"];
    }

    private function extractParamString(string $name, ?string $rawParam): ?string
    {
        if ($rawParam && preg_match('/(\d+(?:\.\d+)?)\s*([bm])/i', $rawParam, $m)) {
            return strtoupper($m[1] . $m[2]);
        }
        if (preg_match('/(?:^|[-_:\/])(\d+(?:\.\d+)?)\s*b(?:[-_:\/\s]|$)/i', $name, $m)) {
            return strtoupper($m[1]) . 'B';
        }
        if (preg_match('/(\d+(?:\.\d+)?)b\b/i', $name, $m)) {
            return strtoupper($m[1]) . 'B';
        }
        return null;
    }

    private function parseParamBillions(?string $paramStr): ?float
    {
        if (!$paramStr) {
            return null;
        }
        if (preg_match('/^(\d+(?:\.\d+)?)B$/i', trim($paramStr), $m)) {
            return (float) $m[1];
        }
        if (preg_match('/^(\d+(?:\.\d+)?)M$/i', trim($paramStr), $m)) {
            return round(((float) $m[1]) / 1000, 3);
        }
        return null;
    }

    private function extractQuantFromName(string $name): ?string
    {
        if (preg_match('/(Q\d+_[A-Z0-9_]+|FP16|BF16|INT4|INT8)/i', $name, $m)) {
            return strtoupper($m[1]);
        }
        return null;
    }

    private function estimateRamGb(?float $sizeGb, ?float $paramB, ?string $quant): ?float
    {
        if ($sizeGb !== null && $sizeGb > 0) {
            // File GGUF + KV cache + runtime context overhead (~1.22x + 0.25 GB baseline)
            return round(($sizeGb * 1.22) + 0.20, 2);
        }
        if ($paramB !== null) {
            $bytesPerParam = match (true) {
                $quant && str_contains($quant, 'Q8') => 1.05,
                $quant && str_contains($quant, 'Q3') => 0.48,
                $quant && str_contains($quant, 'FP16') => 2.0,
                default => 0.62, // ~Q4_K_M
            };
            return round(($paramB * $bytesPerParam) + 0.5, 2);
        }
        return null;
    }

    private function inferFamily(string $low, string $arch): string
    {
        return match (true) {
            str_contains($arch, 'qwen3vl') || str_contains($low, 'qwen3-vl') || str_contains($low, 'qwen3vl') => 'qwen3-vl',
            str_contains($arch, 'qwen3') || str_contains($low, 'qwen3') => 'qwen3',
            str_contains($arch, 'qwen2') || str_contains($low, 'qwen2.5') || str_contains($low, 'qwen2') => 'qwen2.5',
            str_contains($arch, 'llama') || str_contains($low, 'llama') => 'llama',
            str_contains($low, 'deepseek') => 'deepseek',
            str_contains($low, 'gemma') => 'gemma',
            str_contains($low, 'mistral') => 'mistral',
            str_contains($low, 'nomic') || str_contains($arch, 'bert') => 'nomic-bert',
            $arch !== '' => $arch,
            default => 'llm',
        };
    }

    private function recommendTimeout(string $cap, ?float $paramB, bool $isReasoning): int
    {
        if ($cap === 'EMBEDDING') {
            return 60;
        }
        if ($paramB !== null) {
            if ($paramB >= 24.0) {
                return $isReasoning ? 300 : 240;
            }
            if ($paramB >= 12.0) {
                return $isReasoning ? 240 : 180;
            }
            if ($paramB >= 6.5) {
                return $isReasoning ? 210 : 180;
            }
            if ($paramB >= 1.0) {
                return 120; // 1B-4B JSON terstruktur butuh margin lebih longgar pada inferensi CPU
            }
            return 60; // < 1B ultra-ringan (mis. 0.5B)
        }
        return $isReasoning ? 240 : 180;
    }

    private function humanizeDisplayName(string $id): string
    {
        $clean = str_replace(['-', '_', ':'], ' ', $id);
        return ucwords(preg_replace('/\s+/', ' ', trim($clean)) ?: $id);
    }

    private function buildIndonesianNote(
        string $cap,
        ?string $paramStr,
        ?float $ramGb,
        int $timeout,
        string $fitStatus
    ): string {
        $p = $paramStr ? "{$paramStr} • " : '';
        $r = $ramGb !== null ? "Est. RAM ~{$ramGb} GB • " : '';

        return match ($cap) {
            'REASONING' => "{$p}High/Max Reasoning (<think> otomatis dibersihkan) • {$r}Timeout disesuaikan ke {$timeout}s.",
            'MULTIMODAL' => "{$p}Multimodal Vision-Language • {$r}Timeout disesuaikan ke {$timeout}s.",
            'CODER_JSON' => "{$p}Spesialis Struktur JSON & Kode • {$r}Temp 0.1 • Timeout {$timeout}s.",
            'LIGHTWEIGHT' => "{$p}Ultra-Ringan & Cepat (cocok triage/chat ringan; akurasi skema kompleks terbatas) • {$r}Timeout {$timeout}s.",
            'EMBEDDING' => "Model Vektor Embedding (untuk kemiripan semantik AI_EMBEDDING_MODEL, bukan chat utama).",
            default => "{$p}Instruct Umum • {$r}Timeout disesuaikan ke {$timeout}s" . ($fitStatus === 'HEAVY' ? ' (Workstation disarankan).' : '.'),
        };
    }
}
