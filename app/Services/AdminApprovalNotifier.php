<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class AdminApprovalNotifier
{
    public function notifyAdmins(string $subject, string $message): int
    {
        $admins = User::query()
            ->where('role', 'admin')
            ->where('status', 'approved')
            ->get();

        $count = 0;

        foreach ($admins as $admin) {
            Notification::query()->create([
                'user_id' => $admin->id,
                'title' => $subject,
                'message' => $message,
                'type' => 'admin_approval_pending',
                'is_read' => false,
            ]);

            $count++;
        }

        return $count;
    }
}
