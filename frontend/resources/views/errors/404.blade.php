@extends('layouts.app')

@section('title', '404 - Trang Khong Tim Thay')

@section('content')
    <div class="min-h-screen flex items-center justify-center bg-gray-900">
        <div class="text-center">
            <div class="mb-8">
                <span class="text-9xl font-bold text-red-500">404</span>
            </div>
            <h1 class="text-4xl font-bold text-white mb-4">Trang Khong Tim Thay</h1>
            <p class="text-gray-400 mb-8 max-w-md mx-auto">
                Xin loi, trang ban dang tim kiem khong ton tai hoac da bi di chuyen.
            </p>
            <div class="space-x-4">
                <a href="{{ route('home') }}"
                    class="inline-block bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700 transition">
                    Ve Trang Chu
                </a>
                <a href="{{ route('movies.index') }}"
                    class="inline-block bg-gray-700 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-600 transition">
                    Xem Phim
                </a>
            </div>
        </div>
    </div>
@endsection