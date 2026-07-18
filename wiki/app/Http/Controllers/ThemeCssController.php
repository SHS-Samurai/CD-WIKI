<?php

namespace App\Http\Controllers;

use App\Services\ThemeService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThemeCssController extends Controller
{
    public function __invoke(Request $request, ThemeService $service): Response
    {
        $theme = $service->current();
        $css = $service->css($theme);
        $etag = '"'.hash('sha256', $css).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response($css)->withHeaders([
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
