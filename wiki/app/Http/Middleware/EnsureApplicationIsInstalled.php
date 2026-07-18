<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationIsInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests() || $request->routeIs('installation.*')) {
            return $next($request);
        }

        if (! is_file(storage_path('app/installed'))) {
            return redirect()->route('installation.create');
        }

        return $next($request);
    }
}
