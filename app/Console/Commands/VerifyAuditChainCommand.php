<?php

namespace App\Console\Commands;

use App\Models\SystemLog;
use App\Services\Support\AuditLogger;
use Illuminate\Console\Command;

class VerifyAuditChainCommand extends Command
{
    protected $signature = 'trace:audit-verify';
    protected $description = 'Memverifikasi rantai hash audit log (deteksi modifikasi).';

    public function handle(): int
    {
        $prev = '';
        $count = 0;
        $broken = null;

        SystemLog::query()->whereNotNull('entry_hash')->orderBy('id')->chunk(500, function ($rows) use (&$prev, &$count, &$broken) {
            foreach ($rows as $r) {
                $row = $r->getAttributes();
                $row['metadata'] = $r->metadata;
                $expected = AuditLogger::hashEntry($r->prev_hash ?? '', $row);

                if (($r->prev_hash ?? '') !== $prev || $expected !== $r->entry_hash) {
                    $broken = $r->id;
                    return false;
                }
                $prev = $r->entry_hash;
                $count++;
            }
        });

        if ($broken !== null) {
            $this->error("Rantai audit RUSAK pada baris id={$broken}.");
            return self::FAILURE;
        }
        $this->info("Rantai audit utuh: {$count} entri terverifikasi.");
        return self::SUCCESS;
    }
}
