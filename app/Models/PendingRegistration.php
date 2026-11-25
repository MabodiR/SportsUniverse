<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['payload', 'otp_hash'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'expires_at' => 'datetime', 'approved_at' => 'datetime'];
    }
}
