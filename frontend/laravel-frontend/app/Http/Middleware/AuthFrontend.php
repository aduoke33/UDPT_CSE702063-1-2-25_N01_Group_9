<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class AuthFrontend
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Kiểm tra xem session có lưu token từ API trả về không
        if (!Session::has('jwt_token')) {
            return redirect()
                ->route('login')
                ->with('info', 'Bạn vui lòng đăng nhập để tiếp tục thực hiện đặt vé nhé!');
        }

        return $next($request);
    }
}