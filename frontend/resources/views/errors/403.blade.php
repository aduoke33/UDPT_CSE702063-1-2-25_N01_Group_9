@extends('layouts.app')

@section('title', '403 - Truy Cap Bi Tu Choi')

@section('content')
    <div class="min-h-screen flex items-center justify-center bg-gray-900">
        <div class="text-center">
            <div class="mb-8">
                <span class="text-9xl font-bold text-red-500">403</span>
            </div>
            <h1 class="text-4xl font-bold text-white mb-4">Truy Cap Bi Tu Choi</h1>
            <p class="text-gray-400 mb-8 max-w-md mx-auto">
                Ban khong co quyen truy cap trang nay. Vui long dang nhap hoac lien he quan tri vien.
            </p>
            <div class="space-x-4">
                <a href="{{ route('home') }}"
                    class="inline-block bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700 transition">
                    Ve Trang Chu
                </a>
                <a href="{{ route('login') }}"
                    class="inline-block bg-gray-700 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-600 transition">
                    Dang Nhap
                </a>
            </div>
        </div>
    </div>
@endsection