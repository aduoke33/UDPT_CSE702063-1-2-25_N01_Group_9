@extends('layouts.app')

@section('title', 'Thanh Toán Thành Công - CineBook')

@section('content')
    <div class="container mx-auto px-4 py-16">
        <div class="max-w-md mx-auto text-center">
            <!-- Success Icon -->
            <div class="w-24 h-24 bg-green-600/20 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <h1 class="text-3xl font-bold text-white mb-4">Thanh Toán Thành Công!</h1>
            <p class="text-gray-400 mb-8">
                Cảm ơn bạn đã đặt vé. Thông tin vé đã được gửi qua email của bạn.
            </p>

            <!-- Booking Summary -->
            <div class="bg-dark-200 rounded-xl p-6 border border-gray-800 text-left mb-8">
                <div class="text-center mb-6">
                    <span class="text-gray-400 text-sm">Mã Đặt Vé</span>
                    <h2 class="text-2xl font-bold text-white">{{ $booking['id'] ?? 'XXXXXX' }}</h2>
                </div>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Phim</span>
                        <span class="text-white">{{ $booking['movie_title'] ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Rạp</span>
                        <span class="text-white">{{ $booking['cinema_name'] ?? 'CineBook Cinema' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Suất Chiếu</span>
                        <span class="text-white">
                            {{ isset($booking['show_date']) ? date('d/m/Y', strtotime($booking['show_date'])) : '' }}
                            {{ isset($booking['show_time']) ? date('H:i', strtotime($booking['show_time'])) : '' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Ghế</span>
                        <span
                            class="text-white">{{ $booking['seat_codes'] ?? (is_array($booking['seats'] ?? null) ? collect($booking['seats'])->pluck('code')->implode(', ') : ($booking['seats'] ?? 'N/A')) }}</span>
                    </div>
                    <div class="flex justify-between pt-3 border-t border-gray-700">
                        <span class="text-gray-400">Tổng Tiền</span>
                        <span class="text-red-500 font-bold">{{ number_format($booking['total_price'] ?? 0) }}đ</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                <a href="{{ route('bookings.ticket', $booking['id'] ?? 1) }}"
                    class="w-full btn-primary py-3 rounded-lg text-white font-semibold flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                    </svg>
                    Xem Vé Điện Tử
                </a>

                <a href="{{ route('home') }}"
                    class="w-full py-3 rounded-lg border border-gray-600 text-white font-semibold hover:bg-dark-300 transition flex items-center justify-center">
                    Về Trang Chủ
                </a>
            </div>
        </div>
    </div>
@endsection