<?php

namespace Tests\Feature;

use App\Models\JobSeekerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminFileAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_job_seeker_profile_files_through_api(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-photos/photo.jpg', 'photo-bytes');

        $admin = User::factory()->create(['role' => 'admin']);
        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);
        $profile = JobSeekerProfile::query()->create([
            'user_id' => $jobSeeker->id,
            'full_name' => 'Amina Namara',
            'profile_photo' => 'profile-photos/photo.jpg',
        ]);

        Sanctum::actingAs($admin);

        $this->get("/api/admin/files/job-seeker-profile/{$profile->id}/profile_photo")
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_admin_file_endpoint_rejects_unknown_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $jobSeeker = User::factory()->create(['role' => 'job_seeker']);
        $profile = JobSeekerProfile::query()->create([
            'user_id' => $jobSeeker->id,
            'full_name' => 'Amina Namara',
        ]);

        Sanctum::actingAs($admin);

        $this->get("/api/admin/files/job-seeker-profile/{$profile->id}/password")
            ->assertNotFound();
    }
}
