<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationRequest extends Model
{
    public const STATUSES = [
        'PENDING REVIEW', 'UNDER REVIEW', 'APPROVED', 'REJECTED', 'REVISION REQUIRED',
        'CREDENTIAL ISSUED', 'ACTIVE', 'SUSPENDED', 'REVOKED',
    ];

    protected $guarded = [];

    protected $hidden = ['status_code_hash'];

    protected function casts(): array
    {
        return [
            'identification_number' => 'encrypted',
            'contact_email' => 'encrypted',
            'contact_phone' => 'encrypted',
            'recommender_contact' => 'encrypted',
            'reviewed_at' => 'datetime',
        ];
    }

    public function transitions()
    {
        return StatusTransition::where('subject_type', 'REGISTRATION')
            ->where('subject_id', $this->request_reference)->orderBy('id');
    }
}
