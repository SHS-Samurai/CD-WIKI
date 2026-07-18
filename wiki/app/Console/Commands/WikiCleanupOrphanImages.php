<?php

namespace App\Console\Commands;

use App\Models\EditorImage;
use App\Services\ApplicationWriteLock;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WikiCleanupOrphanImages extends Command
{
    protected $signature = 'wiki:cleanup-orphan-images {--hours=24 : Mindestalter ungebundener Bilder}';

    protected $description = 'Entfernt abgebrochene, keinem Artikel zugeordnete Editor-Bilder';

    public function handle(ApplicationWriteLock $writeLock, AuditLogger $audit): int
    {
        $hours = filter_var($this->option('hours'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 8760]]);
        if ($hours === false) {
            $this->error('Das Mindestalter muss zwischen 1 und 8760 Stunden liegen.');

            return self::FAILURE;
        }

        $removed = $writeLock->exclusive(function () use ($hours, $audit): int {
            $count = 0;
            EditorImage::query()
                ->whereNull('article_id')
                ->where('created_at', '<', now()->subHours($hours))
                ->chunkById(100, function ($images) use (&$count, $audit): void {
                    foreach ($images as $image) {
                        DB::transaction(function () use ($image, $audit): void {
                            $audit->write('editor_image.orphan_removed', $image, ['path' => $image->path], web: $image->web);
                            $image->delete();
                        });
                        Storage::disk('local')->delete($image->path);
                        $count++;
                    }
                });

            return $count;
        });

        $this->info("Verwaiste Editor-Bilder entfernt: {$removed}");

        return self::SUCCESS;
    }
}
