<?php

namespace App\Policies;

use App\Models\EmployerProfile;
use App\Models\User;

class EmployerProfilePolicy
{
    public function update(User $user, EmployerProfile $profile): bool
    {
        return $user->id === $profile->user_id || $user->role === 'admin';
    }

    public function approve(User $user): bool
    {
        return $user->role === 'admin';
    }
}
