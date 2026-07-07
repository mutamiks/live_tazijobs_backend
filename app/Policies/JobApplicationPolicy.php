<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;

class JobApplicationPolicy
{
    public function create(User $user, Job $job): bool
    {
        return $user->role === 'job_seeker'
            && $user->jobSeekerProfile?->status === 'approved'
            && $job->status === 'approved';
    }

    public function update(User $user, JobApplication $application): bool
    {
        return $user->role === 'employer' && $application->job->employer_id === $user->id;
    }
}
