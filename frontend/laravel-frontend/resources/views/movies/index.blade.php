@extends('layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-12 text-center">
        <h2 class="fw-bold"><i class="fas fa-fire-alt text-danger"></i> PHIM ĐANG CHIẾU</h2>
        <hr class="mx-auto" style="width: 100px; border-top: 3px solid #e50914;">
    </div>
</div>

<div class="row">
    @forelse($movies as $movie)
        <div class="col-md-3 mb-4">
            <div class="card h-100 shadow-sm border-0 movie-card">
                <img src="{{ $movie['poster_url'] ?? 'https://via.placeholder.com/300x450' }}" class="card-img-top" alt="{{ $movie['title'] }}">
                <div class="card-body">
                    <h5 class="card-title fw-bold text-truncate">{{ $movie['title'] }}</h5>
                    <p class="text-muted mb-1">
                        <i class="far fa-clock"></i> {{ $movie['duration_minutes'] }} phút
                    </p>
                    <p class="text-muted small">
                        <i class="fas fa-tags"></i> {{ $movie['genre'] }}
                    </p>
                </div>
                <div class="card-footer bg-white border-0 pb-3">
                    <a href="{{ route('movies.show', $movie['id']) }}" class="btn btn-outline-danger w-100 fw-bold">
                        ĐẶT VÉ NGAY
                    </a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center py-5">
            <p class="text-muted">Hiện chưa có phim nào được khởi chiếu.</p>
        </div>
    @endforelse
</div>
@endsection