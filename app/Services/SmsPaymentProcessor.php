<?php

namespace App\Services;

use App\Models\SmsPayment;
use App\Models\SmsTopup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SmsPaymentProcessor
{
    public function __construct(private readonly SmsService $sms) {}

    public function refresh(SmsPayment $payment): SmsPayment
    {
        if (blank($payment->transaction_reference)) {
            throw new RuntimeException('This payment has no provider reference.');
        }

        $payment->increment('processing_attempts');
        $payment->refresh();
        $provider = $this->sms->paymentStatus($payment->transaction_reference);
        $raw = strtoupper($provider['TransactionStatus'] ?? $provider['Status'] ?? 'PENDING');
        $providerFailed = str_contains($raw, 'FAIL') || str_contains($raw, 'ERROR');
        $providerSucceeded = str_contains($raw, 'SUCCEED')
            || str_contains($raw, 'SUCCESS')
            || str_contains($raw, 'COMPLETE');
        $status = $providerSucceeded
            ? 'successful'
            : ($providerFailed && $payment->processing_attempts > 3 ? 'failed' : 'pending');

        $payment->update([
            'status' => $status,
            'status_message' => $providerFailed && $payment->processing_attempts <= 3
                ? 'Payment confirmation is still being retried.'
                : ($provider['StatusMessage'] ?? $raw),
            'last_checked_at' => now(),
        ]);

        return $payment->fresh();
    }

    public function distribute(SmsPayment $payment, int $adminId): SmsTopup
    {
        return Cache::lock("sms-payment-distribution:{$payment->id}", 90)->block(5, function () use ($payment, $adminId) {
            $payment->refresh();
            if ($payment->status !== 'successful') {
                throw new RuntimeException('Only successful payments can be distributed.');
            }
            if ($payment->distributed || $payment->topup()->exists()) {
                throw new RuntimeException('This payment has already been distributed.');
            }

            $rate = max((float) config('sms.rate'), 0.01);
            $credits = (int) floor((float) $payment->amount / $rate);
            $provider = $this->sms->giveCredits($credits);
            if (strtolower($provider['Status'] ?? '') === 'failed') {
                throw new RuntimeException($provider['Message'] ?? 'SMS top-up failed.');
            }

            return DB::transaction(function () use ($payment, $adminId, $credits, $rate, $provider) {
                $topup = SmsTopup::query()->create([
                    'sms_payment_id' => $payment->id,
                    'added_by' => $adminId,
                    'sms_credits' => $credits,
                    'rate' => $rate,
                    'amount' => $payment->amount,
                    'provider_status' => $provider['Status'] ?? 'successful',
                    'provider_message' => $provider['Message'] ?? null,
                ]);
                $payment->update(['distributed' => true]);
                return $topup;
            });
        });
    }
}
