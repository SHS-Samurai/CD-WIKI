<?php

namespace App\Http\Middleware;

use App\Services\ApplicationWriteLock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TransactionalWrites
{
    public function __construct(private readonly ApplicationWriteLock $writeLock) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->routeIs('installation.*')) {
            return $next($request);
        }

        if ($request->routeIs('login.store', 'password.email', 'verification.send')) {
            return $this->writeLock->shared(fn () => $next($request));
        }

        return $this->writeLock->shared(fn (): Response => DB::transaction(fn () => $next($request), 3));
    }
}
