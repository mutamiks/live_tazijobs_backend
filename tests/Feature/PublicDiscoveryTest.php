<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\JobCategory;
use App\Models\JobSeekerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_can_search_public_previews_without_private_details(): void
    {
        $employer = User::factory()->create(['role' => 'employer', 'phone' => '+256701111111']);
        $worker = User::factory()->create(['role' => 'job_seeker', 'phone' => '+256702222222']);

        Job::query()->create([
            'employer_id' => $employer->id,
            'title' => 'Senior Carpenter',
            'description' => 'Private job description',
            'district' => 'Kampala',
            'job_type' => 'contract',
            'deadline' => now()->addWeek(),
            'status' => 'approved',
        ]);

        JobSeekerProfile::query()->create([
            'user_id' => $worker->id,
            'full_name' => 'Amina Namara',
            'job_title' => 'Carpenter',
            'phone' => '+256702222222',
            'district' => 'Kampala',
            'skills' => ['Furniture making'],
            'status' => 'approved',
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/public/discovery?search=Carpenter')
            ->assertOk()
            ->assertJsonPath('data.jobs.0.title', 'Senior Carpenter')
            ->assertJsonPath('data.job_seekers.0.display_name', 'A. N.');

        $body = $response->getContent();
        $this->assertStringNotContainsString('Amina Namara', $body);
        $this->assertStringNotContainsString('+256702222222', $body);
        $this->assertStringNotContainsString('Private job description', $body);
    }

    public function test_public_discovery_hides_jobs_from_suspended_employers(): void
    {
        $activeEmployer = User::factory()->create(['role' => 'employer', 'status' => 'approved']);
        $suspendedEmployer = User::factory()->create(['role' => 'employer', 'status' => 'suspended']);

        Job::query()->create([
            'employer_id' => $activeEmployer->id,
            'title' => 'Visible Carpenter',
            'description' => 'Open role.',
            'job_type' => 'contract',
            'deadline' => now()->addWeek(),
            'status' => 'approved',
        ]);

        Job::query()->create([
            'employer_id' => $suspendedEmployer->id,
            'title' => 'Hidden Carpenter',
            'description' => 'Suspended employer role.',
            'job_type' => 'contract',
            'deadline' => now()->addWeek(),
            'status' => 'approved',
        ]);

        $this->getJson('/api/public/discovery?search=Carpenter')
            ->assertOk()
            ->assertJsonCount(1, 'data.jobs')
            ->assertJsonPath('data.jobs.0.title', 'Visible Carpenter');
    }

    public function test_visitors_can_filter_public_jobs_by_separate_fields(): void
    {
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
            'deadline' => now()->addWeek(),
            'status' => 'approved',
        ]);

        Job::query()->create([
            'employer_id' => $employer->id,
            'job_category_id' => $adminCategory->id,
            'title' => 'Office Driver',
            'description' => 'Office support.',
            'district' => 'Wakiso',
            'location' => 'Wakiso',
            'job_type' => 'full_time',
            'salary_min' => 250000,
            'salary_max' => 400000,
            'deadline' => now()->addWeek(),
            'status' => 'approved',
        ]);

        $this->getJson('/api/public/discovery?title=Driver&job_category_id='.$driverCategory->id.'&district=Kampala&salary_min=500000&salary_max=1200000')
            ->assertOk()
            ->assertJsonCount(1, 'data.jobs')
            ->assertJsonPath('data.jobs.0.title', 'Truck Driver');
    }

    public function test_public_discovery_hides_suspended_job_seekers(): void
    {
        $activeWorker = User::factory()->create(['role' => 'job_seeker', 'status' => 'approved']);
        $suspendedWorker = User::factory()->create(['role' => 'job_seeker', 'status' => 'suspended']);

        JobSeekerProfile::query()->create([
            'user_id' => $activeWorker->id,
            'full_name' => 'Visible Worker',
            'job_title' => 'Driver',
            'district' => 'Kampala',
            'status' => 'approved',
            'is_available' => true,
        ]);

        JobSeekerProfile::query()->create([
            'user_id' => $suspendedWorker->id,
            'full_name' => 'Hidden Worker',
            'job_title' => 'Driver',
            'district' => 'Kampala',
            'status' => 'approved',
            'is_available' => true,
        ]);

        $this->getJson('/api/public/discovery?search=Driver')
            ->assertOk()
            ->assertJsonCount(1, 'data.job_seekers')
            ->assertJsonPath('data.job_seekers.0.display_name', 'V. W.');
    }

    public function test_visitors_can_filter_public_workers_by_separate_fields(): void
    {
        $matchingWorker = User::factory()->create(['role' => 'job_seeker', 'status' => 'approved']);
        $otherWorker = User::factory()->create(['role' => 'job_seeker', 'status' => 'approved']);

        JobSeekerProfile::query()->create([
            'user_id' => $matchingWorker->id,
            'full_name' => 'Sarah Worker',
            'job_title' => 'Housekeeper',
            'district' => 'Kampala',
            'skills' => ['Cooking', 'Cleaning'],
            'preferred_job_categories' => ['Domestic work'],
            'experience_years' => 4,
            'status' => 'approved',
            'is_available' => true,
        ]);

        JobSeekerProfile::query()->create([
            'user_id' => $otherWorker->id,
            'full_name' => 'Other Worker',
            'job_title' => 'Driver',
            'district' => 'Wakiso',
            'skills' => ['Driving'],
            'preferred_job_categories' => ['Transport'],
            'experience_years' => 1,
            'status' => 'approved',
            'is_available' => true,
        ]);

        $this->getJson('/api/public/discovery?worker_title=Housekeeper&worker_category=Domestic&worker_district=Kampala&worker_skill=Cooking&experience_years=3')
            ->assertOk()
            ->assertJsonCount(1, 'data.job_seekers')
            ->assertJsonPath('data.job_seekers.0.display_name', 'S. W.');
    }

    public function test_visitor_can_book_worker_for_admin_follow_up(): void
    {
        $worker = User::factory()->create(['role' => 'job_seeker', 'status' => 'approved']);
        $profile = JobSeekerProfile::query()->create([
            'user_id' => $worker->id,
            'full_name' => 'Public Worker',
            'job_title' => 'Housekeeper',
            'district' => 'Kampala',
            'status' => 'approved',
            'is_available' => true,
        ]);

        $this->postJson('/api/public/worker-contacts', [
            'job_seeker_profile_id' => $profile->id,
            'contact_name' => 'Sarah Mukasa',
            'business_name' => 'Mukasa Homes',
            'contact_phone' => '0772123456',
        ])
            ->assertCreated()
            ->assertJsonPath('data.contact_name', 'Sarah Mukasa')
            ->assertJsonPath('data.business_name', 'Mukasa Homes')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('worker_orders', [
            'job_seeker_profile_id' => $profile->id,
            'employer_id' => null,
            'contact_name' => 'Sarah Mukasa',
            'business_name' => 'Mukasa Homes',
            'contact_phone' => '0772123456',
            'status' => 'pending',
        ]);
    }
}
