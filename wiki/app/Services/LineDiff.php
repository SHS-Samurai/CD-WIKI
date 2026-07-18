<?php

namespace App\Services;

class LineDiff
{
    /** @return list<array{type: 'equal'|'remove'|'add', line: string}> */
    public function compare(string $old, string $new): array
    {
        $oldLines = preg_split('/\R/u', $old) ?: [];
        $newLines = preg_split('/\R/u', $new) ?: [];

        if (count($oldLines) * count($newLines) > 250000) {
            return $this->boundedComparison($oldLines, $newLines);
        }

        return $this->longestCommonSubsequence($oldLines, $newLines);
    }

    /** @param list<string> $old @param list<string> $new */
    private function longestCommonSubsequence(array $old, array $new): array
    {
        $matrix = array_fill(0, count($old) + 1, array_fill(0, count($new) + 1, 0));

        for ($i = count($old) - 1; $i >= 0; $i--) {
            for ($j = count($new) - 1; $j >= 0; $j--) {
                $matrix[$i][$j] = $old[$i] === $new[$j]
                    ? $matrix[$i + 1][$j + 1] + 1
                    : max($matrix[$i + 1][$j], $matrix[$i][$j + 1]);
            }
        }

        $result = [];
        $i = $j = 0;
        while ($i < count($old) && $j < count($new)) {
            if ($old[$i] === $new[$j]) {
                $result[] = ['type' => 'equal', 'line' => $old[$i]];
                $i++;
                $j++;
            } elseif ($matrix[$i + 1][$j] >= $matrix[$i][$j + 1]) {
                $result[] = ['type' => 'remove', 'line' => $old[$i++]];
            } else {
                $result[] = ['type' => 'add', 'line' => $new[$j++]];
            }
        }
        while ($i < count($old)) {
            $result[] = ['type' => 'remove', 'line' => $old[$i++]];
        }
        while ($j < count($new)) {
            $result[] = ['type' => 'add', 'line' => $new[$j++]];
        }

        return $result;
    }

    /** @param list<string> $old @param list<string> $new */
    private function boundedComparison(array $old, array $new): array
    {
        $result = [];
        while ($old !== [] && $new !== [] && $old[0] === $new[0]) {
            $result[] = ['type' => 'equal', 'line' => array_shift($old)];
            array_shift($new);
        }

        $suffix = [];
        while ($old !== [] && $new !== [] && $old[array_key_last($old)] === $new[array_key_last($new)]) {
            array_unshift($suffix, ['type' => 'equal', 'line' => array_pop($old)]);
            array_pop($new);
        }

        foreach ($old as $line) {
            $result[] = ['type' => 'remove', 'line' => $line];
        }
        foreach ($new as $line) {
            $result[] = ['type' => 'add', 'line' => $line];
        }

        return [...$result, ...$suffix];
    }
}
