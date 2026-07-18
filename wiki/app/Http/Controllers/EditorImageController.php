<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadEditorImageRequest;
use App\Models\EditorImage;
use App\Models\Web;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EditorImageController extends Controller
{
    public function store(UploadEditorImageRequest $request, Web $web, AuditLogger $audit): JsonResponse
    {
        abort_unless($web->hasRight($request->user(), 'view') && $web->hasRight($request->user(), 'upload'), 403);

        $file = $request->file('image');
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->guessExtension() ?: $file->extension());
        $path = "wiki-images/{$web->id}/{$uuid}.{$extension}";

        if (! Storage::disk('local')->putFileAs("wiki-images/{$web->id}", $file, "{$uuid}.{$extension}")) {
            abort(500, 'Das Bild konnte nicht gespeichert werden.');
        }

        try {
            $image = EditorImage::query()->create([
                'uuid' => $uuid,
                'web_id' => $web->id,
                'user_id' => $request->user()->id,
                'path' => $path,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
            $audit->write('editor_image.uploaded', $image, ['name' => $image->original_name], web: $web);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return response()->json([
            'url' => route('editor-images.show', $image),
            'alt' => pathinfo($image->original_name, PATHINFO_FILENAME),
        ], 201);
    }

    public function show(Request $request, EditorImage $image): StreamedResponse
    {
        $image->loadMissing(['web', 'article']);

        if ($image->article_id !== null) {
            abort_unless($image->article && $image->web->hasRight($request->user(), 'view'), 403);
        } else {
            abort_unless($request->user()?->is_admin || $request->user()?->id === $image->user_id, 403);
        }

        abort_unless(Storage::disk('local')->exists($image->path), 404);

        return Storage::disk('local')->response($image->path, $image->original_name, [
            'Content-Type' => $image->mime_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
