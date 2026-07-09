<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsTopup extends Model
{
    protected $fillable = [
        'sms_payment_id', 'added_by', 'sms_credits', 'rate', 'amount',
        'provider_status', 'provider_message',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SmsPayment::class, 'sms_payment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
