<?php

namespace Tests\Feature;

use App\Models\EmployerProfile;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminJobSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_approved_jobs_across_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employer = User::factory()->create(['role' => 'employer', 'name' => 'Bright Works HR']);
        $category = JobCategory::query()->create(['name' => 'Customer support']);
        EmployerProfile::query()->create([
            'user_id' => $employer->id,
            'company_name' => 'Bright Works Ltd',
            'status' => 'approved',
        ]);

        Job::query()->create([
            'employer_id' => $employer->id,
            'job_category_id' => $category->id,
            'title' => 'Support Agent',
            'description' => 'Help customers.',
            'location' => 'Kampala',
            'job_type' => 'full_time',
            'status' => 'approved',
        ]);

        Job::query()->create([
            'employer_id' => $employer->id,
            'title' => 'Warehouse Clerk',
            'description' => 'Manage stock.',
            'location' => 'Jinja',
            'job_type' => 'contract',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/jobs?search=Customer%20support')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Support Agent');

        $this->getJson('/api/admin/jobs?search=Warehouse')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');
    }

    public function test_admin_can_unlist_and_relist_approved_job(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);
        $job = Job::query()->create([
            'employer_id' => User::factory()->create(['role' => 'employer', 'status' => 'approved'])->id,
            'title' => 'Cleaner',
            'description' => 'Office cleaning.',
            'job_type' => 'full_time',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/jobs/{$job->id}/toggle-listing")
            ->assertOk()
            ->assertJsonPath('data.is_listed', false);

        Sanctum::actingAs($jobSeeker);

        $this->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/jobs')
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'Cleaner')
            ->assertJsonPath('data.data.0.is_listed', false);

        $this->patchJson("/api/admin/jobs/{$job->id}/toggle-listing")
            ->assertOk()
            ->assertJsonPath('data.is_listed', true);
    }
}
