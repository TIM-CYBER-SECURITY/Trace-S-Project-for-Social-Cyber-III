<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder idempoten untuk memastikan akun resmi PUSSIBERAD TNI AD (PUS001TNIAD)
 * dan PUSSIBERAD TNI (010TNI) beserta kredensial dokumentasi tersedia di cloud container.
 */
class OfficialPussiberadSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RbacSeeder::class);

        $adminRole = Role::where('name', 'ADMINISTRATOR')->firstOrFail();
        $operatorRole = Role::where('name', 'OPERATOR')->firstOrFail();

        $pussiberadAd = Organization::updateOrCreate(
            ['name' => 'PUSSIBERAD TNI AD'],
            ['code' => 'PUS001TNIAD', 'status' => 'ACTIVE']
        );

        $pussiberadTni = Organization::updateOrCreate(
            ['code' => '010TNI'],
            ['name' => 'PUSSIBERAD TNI', 'status' => 'ACTIVE']
        );

        $issuedPath = storage_path('app/issued_pussiberad_accounts.json');
        $accounts = null;
        if (is_file($issuedPath)) {
            $decoded = json_decode((string) file_get_contents($issuedPath), true);
            if (is_array($decoded) && $decoded !== []) {
                $accounts = $decoded;
            }
        }

        if ($accounts === null) {
            $accounts = [
                [
                    'trace_id' => 'TRS-984Y-SCC7',
                    'name' => 'Letnan Satu Administrator 01',
                    'role' => 'ADMINISTRATOR',
                    'position' => 'Pama Analis Siber Taktis',
                    'temp_password' => 'Mh@2?8gxgY@*m5uQ',
                ],
                [
                    'trace_id' => 'TRS-4CUK-HCVM',
                    'name' => 'Kapten Administrator 02',
                    'role' => 'ADMINISTRATOR',
                    'position' => 'Pama Kepala Tim Investigasi',
                    'temp_password' => 'jNEKXU3xkWZ$i2-9',
                ],
                [
                    'trace_id' => 'TRS-FZHS-DDNK',
                    'name' => 'Mayor Administrator 03',
                    'role' => 'ADMINISTRATOR',
                    'position' => 'Pamen Kepala Seksi Deteksi Dini',
                    'temp_password' => 'cQ5M#P4s3yH!ETev',
                ],
                [
                    'trace_id' => 'TRS-5KHW-YVS8',
                    'name' => 'Letnan Kolonel Administrator 04',
                    'role' => 'ADMINISTRATOR',
                    'position' => 'Pamen Wakil Komandan Satgas Siber',
                    'temp_password' => 'Fr%!G4Q?tSrr2FDB',
                ],
                [
                    'trace_id' => 'TRS-3YKR-ESDG',
                    'name' => 'Kolonel Administrator 05',
                    'role' => 'ADMINISTRATOR',
                    'position' => 'Pamen Komandan Satgas Siber Kognitif',
                    'temp_password' => 'VW$VxZq5@xrPYR3k',
                ],
                [
                    'trace_id' => 'TRS-NK8C-65GK',
                    'name' => 'Sersan Dua Operator 01',
                    'role' => 'OPERATOR',
                    'position' => 'Bintara Pemindaian Media Sosial',
                    'temp_password' => 'VBeBYLh76#rGP?tT',
                ],
                [
                    'trace_id' => 'TRS-KCED-L4M9',
                    'name' => 'Sersan Satu Operator 02',
                    'role' => 'OPERATOR',
                    'position' => 'Bintara Verifikasi Fakta & Bukti',
                    'temp_password' => 'sB63#ZAmpur#R-az',
                ],
                [
                    'trace_id' => 'TRS-K7YE-3LQS',
                    'name' => 'Sersan Kepala Operator 03',
                    'role' => 'OPERATOR',
                    'position' => 'Bintara Senior Triage Narasi Disinformasi',
                    'temp_password' => 'K5p!8EQg!%sL#Jjg',
                ],
            ];
        }

        foreach ($accounts as $acc) {
            $roleId = strtoupper((string) ($acc['role'] ?? 'OPERATOR')) === 'ADMINISTRATOR'
                ? $adminRole->id
                : $operatorRole->id;

            if (User::where('trace_id', $acc['trace_id'])->doesntExist()) {
                User::create([
                    'trace_id' => $acc['trace_id'],
                    'name' => $acc['name'],
                    'organization_id' => $pussiberadAd->id,
                    'role_id' => $roleId,
                    'position' => $acc['position'] ?? null,
                    'account_status' => 'PROVISIONED',
                    'approval_status' => 'APPROVED',
                    'must_change_password' => true,
                    'password' => $acc['temp_password'],
                ]);
            }
        }

        if (User::where('trace_id', 'TRS-G4BM-42SA')->doesntExist()) {
            User::create([
                'trace_id' => 'TRS-G4BM-42SA',
                'name' => 'Sersan Kepala Dandy Aulia',
                'organization_id' => $pussiberadTni->id,
                'role_id' => $adminRole->id,
                'position' => 'Administrator Sistem Trace-S',
                'account_status' => 'ACTIVE',
                'approval_status' => 'APPROVED',
                'must_change_password' => false,
                'password' => 'snzgPHpN*i96*!KDRT',
            ]);
        }
    }
}
