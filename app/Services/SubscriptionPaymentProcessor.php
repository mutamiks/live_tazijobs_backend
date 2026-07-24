<?php

namespace App\Services;

use App\Models\JobSeekerSubscription;
use App\Models\Notification;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class SubscriptionPaymentProcessor
{
    public function __construct(private readonly SmsService $sms) {}

    public function refresh(SubscriptionPayment $payment): SubscriptionPayment
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

        if ($status === 'successful') {
            return $this->activate($payment->fresh(['package', 'subscription']));
        }

        return $payment->fresh(['package', 'subscription']);
    }

    public function activate(SubscriptionPayment $payment): SubscriptionPayment
    {
        return Cache::lock("subscription-payment-activation:{$payment->id}", 90)->block(5, function () use ($payment) {
            $payment->refresh();

            if ($payment->status !== 'successful') {
                throw new RuntimeException('Only successful payments can activate a subscription.');
            }

            if ($payment->status === 'confirmed' && $payment->job_seeker_subscription_id && $payment->subscription()->exists()) {
                return $payment->fresh(['package', 'subscription.package']);
            }

            $confirmedPayment = DB::transaction(function () use ($payment) {
                $package = $payment->package()->lockForUpdate()->firstOrFail();
                $activeSubscription = $payment->user
                    ->activeJobSeekerSubscription()
                    ->lockForUpdate()
                    ->first();

                if ($activeSubscription) {
                    $activeSubscription->forceFill([
                        'subscription_package_id' => $package->id,
                        'amount_paid' => $activeSubscription->amount_paid + $payment->amount,
                        'job_chance_limit' => max($activeSubscription->job_chance_limit, $package->job_chance_limit),
                        'job_chances_used' => min($activeSubscription->job_chances_used, $package->job_chance_limit),
                        'priority_level' => max($activeSubscription->priority_level, $package->priority_level),
                    ])->save();

                    $subscription = $activeSubscription;
                } else {
                    $subscription = JobSeekerSubscription::query()->create([
                        'user_id' => $payment->user_id,
                        'subscription_package_id' => $package->id,
                        'amount_paid' => $payment->amount,
                        'job_chance_limit' => $package->job_chance_limit,
                        'priority_level' => $package->priority_level,
                        'status' => 'active',
                        'started_at' => now(),
                    ]);
                }

                $payment->update([
                    'job_seeker_subscription_id' => $subscription->id,
                    'status' => 'confirmed',
                ]);

                if ($payment->invoice_number && $payment->user?->status !== 'suspended') {
                    $payment->user->forceFill(['status' => 'suspended'])->save();
                }

                return $payment->fresh(['package', 'subscription.package']);
            });

            $this->notifyConfirmedPayment($confirmedPayment);

            return $confirmedPayment;
        });
    }

    private function notifyConfirmedPayment(SubscriptionPayment $payment): void
    {
        $payment->loadMissing(['user', 'job', 'package']);
        $amount = 'UGX '.number_format((float) $payment->amount);
        $job = $payment->job?->title ? " for {$payment->job->title}" : '';
        $invoice = $payment->invoice_number ? "Invoice {$payment->invoice_number}" : 'Your payment';
        $message = "{$invoice}{$job} has been received and approved. Amount: {$amount}.";

        Notification::query()->create([
            'user_id' => $payment->user_id,
            'title' => 'Payment approved',
            'message' => $message,
            'type' => 'payment_approved',
        ]);

        if (filled($payment->user?->phone)) {
            try {
                $this->sms->send($payment->user->phone, Str::limit("TaziJobs: {$message}", 159, ''));
            } catch (RuntimeException $exception) {
                Log::warning('Payment approval SMS failed', [
                    'subscription_payment_id' => $payment->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
