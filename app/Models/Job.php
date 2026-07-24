<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Job extends Model
{
    protected $fillable = [
        'employer_id',
        'job_category_id',
        'title',
        'positions',
        'description',
        'requirements',
        'responsibilities',
        'location',
        'district',
        'county',
        'subcounty',
        'parish',
        'village',
        'job_type',
        'salary_min',
        'salary_max',
        'deadline',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'is_listed',
    ];

    protected function casts(): array
    {
        return [
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'deadline' => 'date',
            'approved_at' => 'datetime',
            'is_listed' => 'boolean',
        ];
    }

    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'job_category_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', 'approved')
            ->where('is_listed', true)
            ->whereHas('employer', fn (Builder $query) => $query->where('status', 'approved'));
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === 'approved' && $this->is_listed && $this->employer?->status === 'approved';
    }

    public function approvalHistories(): MorphMany
    {
        return $this->morphMany(ApprovalHistory::class, 'approvable')->latest();
    }
}
