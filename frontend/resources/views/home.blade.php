@extends('layouts.app')

@section('title', 'CineBook - Hệ Thống Đặt Vé Xem Phim Trực Tuyến')

@section('content')
    <!-- Hero Section -->
    <section class="relative h-[70vh] min-h-[500px] overflow-hidden">
        <!-- Background -->
        <div class="absolute inset-0 gradient-bg">
            <div class="absolute inset-0 bg-black/50"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-[#0f0f0f] via-transparent to-transparent"></div>
        </div>

        <!-- Content -->
        <div class="relative container mx-auto px-4 h-full flex items-center">
            <div class="max-w-2xl">
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-4">
                    Trải Nghiệm Điện Ảnh
                    <span class="text-red-500">Tuyệt Vời</span>
                </h1>
                <p class="text-lg text-gray-300 mb-8">
                    Đặt vé xem phim trực tuyến nhanh chóng, tiện lợi.
                    Khám phá những bộ phim hay nhất và tận hưởng thời gian tuyệt vời cùng người thân.
                </p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="{{ route('movies.now-showing') }}"
                        class="btn-primary px-8 py-3 rounded-full text-white font-semibold text-center">
                        Đặt Vé Ngay
                    </a>
                    <a href="{{ route('movies.index') }}"
                        class="px-8 py-3 rounded-full border border-gray-600 text-white font-semibold hover:bg-white/10 transition text-center">
                        Xem Tất Cả Phim
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Now Showing Section -->
    <section class="py-12">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-white">Phim Đang Chiếu</h2>
                <a href="{{ route('movies.now-showing') }}"
                    class="text-red-500 hover:text-red-400 font-medium flex items-center">
                    Xem tất cả
                    <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            @if(count($nowShowing) > 0)
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 md:gap-6">
                    @foreach(array_slice($nowShowing, 0, 10) as $movie)
                        <x-movie-card :movie="$movie" />
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                    </svg>
                    <p class="text-gray-400">Hiện tại chưa có phim đang chiếu</p>
                </div>
            @endif
        </div>
    </section>

    <!-- Coming Soon Section -->
    <section class="py-12 bg-dark-100">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-white">Phim Sắp Chiếu</h2>
                <a href="{{ route('movies.coming-soon') }}"
                    class="text-red-500 hover:text-red-400 font-medium flex items-center">
                    Xem tất cả
                    <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            @if(count($comingSoon) > 0)
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 md:gap-6">
                    @foreach(array_slice($comingSoon, 0, 10) as $movie)
                        <x-movie-card :movie="$movie" />
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-gray-400">Hiện tại chưa có phim sắp chiếu</p>
                </div>
            @endif
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-16">
        <div class="container mx-auto px-4">
            <h2 class="text-2xl md:text-3xl font-bold text-white text-center mb-12">Tại Sao Chọn CineBook?</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="text-center p-6">
                    <div class="w-16 h-16 bg-red-600/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">Đặt Vé Nhanh Chóng</h3>
                    <p class="text-gray-400">
                        Chỉ với vài thao tác đơn giản, bạn có thể đặt vé xem phim chỉ trong vài giây.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="text-center p-6">
                    <div class="w-16 h-16 bg-blue-600/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">Thanh Toán An Toàn</h3>
                    <p class="text-gray-400">
                        Hệ thống thanh toán được bảo mật với nhiều phương thức thanh toán đa dạng.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="text-center p-6">
                    <div class="w-16 h-16 bg-green-600/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">Vé Điện Tử Tiện Lợi</h3>
                    <p class="text-gray-400">
                        Nhận vé điện tử ngay lập tức qua email và ứng dụng, không cần in vé giấy.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection