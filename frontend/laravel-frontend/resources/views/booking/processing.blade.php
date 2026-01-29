@extends('layouts.app')

@section('content')
<div class="text-center py-5">
    <div class="spinner-border text-danger" style="width: 3rem; height: 3rem;" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <h2 class="mt-4 fw-bold">Đang kiểm tra thanh toán...</h2>
    <p class="text-muted">Hệ thống đang xác nhận giao dịch của bạn với ngân hàng. Vui lòng không đóng trình duyệt.</p>
    
    <script>
        // Giả lập sau 5 giây sẽ tự động chuyển trang thành công
        setTimeout(() => {
            window.location.href = "{{ route('booking.success') }}";
        }, 5000);
    </script>
</div>
@endsection