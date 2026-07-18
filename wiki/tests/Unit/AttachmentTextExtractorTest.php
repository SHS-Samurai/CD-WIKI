<?php

namespace Tests\Unit;

use App\Services\AttachmentTextExtractor;
use App\Services\SafeZipReader;
use PHPUnit\Framework\TestCase;

class AttachmentTextExtractorTest extends TestCase
{
    public function test_docx_and_xlsx_text_is_extracted_without_zip_extension(): void
    {
        $extractor = new AttachmentTextExtractor(new SafeZipReader);
        $docx = $this->archive(['word/document.xml' => '<w:document><w:p><w:t>Projekt Alpha</w:t></w:p></w:document>']);
        $xlsx = $this->archive(['xl/sharedStrings.xml' => '<sst><si><t>Budget Beta</t></si></sst>']);

        $this->assertStringContainsString('Projekt Alpha', $extractor->extract($docx, 'docx'));
        $this->assertStringContainsString('Budget Beta', $extractor->extract($xlsx, 'xlsx'));

        unlink($docx);
        unlink($xlsx);
    }

    /** @param array<string, string> $files */
    private function archive(array $files): string
    {
        $body = '';
        $directory = '';
        $offset = 0;

        foreach ($files as $name => $content) {
            $compressed = gzdeflate($content);
            $crc = crc32($content);
            $body .= "PK\x03\x04".pack('vvvvvVVVvv', 20, 0, 8, 0, 0, $crc, strlen($compressed), strlen($content), strlen($name), 0).$name.$compressed;
            $directory .= "PK\x01\x02".pack('vvvvvvVVVvvvvvVV', 20, 20, 0, 8, 0, 0, $crc, strlen($compressed), strlen($content), strlen($name), 0, 0, 0, 0, 0, $offset).$name;
            $offset = strlen($body);
        }

        $zip = $body.$directory."PK\x05\x06".pack('vvvvVVv', 0, 0, count($files), count($files), strlen($directory), strlen($body), 0);
        $path = tempnam(sys_get_temp_dir(), 'wiki-zip-');
        file_put_contents($path, $zip);

        return $path;
    }
}
