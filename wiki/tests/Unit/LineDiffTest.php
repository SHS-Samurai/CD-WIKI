<?php

namespace Tests\Unit;

use App\Services\LineDiff;
use PHPUnit\Framework\TestCase;

class LineDiffTest extends TestCase
{
    public function test_it_marks_added_and_removed_lines(): void
    {
        $changes = (new LineDiff)->compare("gleich\nalt", "gleich\nneu");

        $this->assertSame([
            ['type' => 'equal', 'line' => 'gleich'],
            ['type' => 'remove', 'line' => 'alt'],
            ['type' => 'add', 'line' => 'neu'],
        ], $changes);
    }
}
