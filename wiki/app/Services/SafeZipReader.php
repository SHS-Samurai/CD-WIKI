<?php

namespace App\Services;

use RuntimeException;

class SafeZipReader
{
    private const MAX_ENTRIES = 5000;

    private const MAX_TOTAL_UNCOMPRESSED = 100 * 1024 * 1024;

    private const MAX_SELECTED_FILE = 20 * 1024 * 1024;

    /** @return array<string, string> */
    public function read(string $path, callable $selected): array
    {
        $archive = file_get_contents($path);
        if ($archive === false || strlen($archive) > 26 * 1024 * 1024) {
            throw new RuntimeException('Das Office-Dokument ist zu groß oder nicht lesbar.');
        }

        $tail = substr($archive, -65557);
        $relativeEocd = strrpos($tail, "PK\x05\x06");
        if ($relativeEocd === false) {
            throw new RuntimeException('Das Office-Dokument enthält kein gültiges ZIP-Verzeichnis.');
        }

        $eocd = substr($tail, $relativeEocd, 22);
        $directory = unpack('ventries_on_disk/ventries/Vsize/Voffset', substr($eocd, 8, 12));
        if (! is_array($directory)
            || $directory['entries'] !== $directory['entries_on_disk']
            || $directory['entries'] > self::MAX_ENTRIES
            || $directory['entries'] === 0
            || $directory['entries'] === 0xFFFF
            || $directory['offset'] === 0xFFFFFFFF
        ) {
            throw new RuntimeException('Das Office-Dokument verwendet eine nicht unterstützte ZIP-Struktur.');
        }

        $cursor = $directory['offset'];
        $totalUncompressed = 0;
        $result = [];

        for ($index = 0; $index < $directory['entries']; $index++) {
            if (substr($archive, $cursor, 4) !== "PK\x01\x02") {
                throw new RuntimeException('Das ZIP-Verzeichnis ist beschädigt.');
            }

            $entry = unpack(
                'vversion_made/vversion_needed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vname_length/vextra_length/vcomment_length/vdisk/vinternal/Vexternal/Vlocal_offset',
                substr($archive, $cursor + 4, 42),
            );
            if (! is_array($entry)) {
                throw new RuntimeException('Ein ZIP-Eintrag konnte nicht gelesen werden.');
            }

            $name = substr($archive, $cursor + 46, $entry['name_length']);
            $cursor += 46 + $entry['name_length'] + $entry['extra_length'] + $entry['comment_length'];
            $totalUncompressed += $entry['uncompressed'];

            if ($totalUncompressed > self::MAX_TOTAL_UNCOMPRESSED) {
                throw new RuntimeException('Das Office-Dokument überschreitet das sichere Entpacklimit.');
            }
            if (! $selected($name)) {
                continue;
            }
            if (($entry['flags'] & 1) === 1 || $entry['uncompressed'] > self::MAX_SELECTED_FILE) {
                throw new RuntimeException('Ein benötigter ZIP-Eintrag ist verschlüsselt oder zu groß.');
            }
            if ($entry['compressed'] > 0 && $entry['uncompressed'] / $entry['compressed'] > 200) {
                throw new RuntimeException('Das Office-Dokument überschreitet das sichere Kompressionsverhältnis.');
            }

            $local = $entry['local_offset'];
            if (substr($archive, $local, 4) !== "PK\x03\x04") {
                throw new RuntimeException('Ein lokaler ZIP-Eintrag fehlt.');
            }
            $localHeader = unpack('vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vname_length/vextra_length', substr($archive, $local + 4, 26));
            if (! is_array($localHeader)) {
                throw new RuntimeException('Ein lokaler ZIP-Header ist ungültig.');
            }

            $dataOffset = $local + 30 + $localHeader['name_length'] + $localHeader['extra_length'];
            $compressed = substr($archive, $dataOffset, $entry['compressed']);
            $content = match ($entry['method']) {
                0 => $compressed,
                8 => gzinflate($compressed),
                default => throw new RuntimeException('Das Office-Dokument nutzt eine nicht unterstützte Kompression.'),
            };
            if ($content === false || strlen($content) !== $entry['uncompressed']) {
                throw new RuntimeException('Ein Office-Dokumentinhalt konnte nicht sicher entpackt werden.');
            }

            $result[$name] = $content;
        }

        return $result;
    }
}
