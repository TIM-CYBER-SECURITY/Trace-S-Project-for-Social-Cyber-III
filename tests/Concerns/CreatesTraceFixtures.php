<?php

namespace Tests\Concerns;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Identity\TraceIdGenerator;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\File;

trait CreatesTraceFixtures
{
    protected function setUpTraceFixtures(): void
    {
        $this->cleanTestingProvisioning();
        $this->seed(RbacSeeder::class);
    }

    protected function cleanTestingProvisioning(): void
    {
        $path = storage_path('framework/testing/provisioning');
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }

    protected function makeOrg(string $name = 'Direktorat Uji Siber'): Organization
    {
        return Organization::firstOrCreate(['name' => $name], ['status' => 'ACTIVE']);
    }

    protected function makeUser(
        string $roleName = 'OPERATOR',
        ?Organization $org = null,
        string $accountStatus = 'ACTIVE',
        bool $mustChangePassword = false,
        ?string $password = 'Str0ng!Pass#2026',
    ): User {
        $org ??= $this->makeOrg();
        $unit = Unit::firstOrCreate(['organization_id' => $org->id, 'name' => 'Unit Operasional'], ['status' => 'ACTIVE']);
        $role = Role::where('name', strtoupper($roleName))->firstOrFail();

        $tid = app(TraceIdGenerator::class)->generate();

        return User::create([
            'trace_id' => $tid,
            'name' => strtoupper($roleName) . ' ' . $tid,
            'password' => $password,
            'role_id' => $role->id,
            'organization_id' => $org->id,
            'unit_id' => $unit->id,
            'position' => $roleName === 'ADMINISTRATOR' ? 'System Administrator' : 'Cyber Operator',
            'account_status' => $accountStatus,
            'approval_status' => 'APPROVED',
            'must_change_password' => $mustChangePassword,
            'password_changed_at' => $mustChangePassword ? null : now(),
        ]);
    }
}
