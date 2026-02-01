<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Gateway Configuration
    |--------------------------------------------------------------------------
    */
    'gateway_url' => env('API_GATEWAY_URL', 'http://nginx:80'),
    'timeout' => env('API_TIMEOUT', 30),
    
    /*
    |--------------------------------------------------------------------------
    | Service Endpoints
    |--------------------------------------------------------------------------
    */
    'endpoints' => [
        'auth' => [
            'login' => '/api/auth/token',
            'register' => '/api/auth/register',
            'logout' => '/api/auth/logout',
            'me' => '/api/auth/verify',
            'refresh' => '/api/auth/refresh',
            'update_profile' => '/api/auth/profile',
            'change_password' => '/api/auth/change-password',
        ],
        'movies' => [
            'list' => '/api/movies/movies',
            'detail' => '/api/movies/movies/{id}',
            'search' => '/api/movies/movies',
            'now_showing' => '/api/movies/movies',
            'coming_soon' => '/api/movies/movies',
        ],
        'showtimes' => [
            'list' => '/api/movies/showtimes',
            'by_movie' => '/api/movies/showtimes',
            'detail' => '/api/movies/showtimes/{id}',
            'available_seats' => '/api/movies/showtimes/{id}/available-seats',
        ],
        'theaters' => [
            'list' => '/api/movies/theaters',
            'seats' => '/api/movies/seats/{theater_id}',
        ],
        'bookings' => [
            'create' => '/api/bookings/book',
            'list' => '/api/bookings/bookings',
            'detail' => '/api/bookings/bookings/{id}',
            'cancel' => '/api/bookings/bookings/{id}/cancel',
            'my_bookings' => '/api/bookings/bookings',
            'hold_seats' => '/api/bookings/seats/hold',
            'release_seats' => '/api/bookings/seats/release',
            'confirm' => '/api/bookings/bookings/{id}/confirm',
        ],
        'seats' => [
            'by_showtime' => '/api/movies/showtimes/{showtime_id}/available-seats',
            'lock' => '/api/bookings/seats/lock',
            'unlock' => '/api/bookings/seats/unlock',
        ],
        'payments' => [
            'create' => '/api/payments/process',
            'detail' => '/api/payments/payments/{id}',
            'process' => '/api/payments/process',
            'verify' => '/api/payments/payments/{id}/verify',
            'methods' => '/api/payments/methods',
            'history' => '/api/payments/payments',
            'refund' => '/api/payments/payments/{id}/refund',
        ],
        'notifications' => [
            'list' => '/api/notifications/notifications',
            'mark_read' => '/api/notifications/notifications/{id}/read',
            'mark_all_read' => '/api/notifications/notifications/read-all',
        ],
    ],
];
