<?php

namespace Tests\Feature;

use App\Enums\WebVisibility;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use App\Models\Web;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('mysql')]
class MySqlIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Erfordert die gesonderte MySQL-Testdatenbank.');
        }
        if (! str_ends_with((string) DB::getDatabaseName(), '_test')) {
            $this->fail('MySQL-Integrationstests dürfen nur auf einer Datenbank mit Suffix _test laufen.');
        }
    }

    public function test_mysql_fulltext_indexes_exist_and_find_article_and_attachment_text(): void
    {
        $indexes = collect(DB::select(
            'select distinct index_name from information_schema.statistics where table_schema = database() and index_name in (?, ?)',
            ['articles_title_content_fulltext', 'attachments_search_fulltext'],
        ))->pluck('index_name');
        $this->assertContains('articles_title_content_fulltext', $indexes);
        $this->assertContains('attachments_search_fulltext', $indexes);

        $web = Web::query()->create(['slug' => 'mysql-test', 'title' => 'MySQL-Test', 'visibility' => WebVisibility::Public]);
        $article = $web->articles()->create(['title' => 'Hyperkonvergenz', 'slug' => 'hyperkonvergenz', 'content' => 'Quantenarchitektur Nachschlagewerk']);
        $article->attachments()->create([
            'uuid' => fake()->uuid(), 'original_name' => 'handbuch.txt', 'storage_name' => 'handbuch.txt', 'path' => 'test',
            'mime_type' => 'text/plain', 'search_text' => 'Spektralanalyse Dokumentinhalt', 'size' => 1, 'current_revision' => 1,
        ]);

        $this->assertTrue(Article::query()->whereFullText(['title', 'content'], 'Quantenarchitektur')->exists());
        $this->assertTrue($article->attachments()->whereFullText(['original_name', 'search_text'], 'Spektralanalyse')->exists());
    }

    public function test_revision_locking_keeps_revision_numbers_unique_under_parallel_writes(): void
    {
        $user = User::factory()->create();
        $web = Web::query()->create(['slug' => 'revision-test', 'title' => 'Revision-Test', 'visibility' => WebVisibility::Private]);
        $article = $web->articles()->create(['title' => 'Start', 'slug' => 'start', 'content' => 'Null']);
        DB::commit();

        try {
            $processes = collect(range(1, 8))->map(fn (int $number) => new Process([
                PHP_BINARY,
                base_path('tests/Support/concurrent-revision-write.php'),
                (string) $article->id,
                (string) $user->id,
                (string) $number,
            ], base_path(), null, null, 30));
            $processes->each->start();
            $processes->each(function (Process $process): void {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            });

            $numbers = ArticleRevision::query()->where('article_id', $article->id)->pluck('revision_number');
            $this->assertCount(8, $numbers);
            $this->assertCount(8, $numbers->unique());
            $this->assertSame(8, $article->fresh()->current_revision);
        } finally {
            $web->delete();
            $user->delete();
            DB::beginTransaction();
        }
    }
}
