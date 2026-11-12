<?php

namespace App\Console\Commands;

use App\Services\Identity\AccountProvisioner;
use App\Services\Identity\RegistryStore;
use Illuminate\Console\Command;

class ProvisionAccountsCommand extends Command
{
    protected $signature = 'trace:provision-accounts
        {--dry-run : Simulasikan tanpa menulis ke database}
        {--only= : Hanya Trace-ID tertentu}';
    protected $description = 'Mengimpor akun terverifikasi dari registry ke database (idempoten). Kredensial awal ditulis ke berkas privat, tidak pernah ke terminal.';

    public function handle(AccountProvisioner $provisioner, RegistryStore $store): int
    {
        $dry = (bool) $this->option('dry-run');
        try {
            $out = $provisioner->provisionAll($dry, $this->option('only') ?: null, ['type' => 'SYSTEM', 'label' => 'cli:provision']);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($out['errors']) {
            $this->error('Registry tidak valid; impor DIBATALKAN. Jalankan trace:validate-registry untuk detail.');
            foreach ($out['errors'] as $label => $msgs) {
                $this->line("  {$label}: " . implode(' | ', $msgs));
            }
            return self::FAILURE;
        }

        $rows = [];
        foreach ($out['results'] as $r) {
            $rows[] = [$r['trace_id'], $r['role'], $r['status'], implode(' ', $r['warnings'])];
        }
        $this->table(['Trace-ID', 'Peran', 'Hasil', 'Peringatan'], $rows);

        if ($dry) {
            $this->warn('DRY-RUN: tidak ada perubahan yang ditulis.');
            return self::SUCCESS;
        }

        if ($out['issued']) {
            $dir = $store->issuedDir();
            if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
                $this->error('Tidak dapat membuat direktori kredensial: ' . $dir);
                return self::FAILURE;
            }
            foreach ($out['issued'] as $tid => $password) {
                $path = $dir . DIRECTORY_SEPARATOR . $tid . '.credential.txt';
                file_put_contents($path, "Trace-ID : {$tid}\nKata sandi sementara : {$password}\n\nSerahkan melalui kanal aman, lalu HAPUS berkas ini.\nPengguna wajib mengganti kata sandi pada login pertama.\n", LOCK_EX);
                @chmod($path, 0600);
                $this->line("Kredensial awal {$tid} -> {$path}");
            }
            $this->warn('Kata sandi tidak ditampilkan di terminal. Hapus berkas kredensial setelah diserahkan.');
        }

        return self::SUCCESS;
    }
}
