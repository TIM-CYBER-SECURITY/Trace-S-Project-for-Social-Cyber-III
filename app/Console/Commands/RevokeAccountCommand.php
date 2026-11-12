<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Identity\AccountLifecycle;
use Illuminate\Console\Command;

class RevokeAccountCommand extends Command
{
    protected $signature = 'trace:revoke-account {trace_id} {--reason= : Alasan pencabutan}';
    protected $description = 'Mencabut akun secara permanen (final) dan seluruh sesinya.';

    public function handle(AccountLifecycle $lifecycle): int
    {
        $user = User::where('trace_id', $this->argument('trace_id'))->first();
        if (! $user) {
            $this->error('Trace-ID tidak ditemukan.');
            return self::FAILURE;
        }
        try {
            $lifecycle->revoke($user, $this->option('reason'), ['type' => 'SYSTEM', 'label' => 'cli:revoke']);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->info("Akun {$user->trace_id} dicabut (final).");
        return self::SUCCESS;
    }
}
