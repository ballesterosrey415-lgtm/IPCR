<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_pending_registration(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $pending = User::factory()->create([
            'role' => UserRole::Employee,
            'account_status' => AccountStatus::Pending,
            'supervisor_id' => null,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.users.approve', $pending));

        $response->assertRedirect(route('admin.users.pending'));
        $pending->refresh();

        $this->assertSame(AccountStatus::Active, $pending->account_status);
        $this->assertNull($pending->supervisor_id);
    }

    public function test_admin_can_approve_a_registered_user_after_assigning_supervisor_role(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $pendingSupervisor = User::factory()->create([
            'role' => UserRole::Employee,
            'account_status' => AccountStatus::Pending,
        ]);

        $this->actingAs($admin)->patch(route('admin.users.update', $pendingSupervisor), [
            'name' => $pendingSupervisor->name,
            'email' => $pendingSupervisor->email,
            'role' => UserRole::Supervisor->value,
            'account_status' => AccountStatus::Pending->value,
            'supervisor_id' => null,
        ])->assertRedirect(route('admin.users.index'));

        $this->patch(route('admin.users.approve', $pendingSupervisor))
            ->assertRedirect(route('admin.users.pending'));

        $pendingSupervisor->refresh();
        $this->assertSame(UserRole::Supervisor, $pendingSupervisor->role);
        $this->assertSame(AccountStatus::Active, $pendingSupervisor->account_status);

        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $this->get(route('admin.users.edit', $employee))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Edit')
                ->has('supervisors', 1)
                ->where('supervisors.0.id', $pendingSupervisor->id)
            );
    }

    public function test_admin_can_reject_pending_registration(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $pending = User::factory()->create([
            'role' => UserRole::Employee,
            'account_status' => AccountStatus::Pending,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.users.reject', $pending));

        $response->assertRedirect(route('admin.users.pending'));
        $this->assertDatabaseMissing('users', ['id' => $pending->id]);
    }
}
