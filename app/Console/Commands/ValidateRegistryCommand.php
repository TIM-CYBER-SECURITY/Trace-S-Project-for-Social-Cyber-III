<?php

namespace App\Console\Commands;

use App\Services\Identity\RegistryStore;
use App\Services\Identity\RegistryValidator;
use Illuminate\Console\Command;

class ValidateRegistryCommand extends Command
{
    protected $signature = 'trace:validate-registry {--file= : Berkas registry (default: approved-accounts.json)}';
    protected $description = 'Memvalidasi registry Trace-ID terhadap JSON Schema dan aturan lintas-entri.';

    public function handle(RegistryStore $store, RegistryValidator $validator): int
    {
        $file = $this->option('file') ?: $store->approvedFile();
        try {
            $entries = $store->read($file);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $errors = $validator->validate($entries);
        if ($errors) {
            foreach ($errors as $label => $msgs) {
                $this->error("Entri {$label}");
                foreach ($msgs as $m) {
                    $this->line("  - {$m}");
                }
            }
            $this->error(count($errors) . ' entri tidak valid dari ' . count($entries) . '.');
            return self::FAILURE;
        }

        $this->info('Registry valid: ' . count($entries) . ' entri (' . basename($file) . ').');
        return self::SUCCESS;
    }
}
