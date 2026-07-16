<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\EmployerProfile;
use App\Models\JobSeekerProfile;
use App\Models\JobSeekerSubscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserUpdateTest extends TestCase
{
    use RefreshDatabase;

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
            'status' => 'approved',
        ]);
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
            'status' => 'approved',
        ]);
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
}
