<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\EmployerProfile;
use App\Models\JobCategory;
use App\Models\JobSeekerProfile;
use App\Models\JobSeekerSubscription;
use App\Models\Language;
use App\Models\Religion;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_job_seeker_counts_match_user_account_statuses(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create(['role' => 'job_seeker', 'status' => 'pending']);
        User::factory()->count(2)->create(['role' => 'job_seeker', 'status' => 'approved']);
        User::factory()->create(['role' => 'employer', 'status' => 'pending']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('data.total_job_seekers', 5)
            ->assertJsonPath('data.job_seekers_pending', 3)
            ->assertJsonPath('data.job_seekers_approved', 2)
            ->assertJsonPath('data.employers_pending', 1);
    }

    public function test_admin_can_update_user_account_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $role = AdminRole::query()->create([
            'name' => 'Support Desk',
            'slug' => 'support-desk',
            'permissions' => ['access_admin', 'view_users'],
        ]);
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.test',
            'phone' => '+256701111111',
            'role' => 'job_seeker',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'Updated User',
            'email' => 'updated@example.test',
            'phone' => '0772123456',
            'role' => 'admin',
            'admin_role_id' => $role->id,
            'status' => 'approved',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated User')
            ->assertJsonPath('data.email', 'updated@example.test')
            ->assertJsonPath('data.phone', '+256772123456')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.admin_role_id', $role->id)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated User',
            'email' => 'updated@example.test',
            'phone' => '+256772123456',
            'role' => 'admin',
            'admin_role_id' => $role->id,
            'status' => 'approved',
        ]);
    }

    public function test_admin_cannot_demote_or_suspend_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'role' => 'job_seeker',
            'admin_role_id' => null,
            'status' => 'suspended',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
            'status' => 'approved',
        ]);
    }

    public function test_phone_is_required_and_must_be_valid_when_updating_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $user = User::factory()->create(['role' => 'job_seeker', 'status' => 'approved']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '',
            'role' => $user->role,
            'status' => $user->status,
            'admin_role_id' => null,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->patchJson("/api/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '12345',
            'role' => $user->role,
            'status' => $user->status,
            'admin_role_id' => null,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_admin_can_update_job_seeker_profile_with_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $user = User::factory()->create([
            'name' => 'Original Worker',
            'email' => 'worker@example.test',
            'phone' => '+256701111111',
            'role' => 'job_seeker',
            'status' => 'pending',
        ]);
        JobSeekerProfile::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Original Worker',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'Updated Worker',
            'email' => 'worker@example.test',
            'phone' => '0772123456',
            'role' => 'job_seeker',
            'status' => 'approved',
            'admin_role_id' => null,
            'profile' => [
                'full_name' => 'Updated Profile Name',
                'job_title' => 'Driver',
                'phone' => '0772000111',
                'education_level' => 'Secondary O Level',
                'district' => 'Kampala',
                'county' => 'Kampala Central Division',
                'subcounty' => 'Central',
                'parish' => 'Nakasero',
                'village' => 'Nakasero I',
                'experience_years' => 4,
            ],
        ])->assertOk()
            ->assertJsonPath('data.job_seeker_profile.full_name', 'Updated Profile Name')
            ->assertJsonPath('data.job_seeker_profile.phone', '+256772000111')
            ->assertJsonPath('data.job_seeker_profile.education_level', 'Secondary O Level')
            ->assertJsonPath('data.job_seeker_profile.status', 'approved');

        $this->assertDatabaseHas('job_seeker_profiles', [
            'user_id' => $user->id,
            'full_name' => 'Updated Profile Name',
            'job_title' => 'Driver',
            'phone' => '+256772000111',
            'education_level' => 'Secondary O Level',
            'district' => 'Kampala',
            'county' => 'Kampala Central Division',
            'subcounty' => 'Central',
            'parish' => 'Nakasero',
            'village' => 'Nakasero I',
            'status' => 'approved',
        ]);
    }

    public function test_admin_user_update_requires_education_level_for_job_seeker_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $user = User::factory()->create([
            'email' => 'worker-required-education@example.test',
            'phone' => '+256701111111',
            'role' => 'job_seeker',
            'status' => 'approved',
        ]);
        JobSeekerProfile::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Original Worker',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'Updated Worker',
            'email' => 'worker-required-education@example.test',
            'phone' => '0772123456',
            'role' => 'job_seeker',
            'status' => 'approved',
            'admin_role_id' => null,
            'profile' => [
                'full_name' => 'Updated Profile Name',
                'education_level' => '',
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('profile.education_level');
    }

    public function test_admin_user_update_rejects_invalid_education_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $user = User::factory()->create([
            'email' => 'worker-invalid-education@example.test',
            'phone' => '+256701111111',
            'role' => 'job_seeker',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '0772123456',
            'role' => 'job_seeker',
            'status' => 'approved',
            'admin_role_id' => null,
            'profile' => [
                'full_name' => $user->name,
                'education_level' => 'Some Other Level',
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('profile.education_level');
    }

    public function test_admin_can_update_employer_profile_with_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $user = User::factory()->create([
            'name' => 'Bright HR',
            'email' => 'bright@example.test',
            'phone' => '+256701111111',
            'role' => 'employer',
            'status' => 'pending',
        ]);
        EmployerProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Bright HR',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'Bright HR',
            'email' => 'bright@example.test',
            'phone' => '0772123456',
            'role' => 'employer',
            'status' => 'approved',
            'admin_role_id' => null,
            'profile' => [
                'employer_type' => 'company',
                'company_name' => 'Bright Works HR',
                'company_email' => 'hello@bright.test',
                'company_phone' => '0772000111',
                'company_location' => 'Kampala',
                'district' => 'Kampala',
                'county' => 'Kampala Central Division',
                'subcounty' => 'Central',
                'parish' => 'Nakasero',
                'village' => 'Nakasero I',
            ],
        ])->assertOk()
            ->assertJsonPath('data.employer_profile.company_name', 'Bright Works HR')
            ->assertJsonPath('data.employer_profile.company_phone', '+256772000111')
            ->assertJsonPath('data.employer_profile.status', 'approved');

        $this->assertDatabaseHas('employer_profiles', [
            'user_id' => $user->id,
            'company_name' => 'Bright Works HR',
            'company_phone' => '+256772000111',
            'company_location' => 'Kampala',
            'district' => 'Kampala',
            'county' => 'Kampala Central Division',
            'subcounty' => 'Central',
            'parish' => 'Nakasero',
            'village' => 'Nakasero I',
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_update_full_job_seeker_profile_and_files_with_user(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $user = User::factory()->create([
            'name' => 'Full Worker',
            'email' => 'full-worker@example.test',
            'phone' => '+256701111111',
            'role' => 'job_seeker',
            'status' => 'pending',
        ]);
        JobSeekerProfile::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Full Worker',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->post("/api/admin/users/{$user->id}", [
            '_method' => 'PATCH',
            'name' => 'Full Worker Updated',
            'email' => 'full-worker@example.test',
            'phone' => '0772123456',
            'role' => 'job_seeker',
            'status' => 'approved',
            'admin_role_id' => '',
            'profile' => [
                'full_name' => 'Full Worker Profile',
                'job_title' => 'Cleaner',
                'gender' => 'female',
                'date_of_birth' => now()->subYears(28)->toDateString(),
                'location' => 'Kampala',
                'phone' => '0772000111',
                'district' => 'Kampala',
                'county' => 'Kampala Central',
                'subcounty' => 'Central',
                'parish' => 'Nakasero',
                'village' => 'Nakasero I',
                'languages' => ['English', 'Luganda'],
                'religion' => 'Christian',
                'education_level' => 'Secondary O Level',
                'skills' => ['Cleaning', 'Cooking'],
                'experience_years' => 5,
                'bio' => 'Ready for verified opportunities.',
                'work_experience' => 'Five years of home support.',
                'preferred_job_categories' => ['Domestic work'],
                'is_available' => '1',
                'terms_accepted' => '1',
                'profile_photo' => UploadedFile::fake()->image('photo.jpg'),
                'cv_file' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.job_seeker_profile.full_name', 'Full Worker Profile')
            ->assertJsonPath('data.job_seeker_profile.languages.0', 'English');

        $profile = $user->fresh()->jobSeekerProfile()->firstOrFail();
        $this->assertSame(['English', 'Luganda'], $profile->languages);
        $this->assertSame(['Cleaning', 'Cooking'], $profile->skills);
        $this->assertSame(['Domestic work'], $profile->preferred_job_categories);
        $this->assertTrue($profile->is_available);
        $this->assertTrue($profile->terms_accepted);
        Storage::disk('public')->assertExists($profile->profile_photo);
        Storage::disk('public')->assertExists($profile->cv_file);
    }

    public function test_admin_can_update_full_employer_profile_and_files_with_user(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $user = User::factory()->create([
            'name' => 'Full Employer',
            'email' => 'full-employer@example.test',
            'phone' => '+256701111111',
            'role' => 'employer',
            'status' => 'pending',
        ]);
        EmployerProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Full Employer',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->post("/api/admin/users/{$user->id}", [
            '_method' => 'PATCH',
            'name' => 'Full Employer',
            'email' => 'full-employer@example.test',
            'phone' => '0772123456',
            'role' => 'employer',
            'status' => 'approved',
            'admin_role_id' => '',
            'profile' => [
                'employer_type' => 'company',
                'company_name' => 'Full Employer Ltd',
                'company_email' => 'jobs@full-employer.test',
                'company_phone' => '0772000111',
                'company_location' => 'Kampala',
                'district' => 'Kampala',
                'county' => 'Kampala Central',
                'subcounty' => 'Central',
                'parish' => 'Nakasero',
                'village' => 'Nakasero I',
                'company_registration_number' => 'FE-2026',
                'company_description' => 'Hiring support staff.',
                'preferred_worker_type' => 'Support staff',
                'preferred_job_categories' => ['Customer support'],
                'website' => 'https://full-employer.test',
                'company_logo' => UploadedFile::fake()->image('logo.jpg'),
                'business_document_file' => UploadedFile::fake()->create('business.pdf', 50, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.employer_profile.company_name', 'Full Employer Ltd')
            ->assertJsonPath('data.employer_profile.preferred_job_categories.0', 'Customer support');

        $profile = $user->fresh()->employerProfile()->firstOrFail();
        $this->assertSame('Hiring support staff.', $profile->company_description);
        $this->assertSame('Support staff', $profile->preferred_worker_type);
        $this->assertSame(['Customer support'], $profile->preferred_job_categories);
        Storage::disk('public')->assertExists($profile->company_logo);
        Storage::disk('public')->assertExists($profile->business_document_file);
    }

    public function test_admin_can_update_an_active_job_seeker_subscription_package(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $jobSeeker = User::factory()->create(['role' => 'job_seeker', 'status' => 'approved']);
        $starter = SubscriptionPackage::query()->create([
            'name' => 'Starter', 'price' => 10000, 'job_chance_limit' => 2, 'priority_level' => 1, 'is_active' => true,
        ]);
        $professional = SubscriptionPackage::query()->create([
            'name' => 'Professional', 'price' => 30000, 'job_chance_limit' => 6, 'priority_level' => 2, 'is_active' => true,
        ]);
        $subscription = JobSeekerSubscription::query()->create([
            'user_id' => $jobSeeker->id,
            'subscription_package_id' => $starter->id,
            'amount_paid' => $starter->price,
            'job_chance_limit' => $starter->job_chance_limit,
            'priority_level' => $starter->priority_level,
            'status' => 'active',
            'started_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/users/{$jobSeeker->id}/subscription", [
            'subscription_package_id' => $professional->id,
        ])->assertOk()
            ->assertJsonPath('data.subscription_package_id', $professional->id)
            ->assertJsonPath('data.package.name', 'Professional');

        $this->assertDatabaseHas('job_seeker_subscriptions', [
            'id' => $subscription->id,
            'subscription_package_id' => $professional->id,
            'job_chance_limit' => 6,
        ]);
    }

    public function test_admin_can_create_job_seeker_with_full_profile_information(): void
    {
        Storage::fake('public');
        Language::query()->create(['name' => 'English', 'is_active' => true]);
        Language::query()->create(['name' => 'Luganda', 'is_active' => true]);
        Religion::query()->create(['name' => 'Christian', 'is_active' => true]);
        JobCategory::query()->create(['name' => 'Domestic work', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);

        Sanctum::actingAs($admin);

        $this->post('/api/admin/users', [
            'name' => 'Jane Worker',
            'email' => 'jane.worker@example.test',
            'phone' => '0772123456',
            'role' => 'job_seeker',
            'status' => 'approved',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'profile' => [
                'full_name' => 'Jane Worker Profile',
                'job_title' => 'Housekeeper',
                'gender' => 'female',
                'date_of_birth' => now()->subYears(25)->toDateString(),
                'location' => 'Kampala',
                'phone' => '0772000111',
                'district' => 'Kampala',
                'county' => 'Kampala Central',
                'subcounty' => 'Central',
                'parish' => 'Nakasero',
                'village' => 'Nakasero I',
                'languages' => ['English', 'Luganda'],
                'religion' => 'Christian',
                'education_level' => 'Secondary O Level',
                'skills' => ['Cleaning', 'Cooking'],
                'experience_years' => 3,
                'bio' => 'Reliable domestic worker.',
                'work_experience' => 'Three years supporting families.',
                'preferred_job_categories' => ['Domestic work'],
                'is_available' => '1',
                'terms_accepted' => '1',
                'profile_photo' => UploadedFile::fake()->image('photo.jpg'),
                'id_document_front_file' => UploadedFile::fake()->image('front.jpg'),
                'id_document_back_file' => UploadedFile::fake()->image('back.jpg'),
                'id_document_file' => UploadedFile::fake()->create('id.pdf', 50, 'application/pdf'),
                'cv_file' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'),
                'lc1_letter_file' => UploadedFile::fake()->create('lc1.pdf', 50, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'job_seeker')
            ->assertJsonPath('data.status', 'approved');

        $user = User::query()->where('email', 'jane.worker@example.test')->firstOrFail();
        $profile = $user->jobSeekerProfile()->firstOrFail();

        $this->assertSame('+256772123456', $user->phone);
        $this->assertSame('Jane Worker Profile', $profile->full_name);
        $this->assertSame('+256772000111', $profile->phone);
        $this->assertSame(['English', 'Luganda'], $profile->languages);
        $this->assertSame(['Cleaning', 'Cooking'], $profile->skills);
        $this->assertSame(['Domestic work'], $profile->preferred_job_categories);
        $this->assertTrue($profile->is_available);
        $this->assertTrue($profile->terms_accepted);
        $this->assertNotNull($profile->profile_photo);
        $this->assertNotNull($profile->id_document_front_file);
        $this->assertNotNull($profile->id_document_back_file);
        Storage::disk('public')->assertExists($profile->profile_photo);
        Storage::disk('public')->assertExists($profile->cv_file);
    }

    public function test_admin_can_create_employer_with_full_profile_information(): void
    {
        Storage::fake('public');
        JobCategory::query()->create(['name' => 'Customer support', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);

        Sanctum::actingAs($admin);

        $this->post('/api/admin/users', [
            'name' => 'Acme Hiring',
            'email' => 'hr@acme.example.test',
            'phone' => '0772123456',
            'role' => 'employer',
            'status' => 'approved',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'profile' => [
                'employer_type' => 'company',
                'company_name' => 'Acme Services Ltd',
                'company_email' => 'jobs@acme.example.test',
                'company_phone' => '0772000111',
                'company_location' => 'Kampala',
                'district' => 'Kampala',
                'county' => 'Kampala Central',
                'subcounty' => 'Central',
                'parish' => 'Nakasero',
                'village' => 'Nakasero I',
                'company_registration_number' => 'ACME-2026',
                'company_description' => 'Hiring verified workers.',
                'preferred_worker_type' => 'Customer support agents',
                'preferred_job_categories' => ['Customer support'],
                'website' => 'https://acme.example.test',
                'company_logo' => UploadedFile::fake()->image('logo.jpg'),
                'business_document_file' => UploadedFile::fake()->create('business.pdf', 50, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'employer')
            ->assertJsonPath('data.status', 'approved');

        $user = User::query()->where('email', 'hr@acme.example.test')->firstOrFail();
        $profile = $user->employerProfile()->firstOrFail();

        $this->assertSame('+256772123456', $user->phone);
        $this->assertSame('Acme Services Ltd', $profile->company_name);
        $this->assertSame('+256772000111', $profile->company_phone);
        $this->assertSame(['Customer support'], $profile->preferred_job_categories);
        $this->assertSame('https://acme.example.test', $profile->website);
        $this->assertNotNull($profile->company_logo);
        $this->assertNotNull($profile->business_document_file);
        Storage::disk('public')->assertExists($profile->company_logo);
        Storage::disk('public')->assertExists($profile->business_document_file);
    }
}
