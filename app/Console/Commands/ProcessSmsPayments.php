<?php

namespace App\Console\Commands;

use App\Models\SmsPayment;
use App\Services\SmsPaymentProcessor;
use Illuminate\Console\Command;
use Throwable;

class ProcessSmsPayments extends Command
{
    protected $signature = 'sms:process-payments';
    protected $description = 'Check pending SMS payments and distribute successful SMS credits';

    public function handle(SmsPaymentProcessor $processor): int
    {
        if (! config('sms.enabled')) {
            $this->warn('SMS processing is disabled.');
            return self::SUCCESS;
        }

        $maxAttempts = (int) config('sms.max_payment_attempts', 24);
        $payments = SmsPayment::query()
            ->where('distributed', false)
            ->whereIn('status', ['pending', 'successful', 'failed'])
            ->where('processing_attempts', '<', $maxAttempts)
            ->oldest()
            ->limit(100)
            ->get();

        foreach ($payments as $payment) {
            try {
                if (in_array($payment->status, ['pending', 'failed'], true)) {
                    $payment = $processor->refresh($payment);
                }
                if ($payment->status === 'successful' && ! $payment->distributed) {
                    $processor->distribute($payment, $payment->user_id);
                    $this->info("Distributed payment #{$payment->id}.");
                }
            } catch (Throwable $exception) {
                $payment->update([
                    'status' => $payment->fresh()->processing_attempts > 3 ? 'failed' : 'pending',
                    'status_message' => $exception->getMessage(),
                    'last_checked_at' => now(),
                ]);
                report($exception);
                $this->error("Payment #{$payment->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Processed {$payments->count()} SMS payment(s).");
        return self::SUCCESS;
    }
}
