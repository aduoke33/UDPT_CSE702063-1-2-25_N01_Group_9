<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ApiService
{
    protected $baseUrl;
    protected $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.backend.gateway_url');
        $this->timeout = (float) config('services.backend.timeout', 15.0);
    }

    protected function request(): PendingRequest
    {
        $token = Session::get('jwt_token');
        $correlationId = (string) Str::uuid();

        return Http::withHeaders([
            'Accept'           => 'application/json',
            'X-Correlation-ID' => $correlationId,
            'User-Agent'       => request()->header('User-Agent'), // Gửi thông tin trình duyệt khách hàng sang Backend
        ])
        ->when($token, function ($request, $token) {
            return $request->withToken($token);
        })
        ->timeout($this->timeout)
        ->retry(2, 100) // Tự động thử lại 2 lần nếu mạng chập chờn (cách nhau 100ms)
        ->beforeSending(function ($request) use ($correlationId) {
            // Log lại mọi request gửi đi để dễ debug sau này
            Log::info("API Request: [{$correlationId}] {$request->method()} {$request->url()}");
        });
    }
}