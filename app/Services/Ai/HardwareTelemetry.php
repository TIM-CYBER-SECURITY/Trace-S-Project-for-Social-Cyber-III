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
    private ModelProfileResolver $profiles;

    public function __construct(?ModelProfileResolver $profiles = null)
    {
        $this->profiles = $profiles ?? new ModelProfileResolver();
    }

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
            'cpu_threads' => 1,
            'cpu_clock_mhz' => null,
            'cpu_load_pct' => null,
            'ram_total_gb' => null,
            'ram_free_gb' => null,
            'ram_used_gb' => null,
            'ram_used_pct' => null,
            'ram_model' => null,
            'ram_modules' => [],
            'gpu_name' => null,
            'gpu_vram_total_gb' => null,
            'gpu_vram_used_gb' => null,
            'gpu_type' => 'UNAVAILABLE',
            'dgpu_name' => null,
            'dgpu_vram_gb' => null,
            'igpu_name' => null,
            'igpu_vram_gb' => null,
            'gpus' => [],
            'ai_process_ram_gb' => 0.0,
            'ai_active_process' => null,
            'ai_process_breakdown' => [],
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
            'cpu_threads' => null,
            'cpu_clock_mhz' => null,
            'cpu_load_pct' => null,
            'ram_total_gb' => null,
            'ram_free_gb' => null,
            'ram_used_gb' => null,
            'ram_used_pct' => null,
            'ram_model' => null,
            'ram_modules' => [],
            'gpu_name' => null,
            'gpu_vram_total_gb' => null,
            'gpu_vram_used_gb' => null,
            'gpu_type' => 'UNAVAILABLE',
            'dgpu_name' => null,
            'dgpu_vram_gb' => null,
            'igpu_name' => null,
            'igpu_vram_gb' => null,
            'gpus' => [],
            'ai_process_ram_gb' => 0.0,
            'ai_active_process' => null,
            'ai_process_breakdown' => [],
            'disk_free_gb' => $diskFree !== false ? round($diskFree / 1073741824, 1) : null,
            'disk_total_gb' => $diskTotal !== false ? round($diskTotal / 1073741824, 1) : null,
        ];

        if (PHP_OS_FAMILY === 'Windows') {
            $base = array_merge($base, $this->probeWindows());
        } else {
            $base = array_merge($base, $this->probeUnix());
        }

        // Overlay real-time nvidia-smi telemetry onto NVIDIA Discrete GPU if present
        $nv = $this->probeNvidiaSmi();
        if ($nv !== null) {
            $base['gpu_name'] = $nv['name'];
            $base['gpu_vram_total_gb'] = $nv['vram_total_gb'];
            $base['gpu_vram_used_gb'] = $nv['vram_used_gb'];
            $base['gpu_type'] = 'NVIDIA_DISCRETE';
            $base['dgpu_name'] = $nv['name'];
            $base['dgpu_vram_gb'] = $nv['vram_total_gb'];

            $matched = false;
            foreach ($base['gpus'] as &$g) {
                if (($g['role'] ?? '') === 'dGPU' && stripos((string) ($g['name'] ?? ''), 'NVIDIA') !== false) {
                    $g['name'] = $nv['name'];
                    $g['vram_gb'] = $nv['vram_total_gb'];
                    $g['vram_used_gb'] = $nv['vram_used_gb'];
                    $g['utilization_pct'] = $nv['utilization_pct'] ?? null;
                    $matched = true;
                    break;
                }
            }
            unset($g);
            if (!$matched) {
                array_unshift($base['gpus'], [
                    'name' => $nv['name'],
                    'role' => 'dGPU',
                    'vram_gb' => $nv['vram_total_gb'],
                    'vram_used_gb' => $nv['vram_used_gb'],
                    'driver_version' => null,
                    'status' => 'OK',
                    'utilization_pct' => $nv['utilization_pct'] ?? null,
                ]);
            }
        }

        return $base;
    }

    private function probeWindows(): array
    {
        $res = [];
        if (getenv('NUMBER_OF_PROCESSORS')) {
            $res['cpu_threads'] = (int) getenv('NUMBER_OF_PROCESSORS');
            $res['cpu_cores'] = (int) getenv('NUMBER_OF_PROCESSORS');
        }

        try {
            $psScript = <<<'PS'
$ProgressPreference = 'SilentlyContinue'
$cpuReg = (Get-ItemProperty "HKLM:\HARDWARE\DESCRIPTION\System\CentralProcessor\0" -ErrorAction SilentlyContinue).ProcessorNameString
$cpuCim = Get-CimInstance Win32_Processor -ErrorAction SilentlyContinue | Select-Object Name,NumberOfCores,NumberOfLogicalProcessors,MaxClockSpeed,LoadPercentage
$os = Get-CimInstance Win32_OperatingSystem -ErrorAction SilentlyContinue | Select-Object TotalVisibleMemorySize,FreePhysicalMemory
$ram = Get-CimInstance Win32_PhysicalMemory -ErrorAction SilentlyContinue | Select-Object Manufacturer,PartNumber,Capacity,ConfiguredClockSpeed,Speed,SMBIOSMemoryType
$gpu = Get-CimInstance Win32_VideoController -ErrorAction SilentlyContinue | Select-Object Name,AdapterRAM,DriverVersion,Status,VideoProcessor
$proc = Get-Process -ErrorAction SilentlyContinue | Where-Object { $_.ProcessName -match 'LM Studio|lms|llama|ollama|bionic' } | Select-Object ProcessName,WorkingSet64
[PSCustomObject]@{cpu_reg=$cpuReg;cpu=$cpuCim;os=$os;ram=$ram;gpu=$gpu;proc=$proc} | ConvertTo-Json -Compress -Depth 3
PS;
            $utf16 = mb_convert_encoding($psScript, 'UTF-16LE', 'UTF-8');
            $encoded = base64_encode($utf16);
            $cmd = 'powershell -NoProfile -NonInteractive -EncodedCommand ' . $encoded . ' 2>NUL';
            $raw = @shell_exec($cmd);

            if ($raw && preg_match('/\{.*\}/s', $raw, $m) && is_array($json = json_decode($m[0], true))) {
                // 1. CPU details (full name/series, cores, threads, clock, load%)
                $cpuCim = $json['cpu'] ?? null;
                if (is_array($cpuCim)) {
                    $firstCpu = isset($cpuCim['Name']) ? $cpuCim : ($cpuCim[0] ?? []);
                    $cpuName = trim((string) ($firstCpu['Name'] ?? $json['cpu_reg'] ?? ''));
                    if ($cpuName !== '') {
                        $res['cpu_name'] = $cpuName;
                    }
                    if (!empty($firstCpu['NumberOfCores'])) {
                        $res['cpu_cores'] = (int) $firstCpu['NumberOfCores'];
                    }
                    if (!empty($firstCpu['NumberOfLogicalProcessors'])) {
                        $res['cpu_threads'] = (int) $firstCpu['NumberOfLogicalProcessors'];
                    }
                    if (!empty($firstCpu['MaxClockSpeed'])) {
                        $res['cpu_clock_mhz'] = (int) $firstCpu['MaxClockSpeed'];
                    }
                    if (isset($firstCpu['LoadPercentage']) && is_numeric($firstCpu['LoadPercentage'])) {
                        $res['cpu_load_pct'] = (int) $firstCpu['LoadPercentage'];
                    }
                } elseif (!empty($json['cpu_reg'])) {
                    $res['cpu_name'] = trim((string) $json['cpu_reg']);
                }

                // 2. OS Memory usage (usable/free/used)
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

                // 3. Physical RAM modules (Manufacturer, PartNumber, DDR Type, Speed, Module Capacity)
                $ramRows = $json['ram'] ?? null;
                if (is_array($ramRows)) {
                    $dimms = isset($ramRows['Capacity']) ? [$ramRows] : $ramRows;
                    $modules = [];
                    $ddrNames = [];
                    $speeds = [];
                    $parts = [];
                    $mfgList = [];
                    $physicalSumGb = 0.0;

                    foreach ($dimms as $d) {
                        $capBytes = (float) ($d['Capacity'] ?? 0);
                        if ($capBytes <= 0) {
                            continue;
                        }
                        $capGb = round($capBytes / 1073741824, 1);
                        $physicalSumGb += $capGb;
                        $mfg = trim((string) ($d['Manufacturer'] ?? ''));
                        $part = trim((string) ($d['PartNumber'] ?? ''));
                        $speed = (int) ($d['ConfiguredClockSpeed'] ?: ($d['Speed'] ?? 0));
                        $smbios = (int) ($d['SMBIOSMemoryType'] ?? 0);
                        $ddr = match ($smbios) {
                            20 => 'DDR',
                            21 => 'DDR2',
                            24 => 'DDR3',
                            26 => 'DDR4',
                            30 => 'LPDDR4',
                            34 => 'DDR5',
                            35 => 'LPDDR5',
                            default => 'RAM',
                        };
                        $modules[] = [
                            'manufacturer' => $mfg ?: 'Unknown',
                            'part_number' => $part ?: '-',
                            'capacity_gb' => $capGb,
                            'type' => $ddr,
                            'speed_mhz' => $speed ?: null,
                        ];
                        $ddrNames[$ddr] = true;
                        if ($speed > 0) {
                            $speeds[$speed] = true;
                        }
                        if ($part !== '' && $part !== '-') {
                            $parts[$part] = true;
                        }
                        if ($mfg !== '' && stripos($mfg, 'Unknown') === false) {
                            $mfgList[$mfg] = true;
                        }
                    }

                    if ($modules !== []) {
                        $res['ram_modules'] = $modules;
                        $ddrStr = implode('/', array_keys($ddrNames));
                        $spdStr = $speeds !== [] ? ('-' . max(array_keys($speeds)) . ' MHz') : '';
                        $mfgStr = $mfgList !== [] ? implode(', ', array_keys($mfgList)) : '';
                        $partStr = $parts !== [] ? implode(', ', array_keys($parts)) : '';
                        $ident = trim($mfgStr . ' ' . $partStr);
                        $countLabel = count($modules) . 'x ' . ($modules[0]['capacity_gb'] ?? '') . 'GB';
                        $res['ram_model'] = trim("{$countLabel} {$ddrStr}{$spdStr}" . ($ident !== '' ? " ({$ident})" : ''));
                        if (empty($res['ram_total_gb']) && $physicalSumGb > 0) {
                            $res['ram_total_gb'] = round($physicalSumGb, 2);
                        }
                    }
                }

                // 4. All GPUs (Discrete GPU + Integrated GPU / iGPU)
                $gpuRow = $json['gpu'] ?? null;
                if (is_array($gpuRow)) {
                    $rawGpus = isset($gpuRow['Name']) ? [$gpuRow] : $gpuRow;
                    $gpus = [];
                    foreach ($rawGpus as $g) {
                        $name = trim((string) ($g['Name'] ?? ''));
                        if ($name === '' || stripos($name, 'Basic Display') !== false || stripos($name, 'Remote') !== false || stripos($name, 'Parsec') !== false) {
                            continue;
                        }
                        $adapterRam = (float) ($g['AdapterRAM'] ?? 0);
                        $vramGb = $adapterRam > 0 ? round($adapterRam / 1073741824, 2) : null;
                        $isDiscrete = stripos($name, 'NVIDIA') !== false
                            || stripos($name, 'Radeon RX') !== false
                            || stripos($name, 'Radeon PRO') !== false
                            || stripos($name, 'Intel(R) Arc') !== false
                            || stripos($name, 'RTX') !== false
                            || stripos($name, 'GTX') !== false;
                        $role = $isDiscrete ? 'dGPU' : 'iGPU';

                        $gpus[] = [
                            'name' => $name,
                            'role' => $role,
                            'vram_gb' => $isDiscrete ? $vramGb : ($vramGb ? "{$vramGb} (Shared)" : 'Shared'),
                            'vram_raw_gb' => $vramGb,
                            'driver_version' => trim((string) ($g['DriverVersion'] ?? '')),
                            'status' => trim((string) ($g['Status'] ?? 'OK')),
                            'processor' => trim((string) ($g['VideoProcessor'] ?? '')),
                            'utilization_pct' => null,
                        ];

                        if ($isDiscrete && empty($res['dgpu_name'])) {
                            $res['dgpu_name'] = $name;
                            $res['dgpu_vram_gb'] = $vramGb;
                        } elseif (!$isDiscrete && empty($res['igpu_name'])) {
                            $res['igpu_name'] = $name;
                            $res['igpu_vram_gb'] = $vramGb;
                        }
                    }

                    $res['gpus'] = $gpus;
                    if (!empty($res['dgpu_name'])) {
                        $res['gpu_name'] = $res['dgpu_name'];
                        $res['gpu_vram_total_gb'] = $res['dgpu_vram_gb'];
                        $res['gpu_type'] = 'DISCRETE';
                    } elseif (!empty($res['igpu_name'])) {
                        $res['gpu_name'] = $res['igpu_name'];
                        $res['gpu_vram_total_gb'] = $res['igpu_vram_gb'];
                        $res['gpu_type'] = 'INTEGRATED_SHARED';
                    }
                }

                // 5. Real-time Local AI Process RAM Usage (LM Studio, llama-server, Ollama, Bionic)
                $procRow = $json['proc'] ?? null;
                if (is_array($procRow)) {
                    $procs = isset($procRow['ProcessName']) ? [$procRow] : $procRow;
                    $byProc = [];
                    $totalAiBytes = 0.0;
                    foreach ($procs as $p) {
                        $pname = trim((string) ($p['ProcessName'] ?? ''));
                        $ws = (float) ($p['WorkingSet64'] ?? 0);
                        if ($pname === '' || $ws <= 0) {
                            continue;
                        }
                        $totalAiBytes += $ws;
                        $byProc[$pname] = ($byProc[$pname] ?? 0.0) + $ws;
                    }
                    if ($totalAiBytes > 0) {
                        arsort($byProc);
                        $breakdown = [];
                        $topLabels = [];
                        foreach ($byProc as $pname => $bytes) {
                            $gb = round($bytes / 1073741824, 2);
                            $breakdown[] = ['process' => $pname, 'ram_gb' => $gb];
                            if ($gb >= 0.05) {
                                $topLabels[] = "{$pname} ({$gb} GB)";
                            }
                        }
                        $res['ai_process_ram_gb'] = round($totalAiBytes / 1073741824, 2);
                        $res['ai_process_breakdown'] = $breakdown;
                        $res['ai_active_process'] = implode(' + ', array_slice($topLabels, 0, 3));
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
                $res['cpu_threads'] = count($matches[0]);
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
            $out = @shell_exec('nvidia-smi --query-gpu=name,memory.total,memory.used,utilization.gpu --format=csv,noheader,nounits 2>' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'));
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
                    'utilization_pct' => isset($parts[3]) && is_numeric($parts[3]) ? (int) $parts[3] : null,
                ];
            }
        } catch (\Throwable) {
            return null;
        }
        return null;
    }

    /**
     * Deteksi semua engine AI lokal yang aktif serta seluruh katalog model GGUF terpasang
     * lengkap dengan profil perbedaan kemampuan setiap model & kecocokan hardware host.
     */
    public function detectLocalEngines(): array
    {
        $hw = $this->snapshot();
        $endpoints = [];

        // 1. Ollama default port 11434
        $endpoints[] = $this->probeOllama('http://127.0.0.1:11434', $hw);

        // 2. LM Studio default port 1234
        $endpoints[] = $this->probeOpenAiCompat('LM Studio', 'http://127.0.0.1:1234', $hw);

        // 3. Bionic local OpenAI-compatible port 8001
        $endpoints[] = $this->probeOpenAiCompat('Bionic', 'http://127.0.0.1:8001', $hw);

        // 4. Bionic / llama.cpp local port 8080
        $endpoints[] = $this->probeOpenAiCompat('Bionic / llama.cpp', 'http://127.0.0.1:8080', $hw);

        // 5. Scan katalog LM Studio di host saat ini (lms ls --json / fallback direktori GGUF)
        $ggufFiles = $this->scanLmStudioModelsDir($hw);

        return [
            'endpoints' => $endpoints,
            'lmstudio_local_models' => $ggufFiles,
            'lms_cli_available' => $this->findLmsBinary() !== null,
        ];
    }

    public function findLmsBinary(): ?string
    {
        if (app()->environment('testing')) {
            return null;
        }
        $home = getenv('USERPROFILE') ?: getenv('HOME') ?: null;
        $candidates = [];
        if ($home) {
            $exe = PHP_OS_FAMILY === 'Windows' ? 'lms.exe' : 'lms';
            $candidates[] = rtrim($home, '\\/') . DIRECTORY_SEPARATOR . '.lmstudio' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $exe;
        }
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Pastikan server HTTP LM Studio lokal menyala di 127.0.0.1:$port menggunakan `lms server start`.
     * Hanya beroperasi untuk localhost non-testing ketika `lms` CLI terpasang.
     *
     * @return array{ok:bool,already_running:bool,started:bool,error:?string}
     */
    public function ensureLmStudioServerRunning(int $port = 1234): array
    {
        if (app()->environment('testing')) {
            return ['ok' => false, 'already_running' => false, 'started' => false, 'error' => 'Testing mode'];
        }
        if ($port < 1 || $port > 65535) {
            return ['ok' => false, 'already_running' => false, 'started' => false, 'error' => 'Port tidak valid'];
        }

        $url = "http://127.0.0.1:{$port}";
        if ($this->isLocalHttpAlive($url)) {
            return ['ok' => true, 'already_running' => true, 'started' => false, 'error' => null];
        }

        $lms = $this->findLmsBinary();
        if (!$lms) {
            return ['ok' => false, 'already_running' => false, 'started' => false, 'error' => 'CLI lms LM Studio tidak ditemukan pada mesin host.'];
        }

        try {
            $cmd = escapeshellarg($lms) . ' server start --port ' . (int) $port . ' 2>&1';
            @shell_exec($cmd);

            // Poll hingga HTTP endpoint siap (maks ~6 detik)
            for ($i = 0; $i < 12; $i++) {
                if ($this->isLocalHttpAlive($url)) {
                    return ['ok' => true, 'already_running' => false, 'started' => true, 'error' => null];
                }
                usleep(500000);
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'already_running' => false, 'started' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => false, 'already_running' => false, 'started' => false, 'error' => "LM Studio server di port {$port} belum merespons. Pastikan aplikasi LM Studio / daemon lokal tidak terblokir."];
    }

    /**
     * Alihkan model yang sedang termuat di LM Studio localhost dengan aman:
     * unload model lama terlebih dahulu untuk mencegah penumpukan RAM, lalu muat model baru jika RAM mencukupi.
     *
     * @return array{ok:bool,loaded:bool,warning:?string,message:string}
     */
    public function switchLmStudioLoadedModel(string $modelKey, int $port = 1234, ?float $estimatedRamGb = null): array
    {
        $srv = $this->ensureLmStudioServerRunning($port);
        if (!$srv['ok']) {
            return [
                'ok' => false,
                'loaded' => false,
                'warning' => $srv['error'],
                'message' => $srv['error'] ?? 'LM Studio server tidak berjalan.',
            ];
        }

        $url = "http://127.0.0.1:{$port}";
        $currentlyLoaded = [];
        try {
            $resp = Http::connectTimeout(1)->timeout(3)->acceptJson()->get($url . '/api/v0/models');
            if ($resp->successful() && is_array($resp->json('data'))) {
                foreach ($resp->json('data') as $m) {
                    if (strtolower((string) ($m['state'] ?? '')) === 'loaded') {
                        $currentlyLoaded[] = (string) ($m['id'] ?? '');
                    }
                }
            }
        } catch (\Throwable) {
            // abaikan; tetap lanjutkan pengecekan
        }

        if (in_array($modelKey, $currentlyLoaded, true)) {
            return [
                'ok' => true,
                'loaded' => true,
                'warning' => null,
                'message' => "Model '{$modelKey}' sudah termuat di memori LM Studio.",
            ];
        }

        $lms = $this->findLmsBinary();
        if (!$lms) {
            return [
                'ok' => true,
                'loaded' => false,
                'warning' => null,
                'message' => "Server LM Studio ONLINE; model '{$modelKey}' disetel aktif (JIT load saat inferensi).",
            ];
        }

        // Kosongkan model lama terlebih dahulu jika ada agar RAM tidak bertumpuk (mis. 14B -> 0.5B)
        if ($currentlyLoaded !== []) {
            @shell_exec(escapeshellarg($lms) . ' unload --all 2>&1');
            usleep(400000);
        }

        // Cek RAM bebas host setelah unload
        $hw = $this->snapshot(true);
        $ramFree = isset($hw['ram_free_gb']) ? (float) $hw['ram_free_gb'] : null;

        // Jangan paksa preload model besar (>3.5 GB) jika RAM bebas sangat sempit untuk mencegah freeze host
        if ($estimatedRamGb !== null && $estimatedRamGb > 3.5 && $ramFree !== null && $ramFree < ($estimatedRamGb * 0.85)) {
            return [
                'ok' => true,
                'loaded' => false,
                'warning' => "Server LM Studio ONLINE, namun model '{$modelKey}' (~{$estimatedRamGb} GB RAM) belum di-preload otomatis karena RAM bebas saat ini hanya {$ramFree} GB. Tutup aplikasi berat atau pilih model ringan (mis. qwen2.5-0.5b-instruct / llama-3.2-3b).",
                'message' => "LM Studio ONLINE (menunggu RAM cukup untuk preload '{$modelKey}').",
            ];
        }

        // Load model target secara otomatis dengan TTL 30 menit agar tidak mendadak unload di tengah sesi
        @shell_exec(escapeshellarg($lms) . ' load -y --ttl 1800 ' . escapeshellarg($modelKey) . ' 2>&1');

        // Verifikasi status loaded terbaru
        try {
            $after = Http::connectTimeout(1)->timeout(3)->acceptJson()->get($url . '/api/v0/models');
            if ($after->successful() && is_array($after->json('data'))) {
                foreach ($after->json('data') as $m) {
                    if (($m['id'] ?? '') === $modelKey && strtolower((string) ($m['state'] ?? '')) === 'loaded') {
                        return [
                            'ok' => true,
                            'loaded' => true,
                            'warning' => null,
                            'message' => "LM Studio ONLINE dan model '{$modelKey}' berhasil dimuat ke memori.",
                        ];
                    }
                }
            }
        } catch (\Throwable) {
            // lanjut ke respons fallback
        }

        return [
            'ok' => true,
            'loaded' => false,
            'warning' => "Server LM Studio ONLINE; model '{$modelKey}' terdaftar dan akan dimuat JIT saat inferensi.",
            'message' => "LM Studio ONLINE dengan model aktif '{$modelKey}'.",
        ];
    }

    /** Bebaskan memori RAM/VRAM dengan menurunkan model LM Studio saat berpindah ke engine lain. */
    public function unloadAllLocalLmStudioModels(int $port = 1234): void
    {
        if (app()->environment('testing')) {
            return;
        }
        $lms = $this->findLmsBinary();
        if ($lms && $this->isLocalHttpAlive("http://127.0.0.1:{$port}")) {
            @shell_exec(escapeshellarg($lms) . ' unload --all 2>&1');
        }
    }

    /** Bebaskan memori RAM/VRAM Ollama (keep_alive: 0) saat berpindah ke engine/server lain. */
    public function unloadOllamaModel(?string $baseUrl, ?string $model): void
    {
        if (app()->environment('testing') || !$baseUrl || !$model) {
            return;
        }
        try {
            Http::connectTimeout(1)->timeout(3)->acceptJson()->post(rtrim($baseUrl, '/') . '/api/generate', [
                'model' => $model,
                'keep_alive' => 0,
            ]);
        } catch (\Throwable) {
            // abaikan jika Ollama sedang offline
        }
    }

    private function isLocalHttpAlive(string $url): bool
    {
        try {
            $r = Http::connectTimeout(1)->timeout(2)->acceptJson()->get($url . '/v1/models');
            return $r->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function probeOllama(string $url, ?array $hw = null): array
    {
        $t0 = microtime(true);
        try {
            $ver = Http::connectTimeout(1)->timeout(2)->acceptJson()->get($url . '/api/version');
            if (!$ver->successful()) {
                return ['engine' => 'Ollama', 'driver' => 'ollama', 'base_url' => $url, 'online' => false, 'models' => [], 'error' => 'HTTP ' . $ver->status()];
            }
            $tags = Http::connectTimeout(1)->timeout(3)->acceptJson()->get($url . '/api/tags');
            $ps = [];
            try {
                $psResp = Http::connectTimeout(1)->timeout(2)->acceptJson()->get($url . '/api/ps');
                if ($psResp->successful()) {
                    foreach ($psResp->json('models', []) as $p) {
                        $ps[$p['name'] ?? $p['model'] ?? ''] = true;
                    }
                }
            } catch (\Throwable) {
            }

            $models = [];
            foreach ($tags->json('models', []) as $m) {
                $id = $m['name'] ?? $m['model'] ?? '?';
                $sizeBytes = $m['size'] ?? null;
                $resolved = $this->profiles->resolve($id, [
                    'parameters' => $m['details']['parameter_size'] ?? null,
                    'family' => $m['details']['family'] ?? null,
                    'quantization' => $m['details']['quantization_level'] ?? null,
                    'size_bytes' => $sizeBytes,
                ], $hw);
                $models[] = [
                    'id' => $id,
                    'params' => $resolved['parameter_size'],
                    'quantization' => $resolved['quantization'],
                    'size_gb' => $resolved['size_gb'],
                    'capability_type' => $resolved['capability_type'],
                    'is_reasoning' => $resolved['is_reasoning'],
                    'is_vision' => $resolved['is_vision'],
                    'is_embedding' => $resolved['is_embedding'],
                    'recommended_timeout' => $resolved['recommended_timeout'],
                    'hardware_fit' => $resolved['hardware_fit'],
                    'loaded' => isset($ps[$id]),
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
        } catch (\Throwable) {
            return ['engine' => 'Ollama', 'driver' => 'ollama', 'base_url' => $url, 'online' => false, 'models' => [], 'error' => 'Server tidak berjalan'];
        }
    }

    private function probeOpenAiCompat(string $label, string $url, ?array $hw = null): array
    {
        $t0 = microtime(true);
        try {
            $lm = Http::connectTimeout(1)->timeout(2)->acceptJson()->get($url . '/api/v0/models');
            if ($lm->successful() && is_array($lm->json('data'))) {
                $models = array_map(function ($m) use ($hw) {
                    $id = (string) ($m['id'] ?? '?');
                    $resolved = $this->profiles->resolve($id, [
                        'architecture' => $m['arch'] ?? null,
                        'quantization' => $m['quantization'] ?? null,
                        'context_length' => $m['loaded_context_length'] ?? $m['max_context_length'] ?? null,
                        'type' => $m['type'] ?? 'llm',
                    ], $hw);
                    return [
                        'id' => $id,
                        'params' => $resolved['parameter_size'],
                        'quantization' => $resolved['quantization'],
                        'state' => $m['state'] ?? 'not-loaded',
                        'loaded' => strtolower((string) ($m['state'] ?? '')) === 'loaded',
                        'type' => $m['type'] ?? 'llm',
                        'capability_type' => $resolved['capability_type'],
                        'is_reasoning' => $resolved['is_reasoning'],
                        'is_vision' => $resolved['is_vision'],
                        'is_embedding' => $resolved['is_embedding'],
                        'recommended_timeout' => $resolved['recommended_timeout'],
                        'hardware_fit' => $resolved['hardware_fit'],
                    ];
                }, $lm->json('data', []));
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

            $v1 = Http::connectTimeout(1)->timeout(2)->acceptJson()->get($url . '/v1/models');
            if ($v1->successful() && is_array($v1->json('data'))) {
                $models = array_map(function ($m) use ($hw) {
                    $id = (string) ($m['id'] ?? '?');
                    $resolved = $this->profiles->resolve($id, [], $hw);
                    return [
                        'id' => $id,
                        'params' => $resolved['parameter_size'],
                        'quantization' => $resolved['quantization'],
                        'state' => 'ready',
                        'loaded' => true,
                        'type' => 'llm',
                        'capability_type' => $resolved['capability_type'],
                        'recommended_timeout' => $resolved['recommended_timeout'],
                        'hardware_fit' => $resolved['hardware_fit'],
                    ];
                }, $v1->json('data', []));
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
     * Pindai seluruh model LM Studio lokal via `lms ls --json` (metadata resmi akurat),
     * dengan fallback ke direktori ~/.lmstudio/models.
     */
    public function scanLmStudioModelsDir(?array $hw = null): array
    {
        if (app()->environment('testing')) {
            return [];
        }
        $hw = $hw ?? $this->snapshot();

        // 1. Coba lms ls --json untuk mendapatkan modelKey, vision, architecture, paramsString, dan contextLength resmi
        $lms = $this->findLmsBinary();
        if ($lms) {
            try {
                $raw = @shell_exec(escapeshellarg($lms) . ' ls --json 2>' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'));
                if ($raw && is_array($items = json_decode(trim($raw), true))) {
                    $list = [];
                    foreach ($items as $m) {
                        $key = (string) ($m['modelKey'] ?? '');
                        if ($key === '') {
                            continue;
                        }
                        $sizeBytes = (int) ($m['sizeBytes'] ?? 0);
                        $quant = is_array($m['quantization'] ?? null)
                            ? ($m['quantization']['name'] ?? null)
                            : ($m['quantization'] ?? null);
                        $resolved = $this->profiles->resolve($key, [
                            'display_name' => $m['displayName'] ?? null,
                            'paramsString' => $m['paramsString'] ?? null,
                            'architecture' => $m['architecture'] ?? null,
                            'quantization' => $quant,
                            'context_length' => $m['maxContextLength'] ?? null,
                            'vision' => !empty($m['vision']),
                            'type' => $m['type'] ?? 'llm',
                            'size_bytes' => $sizeBytes,
                        ], $hw);

                        $list[] = array_merge($resolved, [
                            'publisher' => (string) ($m['publisher'] ?? 'local'),
                            'repo' => (string) ($m['displayName'] ?? $key),
                            'suggested_key' => $key,
                            'filename' => basename((string) ($m['path'] ?? $key)),
                            'size_bytes' => $sizeBytes,
                            'size_gb' => $resolved['size_gb'] ?? round($sizeBytes / 1073741824, 2),
                            'type' => (string) ($m['type'] ?? 'llm'),
                        ]);
                    }
                    if ($list !== []) {
                        usort($list, fn ($a, $b) => ($b['size_gb'] <=> $a['size_gb']));
                        return array_slice($list, 0, 30);
                    }
                }
            } catch (\Throwable) {
                // Fallback ke pemindaian direktori GGUF di disk
            }
        }

        // 2. Fallback pemindaian berkas GGUF langsung di ~/.lmstudio/models
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
                    $sizeBytes = $file->getSize();
                    $resolved = $this->profiles->resolve($suggestedKey, ['size_bytes' => $sizeBytes], $hw);
                    $found[$suggestedKey] = array_merge($resolved, [
                        'publisher' => $publisher,
                        'repo' => $repo,
                        'suggested_key' => $suggestedKey,
                        'filename' => $fname,
                        'size_bytes' => $sizeBytes,
                        'size_gb' => $resolved['size_gb'] ?? round($sizeBytes / 1073741824, 2),
                        'type' => $resolved['is_embedding'] ? 'embedding' : 'llm',
                    ]);
                }
            }
        } catch (\Throwable) {
            return [];
        }

        $list = array_values($found);
        usort($list, fn ($a, $b) => ($b['size_gb'] <=> $a['size_gb']));
        return array_slice($list, 0, 30);
    }
}
