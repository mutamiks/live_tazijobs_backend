<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
