<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\User;

trait NotifiesUsers
{
    private function notifyUser(User $user, string $type, string $title, ?string $message = null): Notification
    {
        return Notification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ]);
    }
}
