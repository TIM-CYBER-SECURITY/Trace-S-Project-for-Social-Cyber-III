<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Identitas platform (Developer/Owner). Bukan peran operasional dan tidak
 * memiliki izin apa pun terhadap data operasional (informasi, kasus, dsb.).
 */
class PlatformOwner extends Authenticatable
{
    protected $fillable = ['owner_id', 'name', 'password', 'must_change_password', 'status', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
