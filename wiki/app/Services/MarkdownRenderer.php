<?php

namespace App\Services;

use App\Markdown\WikiLinkInlineParser;
use App\Models\Web;
use Illuminate\Support\HtmlString;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    public function render(string $markdown, Web $web): HtmlString
    {
        $targets = $this->linkTargets($markdown, $web);
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
            'max_delimiters_per_line' => 1000,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addInlineParser(new WikiLinkInlineParser($targets), 200);

        $converter = new MarkdownConverter($environment);

        return new HtmlString((string) $converter->convert($markdown));
    }

    /** @return array<string, array{url: string, missing: bool}> */
    private function linkTargets(string $markdown, Web $web): array
    {
        preg_match_all('/\[\[([^\]\r\n]{1,180})\]\]/u', $markdown, $matches);
        $titles = collect($matches[1] ?? [])
            ->map(fn (string $title) => trim($title))
            ->filter()
            ->unique(fn (string $title) => mb_strtolower($title));

        if ($titles->isEmpty()) {
            return [];
        }

        $articles = $web->articles()
            ->whereIn('title_key', $titles->map(fn (string $title) => mb_strtolower($title)))
            ->get(['title', 'slug'])
            ->keyBy(fn ($article) => mb_strtolower($article->title));

        return $titles->mapWithKeys(function (string $title) use ($web, $articles): array {
            $key = mb_strtolower($title);
            $article = $articles->get($key);

            return [$key => $article
                ? ['url' => route('articles.show', [$web, $article]), 'missing' => false]
                : ['url' => route('articles.create', [$web, 'title' => $title]), 'missing' => true]];
        })->all();
    }
}
