<?php

namespace Tests\Feature;

use App\Models\EmployerProfile;
use App\Models\JobSeekerProfile;
use App\Models\Notification;
use App\Models\User;
use App\Models\WorkerOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactedJobSeekersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_contacted_job_seekers_and_employer_suggestions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $employer = User::factory()->create(['role' => 'employer', 'name' => 'Bright Works']);
        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);
        $worker = JobSeekerProfile::query()->create(['user_id' => $jobSeeker->id, 'full_name' => 'Jane Worker']);
        EmployerProfile::query()->create(['user_id' => $employer->id, 'company_name' => 'Bright Works']);
        WorkerOrder::query()->create([
            'employer_id' => $employer->id,
            'job_seeker_profile_id' => $worker->id,
            'salary_offered' => 450000,
            'job_location' => 'Kampala',
            'working_terms' => 'Monday to Friday.',
            'allowances' => 'Lunch provided.',
            'job_description' => 'Office support work.',
            'start_date' => now()->addWeek()->toDateString(),
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/contacted-job-seekers')
            ->assertOk()
            ->assertJsonPath('data.data.0.worker.full_name', 'Jane Worker')
            ->assertJsonPath('data.data.0.employer.employer_profile.company_name', 'Bright Works')
            ->assertJsonPath('data.data.0.working_terms', 'Monday to Friday.')
            ->assertJsonPath('data.data.0.job_description', 'Office support work.');
    }
    public function test_admin_can_approve_a_contact_request_once_and_notify_both_parties(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $employer = User::factory()->create(['role' => 'employer']);
        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);
        $worker = JobSeekerProfile::query()->create(['user_id' => $jobSeeker->id, 'full_name' => 'Jane Worker']);
        $order = WorkerOrder::query()->create([
            'employer_id' => $employer->id,
            'job_seeker_profile_id' => $worker->id,
            'salary_offered' => 450000,
            'job_location' => 'Kampala',
            'working_terms' => 'Monday to Friday.',
            'job_description' => 'Office support work.',
            'start_date' => now()->addWeek()->toDateString(),
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/worker-orders/{$order->id}/decision", [
            'status' => 'approved',
        ])->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('notifications', ['user_id' => $employer->id, 'type' => 'worker_order']);
        $this->assertDatabaseHas('notifications', ['user_id' => $jobSeeker->id, 'type' => 'worker_order']);

        $this->patchJson("/api/admin/worker-orders/{$order->id}/decision", [
            'status' => 'rejected',
            'rejection_reason' => 'Already reviewed.',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'This worker request has already been decided.');
    }
}