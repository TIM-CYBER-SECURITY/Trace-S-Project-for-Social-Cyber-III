<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Alias resmi `trace:validate-accounts` yang mendelegasikan ke validator registry eksternal. */
class ValidateAccountsCommand extends Command
{
    protected $signature = 'trace:validate-accounts {--file= : Berkas registry (default: approved-accounts.json)}';
    protected $description = 'Memvalidasi daftar akun Trace-ID eksternal terhadap skema JSON dan aturan persetujuan.';

    public function handle(): int
    {
        $args = [];
        if ($file = $this->option('file')) {
            $args['--file'] = $file;
        }
        return $this->call('trace:validate-registry', $args);
    }
}
