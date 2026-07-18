<?php

namespace App\Services;

use RuntimeException;

class EnvironmentFile
{
    /** Schreibt Werte und gibt den vorherigen Dateiinhalt für ein Rollback zurück. */
    public function write(array $values): string
    {
        return $this->withLock(LOCK_EX, function (string $path) use ($values): string {
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException('Die Umgebungsdatei konnte nicht gelesen werden.');
            }

            $updated = $contents;
            foreach ($values as $key => $value) {
                $line = $key.'='.$this->quote((string) $value);
                $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
                $updated = preg_match($pattern, $updated) === 1
                    ? (string) preg_replace($pattern, $line, $updated, 1)
                    : rtrim($updated).PHP_EOL.$line.PHP_EOL;
            }

            $this->replaceAtomically($path, $updated);

            return $contents;
        });
    }

    public function restore(string $contents): void
    {
        $this->withLock(LOCK_EX, function (string $path) use ($contents): void {
            $this->replaceAtomically($path, $contents);
        });
    }

    private function withLock(int $operation, callable $callback): mixed
    {
        $path = base_path('.env');
        $lock = fopen($path.'.lock', 'c+');
        if ($lock === false || ! flock($lock, $operation)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Die Umgebungsdatei konnte nicht gesperrt werden.');
        }

        try {
            return $callback($path);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function replaceAtomically(string $path, string $contents): void
    {
        $temporary = $path.'.tmp-'.bin2hex(random_bytes(8));
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Die temporäre Umgebungsdatei konnte nicht geschrieben werden.');
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            if (! rename($temporary, $path)) {
                @unlink($temporary);
                throw new RuntimeException('Die Umgebungsdatei konnte nicht atomar ersetzt werden.');
            }

            return;
        }

        $backup = $path.'.backup-'.bin2hex(random_bytes(8));
        if (! rename($path, $backup)) {
            @unlink($temporary);
            throw new RuntimeException('Die bisherige Umgebungsdatei konnte nicht gesichert werden.');
        }
        if (! rename($temporary, $path)) {
            @rename($backup, $path);
            @unlink($temporary);
            throw new RuntimeException('Die Umgebungsdatei konnte nicht ersetzt werden.');
        }
        @unlink($backup);
    }

    private function quote(string $value): string
    {
        $escaped = str_replace(
            ['\\', '"', '$', "\r", "\n"],
            ['\\\\', '\\"', '\\$', '', '\\n'],
            $value,
        );

        return '"'.$escaped.'"';
    }
}
