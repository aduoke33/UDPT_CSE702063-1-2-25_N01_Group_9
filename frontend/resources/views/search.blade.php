@extends('layouts.app')

@section('title', 'Tìm Kiếm: ' . $query . ' - CineBook')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <!-- Search Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">Kết Quả Tìm Kiếm</h1>
            <p class="text-gray-400 mt-2">
                @if($query)
                    Tìm thấy {{ count($movies) }} kết quả cho "{{ $query }}"
                @else
                    Nhập từ khóa để tìm kiếm phim
                @endif
            </p>
        </div>

        <!-- Search Form -->
        <div class="mb-8">
            <form action="{{ route('search') }}" method="GET" class="flex gap-4">
                <div class="flex-1 relative">
                    <input type="text" name="q" value="{{ $query }}" placeholder="Nhập tên phim, đạo diễn, diễn viên..."
                        class="w-full bg-dark-200 border border-gray-700 rounded-xl py-3 pl-12 pr-4 text-white placeholder-gray-500 focus:outline-none focus:border-red-500 transition">
                    <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <button type="submit" class="btn-primary px-6 py-3 rounded-xl text-white font-medium">
                    Tìm Kiếm
                </button>
            </form>
        </div>

        <!-- Genre Filter -->
        <div class="flex flex-wrap gap-2 mb-8">
            <a href="{{ route('search', ['q' => $query]) }}"
                class="px-4 py-2 rounded-full text-sm font-medium transition {{ !$genre ? 'bg-red-600 text-white' : 'bg-dark-200 text-gray-400 hover:text-white' }}">
                Tất Cả
            </a>
            @foreach(['Hành Động', 'Hài Hước', 'Kinh Dị', 'Tình Cảm', 'Hoạt Hình', 'Khoa Học Viễn Tưởng'] as $g)
                <a href="{{ route('search', ['q' => $query, 'genre' => $g]) }}"
                    class="px-4 py-2 rounded-full text-sm font-medium transition {{ $genre === $g ? 'bg-red-600 text-white' : 'bg-dark-200 text-gray-400 hover:text-white' }}">
                    {{ $g }}
                </a>
            @endforeach
        </div>

        <!-- Results -->
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
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <h3 class="text-xl font-semibold text-white mb-2">Không tìm thấy kết quả</h3>
                <p class="text-gray-400 mb-6">Thử tìm kiếm với từ khóa khác</p>
                <a href="{{ route('movies.index') }}"
                    class="btn-primary inline-block px-6 py-3 rounded-lg text-white font-medium">
                    Xem Tất Cả Phim
                </a>
            </div>
        @endif
    </div>
@endsection