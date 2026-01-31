@extends('layouts.app')

@section('title', 'Lịch Sử Đặt Vé - CineBook')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-white">Lịch Sử Đặt Vé</h1>
                <p class="text-gray-400 mt-2">Quản lý các vé đã đặt của bạn</p>
            </div>

            <!-- Tabs -->
            <div class="flex space-x-4 mb-8 overflow-x-auto pb-2">
                <a href="{{ route('bookings.my', ['filter' => 'all']) }}"
                    class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ ($filter ?? 'all') === 'all' ? 'bg-red-600 text-white' : 'bg-dark-200 text-gray-400 hover:text-white' }}">
                    Tất Cả ({{ $counts['all'] ?? count($bookings) }})
                </a>
                <a href="{{ route('bookings.my', ['filter' => 'upcoming']) }}"
                    class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ ($filter ?? '') === 'upcoming' ? 'bg-red-600 text-white' : 'bg-dark-200 text-gray-400 hover:text-white' }}">
                    Sắp Chiếu ({{ $counts['upcoming'] ?? 0 }})
                </a>
                <a href="{{ route('bookings.my', ['filter' => 'watched']) }}"
                    class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ ($filter ?? '') === 'watched' ? 'bg-red-600 text-white' : 'bg-dark-200 text-gray-400 hover:text-white' }}">
                    Đã Xem ({{ $counts['watched'] ?? 0 }})
                </a>
                <a href="{{ route('bookings.my', ['filter' => 'cancelled']) }}"
                    class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ ($filter ?? '') === 'cancelled' ? 'bg-red-600 text-white' : 'bg-dark-200 text-gray-400 hover:text-white' }}">
                    Đã Hủy ({{ $counts['cancelled'] ?? 0 }})
                </a>
                <a href="{{ route('bookings.my', ['filter' => 'pending']) }}"
                    class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ ($filter ?? '') === 'pending' ? 'bg-red-600 text-white' : 'bg-dark-200 text-gray-400 hover:text-white' }}">
                    Chờ Thanh Toán ({{ $counts['pending'] ?? 0 }})
                </a>
            </div>

            <!-- Bookings List -->
            @if(count($bookings) > 0)
                <div class="space-y-4">
                    @foreach($bookings as $booking)
                        <div
                            class="bg-dark-200 rounded-xl p-6 border border-gray-800 {{ ($booking['status'] ?? '') === 'cancelled' ? 'opacity-60' : '' }}">
                            <div class="flex flex-col md:flex-row gap-6">
                                <!-- Movie Poster -->
                                <img src="{{ $booking['movie_poster'] ?? 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?w=100&h=150&fit=crop' }}"
                                    alt="{{ $booking['movie_title'] ?? 'Movie' }}"
                                    class="w-24 h-36 object-cover rounded-lg flex-shrink-0 mx-auto md:mx-0">

                                <!-- Info -->
                                <div class="flex-1">
                                    <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                                        <div>
                                            <h3 class="text-lg font-semibold text-white">{{ $booking['movie_title'] ?? 'Phim' }}
                                            </h3>
                                            <p class="text-gray-400 text-sm mt-1">Mã vé: <span
                                                    class="font-mono">{{ strtoupper($booking['id'] ?? 'N/A') }}</span></p>
                                        </div>

                                        <!-- Status Badge -->
                                        @php
                                            $isUpcoming = isset($booking['show_date']) && strtotime($booking['show_date']) > time();
                                            $statusClass = match ($booking['status'] ?? 'pending') {
                                                'confirmed' => $isUpcoming ? 'bg-blue-600/20 text-blue-400' : 'bg-green-600/20 text-green-400',
                                                'pending' => 'bg-yellow-600/20 text-yellow-400',
                                                'cancelled' => 'bg-red-600/20 text-red-400',
                                                'completed' => 'bg-green-600/20 text-green-400',
                                                default => 'bg-gray-600/20 text-gray-400',
                                            };
                                            $statusText = match ($booking['status'] ?? 'pending') {
                                                'confirmed' => $isUpcoming ? 'Sắp Chiếu' : 'Đã Xem',
                                                'pending' => 'Chờ Thanh Toán',
                                                'cancelled' => 'Đã Hủy',
                                                'completed' => 'Đã Xem',
                                                default => 'N/A',
                                            };
                                        @endphp
                                        <span class="inline-block px-3 py-1 rounded-full text-sm {{ $statusClass }} mt-2 md:mt-0">
                                            {{ $statusText }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                        <div>
                                            <span class="text-gray-500 text-sm block">Rạp</span>
                                            <span
                                                class="text-white text-sm">{{ $booking['cinema_name'] ?? 'CineBook Cinema' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 text-sm block">Ngày Chiếu</span>
                                            <span
                                                class="text-white text-sm">{{ isset($booking['show_date']) ? date('d/m/Y', strtotime($booking['show_date'])) : 'N/A' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 text-sm block">Suất Chiếu</span>
                                            <span
                                                class="text-white text-sm">{{ isset($booking['show_time']) ? date('H:i', strtotime($booking['show_time'])) : 'N/A' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 text-sm block">Ghế</span>
                                            <span class="text-white text-sm">
                                                @php
                                                    $seatDisplay = $booking['seat_codes'] ?? null;
                                                    if (!$seatDisplay && isset($booking['seats'])) {
                                                        if (is_array($booking['seats'])) {
                                                            $seatDisplay = collect($booking['seats'])->pluck('code')->filter()->implode(', ');
                                                        } else {
                                                            $seatDisplay = $booking['seats'];
                                                        }
                                                    }
                                                @endphp
                                                {{ $seatDisplay ?: 'N/A' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        class="flex flex-col md:flex-row md:items-center md:justify-between mt-4 pt-4 border-t border-gray-700">
                                        <div>
                                            <span class="text-gray-400 text-sm">Tổng Tiền: </span>
                                            <span
                                                class="text-red-500 font-semibold">{{ number_format($booking['total_price'] ?? 0) }}đ</span>
                                        </div>

                                        <div class="flex gap-3 mt-4 md:mt-0">
                                            @if(($booking['status'] ?? '') === 'confirmed')
                                                <a href="{{ route('bookings.ticket', $booking['id']) }}"
                                                    class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition">
                                                    Xem Vé
                                                </a>
                                                @if($isUpcoming)
                                                    <form action="{{ route('bookings.cancel', $booking['id']) }}" method="POST"
                                                        class="inline">
                                                        @csrf
                                                        <button type="submit" onclick="return confirm('Bạn có chắc muốn hủy vé này?')"
                                                            class="px-4 py-2 border border-red-600 text-red-500 rounded-lg text-sm font-medium hover:bg-red-600/20 transition">
                                                            Hủy Vé
                                                        </button>
                                                    </form>
                                                @endif
                                            @elseif(($booking['status'] ?? '') === 'pending')
                                                <a href="{{ route('payment.show', $booking['id']) }}"
                                                    class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition">
                                                    Thanh Toán
                                                </a>
                                                <form action="{{ route('bookings.cancel', $booking['id']) }}" method="POST"
                                                    class="inline">
                                                    @csrf
                                                    <button type="submit" onclick="return confirm('Bạn có chắc muốn hủy đơn này?')"
                                                        class="px-4 py-2 border border-gray-600 text-gray-400 rounded-lg text-sm font-medium hover:bg-dark-300 transition">
                                                        Hủy
                                                    </button>
                                                </form>
                                            @else
                                                <a href="{{ route('bookings.show', $booking['id']) }}"
                                                    class="px-4 py-2 border border-gray-600 text-gray-400 rounded-lg text-sm font-medium hover:bg-dark-300 transition">
                                                    Chi Tiết
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-16">
                    <svg class="w-20 h-20 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                    </svg>
                    <h3 class="text-xl font-semibold text-white mb-2">Chưa có vé nào</h3>
                    <p class="text-gray-400 mb-6">Bạn chưa đặt vé xem phim nào</p>
                    <a href="{{ route('movies.index') }}"
                        class="btn-primary inline-block px-6 py-3 rounded-lg text-white font-medium">
                        Khám Phá Phim
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection