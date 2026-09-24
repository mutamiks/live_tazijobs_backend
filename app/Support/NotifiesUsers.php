<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Str;

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

    private function notifyUserWithSms(User $user, string $type, string $title, ?string $message = null, ?string $smsMessage = null): Notification
    {
        $notification = $this->notifyUser($user, $type, $title, $message);

        if (filled($user->phone) && filled($smsMessage)) {
            try {
                app(SmsService::class)->send($user->phone, Str::limit($smsMessage, 159, ''));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $notification;
    }
}
