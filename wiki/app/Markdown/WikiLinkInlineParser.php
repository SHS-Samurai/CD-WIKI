<?php

namespace App\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

class WikiLinkInlineParser implements InlineParserInterface
{
    /** @param array<string, array{url: string, missing: bool}> $targets */
    public function __construct(private readonly array $targets) {}

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\[\[[^\]\r\n]{1,180}\]\]');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $match = $inlineContext->getFullMatch();
        $title = trim(mb_substr($match, 2, -2));

        if ($title === '') {
            return false;
        }

        $target = $this->targets[mb_strtolower($title)] ?? null;
        if ($target === null) {
            return false;
        }

        $link = new Link($target['url'], $title);
        $link->data->set('attributes', [
            'class' => $target['missing'] ? 'wiki-link wiki-link-missing' : 'wiki-link',
        ]);

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild($link);

        return true;
    }
}
