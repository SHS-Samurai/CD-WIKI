<?php

namespace App\Services;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Web;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AuditLogger
{
    public function write(
        string $action,
        ?Model $target = null,
        array $details = [],
        ?User $user = null,
        ?Web $web = null,
        ?Article $article = null,
        ?int $oldRevision = null,
        ?int $newRevision = null,
        ?Request $request = null,
    ): void {
        if (! Schema::hasTable('audit_logs')) {
            if (app()->runningUnitTests() || is_file(storage_path('app/installed'))) {
                throw new RuntimeException('Die Auditlog-Tabelle fehlt. Die Aktion wurde abgebrochen.');
            }

            return;
        }

        $request ??= request();
        $user ??= $request->user();
        $article ??= $target instanceof Article ? $target : null;
        $web ??= $article?->web ?? ($target instanceof Web ? $target : null);

        AuditLog::query()->create([
            'action' => $action,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'web_id' => $web?->id,
            'article_id' => $article?->id,
            'target_type' => $target ? $target::class : null,
            'target_id' => $target ? (string) $target->getKey() : null,
            'old_revision' => $oldRevision,
            'new_revision' => $newRevision,
            'details' => $details === [] ? null : $details,
            'created_at' => now(),
        ]);
    }
}
