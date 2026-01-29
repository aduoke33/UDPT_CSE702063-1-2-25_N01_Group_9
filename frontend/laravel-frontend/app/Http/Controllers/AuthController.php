<?php

namespace App\Http\Controllers;

use App\Services\Api\AuthApiService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class AuthController extends Controller
{
    protected AuthApiService $authApi;

    public function __construct(AuthApiService $authApi)
    {
        $this->authApi = $authApi;
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);
        /** @var \Illuminate\Http\Client\Response $response */
        $response = $this->authApi->login($credentials);

        if (!$response->successful()) {
            return back()
                ->withErrors(['login_fail' => 'Tài khoản hoặc mật khẩu không đúng!'])
                ->withInput();
        }

        $data = $response->json();

        $token = $data['access_token'] ?? $data['token'] ?? null;
        $user = $data['user'] ?? null;

        if (!$token || !$user) {
            return back()
                ->withErrors(['login_fail' => 'Token hoặc user không tồn tại trong response!'])
                ->withInput();
        }

        Session::put('jwt_token', $token);
        Session::put('user', $user);

        return redirect()
            ->route('movies.index')
            ->with('success', 'Đăng nhập thành công!');
    }

    public function register(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'username' => 'required|string|min:3',
            'email'    => 'required|email',
            'password' => 'required|min:6|confirmed',
            'full_name'=> 'nullable|string',
        ]);
        /** @var \Illuminate\Http\Client\Response $response */
        $response = $this->authApi->register($payload);

        if (!$response->successful()) {
            $errorDetail = $response->json('detail') ?? 'Đăng ký thất bại!';
            return back()
                ->withErrors(['reg_error' => $errorDetail])
                ->withInput();
        }

        return redirect()
            ->route('login')
            ->with('success', 'Đăng ký thành công! Hãy đăng nhập.');
    }

    public function logout(): RedirectResponse
    {
        Session::flush(); // clear toàn bộ session cho sạch
        return redirect()->route('login');
    }
}
