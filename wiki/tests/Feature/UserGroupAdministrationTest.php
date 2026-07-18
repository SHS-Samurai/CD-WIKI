<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGroupAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_group_and_user_membership(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.groups.store'), [
            'name' => 'Redaktion', 'description' => 'Bearbeitet Inhalte',
        ])->assertRedirect();
        $group = Group::query()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Redakteur',
            'email' => 'redaktion@example.test',
            'password' => 'SicheresPasswort123',
            'password_confirmation' => 'SicheresPasswort123',
            'group_ids' => [$group->id],
        ])->assertRedirect();

        $user = User::query()->where('email', 'redaktion@example.test')->firstOrFail();
        $this->assertTrue($user->groups->contains($group));
        $this->assertDatabaseHas('audit_logs', ['action' => 'group.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);
    }

    public function test_last_administrator_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
        ])->assertSessionHasErrors('is_admin');
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_normal_user_cannot_open_administration(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }
}
