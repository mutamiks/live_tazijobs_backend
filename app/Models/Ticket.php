<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'created_by',
        'client_full_name',
        'phones',
        'client_type',
        'ticket_type',
        'comment',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'phones' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
