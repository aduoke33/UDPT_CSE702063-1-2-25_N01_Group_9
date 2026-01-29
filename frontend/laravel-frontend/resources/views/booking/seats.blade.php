@extends('layouts.app')

@section('content')
<div class="container text-center">
    <div class="mb-4">
        <h2 class="fw-bold text-uppercase">Chọn ghế</h2>
        <p class="text-muted">{{ $showtime['movie_title'] }} | {{ $showtime['theater_name'] }} | {{ \Carbon\Carbon::parse($showtime['show_time'])->format('H:i - d/m/Y') }}</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="screen w-100 mb-5 shadow-lg d-flex align-items-center justify-content-center text-white rounded" style="height: 50px; background: #444; font-size: 0.8rem;">
                MÀN HÌNH CHIẾU
            </div>

            <form action="{{ route('booking.process') }}" method="POST" id="bookingForm">
                @csrf
                <input type="hidden" name="showtime_id" value="{{ $showtime['id'] }}">
                <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                    @foreach($seats as $seat)
                        <div class="seat-wrapper">
                            <input type="checkbox" name="seat_ids[]" value="{{ $seat['id'] }}" 
                                id="seat-{{ $seat['id'] }}" class="btn-check"
                                data-price="{{ $showtime['price'] }}"
                                {{ $seat['is_available'] ? '' : 'disabled' }}>
                            <label class="btn {{ $seat['is_available'] ? 'btn-outline-secondary' : 'btn-danger disabled' }} seat-btn" 
                                for="seat-{{ $seat['id'] }}">
                                {{ $seat['seat_row'] }}{{ $seat['seat_number'] }}
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex justify-content-center gap-4 mb-5 small">
                    <span><i class="fas fa-square text-secondary"></i> Trống</span>
                    <span><i class="fas fa-square text-success"></i> Đang chọn</span>
                    <span><i class="fas fa-square text-danger"></i> Đã đặt</span>
                </div>

                <div class="sticky-bottom bg-white p-3 border-top shadow-lg d-flex justify-content-between align-items-center">
                    <div class="text-start">
                        <p class="mb-0 text-muted">Ghế đã chọn: <span id="selectedSeatsList" class="fw-bold text-dark">Chưa chọn</span></p>
                        <h4 class="mb-0 fw-bold text-danger">Tổng: <span id="totalPrice">0</span> VNĐ</h4>
                    </div>
                    <button type="submit" class="btn btn-danger btn-lg px-5 fw-bold" id="btnSubmit" disabled>XÁC NHẬN</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const checkboxes = document.querySelectorAll('.btn-check');
    const displayList = document.getElementById('selectedSeatsList');
    const displayTotal = document.getElementById('totalPrice');
    const btnSubmit = document.getElementById('btnSubmit');

    checkboxes.forEach(box => {
        box.addEventListener('change', () => {
            let selected = [];
            let total = 0;
            checkboxes.forEach(cb => {
                if(cb.checked) {
                    selected.push(cb.nextElementSibling.innerText);
                    total += parseFloat(cb.getAttribute('data-price'));
                }
            });
            displayList.innerText = selected.length > 0 ? selected.join(', ') : 'Chưa chọn';
            displayTotal.innerText = total.toLocaleString('vi-VN');
            btnSubmit.disabled = selected.length === 0;
        });
    });
</script>

<style>
    .seat-btn { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold; }
    .btn-check:checked + .seat-btn { background-color: #198754 !important; color: white !important; border-color: #198754 !important; }
</style>
@endsection