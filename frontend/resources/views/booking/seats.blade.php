@extends('layouts.app')

@section('title', 'Chọn Ghế - CineBook')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Back Button -->
    <a href="{{ route('movies.show', $showtime['movie_id'] ?? 1) }}" class="inline-flex items-center text-gray-400 hover:text-white mb-6">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Quay Lại
    </a>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Seat Map -->
        <div class="lg:col-span-2">
            <div class="bg-dark-200 rounded-xl p-6 border border-gray-800">
                <h2 class="text-xl font-semibold text-white mb-6">Chọn Ghế Ngồi</h2>
                
                <!-- Screen -->
                <div class="mb-8">
                    <div class="h-2 bg-gradient-to-r from-transparent via-gray-400 to-transparent rounded-full mb-2"></div>
                    <p class="text-center text-gray-500 text-sm">MÀN HÌNH</p>
                </div>
                
                <!-- Seats -->
                <div class="flex justify-center mb-8">
                    <div id="seat-map" class="inline-block">
                        @php
                            $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                            $cols = 12;
                            
                            // Get booked seats from API response or session
                            $bookedSeats = [];
                            if (isset($seats['booked_seats'])) {
                                foreach ($seats['booked_seats'] as $seat) {
                                    $bookedSeats[] = $seat['seat_row'] . $seat['seat_number'];
                                }
                            }
                            
                            // Also check session for locally booked seats
                            $bookingHistory = session('booking_history', []);
                            foreach ($bookingHistory as $booking) {
                                if (($booking['showtime_id'] ?? '') === ($showtime['id'] ?? '') && ($booking['status'] ?? '') !== 'cancelled') {
                                    $bookingSeats = $booking['seats'] ?? [];
                                    foreach ($bookingSeats as $seat) {
                                        if (is_array($seat)) {
                                            $bookedSeats[] = $seat['code'] ?? $seat['seat_number'] ?? '';
                                        }
                                    }
                                }
                            }
                            $bookedSeats = array_unique($bookedSeats);
                        @endphp
                        
                        @foreach($rows as $rowIndex => $row)
                        <div class="flex items-center mb-2">
                            <span class="w-6 text-gray-500 text-sm">{{ $row }}</span>
                            <div class="flex gap-2">
                                @for($col = 1; $col <= $cols; $col++)
                                    @php
                                        $seatId = ($rowIndex * $cols) + $col;
                                        $seatCode = $row . $col;
                                        $isBooked = in_array($seatCode, $bookedSeats);
                                        $isVip = $row >= 'D' && $row <= 'F' && $col >= 4 && $col <= 9;
                                        $price = $isVip ? 100000 : 75000;
                                        $status = $isBooked ? 'occupied' : 'available';
                                    @endphp
                                    
                                    @if($col == 3 || $col == 10)
                                        <div class="w-4"></div>
                                    @endif
                                    
                                    <button 
                                        type="button"
                                        class="seat {{ $status === 'occupied' ? 'occupied' : ($isVip ? 'vip available' : 'available') }}"
                                        data-seat-id="{{ $seatId }}"
                                        data-seat-code="{{ $seatCode }}"
                                        data-price="{{ $price }}"
                                        data-type="{{ $isVip ? 'VIP' : 'Standard' }}"
                                        {{ $status === 'occupied' ? 'disabled' : '' }}
                                        onclick="toggleSeat(this)"
                                    >
                                    </button>
                                @endfor
                            </div>
                            <span class="w-6 text-gray-500 text-sm text-right">{{ $row }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                
                <!-- Legend -->
                <div class="flex flex-wrap justify-center gap-6 text-sm">
                    <div class="flex items-center">
                        <div class="seat available w-6 h-6 mr-2"></div>
                        <span class="text-gray-400">Ghế Thường (75.000đ)</span>
                    </div>
                    <div class="flex items-center">
                        <div class="seat vip w-6 h-6 mr-2"></div>
                        <span class="text-gray-400">Ghế VIP (100.000đ)</span>
                    </div>
                    <div class="flex items-center">
                        <div class="seat selected w-6 h-6 mr-2"></div>
                        <span class="text-gray-400">Đang Chọn</span>
                    </div>
                    <div class="flex items-center">
                        <div class="seat occupied w-6 h-6 mr-2"></div>
                        <span class="text-gray-400">Đã Đặt</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Booking Summary -->
        <div class="lg:col-span-1">
            <div class="bg-dark-200 rounded-xl p-6 border border-gray-800 sticky top-24">
                <!-- Movie Info -->
                <div class="flex gap-4 pb-6 border-b border-gray-700">
                    <img 
                        src="{{ $showtime['movie_poster'] ?? 'https://via.placeholder.com/80x120/1f2937/6b7280' }}" 
                        alt="{{ $showtime['movie_title'] ?? 'Movie' }}"
                        class="w-20 h-28 object-cover rounded-lg"
                    >
                    <div>
                        <h3 class="text-white font-semibold">{{ $showtime['movie_title'] ?? 'Phim' }}</h3>
                        <p class="text-gray-400 text-sm mt-1">{{ $showtime['format'] ?? '2D Phu De' }}</p>
                        <p class="text-gray-400 text-sm">{{ $showtime['age_rating'] ?? 'P' }}</p>
                    </div>
                </div>
                
                <!-- Showtime Info -->
                <div class="py-6 border-b border-gray-700">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-400">Rạp</span>
                        <span class="text-white">{{ $showtime['cinema_name'] ?? 'CineBook Cinema' }}</span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-400">Ngày</span>
                        <span class="text-white">{{ isset($showtime['show_date']) ? date('d/m/Y', strtotime($showtime['show_date'])) : (isset($showtime['start_time']) ? date('d/m/Y', strtotime($showtime['start_time'])) : date('d/m/Y')) }}</span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-400">Suất Chiếu</span>
                        <span class="text-white">{{ isset($showtime['show_time']) ? date('H:i', strtotime($showtime['show_time'])) : (isset($showtime['start_time']) ? date('H:i', strtotime($showtime['start_time'])) : '19:00') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Phòng</span>
                        <span class="text-white">{{ $showtime['room'] ?? 'Cinema 1' }}</span>
                    </div>
                </div>
                
                <!-- Selected Seats -->
                <div class="py-6 border-b border-gray-700">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-400">Ghế Đã Chọn</span>
                        <span id="selected-seats" class="text-white">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Số Lượng</span>
                        <span id="seat-count" class="text-white">0</span>
                    </div>
                </div>
                
                <!-- Total -->
                <div class="py-6">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Tổng Cộng</span>
                        <span id="total-price" class="text-2xl font-bold text-red-500">0đ</span>
                    </div>
                </div>
                
                <!-- Continue Button -->
                <button 
                    id="continue-btn"
                    onclick="proceedToCheckout()"
                    disabled
                    class="w-full btn-primary py-3 rounded-lg text-white font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Tiếp Tục
                </button>
                
                <p class="text-center text-gray-500 text-xs mt-4">
                    Ghế sẽ được giữ trong 10 phút
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const selectedSeats = new Map();
const showtimeId = '{{ $showtime['id'] ?? '' }}';

function toggleSeat(btn) {
    if (btn.classList.contains('occupied')) return;
    
    const seatId = btn.dataset.seatId;
    const seatCode = btn.dataset.seatCode;
    const price = parseInt(btn.dataset.price);
    const type = btn.dataset.type;
    
    if (selectedSeats.has(seatId)) {
        selectedSeats.delete(seatId);
        btn.classList.remove('selected');
        if (type === 'VIP') {
            btn.classList.add('vip');
        }
    } else {
        if (selectedSeats.size >= 8) {
            alert('Bạn chỉ có thể chọn tối đa 8 ghế');
            return;
        }
        selectedSeats.set(seatId, { code: seatCode, price: price, type: type });
        btn.classList.add('selected');
        btn.classList.remove('vip');
    }
    
    updateSummary();
}

function updateSummary() {
    const seatCodes = Array.from(selectedSeats.values()).map(s => s.code);
    const totalPrice = Array.from(selectedSeats.values()).reduce((sum, s) => sum + s.price, 0);
    
    document.getElementById('selected-seats').textContent = seatCodes.length > 0 ? seatCodes.join(', ') : '-';
    document.getElementById('seat-count').textContent = selectedSeats.size;
    document.getElementById('total-price').textContent = formatCurrency(totalPrice);
    
    const btn = document.getElementById('continue-btn');
    btn.disabled = selectedSeats.size === 0;
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
}

function proceedToCheckout() {
    if (selectedSeats.size === 0) {
        alert('Vui lòng chọn ít nhất 1 ghế');
        return;
    }
    
    const btn = document.getElementById('continue-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="animate-pulse">Đang xử lý...</span>';
    
    // Keep seat_ids as strings (not parseInt) for UUID compatibility
    const seatIds = Array.from(selectedSeats.keys());
    
    fetch('{{ route("booking.hold-seats") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            showtime_id: showtimeId,
            seat_ids: seatIds,
        }),
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = data.redirect || '{{ route("booking.confirm") }}';
        } else {
            alert(data.message || 'Có lỗi xảy ra. Vui lòng thử lại.');
            btn.disabled = false;
            btn.innerHTML = 'Tiếp Tục';
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Có lỗi xảy ra. Vui lòng thử lại.');
        btn.disabled = false;
        btn.innerHTML = 'Tiếp Tục';
    });
}
</script>
@endpush
