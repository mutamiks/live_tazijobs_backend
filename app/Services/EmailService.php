<?php

namespace App\Services;

use App\Mail\GenericMail;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    public function send(string $to, string $subject, string $body, ?string $fromName = null): bool
    {
        try {
            Mail::to($to)->send(new GenericMail($subject, $body, $fromName));

            return true;
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
