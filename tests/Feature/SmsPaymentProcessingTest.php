<?php

namespace Tests\Feature;

use App\Models\SmsPayment;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsPaymentProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cron_distributes_sms_topup_when_mobile_money_payment_is_paid(): void
    {
        config(['sms.enabled' => true, 'sms.rate' => 35]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $payment = SmsPayment::query()->create([
            'user_id' => $admin->id,
            'amount' => 35000,
            'phone' => '256701111111',
            'transaction_reference' => 'TX-PAID-001',
            'status' => 'pending',
            'distributed' => false,
        ]);

        $this->mock(SmsService::class, function ($mock) {
            $mock->shouldReceive('paymentStatus')
                ->once()
                ->with('TX-PAID-001')
                ->andReturn(['TransactionStatus' => 'PAID']);
            $mock->shouldReceive('giveCredits')
                ->once()
                ->with(1000)
                ->andReturn(['Response' => ['Status' => 'OK', 'Message' => 'Credits added']]);
        });

        $this->artisan('sms:process-payments')->assertExitCode(0);

        $this->assertDatabaseHas('sms_payments', [
            'id' => $payment->id,
            'status' => 'successful',
            'distributed' => true,
        ]);
        $this->assertDatabaseHas('sms_topups', [
            'sms_payment_id' => $payment->id,
            'sms_credits' => 1000,
            'provider_status' => 'OK',
        ]);
    }

    public function test_cron_distributes_existing_successful_sms_payment(): void
    {
        config(['sms.enabled' => true, 'sms.rate' => 50]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $payment = SmsPayment::query()->create([
            'user_id' => $admin->id,
            'amount' => 10000,
            'phone' => '256701111111',
            'transaction_reference' => 'TX-SUCCESS-001',
            'status' => 'successful',
            'distributed' => false,
        ]);

        $this->mock(SmsService::class, function ($mock) {
            $mock->shouldReceive('paymentStatus')->never();
            $mock->shouldReceive('giveCredits')
                ->once()
                ->with(200)
                ->andReturn(['Status' => 'successful', 'Message' => 'Credits added']);
        });

        $this->artisan('sms:process-payments')->assertExitCode(0);

        $this->assertDatabaseHas('sms_payments', [
            'id' => $payment->id,
            'distributed' => true,
        ]);
        $this->assertDatabaseHas('sms_topups', [
            'sms_payment_id' => $payment->id,
            'sms_credits' => 200,
        ]);
    }
}
