<?php

namespace Tests\Feature\Auth;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_registration_shows_information_and_rejects_posts(): void
    {
        $response = $this->get('/register');

        $response->assertOk()->assertSee('Registrierung geschlossen');
        $this->post('/register', $this->registrationData())->assertForbidden();
    }

    public function test_open_registration_authenticates_approved_user(): void
    {
        $this->mode('open');
        $response = $this->post('/register', $this->registrationData());

        $this->assertAuthenticated();
        $this->assertNotNull(User::query()->firstOrFail()->approved_at);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_approval_registration_creates_blocked_user_without_login(): void
    {
        $this->mode('approval');
        $this->post('/register', $this->registrationData())
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest();
        $this->assertNull(User::query()->firstOrFail()->approved_at);
        $this->post('/login', ['email' => 'test@example.com', 'password' => 'SicheresPasswort12'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    private function mode(string $mode): void
    {
        SystemSetting::query()->update(['registration_mode' => $mode]);
        app(SystemSettings::class)->clear();
    }

    private function registrationData(): array
    {
        return ['name' => 'Test User', 'email' => 'test@example.com', 'password' => 'SicheresPasswort12', 'password_confirmation' => 'SicheresPasswort12'];
    }
}
