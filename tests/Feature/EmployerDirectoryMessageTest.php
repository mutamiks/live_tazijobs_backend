<?php

namespace Tests\Feature;

use App\Models\Employer;
use App\Models\EmployerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployerDirectoryMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_employer_directory_with_working_group_derived_from_approved_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        User::factory()->create([
            'role' => 'employer',
            'status' => 'approved',
            'email' => 'approved@business.com',
        ]);

        Employer::query()->create([
            'company_name' => 'Potential Movers',
            'contact_person' => 'Jane',
            'email' => 'lead@potential.com',
            'phone' => '256700000001',
            'category' => 'Logistics',
            'status' => 'pending',
        ]);

        Employer::query()->create([
            'company_name' => 'Future Supply',
            'contact_person' => 'Mark',
            'email' => 'lead@future.com',
            'phone' => '256700000002',
            'category' => 'Retail',
            'status' => 'potential',
        ]);

        $approvedUser = User::query()->where('email', 'approved@business.com')->first();
        EmployerProfile::query()->create([
            'user_id' => $approvedUser->id,
            'company_name' => 'Approved Traders',
            'company_email' => 'approved@business.com',
            'company_phone' => '256700000003',
            'company_location' => 'Kampala',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/employers-directory')
            ->assertOk()
            ->assertJsonPath('data.summary.working', 1)
            ->assertJsonPath('data.summary.pending', 1)
            ->assertJsonPath('data.summary.potential', 1);
    }

    public function test_admin_can_send_bulk_email_to_working_group(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $approvedUser = User::factory()->create([
            'role' => 'employer',
            'status' => 'approved',
            'email' => 'working-partner@example.com',
            'name' => 'Working Partner',
        ]);

        EmployerProfile::query()->create([
            'user_id' => $approvedUser->id,
            'company_name' => 'Working Partner Ltd',
            'company_email' => 'working-partner@example.com',
            'company_phone' => '256700000004',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/employers-directory/message', [
            'status' => 'working',
            'channel' => 'email',
            'subject' => 'Thank you for partnering with us',
            'message' => 'Thank you for always choosing to partner with us.',
        ])
            ->assertOk()
            ->assertJsonPath('data.sent', 1);

        Mail::assertSent(\App\Mail\GenericMail::class, function ($mail) {
            return str_contains($mail->subject, 'Thank you for partnering with us');
        });
    }
}
