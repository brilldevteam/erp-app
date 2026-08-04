<?php

namespace App\Http\Middleware;

use App\Services\AuthSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthSessionIsCurrent
{
    public function __construct(private readonly AuthSessionService $authSessions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && !$this->authSessions->isCurrentRequestValid($request)) {
            $response = $this->authSessions->revokedResponse($request);
            $this->authSessions->forgetCurrentAuthentication($request);

            return $response;
        }

        return $next($request);
    }
}
