@extends('layouts.app')

@section('title', ($title ?? 'Danh Sách Phim') . ' - CineBook')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">{{ $title ?? 'Danh Sách Phim' }}</h1>

            <!-- Filter Tabs -->
            <div class="flex items-center space-x-4 mt-6">
                <a href="{{ route('movies.index') }}"
                    class="px-4 py-2 rounded-full text-sm font-medium transition {{ $type === 'all' ? 'bg-red-600 text-white' : 'bg-dark-200 text-gray-400 hover:text-white' }}">
                    Tất Cả
                </a>
                <a href="{{ route('movies.now-showing') }}"
                    class="px-4 py-2 rounded-full text-sm font-medium transition {{ $type === 'now-showing' ? 'bg-red-600 text-white' : 'bg-dark-200 text-gray-400 hover:text-white' }}">
                    Đang Chiếu
                </a>
                <a href="{{ route('movies.coming-soon') }}"
                    class="px-4 py-2 rounded-full text-sm font-medium transition {{ $type === 'coming-soon' ? 'bg-red-600 text-white' : 'bg-dark-200 text-gray-400 hover:text-white' }}">
                    Sắp Chiếu
                </a>
            </div>
        </div>

        <!-- Movies Grid -->
        @if(count($movies) > 0)
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 md:gap-6">
                @foreach($movies as $movie)
                    <x-movie-card :movie="$movie" />
                @endforeach
            </div>
        @else
            <div class="text-center py-16">
                <svg class="w-20 h-20 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                </svg>
                <h3 class="text-xl font-semibold text-white mb-2">Không tìm thấy phim nào</h3>
                <p class="text-gray-400 mb-6">Hiện tại chưa có phim trong danh mục này</p>
                <a href="{{ route('home') }}" class="btn-primary px-6 py-3 rounded-lg text-white font-medium">
                    Về Trang Chủ
                </a>
            </div>
        @endif
    </div>
@endsection