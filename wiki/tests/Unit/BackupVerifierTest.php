<?php

namespace Tests\Unit;

use App\Services\BackupVerifier;
use PharData;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BackupVerifierTest extends TestCase
{
    public function test_manifest_sql_archive_and_hashes_are_verified(): void
    {
        $directory = $this->backupDirectory();

        try {
            $manifest = (new BackupVerifier)->verify($directory);
            $this->assertSame('2026-07-17T00:00:00+00:00', $manifest['created_at']);
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function test_tampering_is_detected(): void
    {
        $directory = $this->backupDirectory();
        file_put_contents($directory.DIRECTORY_SEPARATOR.'database.sql', 'VERÄNDERT');

        $this->expectException(RuntimeException::class);
        try {
            (new BackupVerifier)->verify($directory);
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function backupDirectory(): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'wiki-backup-'.bin2hex(random_bytes(5));
        mkdir($directory);
        $sql = "-- MySQL dump 10.13\nCREATE TABLE `articles` (`id` bigint);\nINSERT INTO `articles` VALUES (1);\n";
        file_put_contents($directory.DIRECTORY_SEPARATOR.'database.sql', $sql);

        $archivePath = $directory.DIRECTORY_SEPARATOR.'files.tar';
        $archive = new PharData($archivePath);
        $archive->addFromString('private/attachments/test.txt', 'Dateiinhalt');
        unset($archive);

        file_put_contents($directory.DIRECTORY_SEPARATOR.'manifest.json', json_encode([
            'format' => 1,
            'created_at' => '2026-07-17T00:00:00+00:00',
            'database' => 'cd_wiki_test',
            'files' => [
                'database.sql' => hash_file('sha256', $directory.DIRECTORY_SEPARATOR.'database.sql'),
                'files.tar' => hash_file('sha256', $archivePath),
            ],
        ], JSON_THROW_ON_ERROR));

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (['database.sql', 'files.tar', 'manifest.json'] as $file) {
            @unlink($directory.DIRECTORY_SEPARATOR.$file);
        }
        @rmdir($directory);
    }
}
