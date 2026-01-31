<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Home
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/search', [HomeController::class, 'search'])->name('search');

// Auth Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Movies
Route::prefix('movies')->name('movies.')->group(function () {
    Route::get('/', [MovieController::class, 'index'])->name('index');
    Route::get('/now-showing', [MovieController::class, 'nowShowing'])->name('now-showing');
    Route::get('/coming-soon', [MovieController::class, 'comingSoon'])->name('coming-soon');
    Route::get('/{id}', [MovieController::class, 'show'])->name('show');
    Route::get('/{id}/showtimes', [MovieController::class, 'getShowtimes'])->name('showtimes');
});

// API Routes for AJAX
Route::prefix('api')->group(function () {
    Route::get('/showtimes/{id}/seats', [MovieController::class, 'getSeats']);
});

// Protected Routes
Route::middleware('auth.user')->group(function () {
    // Profile
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/change-password', [AuthController::class, 'changePassword'])->name('profile.change-password');

    // Booking
    Route::prefix('booking')->name('booking.')->group(function () {
        Route::get('/seats/{showtimeId}', [BookingController::class, 'selectSeats'])->name('seats');
        Route::post('/hold-seats', [BookingController::class, 'holdSeats'])->name('hold-seats');
        Route::get('/confirm', [BookingController::class, 'confirm'])->name('confirm');
        Route::post('/store', [BookingController::class, 'store'])->name('store');
    });

    // User Bookings
    Route::prefix('bookings')->name('bookings.')->group(function () {
        Route::get('/', [BookingController::class, 'myBookings'])->name('my');
        Route::get('/{id}', [BookingController::class, 'show'])->name('show');
        Route::post('/{id}/cancel', [BookingController::class, 'cancel'])->name('cancel');
        Route::get('/{id}/ticket', [BookingController::class, 'ticket'])->name('ticket');
    });

    // Payment
    Route::prefix('payment')->name('payment.')->group(function () {
        Route::get('/{bookingId}', [PaymentController::class, 'show'])->name('show');
        Route::post('/{bookingId}', [PaymentController::class, 'process'])->name('process');
        Route::get('/{bookingId}/success', [PaymentController::class, 'successPage'])->name('success');
        Route::get('/{bookingId}/failed', [PaymentController::class, 'failedPage'])->name('failed');
        Route::get('/history', [PaymentController::class, 'history'])->name('history');
    });

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('mark-read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
    });
});
