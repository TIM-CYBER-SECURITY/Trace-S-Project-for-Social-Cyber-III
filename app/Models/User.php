<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = ['operator', 'analis', 'komandan', 'admin'];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'clearance_level', 'rank_title', 'unit', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Hak akses per kemampuan (RBAC terpusat; dipakai middleware, Gate, dan view). */
    public function can_(string $ability): bool
    {
        $matrix = config('trace.abilities', []);
        return in_array($this->role, $matrix[$ability] ?? [], true);
    }

    public function roleLabel(): string
    {
        return strtoupper($this->role);
    }
}
