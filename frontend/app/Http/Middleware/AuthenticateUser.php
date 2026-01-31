<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateUser
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
        if (!$this->authService->isAuthenticated()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            
            session(['url.intended' => $request->url()]);
            
            return redirect()->route('login')
                ->with('error', 'Vui long dang nhap de tiep tuc');
        }

        return $next($request);
    }
}
