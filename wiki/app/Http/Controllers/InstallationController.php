<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstallDatabaseRequest;
use App\Models\User;
use App\Services\EnvironmentFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class InstallationController extends Controller
{
    private const WIKI_TABLES = [
        'migrations', 'users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs', 'groups', 'group_user', 'webs', 'web_permissions',
        'articles', 'article_revisions', 'categories', 'article_category', 'editor_images', 'comments',
        'attachments', 'attachment_revisions', 'theme_settings', 'audit_logs', 'system_settings',
    ];

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->isInstalled()) {
            return redirect()->route('home');
        }

        abort_if(! $this->isLocalRequest($request) && blank(config('wiki.installation_token')), 403, 'Für eine entfernte Installation muss zuerst „php artisan wiki:installation-token“ ausgeführt werden.');

        return view('installation.create', [
            'requiresSetupToken' => filled(config('wiki.installation_token')),
        ]);
    }

    public function store(
        InstallDatabaseRequest $request,
        EnvironmentFile $environmentFile,
    ): RedirectResponse {
        if ($this->isInstalled()) {
            return redirect()->route('home');
        }

        abort_unless($this->hasInstallerAccess($request), 403, 'Das Installationstoken ist ungültig.');

        $lock = fopen(storage_path('app/installation.lock'), 'c+');

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            return back()->withErrors([
                'database' => 'Die Installation wird bereits ausgeführt.',
            ]);
        }

        $originalEnvironment = null;
        $markerWritten = false;

        try {
            if ($this->isInstalled()) {
                return redirect()->route('home');
            }

            $credentials = $request->validated();
            $credentials['password'] = (string) ($credentials['password'] ?? '');

            $server = $this->connectToServer($credentials);
            $this->createDatabaseIfMissing($server, $credentials['database']);
            $pdo = $this->connectToDatabase($credentials);
            $this->assertDatabaseCanBeInstalled($pdo);

            $originalEnvironment = $environmentFile->write([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $credentials['host'],
                'DB_PORT' => $credentials['port'],
                'DB_DATABASE' => $credentials['database'],
                'DB_USERNAME' => $credentials['username'],
                'DB_PASSWORD' => $credentials['password'],
                'SESSION_DRIVER' => 'file',
                'SESSION_ENCRYPT' => 'true',
                'SESSION_SECURE_COOKIE' => 'true',
                'CACHE_STORE' => 'file',
                'QUEUE_CONNECTION' => 'sync',
                'INSTALLATION_TOKEN' => '',
            ]);

            Artisan::call('config:clear');
            $this->configureDatabase($credentials);
            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                throw new RuntimeException('Die Datenbankmigration ist fehlgeschlagen.');
            }

            if (User::query()->exists()) {
                throw new RuntimeException('Die gewählte Datenbank enthält bereits Benutzer.');
            }

            DB::transaction(function () use ($credentials, &$markerWritten): void {
                User::query()->create([
                    'name' => $credentials['admin_name'],
                    'email' => $credentials['admin_email'],
                    'password' => $credentials['admin_password'],
                    'is_admin' => true,
                    'email_verified_at' => now(),
                    'approved_at' => now(),
                ]);

                if (file_put_contents(storage_path('app/installed'), now()->toIso8601String().PHP_EOL, LOCK_EX) === false) {
                    throw new RuntimeException('Der Installationsstatus konnte nicht gespeichert werden.');
                }
                $markerWritten = true;
            });
        } catch (PDOException) {
            $this->rollbackFailedInstallation($environmentFile, $originalEnvironment, $markerWritten);

            return back()->withInput($request->except(['password', 'setup_token', 'admin_password', 'admin_password_confirmation']))->withErrors([
                'database' => 'Die MySQL-Verbindung oder das Anlegen der Datenbank ist fehlgeschlagen. Bitte Zugangsdaten und das CREATE-Recht des MySQL-Benutzers prüfen.',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $this->rollbackFailedInstallation($environmentFile, $originalEnvironment, $markerWritten);

            return back()->withInput($request->except(['password', 'setup_token', 'admin_password', 'admin_password_confirmation']))->withErrors([
                'database' => 'Die Installation konnte nicht abgeschlossen werden. Bitte das Anwendungsprotokoll prüfen.',
            ]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return redirect()->route('home')->with(
            'status',
            'Die Datenbank wurde eingerichtet. Das Wiki ist jetzt bereit.',
        );
    }

    private function connectToServer(array $credentials): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%d;charset=utf8mb4',
                $credentials['host'],
                $credentials['port'],
            ),
            $credentials['username'],
            $credentials['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ],
        );
    }

    private function createDatabaseIfMissing(PDO $server, string $database): void
    {
        $query = $server->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
        $query->execute([$database]);
        if ((int) $query->fetchColumn() > 0) {
            return;
        }

        $quotedDatabase = '`'.str_replace('`', '``', $database).'`';
        $server->exec("CREATE DATABASE {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private function connectToDatabase(array $credentials): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $credentials['host'],
                $credentials['port'],
                $credentials['database'],
            ),
            $credentials['username'],
            $credentials['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ],
        );
    }

    private function assertDatabaseCanBeInstalled(PDO $pdo): void
    {
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $foreignTables = array_diff($tables, self::WIKI_TABLES);
        if ($foreignTables !== []) {
            throw new RuntimeException('Die Datenbank enthält fremde Tabellen und kann nicht verwendet werden.');
        }

        if (in_array('users', $tables, true) && (int) $pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn() > 0) {
            throw new RuntimeException('Die gewählte Datenbank enthält bereits Benutzer.');
        }
    }

    private function hasInstallerAccess(Request $request): bool
    {
        $token = (string) config('wiki.installation_token');

        return $token === ''
            ? $this->isLocalRequest($request)
            : hash_equals($token, (string) $request->input('setup_token'));
    }

    private function isLocalRequest(Request $request): bool
    {
        return in_array($request->ip(), ['127.0.0.1', '::1'], true);
    }

    private function configureDatabase(array $credentials): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $credentials['host'],
            'database.connections.mysql.port' => $credentials['port'],
            'database.connections.mysql.database' => $credentials['database'],
            'database.connections.mysql.username' => $credentials['username'],
            'database.connections.mysql.password' => $credentials['password'],
        ]);

        DB::purge('mysql');
    }

    private function rollbackFailedInstallation(EnvironmentFile $environmentFile, ?string $originalEnvironment, bool $markerWritten): void
    {
        if ($markerWritten) {
            @unlink(storage_path('app/installed'));
        }
        if ($originalEnvironment === null) {
            return;
        }

        try {
            $environmentFile->restore($originalEnvironment);
            Artisan::call('config:clear');
        } catch (Throwable $restoreException) {
            report($restoreException);
        }
    }

    private function isInstalled(): bool
    {
        return is_file(storage_path('app/installed'));
    }
}
