<?php

namespace App\Services;

use RuntimeException;

class ApplicationWriteLock
{
    public function shared(callable $callback): mixed
    {
        return $this->withLock(LOCK_SH, $callback);
    }

    public function exclusive(callable $callback): mixed
    {
        return $this->withLock(LOCK_EX, $callback);
    }

    private function withLock(int $operation, callable $callback): mixed
    {
        $handle = fopen(storage_path('app/wiki-write.lock'), 'c+');

        if ($handle === false || ! flock($handle, $operation)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw new RuntimeException('Die Wiki-Schreibsperre konnte nicht gesetzt werden.');
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
