@extends('layouts.app')

@section('title', 'Xác Nhận Đặt Vé - CineBook')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white">Xác Nhận Đặt Vé</h1>
                <p class="text-gray-400 mt-2">Vui lòng kiểm tra thông tin trước khi thanh toán</p>
            </div>

            <!-- Booking Details -->
            <div class="bg-dark-200 rounded-xl p-6 border border-gray-800 mb-6">
                <div class="flex gap-6">
                    <!-- Movie Poster -->
                    <img src="{{ $showtime['movie_poster'] ?? 'https://via.placeholder.com/120x180/1f2937/6b7280' }}"
                        alt="{{ $showtime['movie_title'] ?? 'Movie' }}"
                        class="w-28 h-40 object-cover rounded-lg flex-shrink-0">

                    <!-- Info -->
                    <div class="flex-1">
                        <h2 class="text-xl font-semibold text-white mb-4">{{ $showtime['movie_title'] ?? 'Phim' }}</h2>

                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-400 block">Rạp Chiếu</span>
                                <span class="text-white">{{ $showtime['cinema_name'] ?? 'CineBook Cinema' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block">Phòng Chiếu</span>
                                <span class="text-white">{{ $showtime['room'] ?? 'Cinema 1' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block">Ngày Chiếu</span>
                                <span
                                    class="text-white">{{ isset($showtime['show_date']) ? date('d/m/Y', strtotime($showtime['show_date'])) : (isset($showtime['start_time']) ? date('d/m/Y', strtotime($showtime['start_time'])) : date('d/m/Y')) }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block">Suất Chiếu</span>
                                <span
                                    class="text-white">{{ isset($showtime['show_time']) ? date('H:i', strtotime($showtime['show_time'])) : (isset($showtime['start_time']) ? date('H:i', strtotime($showtime['start_time'])) : '19:00') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Selected Seats -->
            <div class="bg-dark-200 rounded-xl p-6 border border-gray-800 mb-6">
                <h3 class="text-lg font-semibold text-white mb-4">Ghế Đã Chọn</h3>

                <div class="space-y-3">
                    @foreach($selectedSeats as $seat)
                        <div class="flex justify-between items-center py-2 border-b border-gray-700 last:border-0">
                            <div>
                                <span class="text-white font-medium">Ghế
                                    {{ $seat['code'] ?? $seat['seat_number'] ?? 'N/A' }}</span>
                                <span class="text-gray-400 text-sm ml-2">({{ $seat['type'] ?? 'Standard' }})</span>
                            </div>
                            <span class="text-white">{{ number_format($seat['price'] ?? 75000) }}đ</span>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-700">
                    <span class="text-lg text-white font-semibold">Tổng Cộng</span>
                    <span class="text-2xl font-bold text-red-500">{{ number_format($totalPrice) }}đ</span>
                </div>
            </div>

            <!-- Contact Info -->
            <div class="bg-dark-200 rounded-xl p-6 border border-gray-800 mb-6">
                <h3 class="text-lg font-semibold text-white mb-4">Thông Tin Liên Hệ</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <span class="text-gray-400 block text-sm mb-1">Họ Tên</span>
                        <span class="text-white">{{ $currentUser['full_name'] ?? 'Khách Hàng' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-sm mb-1">Email</span>
                        <span class="text-white">{{ $currentUser['email'] ?? 'email@example.com' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-sm mb-1">Số Điện Thoại</span>
                        <span class="text-white">{{ $currentUser['phone'] ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>

            <!-- Terms -->
            <div class="mb-6">
                <label class="flex items-start">
                    <input type="checkbox" id="agree-terms"
                        class="w-4 h-4 mt-1 rounded border-gray-700 bg-dark-100 text-red-500 focus:ring-red-500">
                    <span class="ml-3 text-gray-400 text-sm">
                        Tôi đã đọc và đồng ý với <a href="#" class="text-red-500 hover:text-red-400">Điều khoản đặt vé</a>
                        và <a href="#" class="text-red-500 hover:text-red-400">Chính sách hủy vé</a>
                    </span>
                </label>
            </div>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row gap-4">
                <a href="{{ url()->previous() }}"
                    class="flex-1 py-3 rounded-lg border border-gray-600 text-white font-semibold text-center hover:bg-dark-300 transition">
                    Quay Lại
                </a>
                <form action="{{ route('booking.store') }}" method="POST" class="flex-1">
                    @csrf
                    <button type="submit" id="confirm-btn" disabled
                        class="w-full btn-primary py-3 rounded-lg text-white font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                        Thanh Toán {{ number_format($totalPrice) }}đ
                    </button>
                </form>
            </div>

            <!-- Timer -->
            <div class="text-center mt-6">
                <p class="text-gray-400 text-sm">
                    Thời gian giữ ghế: <span id="timer" class="text-red-500 font-medium">10:00</span>
                </p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Enable/disable confirm button based on terms checkbox
        document.getElementById('agree-terms').addEventListener('change', function () {
            document.getElementById('confirm-btn').disabled = !this.checked;
        });

        // Countdown timer
        let timeLeft = 10 * 60; // 10 minutes
        const timerEl = document.getElementById('timer');

        const countdown = setInterval(() => {
            timeLeft--;

            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            if (timeLeft <= 60) {
                timerEl.classList.add('animate-pulse');
            }

            if (timeLeft <= 0) {
                clearInterval(countdown);
                alert('Thời gian giữ ghế đã hết. Vui lòng chọn lại.');
                window.location.href = '{{ route("movies.index") }}';
            }
        }, 1000);
    </script>
@endpush