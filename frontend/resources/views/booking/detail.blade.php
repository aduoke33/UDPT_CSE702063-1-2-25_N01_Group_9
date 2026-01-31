@extends('layouts.app')

@section('title', 'Chi Tiết Đặt Vé - CineBook')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <!-- Back Button -->
            <a href="{{ route('bookings.my') }}" class="inline-flex items-center text-gray-400 hover:text-white mb-6">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Quay Lại
            </a>

            <!-- Booking Info -->
            <div class="bg-dark-200 rounded-xl p-6 border border-gray-800 mb-6">
                <!-- Status Header -->
                <div class="flex items-center justify-between mb-6 pb-6 border-b border-gray-700">
                    <div>
                        <span class="text-gray-400 text-sm">Mã Đặt Vé</span>
                        <h2 class="text-xl font-bold text-white">{{ strtoupper($booking['id'] ?? 'N/A') }}</h2>
                    </div>
                    @php
                        $statusClass = match ($booking['status'] ?? 'pending') {
                            'confirmed' => 'bg-green-600/20 text-green-400 border-green-600/50',
                            'pending' => 'bg-yellow-600/20 text-yellow-400 border-yellow-600/50',
                            'cancelled' => 'bg-red-600/20 text-red-400 border-red-600/50',
                            'completed' => 'bg-blue-600/20 text-blue-400 border-blue-600/50',
                            default => 'bg-gray-600/20 text-gray-400 border-gray-600/50',
                        };
                        $statusText = match ($booking['status'] ?? 'pending') {
                            'confirmed' => 'Đã Xác Nhận',
                            'pending' => 'Chờ Thanh Toán',
                            'cancelled' => 'Đã Hủy',
                            'completed' => 'Đã Xem',
                            default => 'N/A',
                        };
                    @endphp
                    <span class="px-4 py-2 rounded-full border {{ $statusClass }}">
                        {{ $statusText }}
                    </span>
                </div>

                <!-- Movie Details -->
                <div class="flex gap-6">
                    <img src="{{ $booking['movie_poster'] ?? 'https://via.placeholder.com/120x180/1f2937/6b7280' }}"
                        alt="{{ $booking['movie_title'] ?? 'Movie' }}"
                        class="w-28 h-40 object-cover rounded-lg flex-shrink-0">
                    <div class="flex-1">
                        <h3 class="text-xl font-semibold text-white mb-4">{{ $booking['movie_title'] ?? 'Phim' }}</h3>

                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-400 block">Rạp Chiếu</span>
                                <span class="text-white">{{ $booking['cinema_name'] ?? 'CineBook Cinema' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block">Phòng Chiếu</span>
                                <span class="text-white">{{ $booking['room'] ?? 'Cinema 1' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block">Ngày Chiếu</span>
                                <span
                                    class="text-white">{{ isset($booking['show_date']) ? date('d/m/Y', strtotime($booking['show_date'])) : 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block">Giờ Chiếu</span>
                                <span
                                    class="text-white">{{ isset($booking['show_time']) ? date('H:i', strtotime($booking['show_time'])) : 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seats & Price -->
            <div class="bg-dark-200 rounded-xl p-6 border border-gray-800 mb-6">
                <h3 class="text-lg font-semibold text-white mb-4">Thông Tin Vé</h3>

                <div class="space-y-3">
                    @php
                        $seats = $booking['seats'] ?? [];
                        if (is_string($seats)) {
                            $seats = array_map('trim', explode(',', $seats));
                            $seats = array_map(function ($s) {
                                return ['code' => $s, 'price' => 75000]; }, $seats);
                        }
                    @endphp

                    @forelse($seats as $seat)
                        <div class="flex justify-between items-center py-2 border-b border-gray-700 last:border-0">
                            <span class="text-white">Ghế
                                {{ is_array($seat) ? ($seat['code'] ?? $seat['seat_number'] ?? 'N/A') : $seat }}</span>
                            <span
                                class="text-white">{{ number_format(is_array($seat) ? ($seat['price'] ?? 75000) : 75000) }}đ</span>
                        </div>
                    @empty
                        <div class="text-gray-400">Không có thông tin ghế</div>
                    @endforelse
                </div>

                <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-700">
                    <span class="text-lg text-white font-semibold">Tổng Cộng</span>
                    <span class="text-2xl font-bold text-red-500">{{ number_format($booking['total_price'] ?? 0) }}đ</span>
                </div>
            </div>

            <!-- Payment Info -->
            @if(isset($booking['payment']) || ($booking['status'] ?? '') === 'confirmed')
                <div class="bg-dark-200 rounded-xl p-6 border border-gray-800 mb-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Thông Tin Thanh Toán</h3>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-400 block">Phương Thức</span>
                            <span class="text-white">{{ $booking['payment']['method'] ?? 'Chuyển Khoản' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-400 block">Trạng Thái</span>
                            <span class="text-green-400">Đã Thanh Toán</span>
                        </div>
                        <div>
                            <span class="text-gray-400 block">Thời Gian</span>
                            <span
                                class="text-white">{{ isset($booking['created_at']) ? date('d/m/Y H:i', strtotime($booking['created_at'])) : date('d/m/Y H:i') }}</span>
                        </div>
                        <div>
                            <span class="text-gray-400 block">Mã Giao Dịch</span>
                            <span class="text-white font-mono">{{ strtoupper($booking['id'] ?? 'N/A') }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row gap-4">
                @if(($booking['status'] ?? '') === 'confirmed')
                    <a href="{{ route('bookings.ticket', $booking['id']) }}"
                        class="flex-1 btn-primary py-3 rounded-lg text-white font-semibold text-center">
                        Xem Vé Điện Tử
                    </a>
                    @if(isset($booking['show_date']) && strtotime($booking['show_date']) > time())
                        <form action="{{ route('bookings.cancel', $booking['id']) }}" method="POST" class="flex-1">
                            @csrf
                            <button type="submit"
                                class="w-full py-3 rounded-lg border border-red-600 text-red-500 font-semibold hover:bg-red-600/20 transition"
                                onclick="return confirm('Bạn có chắc muốn hủy đơn đặt vé này? Tiền sẽ được hoàn lại trong 3-5 ngày làm việc.')">
                                Hủy Vé
                            </button>
                        </form>
                    @endif
                @elseif(($booking['status'] ?? '') === 'pending')
                    <a href="{{ route('payment.show', $booking['id']) }}"
                        class="flex-1 btn-primary py-3 rounded-lg text-white font-semibold text-center">
                        Thanh Toán Ngay
                    </a>
                    <form action="{{ route('bookings.cancel', $booking['id']) }}" method="POST" class="flex-1">
                        @csrf
                        <button type="submit"
                            class="w-full py-3 rounded-lg border border-red-600 text-red-500 font-semibold hover:bg-red-600/20 transition"
                            onclick="return confirm('Bạn có chắc muốn hủy đơn đặt vé này?')">
                            Hủy Đơn
                        </button>
                    </form>
                @elseif(($booking['status'] ?? '') === 'cancelled')
                    <div class="flex-1 text-center text-gray-400">
                        Đơn đặt vé này đã bị hủy
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection