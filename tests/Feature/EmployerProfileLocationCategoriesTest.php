<?php

namespace Tests\Feature;

use App\Models\JobCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployerProfileLocationCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_employer_profile_accepts_full_location_and_preferred_categories(): void
    {
        $employer = User::factory()->create(['role' => 'employer']);
        JobCategory::query()->create(['name' => 'Domestic work']);
        JobCategory::query()->create(['name' => 'Customer support']);

        Sanctum::actingAs($employer);

        $this->postJson('/api/employer/profile', [
            'company_name' => 'Bright Works',
            'company_email' => 'hr@bright.test',
            'company_phone' => '+256700000123',
            'company_location' => 'Kampala, Kampala Central Division',
            'district' => 'Kampala',
            'county' => 'Kampala Central Division',
            'subcounty' => 'Kampala Central',
            'parish' => 'Nakasero I',
            'village' => 'NAKASERO',
            'preferred_worker_type' => 'Domestic work, Customer support',
            'preferred_job_categories' => ['Domestic work', 'Customer support'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.district', 'Kampala')
            ->assertJsonPath('data.county', 'Kampala Central Division')
            ->assertJsonPath('data.subcounty', 'Kampala Central')
            ->assertJsonPath('data.parish', 'Nakasero I')
            ->assertJsonPath('data.village', 'NAKASERO')
            ->assertJsonPath('data.preferred_job_categories.0', 'Domestic work')
            ->assertJsonPath('data.preferred_job_categories.1', 'Customer support');
    }
}
