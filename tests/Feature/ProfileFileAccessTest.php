<?php

namespace Tests\Feature;

use App\Models\EmployerProfile;
use App\Models\JobSeekerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileFileAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_seeker_can_view_profile_photo_through_api(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-photos/photo.jpg', 'photo-bytes');

        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);
        JobSeekerProfile::query()->create([
            'user_id' => $jobSeeker->id,
            'full_name' => 'Amina Namara',
            'profile_photo' => 'profile-photos/photo.jpg',
        ]);

        Sanctum::actingAs($jobSeeker);

        $this->get('/api/profile-file/profile_photo')
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertHeader('cache-control', 'max-age=3600, private');
    }

    public function test_employer_can_view_company_logo_through_api(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company-logos/logo.jpg', 'logo-bytes');

        $employer = User::factory()->create(['role' => 'employer']);
        EmployerProfile::query()->create([
            'user_id' => $employer->id,
            'company_name' => 'Tazi Works',
            'company_logo' => 'company-logos/logo.jpg',
        ]);

        Sanctum::actingAs($employer);

        $this->get('/api/profile-file/company_logo')
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertHeader('cache-control', 'max-age=3600, private');
    }

    public function test_profile_file_endpoint_rejects_fields_for_the_wrong_account_type(): void
    {
        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);
        JobSeekerProfile::query()->create([
            'user_id' => $jobSeeker->id,
            'full_name' => 'Amina Namara',
        ]);

        Sanctum::actingAs($jobSeeker);

        $this->get('/api/profile-file/company_logo')->assertNotFound();
    }
}
