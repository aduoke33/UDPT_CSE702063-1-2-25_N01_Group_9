<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class AuthService extends ApiService
{
    /**
     * Login user - OAuth2 form format
     */
    public function login(string $email, string $password): array
    {
        try {
            // OAuth2 requires form-urlencoded data
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->asForm()
                ->post(config('api.endpoints.auth.login'), [
                    'username' => $email,
                    'password' => $password,
                ]);

            $result = $this->handleResponse($response);

            if ($result['success'] && isset($result['data']['access_token'])) {
                Session::put('auth_token', $result['data']['access_token']);
                
                // Store user info from login response
                if (isset($result['data']['user'])) {
                    Session::put('user', $result['data']['user']);
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Login error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Lỗi kết nối. Vui lòng thử lại.'];
        }
    }

    /**
     * Register new user
     */
    public function register(array $data): array
    {
        return $this->post(
            config('api.endpoints.auth.register'),
            $data,
            false
        );
    }

    /**
     * Logout user
     */
    public function logout(): array
    {
        try {
            $response = $this->post(config('api.endpoints.auth.logout'));
        } catch (\Exception $e) {
            // Ignore logout errors
        }
        
        Session::forget(['auth_token', 'user']);
        
        return ['success' => true, 'message' => 'Đăng xuất thành công'];
    }

    /**
     * Get current user
     */
    public function getCurrentUser(): array
    {
        return $this->get(config('api.endpoints.auth.me'));
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return Session::has('auth_token');
    }

    /**
     * Get authenticated user from session
     */
    public function getUser(): ?array
    {
        return Session::get('user');
    }

    /**
     * Refresh user data from API
     */
    public function refreshUser(): array
    {
        $response = $this->getCurrentUser();
        
        if ($response['success']) {
            Session::put('user', $response['data']);
        }
        
        return $response;
    }

    /**
     * Update user profile
     */
    public function updateProfile(array $data): array
    {
        $response = $this->put(
            config('api.endpoints.auth.update_profile', '/api/auth/profile'),
            $data,
            true
        );

        if ($response['success']) {
            // Update session with new user data
            $user = Session::get('user', []);
            $user = array_merge($user, $data);
            Session::put('user', $user);
        }

        return $response;
    }

    /**
     * Change user password
     */
    public function changePassword(string $currentPassword, string $newPassword): array
    {
        return $this->post(
            config('api.endpoints.auth.change_password', '/api/auth/change-password'),
            [
                'current_password' => $currentPassword,
                'new_password' => $newPassword,
            ],
            true
        );
    }
}
