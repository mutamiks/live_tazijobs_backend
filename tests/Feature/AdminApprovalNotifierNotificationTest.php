<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminApprovalNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApprovalNotifierNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_in_app_notifications_for_admin_users(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin One',
            'email' => 'admin@example.com',
            'phone' => '+256700000001',
            'role' => 'admin',
            'status' => 'approved',
            'password' => bcrypt('secret123'),
        ]);

        $service = new AdminApprovalNotifier();
        $sent = $service->notifyAdmins('New approval request', 'A job is awaiting approval.');

        $this->assertSame(1, $sent);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'title' => 'New approval request',
            'message' => 'A job is awaiting approval.',
            'type' => 'admin_approval_pending',
            'is_read' => 0,
        ]);
    }
}
