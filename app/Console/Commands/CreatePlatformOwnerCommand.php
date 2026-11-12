<?php

namespace App\Console\Commands;

use App\Models\PlatformOwner;
use App\Services\Identity\RegistryStore;
use App\Services\Identity\TraceIdGenerator;
use App\Services\Support\AuditLogger;
use Illuminate\Console\Command;

class CreatePlatformOwnerCommand extends Command
{
    protected $signature = 'trace:owner-create {owner_id : Identitas login Owner} {name : Nama lengkap} {--generate : Hasilkan sandi sementara ke berkas privat}';
    protected $description = 'Membuat identitas Developer/Owner platform (terpisah dari peran operasional).';

    public function handle(AuditLogger $audit, RegistryStore $store): int
    {
        $ownerId = strtolower($this->argument('owner_id'));
        if (! preg_match('/^[a-z0-9._-]{3,40}$/', $ownerId)) {
            $this->error('owner_id hanya boleh huruf kecil, angka, titik, garis bawah, atau strip (3-40 karakter).');
            return self::FAILURE;
        }
        if (PlatformOwner::where('owner_id', $ownerId)->exists()) {
            $this->error('Owner sudah terdaftar.');
            return self::FAILURE;
        }

        $min = (int) config('trace.security.owner_password_min_length', 14);
        if ($this->option('generate')) {
            $password = TraceIdGenerator::temporaryPassword(max($min, 20));
        } else {
            $password = (string) $this->secret("Kata sandi (min {$min} karakter)");
            $confirm = (string) $this->secret('Ulangi kata sandi');
            if ($password !== $confirm || strlen($password) < $min) {
                $this->error("Kata sandi tidak cocok atau kurang dari {$min} karakter.");
                return self::FAILURE;
            }
        }

        $owner = PlatformOwner::create([
            'owner_id' => $ownerId, 'name' => $this->argument('name'),
            'password' => $password, 'must_change_password' => (bool) $this->option('generate'),
        ]);
        $audit->log('OWNER_CREATED', $owner, ['owner_id' => $ownerId], null, 'SUCCESS', ['type' => 'SYSTEM', 'label' => 'cli']);

        if ($this->option('generate')) {
            $dir = $store->issuedDir();
            @mkdir($dir, 0700, true);
            $path = $dir . DIRECTORY_SEPARATOR . 'OWNER-' . $ownerId . '.credential.txt';
            file_put_contents($path, "Owner : {$ownerId}\nKata sandi sementara : {$password}\nHAPUS berkas ini setelah login pertama.\n", LOCK_EX);
            @chmod($path, 0600);
            $this->line("Kredensial sementara ditulis ke: {$path}");
        }

        $this->info("Owner {$ownerId} dibuat. Login di /platform/login.");
        return self::SUCCESS;
    }
}
