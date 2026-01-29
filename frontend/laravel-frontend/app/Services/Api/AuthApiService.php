<?php

namespace App\Services\Api;

use App\Services\ApiService;
use Symfony\Component\HttpFoundation\Response;

class AuthApiService extends ApiService
{
    public function login(array $credentials)
    {
        // Gọi tới endpoint /api/auth/login đã cấu hình trong nginx 
        return $this->request()->post($this->baseUrl . '/api/auth/login', [
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'email'     => $credentials['email'],
            'full_name' => $credentials['full_name']

        ]);
    }

    public function register(array $data)
    {
        // Gọi tới endpoint /api/auth/register 
        return $this->request()->post($this->baseUrl . '/api/auth/register', $data);
    }
}