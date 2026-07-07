<?php

namespace Tests\Feature;

use App\Models\JobSeekerProfile;
use App\Models\Language;
use App\Models\Notification;
use App\Models\Religion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_rejection_stores_reason_history_and_notification(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $jobSeeker = User::factory()->create(['role' => 'job_seeker', 'status' => 'pending']);
        $profile = JobSeekerProfile::query()->create([
            'user_id' => $jobSeeker->id,
            'full_name' => 'Jane Applicant',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/job-seeker-profiles/{$profile->id}/decision", [
            'status' => 'rejected',
            'rejection_reason' => 'CV is missing.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'CV is missing.');

        $this->assertDatabaseHas('approval_histories', [
            'approvable_type' => JobSeekerProfile::class,
            'approvable_id' => $profile->id,
            'admin_id' => $admin->id,
            'from_status' => 'pending',
            'to_status' => 'rejected',
            'rejection_reason' => 'CV is missing.',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $jobSeeker->id,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $jobSeeker->id,
            'type' => 'job_seeker_profile',
        ]);
    }

    public function test_rejected_profile_can_be_resubmitted_as_pending(): void
    {
        $jobSeeker = User::factory()->create(['role' => 'job_seeker', 'status' => 'rejected']);
        JobSeekerProfile::query()->create([
            'user_id' => $jobSeeker->id,
            'full_name' => 'Jane Applicant',
            'status' => 'rejected',
            'rejection_reason' => 'Add skills.',
        ]);

        Language::query()->create(['name' => 'English']);
        Religion::query()->create(['name' => 'Christian']);

        Sanctum::actingAs($jobSeeker);

        $this->post('/api/job-seeker/profile', [
            'full_name' => 'Jane Applicant',
            'district' => 'Kampala',
            'county' => 'Kampala Central',
            'subcounty' => 'Central',
            'parish' => 'Nakasero',
            'village' => 'Nakasero I',
            'languages' => ['English'],
            'religion' => 'Christian',
            'skills' => ['Laravel', 'React'],
            'experience_years' => 2,
            'terms_accepted' => true,
            'id_document_front_file' => UploadedFile::fake()->image('id-front.jpg'),
            'id_document_back_file' => UploadedFile::fake()->image('id-back.jpg'),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.rejection_reason', null);
    }
}
