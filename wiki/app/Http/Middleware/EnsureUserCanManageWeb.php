<?php

namespace App\Http\Middleware;

use App\Models\Web;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $web = $request->route('web');

        abort_unless($web instanceof Web && $web->hasRight($request->user(), 'manage'), 403);

        return $next($request);
    }
}
