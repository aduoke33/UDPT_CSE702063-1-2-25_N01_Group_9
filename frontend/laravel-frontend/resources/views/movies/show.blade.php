@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-4">
        <img src="{{ $movie['poster_url'] ?? 'https://via.placeholder.com/300x450' }}" class="img-fluid rounded shadow" alt="{{ $movie['title'] }}">
    </div>
    <div class="col-md-8">
        <h1 class="fw-bold">{{ $movie['title'] }}</h1>
        <div class="mb-3">
            <span class="badge bg-danger">{{ $movie['rating'] ?? 'T16' }}</span>
            <span class="ms-2 text-muted fw-bold">{{ $movie['duration_minutes'] }} phút</span>
        </div>
        <p class="lead">{{ $movie['description'] }}</p>
        
        <h4 class="mt-5 fw-bold"><i class="fas fa-calendar-alt text-primary"></i> CHỌN SUẤT CHIẾU</h4>
        <div class="row mt-3">
            @if(isset($movie['showtimes']) && count($movie['showtimes']) > 0)
                @foreach($movie['showtimes'] as $showtime)
                    <div class="col-md-4 mb-3">
                        <a href="{{ route('booking.seats', $showtime['id']) }}" class="btn btn-outline-dark w-100 py-3 shadow-sm">
                            <span class="d-block fw-bold">{{ \App\Helpers\ViewHelper::formatDateTime($showtime['show_time']) }}</span>
                            <small class="text-muted">Phòng: {{ $showtime['theater_name'] ?? 'P01' }}</small>
                        </a>
                    </div>
                @endforeach
            @else
                <p class="text-muted">Rất tiếc, hôm nay chưa có suất chiếu cho phim này.</p>
            @endif
        </div>
    </div>
</div>
@endsection