<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareAuthData
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isAuthenticated = $this->authService->isAuthenticated();
        $user = $isAuthenticated ? $this->authService->getUser() : null;

        View::share('isAuthenticated', $isAuthenticated);
        View::share('currentUser', $user);

        return $next($request);
    }
}
