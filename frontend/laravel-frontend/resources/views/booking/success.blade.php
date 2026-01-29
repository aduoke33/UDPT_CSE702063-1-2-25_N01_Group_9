@extends('layouts.app')

@section('content')
<div class="row justify-content-center py-5">
    <div class="col-md-7 text-center">
        <div class="display-1 text-success mb-4">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1 class="fw-bold text-uppercase">Đặt vé thành công!</h1>
        <p class="lead text-muted">Cảm ơn bạn đã lựa chọn Phenikaa Cinema. Giao dịch của bạn đã được hoàn tất.</p>
        
        <div class="card border-0 shadow-sm bg-light my-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3 text-primary">Thông tin vé điện tử</h5>
                <p class="mb-1 text-muted">Mã vé (Booking ID): <strong>#{{ substr($booking_id ?? 'PK123456', 0, 8) }}</strong></p>
                <p class="small text-danger">Thông tin chi tiết về vé và mã QR vào phòng chiếu đã được gửi tới email của bạn.</p>
            </div>
        </div>

        <div class="d-grid gap-2 d-md-block">
            <a href="{{ route('movies.index') }}" class="btn btn-outline-dark px-4 py-2 fw-bold">VỀ TRANG CHỦ</a>
            <a href="#" class="btn btn-danger px-4 py-2 fw-bold">XEM LỊCH SỬ ĐẶT VÉ</a>
        </div>
    </div>
</div>
@endsection