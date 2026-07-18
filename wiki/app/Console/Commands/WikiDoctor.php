<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class WikiDoctor extends Command
{
    protected $signature = 'wiki:doctor';

    protected $description = 'Prüft die produktionskritischen Wiki-, MySQL- und Speicherfunktionen';

    public function handle(): int
    {
        $errors = [];
        $this->check(PHP_VERSION_ID >= 80300, 'PHP 8.3+', $errors);
        $this->check(DB::getDriverName() === 'mysql', 'MySQL-Verbindung', $errors);

        try {
            DB::select('select 1');
            $this->info('OK  Datenbankabfrage');
        } catch (Throwable $exception) {
            $errors[] = 'Datenbankabfrage: '.$exception->getMessage();
        }

        foreach (['users', 'webs', 'articles', 'article_revisions', 'attachments', 'audit_logs', 'system_settings'] as $table) {
            $this->check(Schema::hasTable($table), "Tabelle {$table}", $errors);
        }

        if (DB::getDriverName() === 'mysql') {
            $indexes = collect(DB::select(
                'select distinct index_name from information_schema.statistics where table_schema = database() and index_name in (?, ?)',
                ['articles_title_content_fulltext', 'attachments_search_fulltext'],
            ))->pluck('index_name');
            foreach (['articles_title_content_fulltext', 'attachments_search_fulltext'] as $index) {
                $this->check($indexes->contains($index), "FULLTEXT-Index {$index}", $errors);
            }
        }

        $probe = '.doctor-'.bin2hex(random_bytes(8));
        $written = Storage::disk('local')->put($probe, 'wiki-doctor');
        $read = $written && Storage::disk('local')->get($probe) === 'wiki-doctor';
        Storage::disk('local')->delete($probe);
        $this->check($read, 'Privater Dateispeicher', $errors);
        $this->check(User::query()->where('is_admin', true)->whereNotNull('approved_at')->exists(), 'Freigegebener Administrator', $errors);
        $this->check(! app()->isProduction() || ! config('app.debug'), 'APP_DEBUG in Produktion aus', $errors);
        $this->check(! app()->isProduction() || str_starts_with((string) config('app.url'), 'https://'), 'HTTPS-APP_URL in Produktion', $errors);
        $this->check(! app()->isProduction() || config('session.secure') === true, 'Sicheres Session-Cookie in Produktion', $errors);
        $this->check(! app()->isProduction() || config('session.encrypt') === true, 'Verschlüsselte Sitzung in Produktion', $errors);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error('FEHLER  '.$error);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Alle produktionskritischen Prüfungen waren erfolgreich.');

        return self::SUCCESS;
    }

    /** @param list<string> $errors */
    private function check(bool $condition, string $label, array &$errors): void
    {
        if ($condition) {
            $this->info("OK  {$label}");
        } else {
            $errors[] = $label;
        }
    }
}
