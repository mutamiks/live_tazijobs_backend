<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\JobSeekerSubscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_seekers_can_filter_approved_jobs(): void
    {
        $employer = User::factory()->create(['role' => 'employer']);
        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);
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

        Job::query()->create([
            'employer_id' => $employer->id,
            'title' => 'Laravel Developer',
            'description' => 'Build APIs.',
            'location' => 'Kampala',
            'job_type' => 'remote',
            'salary_min' => 1200,
            'salary_max' => 2500,
            'deadline' => now()->addDays(10)->toDateString(),
            'status' => 'approved',
        ]);

        Job::query()->create([
            'employer_id' => $employer->id,
            'title' => 'Office Assistant',
            'description' => 'Front desk work.',
            'location' => 'Entebbe',
            'job_type' => 'full_time',
            'salary_min' => 300,
            'salary_max' => 600,
            'deadline' => now()->addDays(2)->toDateString(),
            'status' => 'approved',
        ]);

        Sanctum::actingAs($jobSeeker);

        $this->getJson('/api/jobs?search=Laravel&location=Kampala&job_type=remote&salary_min=1000&salary_max=2600&deadline_from='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Laravel Developer');
    }
}
