<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTicketManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_ticket(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/tickets', [
            'client_full_name' => 'Jane Applicant',
            'phones' => ['0772123456', '0755123456'],
            'client_type' => 'job_seeker',
            'ticket_type' => 'Payment issue',
            'comment' => 'Client says invoice payment is not reflecting.',
        ])->assertCreated()
            ->assertJsonPath('data.client_full_name', 'Jane Applicant')
            ->assertJsonPath('data.client_type', 'job_seeker')
            ->assertJsonPath('data.creator.name', $admin->name);

        $this->assertDatabaseHas('tickets', [
            'client_full_name' => 'Jane Applicant',
            'client_type' => 'job_seeker',
            'ticket_type' => 'Payment issue',
            'created_by' => $admin->id,
        ]);
    }

    public function test_admin_can_search_tickets_by_name_phone_and_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        Ticket::query()->create([
            'created_by' => $admin->id,
            'client_full_name' => 'Jane Applicant',
            'phones' => ['0772123456'],
            'client_type' => 'job_seeker',
            'ticket_type' => 'Payment issue',
            'comment' => 'Invoice payment is not reflecting.',
        ]);
        Ticket::query()->create([
            'created_by' => $admin->id,
            'client_full_name' => 'Bright Works HR',
            'phones' => ['0788999000'],
            'client_type' => 'employer',
            'ticket_type' => 'Job issue',
            'comment' => 'Needs help editing a job.',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/tickets?search=0772123456')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.client_full_name', 'Jane Applicant');

        $this->getJson('/api/admin/tickets?client_type=employer&search=Job')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.client_full_name', 'Bright Works HR');
    }
}
