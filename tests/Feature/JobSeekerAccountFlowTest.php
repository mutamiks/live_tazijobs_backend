<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobSeekerAccountFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_seeker_can_register_without_email_and_login_with_phone(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Amina Namara',
            'phone' => '+256701000111',
            'password' => 'password',
            'role' => 'job_seeker',
            'terms_accepted' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', null)
            ->assertJsonPath('data.user.phone', '+256701000111')
            ->assertJsonPath('data.user.role', 'job_seeker');

        $this->assertDatabaseHas('users', [
            'name' => 'Amina Namara',
            'email' => null,
            'phone' => '+256701000111',
            'role' => 'job_seeker',
            'status' => 'pending',
        ]);

        $this->postJson('/api/login', [
            'login' => '0701000111',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.phone', '+256701000111')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_employer_registration_still_requires_terms(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Bright Works',
            'email' => 'hr@bright.test',
            'phone' => '+256702000111',
            'password' => 'password',
            'role' => 'employer',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['terms_accepted']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_employer_can_register_without_email(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Bright Works',
            'phone' => '+256702000112',
            'password' => 'password',
            'role' => 'employer',
            'terms_accepted' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', null)
            ->assertJsonPath('data.user.role', 'employer');
    }

    public function test_employer_can_register_after_accepting_terms(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Bright Works',
            'email' => 'hr@bright.test',
            'phone' => '+256702000111',
            'password' => 'password',
            'role' => 'employer',
            'terms_accepted' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'hr@bright.test')
            ->assertJsonPath('data.user.role', 'employer');
    }

    public function test_suspended_user_can_reactivate_account_with_sms_code(): void
    {
        config([
            'sms.enabled' => true,
            'sms.gateway_url' => 'https://sms.example.test/send',
            'sms.username' => 'sms-user',
            'sms.password' => 'sms-pass',
        ]);
        Http::fake(['sms.example.test/*' => Http::response('OK', 200)]);

        $user = User::factory()->create([
            'role' => 'job_seeker',
            'status' => 'suspended',
            'phone' => '+256701000333',
        ]);

        $this->postJson('/api/reactivation-code', [
            'phone' => '0701000333',
        ])->assertOk();

        $code = \App\Models\SmsVerificationCode::query()
            ->where('phone', '256701000333')
            ->where('purpose', 'account_reactivation')
            ->latest()
            ->first();

        $code->forceFill(['code_hash' => \Illuminate\Support\Facades\Hash::make('123456')])->save();

        $this->postJson('/api/reactivate-account', [
            'phone' => '0701000333',
            'code' => '123456',
        ])->assertOk()
            ->assertJsonPath('message', 'Account reactivated. You can now sign in.');

        $this->assertSame('approved', $user->fresh()->status);
    }
}
