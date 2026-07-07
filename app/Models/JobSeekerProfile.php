<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class JobSeekerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
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
}
