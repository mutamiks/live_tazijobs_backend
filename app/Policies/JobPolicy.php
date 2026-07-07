<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    public function create(User $user): bool
    {
        return $user->role === 'employer' && $user->employerProfile?->status === 'approved';
    }

    public function view(User $user, Job $job): bool
    {
        return $job->status === 'approved' || $user->id === $job->employer_id || $user->role === 'admin';
    }

    public function approve(User $user): bool
    {
        return $user->role === 'admin';
    }
}
