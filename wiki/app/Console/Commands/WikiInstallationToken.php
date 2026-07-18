<?php

namespace App\Console\Commands;

use App\Services\EnvironmentFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class WikiInstallationToken extends Command
{
    protected $signature = 'wiki:installation-token';

    protected $description = 'Erzeugt ein einmaliges Zugriffstoken für die entfernte Ersteinrichtung';

    public function handle(EnvironmentFile $environmentFile): int
    {
        if (is_file(storage_path('app/installed'))) {
            $this->error('Das Wiki ist bereits installiert.');

            return self::FAILURE;
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $environmentFile->write(['INSTALLATION_TOKEN' => $token]);
        Artisan::call('config:clear');
        $this->components->info('Installationstoken: '.$token);
        $this->line('Das Token wird nach erfolgreicher Installation automatisch ungültig.');

        return self::SUCCESS;
    }
}
