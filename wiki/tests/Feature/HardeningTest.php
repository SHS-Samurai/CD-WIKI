<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_entries_are_append_only(): void
    {
        $entry = AuditLog::query()->create(['action' => 'test', 'created_at' => now()]);

        try {
            $entry->update(['action' => 'changed']);
            $this->fail('Die Änderung des Auditlogs wurde nicht verhindert.');
        } catch (LogicException) {
            $this->assertDatabaseHas('audit_logs', ['id' => $entry->id, 'action' => 'test']);
        }

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_database_itself_rejects_audit_log_changes(): void
    {
        $entry = AuditLog::query()->create(['action' => 'database-test', 'created_at' => now()]);

        try {
            DB::table('audit_logs')->where('id', $entry->id)->update(['action' => 'manipuliert']);
            $this->fail('Der Datenbanktrigger hat die Änderung nicht verhindert.');
        } catch (QueryException) {
            $this->assertDatabaseHas('audit_logs', ['id' => $entry->id, 'action' => 'database-test']);
        }

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $entry->id)->delete();
    }

    public function test_admin_can_change_registration_mode(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.settings.update'), ['registration_mode' => 'approval'])
            ->assertSessionHasNoErrors();

        app(SystemSettings::class)->clear();
        $this->assertSame('approval', SystemSetting::query()->value('registration_mode'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.registration_updated']);
    }

    public function test_security_policy_contains_no_unsafe_script_or_style_exception(): void
    {
        $policy = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertNotNull($policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
        $this->assertStringNotContainsString('unsafe-inline', $policy);
    }

    public function test_profile_change_is_rolled_back_if_audit_write_fails(): void
    {
        $user = User::factory()->create(['name' => 'Vorher']);
        $logger = $this->mock(AuditLogger::class);
        $logger->shouldReceive('write')->once()->andThrow(new \RuntimeException('Audit nicht verfügbar'));

        try {
            $this->withoutExceptionHandling()->actingAs($user)->patch('/profile', [
                'name' => 'Nachher',
                'email' => $user->email,
            ]);
            $this->fail('Der Auditfehler wurde nicht weitergegeben.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit nicht verfügbar', $exception->getMessage());
        }

        $this->assertSame('Vorher', $user->fresh()->name);
    }
}
