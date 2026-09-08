<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureUserIsAdmin
{
    /**
     * Allow only active admin users through.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin() || ! $user->isActive()) {
            throw new AccessDeniedHttpException('You are not authorized to perform this action.');
        }

        return $next($request);
    }
}
