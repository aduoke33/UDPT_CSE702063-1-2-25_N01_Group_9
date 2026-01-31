@extends('layouts.app')

@section('title', 'Thanh Toán - CineBook')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Payment Methods -->
                <div class="lg:col-span-2">
                    <div class="bg-dark-200 rounded-xl p-6 border border-gray-800">
                        <h2 class="text-xl font-semibold text-white mb-6">Chọn Phương Thức Thanh Toán</h2>

                        <form action="{{ route('payment.process', $booking['id']) }}" method="POST" id="payment-form">
                            @csrf

                            <div class="space-y-4">
                                <!-- Banking -->
                                <label class="payment-method block cursor-pointer">
                                    <input type="radio" name="payment_method" value="banking" class="hidden" checked>
                                    <div
                                        class="border-2 border-gray-700 rounded-xl p-4 transition payment-card border-red-500 bg-red-600/10">
                                        <div class="flex items-center">
                                            <div
                                                class="w-12 h-12 bg-blue-600/20 rounded-lg flex items-center justify-center mr-4">
                                                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 class="text-white font-medium">Chuyển Khoản Ngân Hàng</h3>
                                                <p class="text-gray-400 text-sm">Hỗ trợ tất cả ngân hàng nội địa</p>
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                <!-- E-Wallet -->
                                <label class="payment-method block cursor-pointer">
                                    <input type="radio" name="payment_method" value="ewallet" class="hidden">
                                    <div
                                        class="border-2 border-gray-700 rounded-xl p-4 transition payment-card hover:border-gray-600">
                                        <div class="flex items-center">
                                            <div
                                                class="w-12 h-12 bg-green-600/20 rounded-lg flex items-center justify-center mr-4">
                                                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 class="text-white font-medium">Ví Điện Tử</h3>
                                                <p class="text-gray-400 text-sm">MoMo, ZaloPay, VNPay</p>
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                <!-- Credit Card -->
                                <label class="payment-method block cursor-pointer">
                                    <input type="radio" name="payment_method" value="credit_card" class="hidden">
                                    <div
                                        class="border-2 border-gray-700 rounded-xl p-4 transition payment-card hover:border-gray-600">
                                        <div class="flex items-center">
                                            <div
                                                class="w-12 h-12 bg-purple-600/20 rounded-lg flex items-center justify-center mr-4">
                                                <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 class="text-white font-medium">Thẻ Quốc Tế</h3>
                                                <p class="text-gray-400 text-sm">Visa, MasterCard, JCB</p>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Card Details (Hidden by default) -->
                            <div id="card-details" class="hidden mt-6 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Số Thẻ</label>
                                    <input type="text" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19"
                                        class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Tên Chủ Thẻ</label>
                                    <input type="text" name="card_holder" placeholder="NGUYEN VAN A"
                                        class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500 uppercase">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Ngày Hết Hạn</label>
                                        <input type="text" name="expiry" placeholder="MM/YY" maxlength="5"
                                            class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">CVV</label>
                                        <input type="password" name="cvv" placeholder="***" maxlength="4"
                                            class="w-full bg-dark-100 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-red-500">
                                    </div>
                                </div>
                            </div>

                            <!-- Submit -->
                            <button type="submit" class="w-full btn-primary py-3 rounded-lg text-white font-semibold mt-6">
                                Thanh Toán {{ number_format($booking['total_price'] ?? 0) }}đ
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-dark-200 rounded-xl p-6 border border-gray-800 sticky top-24">
                        <h3 class="text-lg font-semibold text-white mb-4">Thông Tin Đơn Hàng</h3>

                        <!-- Movie Info -->
                        <div class="flex gap-4 pb-4 border-b border-gray-700">
                            <img src="{{ $booking['movie_poster'] ?? 'https://via.placeholder.com/80x120/1f2937/6b7280' }}"
                                alt="{{ $booking['movie_title'] ?? 'Movie' }}" class="w-16 h-24 object-cover rounded-lg">
                            <div>
                                <h4 class="text-white font-medium">{{ $booking['movie_title'] ?? 'Phim' }}</h4>
                                <p class="text-gray-400 text-sm mt-1">{{ $booking['format'] ?? '2D Phụ Đề' }}</p>
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="py-4 border-b border-gray-700 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Rạp</span>
                                <span class="text-white">{{ $booking['cinema_name'] ?? 'CineBook' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Ngày</span>
                                <span
                                    class="text-white">{{ isset($booking['show_date']) ? date('d/m/Y', strtotime($booking['show_date'])) : date('d/m/Y') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Suất Chiếu</span>
                                <span
                                    class="text-white">{{ isset($booking['show_time']) ? date('H:i', strtotime($booking['show_time'])) : '19:00' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Ghế</span>
                                <span
                                    class="text-white">{{ $booking['seat_codes'] ?? (is_array($booking['seats'] ?? null) ? collect($booking['seats'])->pluck('code')->implode(', ') : ($booking['seats'] ?? 'N/A')) }}</span>
                            </div>
                        </div>

                        <!-- Total -->
                        <div class="py-4">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">Tổng Cộng</span>
                                <span
                                    class="text-xl font-bold text-red-500">{{ number_format($booking['total_price'] ?? 0) }}đ</span>
                            </div>
                        </div>

                        <!-- Security Note -->
                        <div class="flex items-center text-gray-500 text-xs mt-4">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            Thông tin thanh toán được mã hóa SSL
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Handle payment method selection
        document.querySelectorAll('.payment-method input').forEach(radio => {
            radio.addEventListener('change', function () {
                // Reset all cards
                document.querySelectorAll('.payment-card').forEach(card => {
                    card.classList.remove('border-red-500', 'bg-red-600/10');
                    card.classList.add('border-gray-700');
                });

                // Highlight selected
                this.closest('.payment-method').querySelector('.payment-card').classList.add('border-red-500', 'bg-red-600/10');
                this.closest('.payment-method').querySelector('.payment-card').classList.remove('border-gray-700');

                // Show/hide card details
                const cardDetails = document.getElementById('card-details');
                if (this.value === 'credit_card') {
                    cardDetails.classList.remove('hidden');
                } else {
                    cardDetails.classList.add('hidden');
                }
            });
        });

        // Format card number
        document.querySelector('input[name="card_number"]')?.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
            let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formatted;
        });

        // Format expiry date
        document.querySelector('input[name="expiry"]')?.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
    </script>
@endpush