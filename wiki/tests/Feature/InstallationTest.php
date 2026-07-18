<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstallationTest extends TestCase
{
    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/framework/testing/installation');
        File::deleteDirectory($this->testStoragePath);
        File::makeDirectory($this->testStoragePath.'/app', 0755, true);
        $this->app->useStoragePath($this->testStoragePath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);

        parent::tearDown();
    }

    public function test_installation_page_is_available_before_setup(): void
    {
        $response = $this->get(route('installation.create'));

        $response->assertOk();
        $response->assertSee('Datenbank verbinden');
        $response->assertSee('127.0.0.1');
        $response->assertSee('3306');
        $response->assertSee('automatisch erstellt');
    }

    public function test_installation_form_validates_database_fields(): void
    {
        $response = $this->post(route('installation.store'), []);

        $response->assertSessionHasErrors(['database', 'username', 'admin_name', 'admin_email', 'admin_password']);
        $response->assertSessionDoesntHaveErrors(['host', 'port']);
    }

    public function test_installation_page_redirects_after_setup(): void
    {
        File::put(storage_path('app/installed'), 'installed');

        $response = $this->get(route('installation.create'));

        $response->assertRedirect(route('home'));
    }

    public function test_remote_installation_requires_server_generated_token(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
            ->get(route('installation.create'))
            ->assertForbidden();

        config(['wiki.installation_token' => 'server-secret']);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
            ->get(route('installation.create'))
            ->assertOk()
            ->assertSee('Installationstoken');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
            ->post(route('installation.store'), [
                'setup_token' => 'falsch',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'wiki_test',
                'username' => 'wiki',
                'admin_name' => 'Admin',
                'admin_email' => 'admin@example.com',
                'admin_password' => 'SicheresPasswort12',
                'admin_password_confirmation' => 'SicheresPasswort12',
            ])->assertForbidden();
    }
}
