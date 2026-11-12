<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Data referensi otorisasi (BUKAN akun). Aman dan wajib dijalankan di semua lingkungan,
 * termasuk produksi. Sumber matriks: config/trace.php ('roles', 'permissions').
 * Idempoten: dapat dijalankan berulang untuk menyelaraskan matriks izin.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [];
        foreach (config('trace.roles') as $name => $desc) {
            $roles[$name] = Role::updateOrCreate(['name' => $name], ['description' => $desc]);
        }

        $assign = array_fill_keys(array_keys($roles), []);
        foreach (config('trace.permissions') as $key => [$desc, $roleNames]) {
            $perm = Permission::updateOrCreate(['permission_key' => $key], ['description' => $desc]);
            foreach ($roleNames as $rn) {
                $assign[$rn][] = $perm->id;
            }
        }

        foreach ($roles as $name => $role) {
            $role->permissions()->sync($assign[$name]);
        }
    }
}
