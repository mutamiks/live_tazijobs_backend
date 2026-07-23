<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_package_id',
        'job_seeker_subscription_id',
        'job_id',
        'created_by',
        'invoice_number',
        'amount',
        'phone',
        'description',
        'admin_notes',
        'type',
        'status',
        'transaction_reference',
        'status_message',
        'processing_attempts',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processing_attempts' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(JobSeekerSubscription::class, 'job_seeker_subscription_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
