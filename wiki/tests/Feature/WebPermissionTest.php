<?php

namespace Tests\Feature;

use App\Enums\WebPermissionSubject;
use App\Enums\WebVisibility;
use App\Models\Group;
use App\Models\User;
use App\Models\Web;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_web_can_be_read_without_login(): void
    {
        $web = $this->web(WebVisibility::Public);

        $this->assertTrue($web->hasRight(null, 'view'));
        $this->assertFalse($web->hasRight(null, 'create'));
    }

    public function test_rights_can_be_assigned_to_authenticated_users_and_groups(): void
    {
        $web = $this->web(WebVisibility::Private);
        $user = User::factory()->create();
        $group = Group::query()->create(['name' => 'Redaktion']);
        $user->groups()->attach($group);

        $web->permissions()->create([
            'subject_type' => WebPermissionSubject::Authenticated,
            'can_view' => true,
        ]);
        $web->permissions()->create([
            'subject_type' => WebPermissionSubject::Group,
            'group_id' => $group->id,
            'can_create' => true,
            'can_edit' => true,
        ]);

        $this->assertTrue($web->hasRight($user, 'view'));
        $this->assertTrue($web->hasRight($user, 'create'));
        $this->assertTrue($web->hasRight($user, 'edit'));
        $this->assertFalse($web->hasRight($user, 'delete'));
    }

    public function test_administrator_has_all_rights_except_nobody_else_can_open_admin_web(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $adminWeb = Web::query()->where('is_admin_web', true)->firstOrFail();

        $this->assertTrue($adminWeb->hasRight($admin, 'manage'));
        $this->assertFalse($adminWeb->hasRight($user, 'view'));
        $this->assertFalse($adminWeb->hasRight(null, 'view'));
    }

    public function test_admin_can_open_web_and_permission_management(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $web = $this->web(WebVisibility::Private);

        $this->actingAs($admin)->get(route('admin.webs.index'))
            ->assertOk()
            ->assertSee('Web-Verwaltung');
        $this->actingAs($admin)->get(route('admin.webs.permissions.index', $web))
            ->assertOk()
            ->assertSee('Web-Rechte');
    }

    public function test_admin_can_assign_a_web_right_to_one_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();
        $web = $this->web(WebVisibility::Private);

        $this->actingAs($admin)->post(route('admin.webs.permissions.store', $web), [
            'subject_type' => WebPermissionSubject::User->value,
            'user_id' => $member->id,
            'can_view' => '1',
            'can_create' => '1',
        ])->assertRedirect();

        $this->assertTrue($web->hasRight($member, 'view'));
        $this->assertTrue($web->hasRight($member, 'create'));
        $this->assertFalse($web->hasRight($member, 'delete'));
    }

    public function test_web_manager_can_edit_web_and_permissions_without_global_admin_role(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $web = $this->web(WebVisibility::Private);
        $web->permissions()->create([
            'subject_type' => WebPermissionSubject::User,
            'user_id' => $manager->id,
            'can_view' => true,
            'can_manage' => true,
        ]);

        $this->actingAs($manager)->get(route('admin.webs.permissions.index', $web))->assertOk();
        $this->actingAs($manager)->patch(route('admin.webs.update', $web), [
            'title' => 'Verwaltetes Web',
            'slug' => $web->slug,
            'description' => 'Durch Web-Manager geändert',
            'visibility' => WebVisibility::Private->value,
        ])->assertRedirect(route('webs.show', $web));
        $this->actingAs($manager)->post(route('admin.webs.permissions.store', $web), [
            'subject_type' => WebPermissionSubject::User->value,
            'user_id' => $member->id,
            'can_edit' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($web->fresh()->hasRight($member, 'view'));
        $this->assertTrue($web->fresh()->hasRight($member, 'edit'));
    }

    public function test_public_subject_cannot_receive_write_rights(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $web = $this->web(WebVisibility::Private);

        $this->actingAs($admin)->post(route('admin.webs.permissions.store', $web), [
            'subject_type' => WebPermissionSubject::Public->value,
            'can_create' => '1',
        ])->assertSessionHasErrors('can_create');

        $this->assertFalse($web->hasRight(null, 'create'));
        $this->assertDatabaseMissing('web_permissions', [
            'web_id' => $web->id,
            'subject_type' => WebPermissionSubject::Public->value,
        ]);
    }

    public function test_unverified_user_has_no_write_rights(): void
    {
        $user = User::factory()->unverified()->create();
        $web = $this->web(WebVisibility::Public);
        $web->permissions()->create([
            'subject_type' => WebPermissionSubject::User,
            'user_id' => $user->id,
            'can_create' => true,
        ]);

        $this->assertTrue($web->hasRight($user, 'view'));
        $this->assertFalse($web->hasRight($user, 'create'));
        $this->actingAs($user)->get(route('articles.create', $web))->assertRedirect(route('verification.notice'));
    }

    private function web(WebVisibility $visibility): Web
    {
        return Web::query()->create([
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(2),
            'visibility' => $visibility,
        ]);
    }
}
