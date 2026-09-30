<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApplicationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_job_applications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employer = User::factory()->create(['role' => 'employer']);
        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);
        $job = Job::query()->create([
            'employer_id' => $employer->id,
            'title' => 'Accounts Assistant',
            'description' => 'Support finance operations.',
            'job_type' => 'full_time',
            'status' => 'approved',
        ]);

        JobApplication::query()->create([
            'job_id' => $job->id,
            'job_seeker_id' => $jobSeeker->id,
            'cover_letter' => 'I can support accounts.',
            'status' => 'submitted',
            'approval_status' => 'approved',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/applications')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.job.title', 'Accounts Assistant')
            ->assertJsonPath('data.data.0.job_seeker.name', $jobSeeker->name)
            ->assertJsonPath('data.total', 1);
    }

    public function test_admin_application_list_is_capped_at_fifteen_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employer = User::factory()->create(['role' => 'employer']);
        $job = Job::query()->create([
            'employer_id' => $employer->id,
            'title' => 'Accounts Assistant',
            'description' => 'Support finance operations.',
            'job_type' => 'full_time',
            'status' => 'approved',
        ]);

        foreach (range(1, 16) as $index) {
            $jobSeeker = User::factory()->create(['role' => 'job_seeker']);
            JobApplication::query()->create([
                'job_id' => $job->id,
                'job_seeker_id' => $jobSeeker->id,
                'cover_letter' => "Application {$index}",
                'status' => 'submitted',
                'approval_status' => 'pending',
            ]);
        }

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/applications?per_page=100')
            ->assertOk()
            ->assertJsonCount(15, 'data.data')
            ->assertJsonPath('data.per_page', 15)
            ->assertJsonPath('data.total', 16);
    }
}
