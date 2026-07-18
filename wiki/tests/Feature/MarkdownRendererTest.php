<?php

namespace Tests\Feature;

use App\Enums\WebVisibility;
use App\Models\Web;
use App\Services\MarkdownRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkdownRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_markdown_escapes_html_and_blocks_unsafe_links(): void
    {
        $web = $this->web();
        $html = (string) app(MarkdownRenderer::class)->render(
            '<script>alert(1)</script> [Gefahr](javascript:alert(1))',
            $web,
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_wiki_links_target_articles_in_same_web_and_mark_missing_articles(): void
    {
        $web = $this->web();
        $article = $web->articles()->create([
            'title' => 'Vorhanden', 'slug' => 'vorhanden', 'content' => 'Text',
        ]);

        $html = (string) app(MarkdownRenderer::class)->render('[[Vorhanden]] und [[Noch nicht da]]', $web);

        $this->assertStringContainsString(route('articles.show', [$web, $article]), $html);
        $this->assertStringContainsString('wiki-link-missing', $html);
        $this->assertStringContainsString(rawurlencode('Noch nicht da'), $html);
    }

    private function web(): Web
    {
        return Web::query()->create([
            'slug' => 'wissen',
            'title' => 'Wissen',
            'visibility' => WebVisibility::Public,
        ]);
    }
}
