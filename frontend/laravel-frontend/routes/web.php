<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\NotificationController;

/*
|--------------------------------------------------------------------------
| Public Routes (Ai cũng xem được)
|--------------------------------------------------------------------------
*/

// Đổi trang chủ mặc định sang danh sách phim
Route::get('/', [MovieController::class, 'index'])->name('home');
Route::get('/movies', [MovieController::class, 'index'])->name('movies.index');
Route::get('/movies/{id}', [MovieController::class, 'show'])->name('movies.show');

// Auth Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Protected Routes (Bắt buộc phải đăng nhập - auth.frontend)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.frontend'])->group(function () {
    
    // Booking - Bước 1: Chọn ghế
    Route::get('/booking/seats/{showtimeId}', [BookingController::class, 'selectSeats'])->name('booking.seats');
    Route::post('/booking/process', [BookingController::class, 'processBooking'])->name('booking.process');

    // Booking - Bước 2 & 3: Thanh toán (Cần thêm booking.guard để tránh vào thẳng link)
    Route::middleware(['booking.guard'])->group(function () {
        Route::get('/payment/checkout', [PaymentController::class, 'showCheckout'])->name('payment.checkout');
        Route::get('/payment/processing', [PaymentController::class, 'processing'])->name('booking.processing');
        Route::get('/payment/success', [PaymentController::class, 'success'])->name('booking.success');
    });

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
});