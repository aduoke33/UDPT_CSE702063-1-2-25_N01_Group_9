@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 shadow-lg">
            <div class="card-header bg-danger text-white text-center py-3">
                <h4 class="mb-0 fw-bold text-uppercase">Thanh toán vé xem phim</h4>
            </div>
            <div class="card-body p-4 text-center">
                <p class="mb-4 text-muted">Vui lòng quét mã QR dưới đây để hoàn tất thanh toán trong vòng 10 phút.</p>
                
                <div class="bg-light p-3 d-inline-block rounded mb-4">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data={{ urlencode($payment_url) }}" alt="QR Payment" class="img-fluid">
                </div>

                <div class="text-start bg-light p-3 rounded mb-4">
                    <h6 class="fw-bold border-bottom pb-2">Thông tin đơn hàng</h6>
                    <p class="mb-1"><strong>Phim:</strong> {{ $booking['movie_title'] }}</p>
                    <p class="mb-1"><strong>Ghế:</strong> {{ implode(', ', $booking['seat_names']) }}</p>
                    <p class="mb-0"><strong>Số tiền:</strong> <span class="text-danger fw-bold fs-5">{{ number_format($booking['total_price']) }} VNĐ</span></p>
                </div>

                <a href="{{ route('booking.processing', ['id' => $booking['id']]) }}" class="btn btn-success btn-lg w-100 fw-bold">TÔI ĐÃ CHUYỂN KHOẢN</a>
                <p class="mt-3 small text-muted"><i class="fas fa-shield-alt"></i> Giao dịch an toàn được bảo mật bởi Phenikaa Pay</p>
            </div>
        </div>
    </div>
</div>
@endsection