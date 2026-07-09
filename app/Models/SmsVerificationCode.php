<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsVerificationCode extends Model
{
    protected $fillable = ['phone', 'purpose', 'code_hash', 'expires_at', 'used_at', 'attempts'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
