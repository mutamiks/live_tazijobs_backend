<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class JobSeekerProfile extends Model
{
    protected $appends = ['profile_photo_thumbnail_url'];

    protected $fillable = [
        'user_id',
        'full_name',
        'job_title',
        'gender',
        'date_of_birth',
        'location',
        'district',
        'county',
        'subcounty',
        'parish',
        'village',
        'languages',
        'religion',
        'phone',
        'education_level',
        'skills',
        'experience_years',
        'bio',
        'work_experience',
        'preferred_job_categories',
        'cv_file',
        'lc1_letter_file',
        'id_document_file',
        'id_document_front_file',
        'id_document_back_file',
        'profile_photo',
        'profile_photo_thumbnail',
        'terms_accepted',
        'is_available',
        'status',
        'rejection_reason',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'skills' => 'array',
            'languages' => 'array',
            'preferred_job_categories' => 'array',
            'terms_accepted' => 'boolean',
            'is_available' => 'boolean',
            'experience_years' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvalHistories(): MorphMany
    {
        return $this->morphMany(ApprovalHistory::class, 'approvable')->latest();
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', 'approved')
            ->where('is_available', true)
            ->whereHas('user', fn (Builder $query) => $query->where('status', 'approved'));
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === 'approved'
            && $this->is_available
            && $this->user?->status === 'approved';
    }

    public function getProfilePhotoThumbnailUrlAttribute(): ?string
    {
        return $this->profile_photo_thumbnail
            ? Storage::disk('public')->url($this->profile_photo_thumbnail)
            : null;
    }
}
