<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Identity\AccountProvisioner;
use App\Services\Identity\RegistryStore;
use App\Services\Identity\RegistryValidator;
use App\Services\Identity\TraceIdGenerator;
use Illuminate\Console\Command;

/**
 * Jalur resmi pengembang untuk mencatat permintaan terverifikasi ke registry eksternal
 * dan menerbitkan akun Trace-ID + kata sandi sementara sekali-pakai ke berkas privat.
 */
class IssueAccountCommand extends Command
{
    protected $signature = 'trace:issue-account
        {full_name : Nama lengkap personel terverifikasi}
        {organization : Nama instansi / organisasi terverifikasi}
        {--code= : Kode Instansi resmi yang ditetapkan Developer (huruf, angka, simbol)}
        {--role=OPERATOR : Peran akun (OPERATOR atau ADMINISTRATOR)}
        {--unit= : Unit kerja (opsional)}
        {--position= : Jabatan/posisi personel (opsional)}
        {--approved-by=developer-cli : Identitas developer penyetuju}
        {--notes= : Catatan verifikasi eksternal}
        {--expires= : Tanggal kedaluwarsa ISO 8601 (opsional)}
        {--dry-run : Simulasikan tanpa menulis ke registry atau database}';

    protected $description = 'Menerbitkan Trace-ID dan kredensial awal terverifikasi melalui jalur provisioning pengembang (tanpa registrasi publik).';

    public function handle(
        TraceIdGenerator $generator,
        RegistryStore $store,
        RegistryValidator $validator,
        AccountProvisioner $provisioner,
    ): int {
        $fullName = trim((string) $this->argument('full_name'));
        $organization = trim((string) $this->argument('organization'));
        $rawCode = trim((string) $this->option('code'));
        $role = strtoupper(trim((string) $this->option('role')));
        $approvedBy = trim((string) $this->option('approved-by'));
        $dryRun = (bool) $this->option('dry-run');

        if (mb_strlen($fullName) < 2 || mb_strlen($organization) < 2) {
            $this->error('Nama lengkap dan nama instansi minimal 2 karakter.');
            return self::FAILURE;
        }
        if ($rawCode !== '' && (mb_strlen($rawCode) < 2 || mb_strlen($rawCode) > 64)) {
            $this->error('Kode Instansi harus antara 2 hingga 64 karakter (huruf, angka, dan/atau simbol).');
            return self::FAILURE;
        }
        if (! in_array($role, ['OPERATOR', 'ADMINISTRATOR'], true)) {
            $this->error('Peran harus OPERATOR atau ADMINISTRATOR.');
            return self::FAILURE;
        }
        if ($approvedBy === '') {
            $this->error('--approved-by tidak boleh kosong.');
            return self::FAILURE;
        }

        $orgCode = $rawCode !== ''
            ? $rawCode
            : (Organization::where('name', $organization)->value('code')
                ?: ('INS-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $organization) ?: 'ORG', 0, 4)) . '#' . str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT)));

        $position = ($p = trim((string) $this->option('position'))) !== '' ? $p : null;
        $traceId = $generator->generate();
        $entry = [
            'trace_id' => $traceId,
            'full_name' => $fullName,
            'organization' => $organization,
            'organization_code' => $orgCode,
            'unit' => ($u = trim((string) $this->option('unit'))) !== '' ? $u : null,
            'position' => $position,
            'role' => $role,
            'approval_status' => 'APPROVED',
            'account_status' => 'PROVISIONED',
            'approved_by' => $approvedBy,
            'approved_at' => now()->toIso8601String(),
            'provisioned_at' => null,
            'expires_at' => ($exp = trim((string) $this->option('expires'))) !== '' ? $exp : null,
            'notes' => ($n = trim((string) $this->option('notes'))) !== '' ? $n : null,
        ];

        $existing = $store->approved();
        $candidateList = array_merge($existing, [$entry]);
        $errors = $validator->validate($candidateList);
        if ($errors) {
            foreach ($errors as $label => $msgs) {
                $this->error("Entri {$label}: " . implode(' | ', $msgs));
            }
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->table(['Trace-ID', 'Nama', 'Instansi', 'Kode Instansi', 'Peran/Posisi', 'Status', 'Mode'], [[
                $traceId, $fullName, $organization, $orgCode, $position ? "{$role} ({$position})" : $role, 'PROVISIONED', 'DRY-RUN',
            ]]);
            $this->warn('DRY-RUN: entri belum ditulis ke registry maupun database.');
            return self::SUCCESS;
        }

        $store->append($store->approvedFile(), $entry);

        $out = $provisioner->provisionAll(false, $traceId, ['type' => 'SYSTEM', 'label' => 'cli:issue:' . $approvedBy]);
        if ($out['errors'] || empty($out['issued'][$traceId])) {
            $this->error('Gagal melakukan provisioning akun ke database.');
            return self::FAILURE;
        }

        $dir = $store->issuedDir();
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            $this->error('Tidak dapat membuat direktori kredensial: ' . $dir);
            return self::FAILURE;
        }

        $password = $out['issued'][$traceId];
        $roleDisplay = $position ? "{$role} ({$position})" : $role;
        $path = $dir . DIRECTORY_SEPARATOR . $traceId . '.credential.txt';
        file_put_contents(
            $path,
            "Trace-ID              : {$traceId}\n"
            . "Nama                  : {$fullName}\n"
            . "Instansi              : {$organization}\n"
            . "Kode Instansi         : {$orgCode}\n"
            . "Peran / Posisi        : {$roleDisplay}\n"
            . "Status                : PROVISIONED (Wajib ganti kata sandi saat login pertama)\n"
            . "Kata sandi sementara  : {$password}\n\n"
            . "Serahkan melalui kanal aman kepada personel berwenang, lalu HAPUS berkas ini.\n"
            . "Pengguna wajib mengganti kata sandi secara mandiri pada login pertama.\n",
            LOCK_EX
        );
        @chmod($path, 0600);

        $this->table(['Trace-ID', 'Nama', 'Instansi', 'Kode Instansi', 'Peran', 'Status', 'Berkas Kredensial'], [[
            $traceId, $fullName, $organization, $orgCode, $roleDisplay, 'PROVISIONED', $path,
        ]]);
        $this->warn('Kata sandi sementara disimpan aman pada berkas kredensial di atas (tidak dicetak ke terminal).');

        return self::SUCCESS;
    }
}
