<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    public const LOGIN_STATUSES = ['PROVISIONED', 'ACTIVE'];

    protected $fillable = [
        'name', 'email', 'password', 'trace_id', 'organization_id', 'unit_id', 'position',
        'role_id', 'account_status', 'approval_status', 'must_change_password',
        'password_changed_at', 'expires_at', 'last_login_at',
        'last_device_code', 'last_login_ip', 'last_user_agent',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** @var array<string,bool>|null */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'expires_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function roleRef(): BelongsTo { return $this->belongsTo(Role::class, 'role_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }

    /** Nama peran huruf kecil ('administrator' | 'operator'); kosong bila belum ditetapkan. */
    public function getRoleAttribute(): string
    {
        return strtolower((string) $this->roleRef?->name);
    }

    public function hasRole(string ...$roles): bool
    {
        $mine = strtoupper((string) $this->roleRef?->name);
        foreach ($roles as $r) {
            if (strtoupper($r) === $mine) {
                return true;
            }
        }
        return false;
    }

    public function isAdministrator(): bool { return $this->hasRole('ADMINISTRATOR'); }

    /** Deny-by-default: izin hanya ada bila terdaftar pada peran akun. */
    public function hasPermission(string $key): bool
    {
        if ($this->permissionCache === null) {
            $this->permissionCache = $this->roleRef
                ? $this->roleRef->permissions()->pluck('permission_key')->flip()->map(fn () => true)->all()
                : [];
        }
        return isset($this->permissionCache[$key]);
    }

    /** Alias historis yang dipakai view/middleware lama. */
    public function can_(string $ability): bool
    {
        return $this->hasPermission($ability);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function canAuthenticate(): bool
    {
        return in_array($this->account_status, self::LOGIN_STATUSES, true)
            && $this->approval_status === 'APPROVED'
            && ! $this->isExpired();
    }

    public function roleLabel(): string
    {
        return strtoupper((string) $this->roleRef?->name ?: 'NO ROLE');
    }

    public static function computeDeviceCode(?string $ip, ?string $ua): string
    {
        return 'DEV-' . strtoupper(substr(hash('sha256', ($ip ?? '0.0.0.0') . '|' . ($ua ?? 'cli')), 0, 8));
    }
}
