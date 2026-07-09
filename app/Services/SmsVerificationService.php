<?php

namespace App\Services;

use App\Models\SmsVerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SmsVerificationService
{
    public function __construct(private readonly SmsService $sms) {}

    public function send(string $phone, string $purpose): void
    {
        $phone = $this->sms->normalizePhone($phone);
        $code = (string) random_int(100000, 999999);
        $record = SmsVerificationCode::query()->create([
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $message = $purpose === 'password_reset'
            ? "TaziJobs password reset code: {$code}. Expires in 10 minutes."
            : "TaziJobs verification code: {$code}. Expires in 10 minutes.";

        try {
            $this->sms->send($phone, $message);
        } catch (\Throwable $exception) {
            $record->delete();
            throw $exception;
        }

        SmsVerificationCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->whereKeyNot($record->getKey())
            ->update(['used_at' => now()]);
    }

    public function verify(string $phone, string $purpose, string $code): void
    {
        $phone = $this->sms->normalizePhone($phone);
        $record = SmsVerificationCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $record || $record->expires_at->isPast() || $record->attempts >= 5) {
            throw ValidationException::withMessages(['code' => 'The verification code is invalid or expired.']);
        }
        $record->increment('attempts');
        if (! Hash::check($code, $record->code_hash)) {
            throw ValidationException::withMessages(['code' => 'The verification code is incorrect.']);
        }
        $record->update(['used_at' => now()]);
    }
}
