<?php

namespace App\Services;

use PharData;
use RuntimeException;
use Throwable;

class BackupVerifier
{
    /** @return array<string, mixed> */
    public function verify(string $directory): array
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $manifestPath = $directory.DIRECTORY_SEPARATOR.'manifest.json';
        $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : null;

        if (! is_array($manifest) || ($manifest['format'] ?? null) !== 1 || ! isset($manifest['created_at'], $manifest['database'], $manifest['files']) || ! is_array($manifest['files'])) {
            throw new RuntimeException('Das Backup-Manifest fehlt oder ist ungültig.');
        }

        if (array_values(array_keys($manifest['files'])) !== ['database.sql', 'files.tar']) {
            throw new RuntimeException('Das Backup-Manifest enthält eine unerwartete Dateiliste.');
        }

        foreach ($manifest['files'] as $name => $expectedHash) {
            $path = $directory.DIRECTORY_SEPARATOR.$name;
            if (! is_string($expectedHash) || preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1 || ! is_file($path) || ! hash_equals($expectedHash, hash_file('sha256', $path))) {
                throw new RuntimeException("Die Prüfsumme für {$name} stimmt nicht.");
            }
        }

        $this->verifyDatabaseDump($directory.DIRECTORY_SEPARATOR.'database.sql');
        $this->verifyArchive($directory.DIRECTORY_SEPARATOR.'files.tar');

        return $manifest;
    }

    private function verifyDatabaseDump(string $path): void
    {
        $sample = file_get_contents($path, false, null, 0, 256 * 1024);
        if (! is_string($sample) || strlen($sample) < 32 || preg_match('/(?:MySQL dump|MariaDB dump|CREATE TABLE|INSERT INTO)/i', $sample) !== 1) {
            throw new RuntimeException('Der Datenbankdump ist leer oder besitzt kein erkennbares SQL-Format.');
        }
    }

    private function verifyArchive(string $path): void
    {
        try {
            $archive = new PharData($path);
            foreach ($archive as $entry) {
                $name = str_replace('\\', '/', $entry->getFilename());
                if ($name === '..' || str_starts_with($name, '../') || str_contains($name, '/../') || str_starts_with($name, '/')) {
                    throw new RuntimeException('Das Dateiarchiv enthält einen unsicheren Pfad.');
                }
            }
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('Das Dateiarchiv ist beschädigt oder nicht lesbar.', previous: $exception);
        }
    }
}
