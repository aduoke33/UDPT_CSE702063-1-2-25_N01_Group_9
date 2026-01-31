@extends('layouts.app')

@section('title', 'Vé Điện Tử - CineBook')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto">
            <!-- Ticket Card -->
            <div class="bg-gradient-to-br from-red-600 to-red-800 rounded-3xl overflow-hidden shadow-2xl" id="ticket">
                <!-- Header -->
                <div class="bg-white/10 px-6 py-4 text-center">
                    <div class="flex items-center justify-center space-x-2 mb-2">
                        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <span class="text-xl font-bold text-white">CineBook</span>
                    </div>
                    <p class="text-white/80 text-sm">Vé Điện Tử - E-Ticket</p>
                </div>

                <!-- Movie Info -->
                <div class="px-6 py-6 text-center">
                    <h2 class="text-2xl font-bold text-white mb-2">{{ $booking['movie_title'] ?? 'Tên Phim' }}</h2>
                    <p class="text-white/80">{{ $booking['format'] ?? '2D Phụ Đề' }}</p>
                </div>

                <!-- Divider with circles -->
                <div class="relative">
                    <div class="border-t-2 border-dashed border-white/30"></div>
                    <div class="absolute -left-4 top-1/2 -translate-y-1/2 w-8 h-8 bg-[#0f0f0f] rounded-full"></div>
                    <div class="absolute -right-4 top-1/2 -translate-y-1/2 w-8 h-8 bg-[#0f0f0f] rounded-full"></div>
                </div>

                <!-- Details -->
                <div class="px-6 py-6">
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <span class="text-white/60 text-xs uppercase tracking-wider block">Rạp</span>
                            <span class="text-white font-semibold">{{ $booking['cinema_name'] ?? 'CineBook Cinema' }}</span>
                        </div>
                        <div>
                            <span class="text-white/60 text-xs uppercase tracking-wider block">Phòng</span>
                            <span class="text-white font-semibold">{{ $booking['room'] ?? 'Cinema 1' }}</span>
                        </div>
                        <div>
                            <span class="text-white/60 text-xs uppercase tracking-wider block">Ngày</span>
                            <span
                                class="text-white font-semibold">{{ isset($booking['show_date']) ? date('d/m/Y', strtotime($booking['show_date'])) : date('d/m/Y') }}</span>
                        </div>
                        <div>
                            <span class="text-white/60 text-xs uppercase tracking-wider block">Giờ</span>
                            <span
                                class="text-white font-semibold">{{ isset($booking['show_time']) ? date('H:i', strtotime($booking['show_time'])) : '19:00' }}</span>
                        </div>
                    </div>

                    <div class="text-center mb-6">
                        <span class="text-white/60 text-xs uppercase tracking-wider block mb-1">Ghế</span>
                        <span class="text-2xl font-bold text-white">
                            @php
                                $seatDisplay = $booking['seat_codes'] ?? null;
                                if (!$seatDisplay && isset($booking['seats'])) {
                                    if (is_array($booking['seats'])) {
                                        $seatDisplay = collect($booking['seats'])->pluck('code')->filter()->implode(', ');
                                        if (empty($seatDisplay)) {
                                            $seatDisplay = collect($booking['seats'])->pluck('seat_number')->filter()->implode(', ');
                                        }
                                    } else {
                                        $seatDisplay = $booking['seats'];
                                    }
                                }
                            @endphp
                            {{ $seatDisplay ?: 'N/A' }}
                        </span>
                    </div>

                    <!-- QR Code -->
                    <div class="flex justify-center mb-6">
                        <div class="bg-white p-4 rounded-xl">
                            <div class="w-32 h-32 flex items-center justify-center">
                                @php
                                    $ticketCode = strtoupper($booking['id'] ?? 'TICKET');
                                    $qrData = url('/bookings/' . ($booking['id'] ?? 'ticket'));
                                @endphp
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($qrData) }}"
                                    alt="QR Code" class="w-30 h-30">
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <span class="text-white/60 text-xs uppercase tracking-wider block mb-1">Mã Vé</span>
                        <span
                            class="text-lg font-mono font-bold text-white tracking-wider">{{ strtoupper($booking['id'] ?? 'XXXXXX') }}</span>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-white/10 px-6 py-4 text-center">
                    <p class="text-white/60 text-xs">
                        Vui lòng xuất trình mã QR hoặc mã vé khi vào rạp
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 space-y-3">
                <button onclick="downloadTicket()"
                    class="w-full py-3 rounded-lg bg-dark-200 border border-gray-700 text-white font-semibold hover:bg-dark-300 transition flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Tải Về
                </button>

                <button onclick="shareTicket()"
                    class="w-full py-3 rounded-lg bg-dark-200 border border-gray-700 text-white font-semibold hover:bg-dark-300 transition flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                    </svg>
                    Chia Sẻ
                </button>

                <a href="{{ route('bookings.my') }}"
                    class="w-full py-3 rounded-lg text-gray-400 hover:text-white transition flex items-center justify-center">
                    Quay Lại Danh Sách
                </a>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function downloadTicket() {
            // Use html2canvas to capture ticket
            if (typeof html2canvas !== 'undefined') {
                html2canvas(document.getElementById('ticket')).then(canvas => {
                    const link = document.createElement('a');
                    link.download = 'cinebook-ticket-{{ $booking["id"] ?? "ticket" }}.png';
                    link.href = canvas.toDataURL();
                    link.click();
                });
            } else {
                window.print();
            }
        }

        function shareTicket() {
            if (navigator.share) {
                navigator.share({
                    title: 'Vé Xem Phim - CineBook',
                    text: 'Mã vé: {{ $booking["id"] ?? "N/A" }}',
                    url: window.location.href,
                });
            } else {
                navigator.clipboard.writeText(window.location.href);
                alert('Đã sao chép đường dẫn!');
            }
        }
    </script>
@endpush