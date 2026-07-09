<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SmsPayment extends Model
{
    protected $fillable = [
        'user_id', 'amount', 'phone', 'description', 'transaction_reference',
        'status', 'status_message', 'distributed',
        'processing_attempts', 'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'distributed' => 'boolean',
            'processing_attempts' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topup(): HasOne
    {
        return $this->hasOne(SmsTopup::class);
    }
}
