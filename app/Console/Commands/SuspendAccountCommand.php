<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Identity\AccountLifecycle;
use Illuminate\Console\Command;

class SuspendAccountCommand extends Command
{
    protected $signature = 'trace:suspend-account {trace_id} {--reason= : Alasan penangguhan}';
    protected $description = 'Menangguhkan akun dan mencabut seluruh sesinya.';

    public function handle(AccountLifecycle $lifecycle): int
    {
        $user = User::where('trace_id', $this->argument('trace_id'))->first();
        if (! $user) {
            $this->error('Trace-ID tidak ditemukan.');
            return self::FAILURE;
        }
        try {
            $lifecycle->suspend($user, $this->option('reason'), ['type' => 'SYSTEM', 'label' => 'cli:suspend']);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->info("Akun {$user->trace_id} ditangguhkan.");
        return self::SUCCESS;
    }
}
