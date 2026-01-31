<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ApiService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('api.gateway_url');
        $this->timeout = config('api.timeout', 30);
    }

    /**
     * Get auth token from session
     */
    protected function getToken(): ?string
    {
        return Session::get('auth_token');
    }

    /**
     * Build HTTP client with common settings
     */
    protected function client(bool $withAuth = true)
    {
        $client = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson();

        if ($withAuth && $this->getToken()) {
            $client = $client->withToken($this->getToken());
        }

        return $client;
    }

    /**
     * Make GET request
     */
    public function get(string $endpoint, array $params = [], bool $withAuth = true): array
    {
        try {
            $response = $this->client($withAuth)->get($endpoint, $params);
            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('API GET Error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Connection error. Please try again.'];
        }
    }

    /**
     * Make POST request
     */
    public function post(string $endpoint, array $data = [], bool $withAuth = true): array
    {
        try {
            $response = $this->client($withAuth)->post($endpoint, $data);
            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('API POST Error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Connection error. Please try again.'];
        }
    }

    /**
     * Make PUT request
     */
    public function put(string $endpoint, array $data = [], bool $withAuth = true): array
    {
        try {
            $response = $this->client($withAuth)->put($endpoint, $data);
            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('API PUT Error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Connection error. Please try again.'];
        }
    }

    /**
     * Make DELETE request
     */
    public function delete(string $endpoint, bool $withAuth = true): array
    {
        try {
            $response = $this->client($withAuth)->delete($endpoint);
            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('API DELETE Error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Connection error. Please try again.'];
        }
    }

    /**
     * Handle API response
     */
    protected function handleResponse($response): array
    {
        $data = $response->json() ?? [];

        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $data,
                'status' => $response->status()
            ];
        }

        // Handle unauthorized - clear session
        if ($response->status() === 401) {
            Session::forget(['auth_token', 'user']);
        }

        return [
            'success' => false,
            'error' => $data['detail'] ?? $data['message'] ?? 'An error occurred',
            'errors' => $data['errors'] ?? [],
            'status' => $response->status()
        ];
    }
}
