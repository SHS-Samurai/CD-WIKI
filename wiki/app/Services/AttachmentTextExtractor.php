<?php

namespace App\Services;

use Illuminate\Support\Str;

class AttachmentTextExtractor
{
    private const MAX_INDEX_CHARS = 1_000_000;

    public function __construct(private readonly SafeZipReader $zip) {}

    public function extract(string $path, string $extension): ?string
    {
        $text = match ($extension) {
            'txt', 'md' => file_get_contents($path) ?: '',
            'html' => $this->html(file_get_contents($path) ?: ''),
            'pdf' => $this->pdf(file_get_contents($path) ?: ''),
            'docx' => $this->docx($path),
            'xlsx' => $this->xlsx($path),
            default => '',
        };

        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : Str::limit($text, self::MAX_INDEX_CHARS, '');
    }

    private function html(string $html): string
    {
        $html = (string) preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', ' ', $html);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function pdf(string $pdf): string
    {
        $sources = [$pdf];
        preg_match_all('/<<(?<dictionary>.*?)>>\s*stream\r?\n(?<stream>.*?)\r?\nendstream/s', $pdf, $streams, PREG_SET_ORDER);
        foreach ($streams as $stream) {
            if (str_contains($stream['dictionary'], '/FlateDecode') && strlen($stream['stream']) <= 10 * 1024 * 1024) {
                $decoded = @gzuncompress($stream['stream']);
                if ($decoded !== false && strlen($decoded) <= 20 * 1024 * 1024) {
                    $sources[] = $decoded;
                }
            }
        }

        preg_match_all('/\((?:\\\\.|[^\\)]){2,}\)/s', implode("\n", $sources), $matches);

        return implode(' ', array_map(
            fn (string $value): string => stripcslashes(substr($value, 1, -1)),
            $matches[0] ?? [],
        ));
    }

    private function docx(string $path): string
    {
        $entries = $this->zip->read($path, fn (string $name): bool => preg_match('~^word/(document|header\d+|footer\d+)\.xml$~', $name) === 1);

        return implode(' ', array_map($this->xmlText(...), $entries));
    }

    private function xlsx(string $path): string
    {
        $entries = $this->zip->read($path, fn (string $name): bool => $name === 'xl/sharedStrings.xml' || preg_match('~^xl/worksheets/sheet\d+\.xml$~', $name) === 1);

        return implode(' ', array_map($this->xmlText(...), $entries));
    }

    private function xmlText(string $xml): string
    {
        $xml = (string) preg_replace('~<(w:p|row|si|c)\b~', ' <$1 ', $xml);
        $xml = (string) preg_replace('~</(w:t|t|v)>~', ' ', $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
