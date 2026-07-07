<?php

namespace Tests\Feature;

use App\Models\EmployerProfile;
use App\Models\Job;
use App\Models\JobSeekerSubscription;
use App\Models\JobSeekerProfile;
use App\Models\JobCategory;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaziJobAppWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_employer_must_be_approved_before_posting_jobs(): void
    {
        $employer = User::factory()->create(['role' => 'employer']);
        $category = JobCategory::query()->create(['name' => 'Domestic work']);
        Sanctum::actingAs($employer);

        $this->postJson('/api/employer/profile', [
            'company_name' => 'Bright Works',
            'company_email' => 'hr@bright.test',
        ])->assertCreated();

        $this->postJson('/api/employer/jobs', [
            'title' => 'Support Agent',
            'job_category_id' => $category->id,
            'description' => 'Help customers solve product questions.',
            'job_type' => 'full_time',
        ])->assertUnprocessable();

        EmployerProfile::query()->where('user_id', $employer->id)->update(['status' => 'approved']);

        $this->postJson('/api/employer/jobs', [
            'title' => 'Support Agent',
            'job_category_id' => $category->id,
            'description' => 'Help customers solve product questions.',
            'job_type' => 'full_time',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_job_seeker_must_be_approved_before_applying(): void
    {
        $employer = User::factory()->create(['role' => 'employer']);
        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);

        $job = Job::query()->create([
            'employer_id' => $employer->id,
            'title' => 'Junior Developer',
            'description' => 'Build and support Laravel APIs.',
            'job_type' => 'remote',
            'status' => 'approved',
        ]);

        JobSeekerProfile::query()->create([
            'user_id' => $jobSeeker->id,
            'full_name' => 'Jane Applicant',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($jobSeeker);

        $this->postJson("/api/jobs/{$job->id}/apply", [
            'cover_letter' => 'I would love to apply.',
        ])->assertUnprocessable();

        $jobSeeker->jobSeekerProfile()->update(['status' => 'approved']);

        $package = SubscriptionPackage::query()->create([
            'name' => 'Starter',
            'price' => 10000,
            'job_chance_limit' => 2,
            'priority_level' => 1,
        ]);

        JobSeekerSubscription::query()->create([
            'user_id' => $jobSeeker->id,
            'subscription_package_id' => $package->id,
            'amount_paid' => $package->price,
            'job_chance_limit' => $package->job_chance_limit,
            'priority_level' => $package->priority_level,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->postJson("/api/jobs/{$job->id}/apply", [
            'cover_letter' => 'I would love to apply.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.approval_status', 'pending');
    }

    public function test_employer_cannot_update_application_status_before_admin_approval(): void
    {
        $employer = User::factory()->create(['role' => 'employer']);
        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);
        $job = Job::query()->create([
            'employer_id' => $employer->id,
            'title' => 'Cleaner',
            'description' => 'Office cleaning.',
            'job_type' => 'full_time',
            'status' => 'approved',
        ]);

        $application = \App\Models\JobApplication::query()->create([
            'job_id' => $job->id,
            'job_seeker_id' => $jobSeeker->id,
            'status' => 'submitted',
            'approval_status' => 'pending',
        ]);

        Sanctum::actingAs($employer);

        $this->patchJson("/api/employer/applications/{$application->id}/status", [
            'status' => 'shortlisted',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Application must be approved by admin first.');
    }

    public function test_job_seeker_must_subscribe_before_searching_jobs(): void
    {
        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);

        Sanctum::actingAs($jobSeeker);

        $this->getJson('/api/jobs')
            ->assertStatus(402)
            ->assertJsonPath('message', 'Subscribe to a package before searching for jobs.');
    }
}
