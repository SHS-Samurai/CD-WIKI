<?php

namespace App\Console\Commands;

use App\Services\BackupVerifier;
use Illuminate\Console\Command;
use Throwable;

class WikiBackupVerify extends Command
{
    protected $signature = 'wiki:backup:verify {path : Backup-Verzeichnis}';

    protected $description = 'Prüft Manifest und Prüfsummen eines Wiki-Backups';

    public function handle(BackupVerifier $verifier): int
    {
        try {
            $manifest = $verifier->verify($this->argument('path'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup ist vollständig und unverändert ('.$manifest['created_at'].').');

        return self::SUCCESS;
    }
}
