<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Article;
use App\Models\Attachment;
use App\Models\AttachmentRevision;
use App\Models\Web;
use App\Services\AttachmentService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request, Web $web, Article $article, AttachmentService $service, AuditLogger $audit): RedirectResponse
    {
        abort_unless($web->hasRight($request->user(), 'view') && $web->hasRight($request->user(), 'upload'), 403);
        $attachment = $service->store($article, $request->file('attachment'), $request->user());
        try {
            $audit->write('attachment.saved', $attachment, ['revision' => $attachment->current_revision], article: $article, web: $web);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($attachment->path);
            throw $exception;
        }

        return back()->with('status', 'Der Anhang wurde gespeichert.');
    }

    public function download(Request $request, Web $web, Article $article, Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->article_id === $article->id && $web->hasRight($request->user(), 'view'), 403);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function downloadRevision(Request $request, Web $web, Article $article, Attachment $attachment, AttachmentRevision $revision): StreamedResponse
    {
        abort_unless(
            $attachment->article_id === $article->id
            && $revision->attachment_id === $attachment->id
            && $web->hasRight($request->user(), 'view'),
            403,
        );
        abort_unless(Storage::disk('local')->exists($revision->path), 404);

        return Storage::disk('local')->download(
            $revision->path,
            "Revision-{$revision->revision_number}-{$attachment->original_name}",
            [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    public function destroy(Request $request, Web $web, Article $article, Attachment $attachment, AuditLogger $audit): RedirectResponse
    {
        abort_unless($attachment->article_id === $article->id && $web->hasRight($request->user(), 'view') && $web->hasRight($request->user(), 'delete'), 403);
        $attachment->delete();
        $audit->write('attachment.deleted', $attachment, article: $article, web: $web);

        return back()->with('status', 'Der Anhang wurde in den Papierkorb verschoben.');
    }
}
