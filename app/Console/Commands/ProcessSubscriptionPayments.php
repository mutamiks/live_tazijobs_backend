<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPayment;
use App\Services\SubscriptionPaymentProcessor;
use Illuminate\Console\Command;
use Throwable;

class ProcessSubscriptionPayments extends Command
{
    protected $signature = 'subscriptions:process-payments';
    protected $description = 'Check pending subscription payments and activate successful packages';

    public function handle(SubscriptionPaymentProcessor $processor): int
    {
        $maxAttempts = (int) config('sms.max_payment_attempts', 24);
        $payments = SubscriptionPayment::query()
            ->where('status', 'pending')
            ->whereNotNull('transaction_reference')
            ->where('processing_attempts', '<', $maxAttempts)
            ->oldest('last_checked_at')
            ->limit(25)
            ->get();

        foreach ($payments as $payment) {
            try {
                $payment = $processor->refresh($payment);
                if ($payment->status === 'confirmed') {
                    $this->info("Activated subscription payment #{$payment->id}.");
                }
            } catch (Throwable $exception) {
                $payment->refresh();
                if ($payment->processing_attempts > 3) {
                    $payment->update(['status' => 'failed', 'status_message' => $exception->getMessage()]);
                }
                $this->error("Payment #{$payment->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Processed {$payments->count()} subscription payment(s).");

        return self::SUCCESS;
    }
}
