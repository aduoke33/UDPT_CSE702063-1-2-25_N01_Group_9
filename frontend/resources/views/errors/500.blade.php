@extends('layouts.app')

@section('title', '500 - Loi May Chu')

@section('content')
    <div class="min-h-screen flex items-center justify-center bg-gray-900">
        <div class="text-center">
            <div class="mb-8">
                <span class="text-9xl font-bold text-red-500">500</span>
            </div>
            <h1 class="text-4xl font-bold text-white mb-4">Loi May Chu</h1>
            <p class="text-gray-400 mb-8 max-w-md mx-auto">
                Xin loi, da co loi xay ra tren he thong. Vui long thu lai sau.
            </p>
            <div class="space-x-4">
                <a href="{{ route('home') }}"
                    class="inline-block bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700 transition">
                    Ve Trang Chu
                </a>
                <button onclick="location.reload()"
                    class="inline-block bg-gray-700 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-600 transition">
                    Thu Lai
                </button>
            </div>
        </div>
    </div>
@endsection