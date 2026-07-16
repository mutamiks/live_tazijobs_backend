<?php

namespace Tests\Feature;

use App\Models\Job;
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
}
