<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * HardwareTelemetry & Local AI Engine Discovery (100% REAL HOST TELEMETRY).
 * Membaca spesifikasi nyata CPU, RAM, GPU/VRAM, ruang disk, serta engine AI lokal
 * (Ollama, LM Studio, llama.cpp/vLLM, dan berkas GGUF terpasang di mesin host).
 * TIDAK pernah mengarang metrik; bila suatu pembacaan tidak tersedia, melaporkan UNAVAILABLE.
 */
class HardwareTelemetry
{
    public function snapshot(bool $forceRefresh = false): array
    {
        if (app()->environment('testing')) {
            return $this->minimalHostInfo();
        }

        if ($forceRefresh) {
            Cache::forget('trace_host_hardware_telemetry');
        }

        return Cache::remember('trace_host_hardware_telemetry', now()->addSeconds(15), function () {
            $host = $this->readHostHardware();
            $host['captured_at'] = now()->toIso8601String();
            return $host;
        });
    }

    private function minimalHostInfo(): array
    {
        return [
            'available' => true,
            'os' => PHP_OS_FAMILY,
            'hostname' => gethostname() ?: 'localhost',
            'cpu_name' => PHP_OS_FAMILY . ' Host CPU',
            'cpu_cores' => 1,
            'ram_total_gb' => null,
            'ram_free_gb' => null,
            'ram_used_gb' => null,
            'ram_used_pct' => null,
            'gpu_name' => null,
            'gpu_vram_total_gb' => null,
            'gpu_vram_used_gb' => null,
            'gpu_type' => 'UNAVAILABLE',
            'disk_free_gb' => null,
            'disk_total_gb' => null,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    private function readHostHardware(): array
    {
        $diskFree = @disk_free_space(base_path());
        $diskTotal = @disk_total_space(base_path());

        $base = [
            'available' => true,
            'os' => PHP_OS_FAMILY,
            'hostname' => gethostname() ?: 'localhost',
            'cpu_name' => null,
            'cpu_cores' => null,
            'ram_total_gb' => null,
            'ram_free_gb' => null,
            'ram_used_gb' => null,
            'ram_used_pct' => null,
            'gpu_name' => null,
            'gpu_vram_total_gb' => null,
            'gpu_vram_used_gb' => null,
            'gpu_type' => 'UNAVAILABLE',
            'disk_free_gb' => $diskFree !== false ? round($diskFree / 1073741824, 1) : null,
            'disk_total_gb' => $diskTotal !== false ? round($diskTotal / 1073741824, 1) : null,
        ];

        if (PHP_OS_FAMILY === 'Windows') {
            $base = array_merge($base, $this->probeWindows());
        } else {
            $base = array_merge($base, $this->probeUnix());
        }

        // Cek nvidia-smi jika ada GPU NVIDIA diskret (e.g. workstation PUSSIBERAD)
        $nv = $this->probeNvidiaSmi();
        if ($nv !== null) {
            $base['gpu_name'] = $nv['name'];
            $base['gpu_vram_total_gb'] = $nv['vram_total_gb'];
            $base['gpu_vram_used_gb'] = $nv['vram_used_gb'];
            $base['gpu_type'] = 'NVIDIA_DISCRETE';
        }

        return $base;
    }

    private function probeWindows(): array
    {
        $res = [];
        if (getenv('NUMBER_OF_PROCESSORS')) {
            $res['cpu_cores'] = (int) getenv('NUMBER_OF_PROCESSORS');
        }

        try {
            $cmd = 'powershell -NoProfile -NonInteractive -Command ' . escapeshellarg(
                '$cpu = (Get-ItemProperty "HKLM:\HARDWARE\DESCRIPTION\System\CentralProcessor\0" -ErrorAction SilentlyContinue).ProcessorNameString; '
                . '$os = Get-CimInstance Win32_OperatingSystem | Select-Object TotalVisibleMemorySize,FreePhysicalMemory; '
                . '$gpu = Get-CimInstance Win32_VideoController | Select-Object Name,AdapterRAM; '
                . '[PSCustomObject]@{cpu=$cpu;os=$os;gpu=$gpu} | ConvertTo-Json -Compress -Depth 3'
            );
            $raw = @shell_exec($cmd);
            if ($raw && is_array($json = json_decode(trim($raw), true))) {
                if (!empty($json['cpu'])) {
                    $res['cpu_name'] = trim((string) $json['cpu']);
                }
                if (!empty($json['os']['TotalVisibleMemorySize'])) {
                    $totalKb = (float) $json['os']['TotalVisibleMemorySize'];
                    $freeKb = (float) ($json['os']['FreePhysicalMemory'] ?? 0);
                    $totalGb = round($totalKb / 1048576, 2);
                    $freeGb = round($freeKb / 1048576, 2);
                    $usedGb = max(0, round($totalGb - $freeGb, 2));
                    $res['ram_total_gb'] = $totalGb;
                    $res['ram_free_gb'] = $freeGb;
                    $res['ram_used_gb'] = $usedGb;
                    $res['ram_used_pct'] = $totalGb > 0 ? round(($usedGb / $totalGb) * 100, 1) : null;
                }
                $gpuRow = $json['gpu'] ?? null;
                if (is_array($gpuRow)) {
                    // Win32_VideoController bisa mengembalikan array objek bila ada >1 GPU, pilih non-Microsoft Basic
                    $gpus = isset($gpuRow['Name']) ? [$gpuRow] : $gpuRow;
                    foreach ($gpus as $g) {
                        $name = trim((string) ($g['Name'] ?? ''));
                        if ($name !== '' && stripos($name, 'Basic Display') === false) {
                            $res['gpu_name'] = $name;
                            $adapterRam = (float) ($g['AdapterRAM'] ?? 0);
                            $res['gpu_type'] = stripos($name, 'NVIDIA') !== false || stripos($name, 'Radeon RX') !== false ? 'DISCRETE' : 'INTEGRATED_SHARED';
                            if ($adapterRam > 0 && $res['gpu_type'] === 'DISCRETE') {
                                $res['gpu_vram_total_gb'] = round($adapterRam / 1073741824, 2);
                            }
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Biarkan apa adanya tanpa mengarang nilai
        }

        return $res;
    }

    private function probeUnix(): array
    {
        $res = [];
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = (string) @file_get_contents('/proc/cpuinfo');
            if (preg_match('/model name\s*:\s*(.+)/i', $cpuinfo, $m)) {
                $res['cpu_name'] = trim($m[1]);
            }
            if (preg_match_all('/^processor\s*:/m', $cpuinfo, $matches)) {
                $res['cpu_cores'] = count($matches[0]);
            }
        }
        if (is_readable('/proc/meminfo')) {
            $mem = (string) @file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)\s+kB/i', $mem, $mt)) {
                $totalGb = round(((float) $mt[1]) / 1048576, 2);
                $availKb = preg_match('/MemAvailable:\s+(\d+)\s+kB/i', $mem, $ma) ? (float) $ma[1] : 0.0;
                $freeGb = round($availKb / 1048576, 2);
                $usedGb = max(0, round($totalGb - $freeGb, 2));
                $res['ram_total_gb'] = $totalGb;
                $res['ram_free_gb'] = $freeGb;
                $res['ram_used_gb'] = $usedGb;
                $res['ram_used_pct'] = $totalGb > 0 ? round(($usedGb / $totalGb) * 100, 1) : null;
            }
        }
        return $res;
    }

    private function probeNvidiaSmi(): ?array
    {
        try {
            $out = @shell_exec('nvidia-smi --query-gpu=name,memory.total,memory.used --format=csv,noheader,nounits 2>' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'));
            if (!$out) {
                return null;
            }
            $line = trim(explode("\n", trim($out))[0] ?? '');
            $parts = array_map('trim', explode(',', $line));
            if (count($parts) >= 3 && is_numeric($parts[1])) {
                return [
                    'name' => $parts[0],
                    'vram_total_gb' => round(((float) $parts[1]) / 1024, 2),
                    'vram_used_gb' => round(((float) $parts[2]) / 1024, 2),
                ];
            }
        } catch (\Throwable) {
            return null;
        }
        return null;
    }

    /**
     * Deteksi engine AI lokal yang sedang aktif dan model GGUF di disk lokal (read-only, cepat).
     */
    public function detectLocalEngines(): array
    {
        $endpoints = [];

        // 1. Ollama default port 11434
        $endpoints[] = $this->probeOllama('http://127.0.0.1:11434');

        // 2. LM Studio default port 1234
        $endpoints[] = $this->probeOpenAiCompat('LM Studio', 'http://127.0.0.1:1234');

        // 3. llama.cpp / vLLM local port 8080
        $endpoints[] = $this->probeOpenAiCompat('llama.cpp / vLLM', 'http://127.0.0.1:8080');

        // 4. Scan direktori model LM Studio di host saat ini (~/.lmstudio/models) bila tersedia
        $ggufFiles = $this->scanLmStudioModelsDir();

        return [
            'endpoints' => $endpoints,
            'lmstudio_local_models' => $ggufFiles,
        ];
    }

    private function probeOllama(string $url): array
    {
        $t0 = microtime(true);
        try {
            $ver = Http::timeout(2)->acceptJson()->get($url . '/api/version');
            if (!$ver->successful()) {
                return ['engine' => 'Ollama', 'driver' => 'ollama', 'base_url' => $url, 'online' => false, 'models' => [], 'error' => 'HTTP ' . $ver->status()];
            }
            $tags = Http::timeout(2)->acceptJson()->get($url . '/api/tags');
            $models = [];
            foreach ($tags->json('models', []) as $m) {
                $models[] = [
                    'id' => $m['name'] ?? $m['model'] ?? '?',
                    'params' => $m['details']['parameter_size'] ?? null,
                    'quantization' => $m['details']['quantization_level'] ?? null,
                    'size_gb' => isset($m['size']) ? round($m['size'] / 1073741824, 2) : null,
                ];
            }
            return [
                'engine' => 'Ollama',
                'driver' => 'ollama',
                'base_url' => $url,
                'online' => true,
                'version' => $ver->json('version'),
                'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
                'models' => $models,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['engine' => 'Ollama', 'driver' => 'ollama', 'base_url' => $url, 'online' => false, 'models' => [], 'error' => 'Server tidak berjalan'];
        }
    }

    private function probeOpenAiCompat(string $label, string $url): array
    {
        $t0 = microtime(true);
        try {
            $lm = Http::timeout(2)->acceptJson()->get($url . '/api/v0/models');
            if ($lm->successful() && is_array($lm->json('data'))) {
                $models = array_map(fn ($m) => [
                    'id' => $m['id'] ?? '?',
                    'params' => null,
                    'quantization' => $m['quantization'] ?? null,
                    'state' => $m['state'] ?? 'not-loaded',
                    'type' => $m['type'] ?? 'llm',
                ], $lm->json('data', []));
                return [
                    'engine' => $label,
                    'driver' => 'openai_compatible',
                    'base_url' => $url,
                    'online' => true,
                    'version' => 'v0/v1',
                    'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
                    'models' => $models,
                    'error' => null,
                ];
            }

            $v1 = Http::timeout(2)->acceptJson()->get($url . '/v1/models');
            if ($v1->successful() && is_array($v1->json('data'))) {
                $models = array_map(fn ($m) => [
                    'id' => $m['id'] ?? '?',
                    'params' => null,
                    'quantization' => null,
                    'state' => 'ready',
                    'type' => 'llm',
                ], $v1->json('data', []));
                return [
                    'engine' => $label,
                    'driver' => 'openai_compatible',
                    'base_url' => $url,
                    'online' => true,
                    'version' => 'v1',
                    'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
                    'models' => $models,
                    'error' => null,
                ];
            }

            return ['engine' => $label, 'driver' => 'openai_compatible', 'base_url' => $url, 'online' => false, 'models' => [], 'error' => 'HTTP ' . $v1->status()];
        } catch (\Throwable) {
            return ['engine' => $label, 'driver' => 'openai_compatible', 'base_url' => $url, 'online' => false, 'models' => [], 'error' => 'Server tidak berjalan'];
        }
    }

    /**
     * Pindai berkas GGUF terpasang di ~/.lmstudio/models tanpa memicu daemon berat.
     */
    public function scanLmStudioModelsDir(): array
    {
        if (app()->environment('testing')) {
            return [];
        }
        $home = getenv('USERPROFILE') ?: getenv('HOME') ?: null;
        if (!$home) {
            return [];
        }
        $dir = rtrim($home, '\\/') . DIRECTORY_SEPARATOR . '.lmstudio' . DIRECTORY_SEPARATOR . 'models';
        if (!is_dir($dir)) {
            return [];
        }

        $found = [];
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            $it->setMaxDepth(4);
            foreach ($it as $file) {
                /** @var \SplFileInfo $file */
                $fname = $file->getFilename();
                if (strtolower($file->getExtension()) !== 'gguf' || str_starts_with(strtolower($fname), 'mmproj')) {
                    continue;
                }
                $rel = ltrim(str_replace(str_replace('\\', '/', $dir), '', str_replace('\\', '/', $file->getPathname())), '/');
                $parts = explode('/', $rel);
                $publisher = $parts[0] ?? 'local';
                $repo = $parts[1] ?? pathinfo($fname, PATHINFO_FILENAME);
                $suggestedKey = strtolower(preg_replace('/-GGUF$/i', '', $repo));

                if (!isset($found[$suggestedKey])) {
                    $found[$suggestedKey] = [
                        'publisher' => $publisher,
                        'repo' => $repo,
                        'suggested_key' => $suggestedKey,
                        'filename' => $fname,
                        'size_gb' => round($file->getSize() / 1073741824, 2),
                    ];
                }
            }
        } catch (\Throwable) {
            return [];
        }

        $list = array_values($found);
        usort($list, fn ($a, $b) => ($b['size_gb'] <=> $a['size_gb']));
        return array_slice($list, 0, 25);
    }
}
