<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_failed',
        ]);
    }

    public function test_unapproved_login_is_persisted_in_audit_log(): void
    {
        $user = User::factory()->create(['approved_at' => null]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_unapproved']);
    }

    public function test_revoked_account_is_logged_out_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['approved_at' => null]);

        $this->get('/profile')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.session_revoked',
            'user_id' => $user->id,
        ]);
    }

    public function test_password_reset_requests_are_rate_limited(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $attempt) {
            $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        }

        $this->post('/forgot-password', ['email' => $user->email])->assertTooManyRequests();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
