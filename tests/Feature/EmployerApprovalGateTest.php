<?php

namespace Tests\Feature;

use App\Models\EmployerProfile;
use App\Models\JobSeekerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployerApprovalGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_employer_cannot_access_worker_information(): void
    {
        $employer = User::factory()->create(['role' => 'employer']);
        EmployerProfile::query()->create([
            'user_id' => $employer->id,
            'company_name' => 'Bright Works',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employer);

        $this->getJson('/api/workers')
            ->assertForbidden()
            ->assertJsonPath('message', 'Employer profile must be approved before accessing worker information.');
    }

    public function test_approved_employer_can_access_worker_information(): void
    {
        $employer = User::factory()->create(['role' => 'employer']);
        $workerUser = User::factory()->create(['role' => 'job_seeker']);
        EmployerProfile::query()->create([
            'user_id' => $employer->id,
            'company_name' => 'Bright Works',
            'status' => 'approved',
        ]);
        JobSeekerProfile::query()->create([
            'user_id' => $workerUser->id,
            'full_name' => 'Amina Namara',
            'status' => 'approved',
            'is_available' => true,
        ]);

        Sanctum::actingAs($employer);

        $this->getJson('/api/workers')
            ->assertOk()
            ->assertJsonPath('data.data.0.full_name', 'Amina Namara');
    }

    public function test_approved_employer_cannot_see_suspended_job_seekers_in_worker_search(): void
    {
        $employer = User::factory()->create(['role' => 'employer']);
        $activeWorker = User::factory()->create(['role' => 'job_seeker', 'status' => 'approved']);
        $suspendedWorker = User::factory()->create(['role' => 'job_seeker', 'status' => 'suspended']);
        EmployerProfile::query()->create([
            'user_id' => $employer->id,
            'company_name' => 'Bright Works',
            'status' => 'approved',
        ]);
        JobSeekerProfile::query()->create([
            'user_id' => $activeWorker->id,
            'full_name' => 'Visible Driver',
            'job_title' => 'Driver',
            'status' => 'approved',
            'is_available' => true,
        ]);
        JobSeekerProfile::query()->create([
            'user_id' => $suspendedWorker->id,
            'full_name' => 'Hidden Driver',
            'job_title' => 'Driver',
            'status' => 'approved',
            'is_available' => true,
        ]);

        Sanctum::actingAs($employer);

        $this->getJson('/api/workers?search=Driver')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.full_name', 'Visible Driver');
    }
}
