@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold"><i class="fas fa-bell text-warning me-2"></i>THÔNG BÁO CỦA TÔI</h3>
            <span class="badge bg-danger rounded-pill">{{ count(array_filter($notifications, fn($n) => !$n['is_read'])) }} Mới</span>
        </div>

        @if(count($notifications) > 0)
            <div class="list-group shadow-sm rounded-4 overflow-hidden">
                @foreach($notifications as $noti)
                    <div class="list-group-item list-group-item-action p-4 border-start border-4 {{ $noti['is_read'] ? 'border-light' : 'border-primary bg-light' }}">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <h5 class="mb-1 fw-bold {{ $noti['is_read'] ? 'text-muted' : 'text-primary' }}">
                                {{ $noti['title'] }}
                            </h5>
                            <small class="text-muted">
                                <i class="far fa-clock me-1"></i>{{ \Carbon\Carbon::parse($noti['created_at'])->diffForHumans() }}
                            </small>
                        </div>
                        <p class="mb-2 text-dark">{{ $noti['message'] }}</p>
                        
                        @if(!$noti['is_read'])
                            <a href="{{ route('notifications.read', $noti['id']) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                Đánh dấu đã đọc
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                <i class="fas fa-envelope-open text-muted mb-3" style="font-size: 4rem;"></i>
                <p class="text-muted">Bạn chưa có thông báo nào mới.</p>
                <a href="{{ route('movies.index') }}" class="btn btn-danger px-4">Xem phim ngay</a>
            </div>
        @endif
    </div>
</div>

<style>
    .list-group-item { transition: all 0.2s; border-bottom: 1px solid #eee; }
    .list-group-item:hover { background-color: #f8f9fa; }
</style>
@endsection