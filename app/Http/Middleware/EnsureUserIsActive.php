<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureUserIsActive
{
    /**
     * Allow only active users through.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActive()) {
            throw new AccessDeniedHttpException('Your account has been deactivated.');
        }

        return $next($request);
    }
}
