<?php

namespace Tests\Feature;

use App\Models\JobSeekerSubscription;
use App\Models\Job;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionMobileMoneyPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_payment_is_pending_until_mobile_money_confirmation(): void
    {
        config([
            'sms.enabled' => true,
            'sms.payment_url' => 'https://payments.example.test/task.php',
            'sms.payment_username' => 'merchant',
            'sms.payment_password' => 'secret',
            'sms.payment_method' => 'mmdeposit',
        ]);

        Http::fake([
            'payments.example.test/*' => Http::response(
                '<?xml version="1.0"?><AutoCreate><Response><Status>OK</Status><StatusMessage>Pending approval</StatusMessage><TransactionReference>SUB-123</TransactionReference></Response></AutoCreate>',
                200,
            ),
        ]);

        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);
        $package = SubscriptionPackage::query()->create([
            'name' => 'Starter',
            'price' => 10000,
            'job_chance_limit' => 2,
            'priority_level' => 1,
        ]);

        Sanctum::actingAs($jobSeeker);

        $this->postJson('/api/subscriptions', [
            'subscription_package_id' => $package->id,
            'phone' => '0772123456',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.phone', '256772123456')
            ->assertJsonPath('data.transaction_reference', 'SUB-123');

        $this->assertDatabaseCount('job_seeker_subscriptions', 0);
        $this->assertDatabaseHas('subscription_payments', [
            'user_id' => $jobSeeker->id,
            'subscription_package_id' => $package->id,
            'phone' => '256772123456',
            'status' => 'pending',
        ]);
    }

    public function test_successful_mobile_money_payment_activates_subscription(): void
    {
        config([
            'sms.enabled' => true,
            'sms.payment_url' => 'https://payments.example.test/task.php',
            'sms.payment_username' => 'merchant',
            'sms.payment_password' => 'secret',
            'sms.payment_status_method' => 'mmstatus',
        ]);

        Http::fake([
            'payments.example.test/*' => Http::response(
                '<?xml version="1.0"?><AutoCreate><Response><Status>OK</Status><TransactionStatus>SUCCEEDED</TransactionStatus><StatusMessage>Payment received</StatusMessage></Response></AutoCreate>',
                200,
            ),
        ]);

        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);
        $package = SubscriptionPackage::query()->create([
            'name' => 'Starter',
            'price' => 10000,
            'job_chance_limit' => 2,
            'priority_level' => 1,
        ]);
        $payment = SubscriptionPayment::query()->create([
            'user_id' => $jobSeeker->id,
            'subscription_package_id' => $package->id,
            'amount' => $package->price,
            'phone' => '256772123456',
            'type' => 'initial',
            'status' => 'pending',
            'transaction_reference' => 'SUB-123',
        ]);

        Sanctum::actingAs($jobSeeker);

        $this->patchJson("/api/subscriptions/payments/{$payment->id}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.subscription.subscription_package_id', $package->id);

        $this->assertDatabaseHas('job_seeker_subscriptions', [
            'user_id' => $jobSeeker->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
        ]);

        $this->assertSame(1, JobSeekerSubscription::query()->count());
    }

    public function test_admin_generates_invoice_that_job_seeker_can_view_and_pay(): void
    {
        config([
            'sms.enabled' => true,
            'sms.payment_url' => 'https://payments.example.test/task.php',
            'sms.payment_username' => 'merchant',
            'sms.payment_password' => 'secret',
            'sms.payment_method' => 'mmdeposit',
        ]);

        Http::fake([
            'payments.example.test/*' => Http::response(
                '<?xml version="1.0"?><AutoCreate><Response><Status>OK</Status><StatusMessage>Pending approval</StatusMessage><TransactionReference>INV-123</TransactionReference></Response></AutoCreate>',
                200,
            ),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $jobSeeker = User::factory()->create(['role' => 'job_seeker', 'status' => 'approved']);
        $employer = User::factory()->create(['role' => 'employer']);
        $job = Job::query()->create([
            'employer_id' => $employer->id,
            'title' => 'Accounts Assistant',
            'description' => 'Support finance operations.',
            'job_type' => 'full_time',
            'status' => 'approved',
        ]);
        $package = SubscriptionPackage::query()->create([
            'name' => 'Starter',
            'price' => 15000,
            'job_chance_limit' => 2,
            'priority_level' => 1,
        ]);

        Sanctum::actingAs($admin);

        $invoice = $this->postJson("/api/admin/users/{$jobSeeker->id}/invoices", [
            'subscription_package_id' => $package->id,
            'job_id' => $job->id,
            'amount' => 16000,
            'description' => 'Placement invoice.',
            'admin_notes' => 'Call before payment.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'unpaid')
            ->assertJsonPath('data.amount', '16000.00')
            ->json('data');

        Sanctum::actingAs($jobSeeker);

        $this->getJson('/api/invoices')
            ->assertOk()
            ->assertJsonPath('data.data.0.invoice_number', $invoice['invoice_number']);

        $this->postJson("/api/invoices/{$invoice['id']}/pay", [
            'phone' => '0772123456',
        ])->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.phone', '256772123456')
            ->assertJsonPath('data.transaction_reference', 'INV-123');
    }
}
