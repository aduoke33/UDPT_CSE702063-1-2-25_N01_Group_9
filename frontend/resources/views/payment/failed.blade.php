@extends('layouts.app')

@section('title', 'Thanh Toán Thất Bại - CineBook')

@section('content')
    <div class="container mx-auto px-4 py-16">
        <div class="max-w-md mx-auto text-center">
            <!-- Error Icon -->
            <div class="w-24 h-24 bg-red-600/20 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>

            <h1 class="text-3xl font-bold text-white mb-4">Thanh Toán Thất Bại</h1>
            <p class="text-gray-400 mb-8">
                Xin lỗi, giao dịch thanh toán không thành công. Vui lòng thử lại hoặc chọn phương thức thanh toán khác.
            </p>

            <!-- Error Details -->
            <div class="bg-dark-200 rounded-xl p-6 border border-red-800/50 text-left mb-8">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-red-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <h3 class="text-white font-medium mb-1">Nguyên Nhân Có Thể</h3>
                        <ul class="text-gray-400 text-sm space-y-1">
                            <li>- Số dư tài khoản không đủ</li>
                            <li>- Thông tin thẻ không chính xác</li>
                            <li>- Kết nối bị gián đoạn</li>
                            <li>- Giao dịch bị từ chối bởi ngân hàng</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                @if($booking)
                    <a href="{{ route('payment.show', $booking['id'] ?? 1) }}"
                        class="w-full btn-primary py-3 rounded-lg text-white font-semibold flex items-center justify-center">
                        Thử Lại
                    </a>
                @endif

                <a href="{{ route('bookings.my') }}"
                    class="w-full py-3 rounded-lg border border-gray-600 text-white font-semibold hover:bg-dark-300 transition flex items-center justify-center">
                    Xem Đơn Đặt Vé
                </a>

                <a href="{{ route('home') }}" class="block text-gray-400 hover:text-white transition">
                    Về Trang Chủ
                </a>
            </div>
        </div>
    </div>
@endsection