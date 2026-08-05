<?php

namespace Tests\Feature;

use App\Models\EmployerProfile;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\JobSeekerProfile;
use App\Models\User;
use App\Services\SmsService;
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

    public function test_admin_can_filter_approved_jobs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employer = User::factory()->create(['role' => 'employer', 'status' => 'approved']);
        $driverCategory = JobCategory::query()->create(['name' => 'Driving']);
        $adminCategory = JobCategory::query()->create(['name' => 'Administration']);

        Job::query()->create([
            'employer_id' => $employer->id,
            'job_category_id' => $driverCategory->id,
            'title' => 'Truck Driver',
            'description' => 'Drive deliveries.',
            'district' => 'Kampala',
            'location' => 'Kampala',
            'job_type' => 'full_time',
            'salary_min' => 700000,
            'salary_max' => 1000000,
            'deadline' => now()->addDays(10)->toDateString(),
            'status' => 'approved',
            'is_listed' => true,
        ]);

        Job::query()->create([
            'employer_id' => $employer->id,
            'job_category_id' => $adminCategory->id,
            'title' => 'Office Assistant',
            'description' => 'Support admin work.',
            'district' => 'Wakiso',
            'location' => 'Wakiso',
            'job_type' => 'contract',
            'salary_min' => 250000,
            'salary_max' => 400000,
            'deadline' => now()->addDays(2)->toDateString(),
            'status' => 'approved',
            'is_listed' => false,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/jobs?job_category_id='.$driverCategory->id.'&district=Kampala&job_type=full_time&listing=listed&salary_min=500000&salary_max=1200000&deadline_from='.now()->addDays(5)->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Truck Driver');
    }

    public function test_admin_can_upload_job_with_allowances_prefer_not_to_say(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employer = User::factory()->create(['role' => 'employer', 'status' => 'approved']);
        $category = JobCategory::query()->create(['name' => 'Customer support']);
        EmployerProfile::query()->create([
            'user_id' => $employer->id,
            'company_name' => 'Bright Works Ltd',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/jobs', [
            'employer_id' => $employer->id,
            'job_category_id' => $category->id,
            'title' => 'Support Agent',
            'description' => 'Help customers.',
            'job_type' => 'full_time',
            'allowances' => [
                'prefer_not_to_say' => true,
                'items' => [],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.allowances.prefer_not_to_say', true)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_admin_job_upload_notifies_approved_job_seekers_in_selected_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employer = User::factory()->create(['role' => 'employer', 'status' => 'approved']);
        $matchingSeeker = User::factory()->create(['role' => 'job_seeker', 'status' => 'approved', 'phone' => '+256701111111']);
        $otherSeeker = User::factory()->create(['role' => 'job_seeker', 'status' => 'approved', 'phone' => '+256702222222']);
        $pendingSeeker = User::factory()->create(['role' => 'job_seeker', 'status' => 'pending', 'phone' => '+256703333333']);
        $category = JobCategory::query()->create(['name' => 'Customer support']);

        EmployerProfile::query()->create([
            'user_id' => $employer->id,
            'company_name' => 'Bright Works Ltd',
            'status' => 'approved',
        ]);
        JobSeekerProfile::query()->create([
            'user_id' => $matchingSeeker->id,
            'full_name' => 'Matching Seeker',
            'preferred_job_categories' => ['Customer support'],
            'status' => 'approved',
        ]);
        JobSeekerProfile::query()->create([
            'user_id' => $otherSeeker->id,
            'full_name' => 'Other Seeker',
            'preferred_job_categories' => ['Driving'],
            'status' => 'approved',
        ]);
        JobSeekerProfile::query()->create([
            'user_id' => $pendingSeeker->id,
            'full_name' => 'Pending Seeker',
            'preferred_job_categories' => ['Customer support'],
            'status' => 'pending',
        ]);

        $this->mock(SmsService::class)
            ->shouldReceive('send')
            ->once()
            ->with('+256701111111', 'A new job has been posted. Log in and go to Alerts to view the details.')
            ->andReturn('TEST');

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/jobs', [
            'employer_id' => $employer->id,
            'job_category_id' => $category->id,
            'title' => 'Support Agent',
            'description' => 'Help customers.',
            'district' => 'Kampala',
            'job_type' => 'full_time',
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $matchingSeeker->id,
            'type' => 'new_category_job',
            'title' => 'New job in your category',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $otherSeeker->id,
            'type' => 'new_category_job',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $pendingSeeker->id,
            'type' => 'new_category_job',
        ]);
    }

    public function test_admin_job_approval_notifies_approved_job_seekers_in_selected_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employer = User::factory()->create(['role' => 'employer', 'status' => 'approved']);
        $jobSeeker = User::factory()->create(['role' => 'job_seeker', 'status' => 'approved', 'phone' => '+256701111111']);
        $category = JobCategory::query()->create(['name' => 'Domestic work']);
        EmployerProfile::query()->create([
            'user_id' => $employer->id,
            'company_name' => 'Home Care Ltd',
            'status' => 'approved',
        ]);
        JobSeekerProfile::query()->create([
            'user_id' => $jobSeeker->id,
            'full_name' => 'Approved Seeker',
            'preferred_job_categories' => ['Domestic work'],
            'status' => 'approved',
        ]);
        $job = Job::query()->create([
            'employer_id' => $employer->id,
            'job_category_id' => $category->id,
            'title' => 'Housekeeper',
            'description' => 'Keep the home clean.',
            'job_type' => 'full_time',
            'status' => 'pending',
        ]);

        $this->mock(SmsService::class)
            ->shouldReceive('send')
            ->once()
            ->with('+256701111111', 'A new job has been posted. Log in and go to Alerts to view the details.')
            ->andReturn('TEST');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/jobs/{$job->id}/decision", [
            'status' => 'approved',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $jobSeeker->id,
            'type' => 'new_category_job',
            'title' => 'New job in your category',
        ]);
    }

    public function test_admin_can_update_job_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employer = User::factory()->create(['role' => 'employer', 'status' => 'approved']);
        $category = JobCategory::query()->create(['name' => 'Domestic work']);
        $newCategory = JobCategory::query()->create(['name' => 'Customer support']);
        EmployerProfile::query()->create([
            'user_id' => $employer->id,
            'company_name' => 'Bright Works Ltd',
            'status' => 'approved',
        ]);
        $job = Job::query()->create([
            'employer_id' => $employer->id,
            'job_category_id' => $category->id,
            'title' => 'Cleaner',
            'description' => 'Clean offices.',
            'job_type' => 'full_time',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/jobs/{$job->id}", [
            'employer_id' => $employer->id,
            'job_category_id' => $newCategory->id,
            'title' => 'Senior Cleaner',
            'positions' => 3,
            'description' => 'Clean offices and supervise a team.',
            'requirements' => 'Two years of cleaning experience.',
            'responsibilities' => 'Team supervision and office cleaning.',
            'district' => 'Kampala',
            'county' => 'Kampala Central Division',
            'subcounty' => 'Central',
            'parish' => 'Nakasero',
            'village' => 'Nakasero I',
            'job_type' => 'contract',
            'salary_min' => 300000,
            'salary_max' => 450000,
            'allowances' => [
                'prefer_not_to_say' => false,
                'items' => [
                    ['type' => 'transport', 'amount' => 50000],
                ],
            ],
            'deadline' => now()->addWeek()->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Senior Cleaner')
            ->assertJsonPath('data.category.name', 'Customer support')
            ->assertJsonPath('data.positions', 3)
            ->assertJsonPath('data.allowances.items.0.type', 'transport');
    }
}
