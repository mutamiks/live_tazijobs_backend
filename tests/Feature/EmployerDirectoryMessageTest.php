<?php

namespace Tests\Feature;

use App\Models\Employer;
use App\Models\EmployerProfile;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployerDirectoryMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_employer_can_be_created_without_an_email(): void
    {
        $this->postJson('/api/employers', [
            'company_name' => 'No Email Logistics',
            'category' => 'Logistics',
        ])
            ->assertCreated()
            ->assertJsonPath('employer.email', null);

        $this->assertDatabaseHas('employers', [
            'company_name' => 'No Email Logistics',
            'email' => null,
        ]);
    }

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
            'recipient_ids' => ['user-'.$approvedUser->id],
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

    public function test_admin_can_send_a_custom_sms_only_to_selected_employers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $selected = Employer::query()->create([
            'company_name' => 'Potential Logistics',
            'phone' => '+256700000001',
            'category' => 'Logistics',
            'status' => 'potential',
        ]);
        Employer::query()->create([
            'company_name' => 'Other Potential Logistics',
            'phone' => '+256700000002',
            'category' => 'Logistics',
            'status' => 'potential',
        ]);
        Sanctum::actingAs($admin);

        $this->mock(SmsService::class, function ($mock) {
            $mock->shouldReceive('normalizePhone')
                ->once()
                ->with('+256700000001')
                ->andReturn('256700000001');
            $mock->shouldReceive('send')
                ->once()
                ->with('256700000001', 'A custom message for potential employers.')
                ->andReturn('OK');
        });

        $this->postJson('/api/admin/employers-directory/message', [
            'recipient_ids' => [(string) $selected->id],
            'channel' => 'sms',
            'message' => 'A custom message for potential employers.',
        ])
            ->assertOk()
            ->assertJsonPath('data.sent', 1)
            ->assertJsonPath('data.failed', []);
    }

    public function test_admin_cannot_send_a_directory_message_without_selecting_recipients(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/employers-directory/message', [
            'channel' => 'sms',
            'message' => 'A custom message.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_ids');
    }
}
