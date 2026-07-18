<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AttachmentService
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'txt', 'md', 'xlsx', 'html'];

    public function __construct(private readonly AttachmentTextExtractor $textExtractor) {}

    public function store(Article $article, UploadedFile $file, User $user): Attachment
    {
        $name = $this->safeName($file->getClientOriginalName());
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $this->validateContent($file, $extension);
        try {
            $searchText = $this->textExtractor->extract($file->getRealPath(), $extension);
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'attachment' => 'Die Datei ist beschädigt oder kann nicht sicher verarbeitet werden.',
            ]);
        }
        $storageName = mb_strtolower($name);
        $path = null;

        try {
            return DB::transaction(function () use ($article, $file, $user, $name, $storageName, $extension, $searchText, &$path): Attachment {
                $existing = $article->attachments()
                    ->withTrashed()
                    ->where('storage_name', $storageName)
                    ->lockForUpdate()
                    ->first();
                $uuid = $existing?->uuid ?? (string) Str::uuid();
                $revision = ($existing?->current_revision ?? 0) + 1;
                $path = "attachments/{$article->id}/{$uuid}/revisions/".str_pad((string) $revision, 6, '0', STR_PAD_LEFT).'.'.$extension;

                if (! Storage::disk('local')->putFileAs(dirname($path), $file, basename($path))) {
                    throw ValidationException::withMessages(['attachment' => 'Die Datei konnte nicht gespeichert werden.']);
                }

                $attachment = $existing ?? new Attachment([
                    'uuid' => $uuid,
                    'article_id' => $article->id,
                    'storage_name' => $storageName,
                    'uploaded_by' => $user->id,
                ]);
                $attachment->fill([
                    'original_name' => $name,
                    'path' => $path,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'search_text' => $searchText,
                    'size' => $file->getSize(),
                    'current_revision' => $revision,
                    'updated_by' => $user->id,
                ]);
                $attachment->deleted_at = null;
                $attachment->save();
                $attachment->revisions()->create([
                    'revision_number' => $revision,
                    'path' => $path,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'user_id' => $user->id,
                    'created_at' => now(),
                ]);

                return $attachment;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name));

        if ($name === '' || mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['attachment' => 'Der Dateiname ist ungültig oder zu lang.']);
        }

        return $name;
    }

    private function validateContent(UploadedFile $file, string $extension): void
    {
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['attachment' => 'Erlaubt sind PDF, DOCX, TXT, Markdown, XLSX und HTML.']);
        }

        $content = file_get_contents($file->getRealPath());
        if ($content === false) {
            throw ValidationException::withMessages(['attachment' => 'Die Datei konnte nicht geprüft werden.']);
        }

        if ($extension === 'pdf' && ! str_starts_with($content, '%PDF-')) {
            throw ValidationException::withMessages(['attachment' => 'Die PDF-Signatur ist ungültig.']);
        }
        if (in_array($extension, ['docx', 'xlsx'], true)) {
            $folder = $extension === 'docx' ? 'word/' : 'xl/';
            if (! str_starts_with($content, 'PK') || ! str_contains($content, '[Content_Types].xml') || ! str_contains($content, $folder)) {
                throw ValidationException::withMessages(['attachment' => 'Die Office-Dateistruktur ist ungültig.']);
            }
        }
        if (in_array($extension, ['txt', 'md', 'html'], true) && str_contains($content, "\0")) {
            throw ValidationException::withMessages(['attachment' => 'Textdateien dürfen keine Nullbytes enthalten.']);
        }
    }
}
