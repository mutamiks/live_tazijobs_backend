<?php

namespace App\Policies;

use App\Models\JobSeekerProfile;
use App\Models\User;

class JobSeekerProfilePolicy
{
    public function update(User $user, JobSeekerProfile $profile): bool
    {
        return $user->id === $profile->user_id || $user->role === 'admin';
    }

    public function approve(User $user): bool
    {
        return $user->role === 'admin';
    }
}
