@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 text-center">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-dark text-white py-3">
                <h4 class="mb-0 fw-bold"><i class="fas fa-qrcode me-2"></i>QUÉT MÃ THANH TOÁN</h4>
            </div>
            <div class="card-body p-5">
                <div class="mb-4">
                    <p class="text-muted mb-1">Đơn hàng sẽ hết hạn sau:</p>
                    <div id="countdown" class="display-6 fw-bold text-danger">10:00</div>
                </div>

                <div class="qr-container p-3 border rounded-4 bg-white shadow-sm d-inline-block mb-4">
                    {{-- Sử dụng API VietQR hoặc QRServer --}}
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data={{ urlencode($qr_data) }}" 
                         alt="QR Code Thanh Toán" class="img-fluid rounded">
                </div>

                <div class="text-start bg-light p-3 rounded-3 mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Tổng tiền:</span>
                        <span class="fw-bold text-danger fs-5">{{ number_format($booking['total_price']) }} VNĐ</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Nội dung:</span>
                        <span class="fw-bold text-primary">{{ $booking['id'] }}</span>
                    </div>
                    <div class="small text-muted mt-2">
                        <i class="fas fa-info-circle me-1"></i> Vui lòng nhập chính xác nội dung chuyển khoản để hệ thống xác nhận tự động.
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <a href="{{ route('booking.processing') }}" class="btn btn-success btn-lg fw-bold py-3 shadow">
                        <i class="fas fa-check-circle me-2"></i>TÔI ĐÃ CHUYỂN KHOẢN
                    </a>
                    <a href="{{ route('movies.index') }}" class="btn btn-link text-muted text-decoration-none">
                        Hủy giao dịch
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Logic đếm ngược 10 phút
    let time = 600; 
    const countdownEl = document.getElementById('countdown');

    const timer = setInterval(() => {
        let minutes = Math.floor(time / 60);
        let seconds = time % 60;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        countdownEl.innerHTML = `${minutes}:${seconds}`;
        time--;

        if (time < 0) {
            clearInterval(timer);
            alert('Giao dịch đã hết hạn. Vui lòng đặt vé lại!');
            window.location.href = "{{ route('movies.index') }}";
        }
    }, 1000);
</script>

<style>
    .qr-container { transition: transform 0.3s ease; }
    .qr-container:hover { transform: scale(1.02); }
    #countdown { letter-spacing: 2px; }
</style>
@endsection