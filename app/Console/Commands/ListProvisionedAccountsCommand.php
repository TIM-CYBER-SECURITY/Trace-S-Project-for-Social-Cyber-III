<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListProvisionedAccountsCommand extends Command
{
    protected $signature = 'trace:list-accounts {--status= : Filter account_status}';
    protected $description = 'Menampilkan akun yang telah diprovisioning (tanpa data kredensial).';

    public function handle(): int
    {
        $q = User::with(['roleRef', 'organization'])->whereNotNull('trace_id')->orderBy('trace_id');
        if ($s = $this->option('status')) {
            $q->where('account_status', strtoupper($s));
        }
        $rows = $q->get()->map(fn (User $u) => [
            $u->trace_id, $u->name, $u->roleRef?->name, $u->organization?->name,
            $u->account_status, $u->last_login_at?->format('Y-m-d H:i') ?? '-',
        ])->all();

        $this->table(['Trace-ID', 'Nama', 'Peran', 'Organisasi', 'Status', 'Login terakhir'], $rows);
        return self::SUCCESS;
    }
}
