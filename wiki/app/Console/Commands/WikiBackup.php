<?php

namespace App\Console\Commands;

use App\Services\ApplicationWriteLock;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class WikiBackup extends Command
{
    protected $signature = 'wiki:backup {--path= : Absolutes Zielverzeichnis}';

    protected $description = 'Erstellt ein konsistentes MySQL- und Datei-Backup mit Prüfsummen';

    public function handle(Filesystem $files, ApplicationWriteLock $writeLock): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Backups sind nur für die produktive MySQL-Verbindung vorgesehen.');

            return self::FAILURE;
        }

        $target = $this->option('path') ?: storage_path('app/backups/'.now()->format('Ymd-His'));
        if (! str_starts_with((string) $target, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\/]/', (string) $target)) {
            $this->error('Das Backup-Ziel muss ein absoluter Pfad sein.');

            return self::FAILURE;
        }
        if ($files->exists($target)) {
            $this->error('Das Backup-Ziel existiert bereits.');

            return self::FAILURE;
        }

        $files->makeDirectory($target, 0700, true);
        try {
            $writeLock->exclusive(function () use ($target, $files): void {
                $dump = $target.DIRECTORY_SEPARATOR.'database.sql';
                $this->dumpDatabase($dump);
                $archivePath = $target.DIRECTORY_SEPARATOR.'files.tar';
                $archive = new PharData($archivePath);
                $this->addTree($archive, storage_path('app/private'), 'private');
                $this->addTree($archive, storage_path('app/public'), 'public');
                unset($archive);

                $manifest = [
                    'format' => 1,
                    'created_at' => now()->toIso8601String(),
                    'database' => (string) config('database.connections.mysql.database'),
                    'files' => [
                        'database.sql' => hash_file('sha256', $dump),
                        'files.tar' => hash_file('sha256', $archivePath),
                    ],
                ];
                $files->put($target.DIRECTORY_SEPARATOR.'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            });
        } catch (Throwable $exception) {
            $files->deleteDirectory($target);
            $this->error('Backup fehlgeschlagen: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup erstellt: {$target}");

        return self::SUCCESS;
    }

    private function dumpDatabase(string $target): void
    {
        $connection = config('database.connections.mysql');
        $binary = (string) ($connection['dump_binary'] ?? 'mysqldump');
        $process = new Process([
            $binary,
            '--single-transaction',
            '--quick',
            '--routines',
            '--events',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--host='.(string) $connection['host'],
            '--port='.(string) $connection['port'],
            '--user='.(string) $connection['username'],
            '--result-file='.$target,
            (string) $connection['database'],
        ], base_path(), ['MYSQL_PWD' => (string) $connection['password']]);
        $process->setTimeout(600);
        $process->run();
        if (! $process->isSuccessful() || ! is_file($target)) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump konnte nicht ausgeführt werden.');
        }
    }

    private function addTree(PharData $archive, string $root, string $prefix): void
    {
        if (! is_dir($root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $archive->addFile($file->getPathname(), $prefix.'/'.$relative);
            }
        }
    }
}
